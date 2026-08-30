<?php

declare(strict_types = 1);

namespace Modufolio\Media\Repository;

use Modufolio\Media\Entity\Media;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\Uuid as RamseyUuid;

/**
 * @extends EntityRepository<Media>
 */
class MediaRepository extends EntityRepository
{
    /**
     * The application's tag entity, for resolving `tag:<slug>` filters. Tags
     * are an app-level concern (polymorphic taggables); the package only needs
     * a class to look slugs up against. Unset, slug filters match nothing.
     *
     * @var class-string|null
     */
    private static ?string $tagEntityClass = null;

    /** @param class-string|null $class */
    public static function useTagEntity(?string $class): void
    {
        self::$tagEntityClass = $class;
    }

    public function findOneByUuid(string $uuid): ?Media
    {
        try {
            $uuidObject = RamseyUuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $this->findOneBy(['uuid' => $uuidObject]);
    }

    /**
     * Load the Media entities for a list of uuids in a single query. Non-string
     * entries and uuids that don't resolve are skipped. Order is not preserved —
     * callers that need ordering (e.g. reorder) should use resolveIdsByUuids().
     *
     * @param  mixed[] $uuids
     * @return Media[]
     */
    public function findByUuids(array $uuids): array
    {
        $canonical = $this->canonicalizeUuids($uuids);

        if ($canonical === []) {
            return [];
        }

        return $this->createQueryBuilder('m')
            ->where('m.uuid IN (:uuids)')
            ->setParameter('uuids', array_unique($canonical), ArrayParameterType::STRING)
            ->getQuery()
            ->getResult();
    }

    /**
     * Canonicalize a mixed list to valid uuid strings, preserving order and
     * skipping non-strings / malformed values.
     *
     * @param  mixed[] $uuids
     * @return string[]
     */
    private function canonicalizeUuids(array $uuids): array
    {
        $canonical = [];
        foreach ($uuids as $uuid) {
            if (!is_string($uuid)) {
                continue;
            }
            try {
                $canonical[] = RamseyUuid::fromString($uuid)->toString();
            } catch (\InvalidArgumentException) {
            }
        }

        return $canonical;
    }

    /**
     * Resolve an ordered list of Media uuids to internal int ids in a
     * single query. Non-string entries and uuids that don't resolve to an
     * existing Media are silently skipped; the relative order of the
     * resolved ids is preserved, which matters for the reorder endpoints.
     *
     * @param mixed[] $uuids
     * @return int[]
     */
    public function resolveIdsByUuids(array $uuids): array
    {
        $canonical = $this->canonicalizeUuids($uuids);

        if ($canonical === []) {
            return [];
        }

        $idByUuid = $this->getEntityManager()->getConnection()->fetchAllKeyValue(
            'SELECT uuid, id FROM media WHERE uuid IN (?)',
            [array_unique($canonical)],
            [ArrayParameterType::STRING]
        );

        $ids = [];
        foreach ($canonical as $uuid) {
            if (isset($idByUuid[$uuid])) {
                $ids[] = (int) $idByUuid[$uuid];
            }
        }
        return $ids;
    }

    /**
     * Resolve a list of Media uuids to internal int ids in a single query,
     * returned as a uuid => id map (unresolvable uuids are simply absent).
     * Used where the caller needs both directions — e.g. resolving uuids to
     * query by id, then mapping an id-keyed result back to uuid for the
     * response, since the frontend addresses media by uuid everywhere.
     *
     * @param mixed[] $uuids
     * @return array<string, int>
     */
    public function mapUuidsToIds(array $uuids): array
    {
        $canonical = $this->canonicalizeUuids($uuids);

        if ($canonical === []) {
            return [];
        }

        $idByUuid = $this->getEntityManager()->getConnection()->fetchAllKeyValue(
            'SELECT uuid, id FROM media WHERE uuid IN (?)',
            [array_unique($canonical)],
            [ArrayParameterType::STRING]
        );

        return array_map('intval', $idByUuid);
    }

    public function findAllOrdered(?int $limit = null, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('m')
            ->orderBy('m.createdAt', 'DESC')
            ->setFirstResult($offset);

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function findImagesOrdered(?int $limit = null, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.mimeType LIKE :mimeType')
            ->setParameter('mimeType', 'image/%')
            ->orderBy('m.createdAt', 'DESC')
            ->setFirstResult($offset);

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByFilename(string $filename): ?Media
    {
        return $this->findOneBy(['filename' => $filename]);
    }

    /**
     * Find an existing media item whose stored-master or raw-upload checksum
     * matches the given sha1. Matching against both columns means a re-upload
     * of the same original dedups even when the stored master was rewritten
     * (downscaled/re-oriented) after its first upload.
     */
    public function findDuplicateByChecksum(string $sha1): ?Media
    {
        return $this->createQueryBuilder('m')
            ->where('m.checksum = :sha1 OR m.originalChecksum = :sha1')
            ->setParameter('sha1', $sha1)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findNewestByOriginalFilename(string $originalFilename): ?Media
    {
        return $this->createQueryBuilder('m')
            ->where('m.originalFilename = :originalFilename')
            ->setParameter('originalFilename', $originalFilename)
            ->orderBy('m.createdAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find the next media item (older, since list is ordered DESC by createdAt).
     */
    public function findNextMedia(Media $media): ?Media
    {
        return $this->createQueryBuilder('m')
            ->where('m.createdAt < :createdAt OR (m.createdAt = :createdAt AND m.id < :id)')
            ->setParameter('createdAt', $media->getCreatedAt())
            ->setParameter('id', $media->getId())
            ->orderBy('m.createdAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find the previous media item (newer, since list is ordered DESC by createdAt).
     */
    public function findPreviousMedia(Media $media): ?Media
    {
        return $this->createQueryBuilder('m')
            ->where('m.createdAt > :createdAt OR (m.createdAt = :createdAt AND m.id > :id)')
            ->setParameter('createdAt', $media->getCreatedAt())
            ->setParameter('id', $media->getId())
            ->orderBy('m.createdAt', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Front-page picks in their curated order.
     *
     * @return Media[]
     */
    public function findFeatured(?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.isFeatured = true')
            ->orderBy('m.featuredOrder', 'ASC');
        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function findFavorites(?int $limit = null, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.isFavorite = :isFavorite')
            ->setParameter('isFavorite', true)
            ->orderBy('m.createdAt', 'DESC')
            ->setFirstResult($offset);

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Return one page of media as detached Media objects for read-only listing.
     *
     * The filter/search/sort/pagination is resolved to an ordered id list via
     * the ORM (reusing the shared filter helpers), then the display columns are
     * fetched with plain DBAL — deliberately excluding the up-to-1MB
     * `video_cover` blob, which full-entity hydration would otherwise load for
     * every row. The blob is re-attached only to the video rows that have one.
     *
     * @return Media[]
     */
    public function findPaginated(int $page, int $perPage, string $sort = 'newest', string $filter = 'all', string $search = ''): array
    {
        $qb = $this->createQueryBuilder('m')->select('m.id');
        $this->applyFilter($qb, $filter);
        $this->applySearch($qb, $search);
        $this->applySort($qb, $sort);
        $qb->setFirstResult(($page - 1) * $perPage)
           ->setMaxResults($perPage);

        /** @var list<int> $ids */
        $ids = array_map('intval', $qb->getQuery()->getSingleColumnResult());

        return $this->hydrateListRows($ids);
    }

    /**
     * Load the display columns for the given media ids via DBAL (no ORM
     * hydration, no video_cover blob), build detached Media value objects, and
     * return them in the exact order of $ids. Video items get their cover blob
     * attached in a second, targeted query.
     *
     * @param  list<int> $ids
     * @return Media[]
     */
    private function hydrateListRows(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $conn = $this->getEntityManager()->getConnection();

        $rows = $conn->fetchAllAssociative(
            'SELECT id, uuid, filename, original_filename, file_path, mime_type, file_size,
                    width, height, metadata, title, alt_text, caption, focus,
                    is_public, is_favorite, thumbhash, rating, created_at, updated_at
             FROM media WHERE id IN (?)',
            [$ids],
            [ArrayParameterType::INTEGER],
        );

        $byId = [];
        $videoIds = [];
        foreach ($rows as $row) {
            $media = Media::fromRow($row);
            $byId[(int) $row['id']] = $media;
            if ($media->isVideo()) {
                $videoIds[] = (int) $row['id'];
            }
        }

        // Only videos need the (potentially large) cover blob, for the base64
        // thumbnail the presenter inlines — fetch just those.
        if ($videoIds !== []) {
            $covers = $conn->fetchAllKeyValue(
                'SELECT id, video_cover FROM media WHERE id IN (?) AND video_cover IS NOT NULL',
                [$videoIds],
                [ArrayParameterType::INTEGER],
            );
            foreach ($covers as $id => $blob) {
                if (is_resource($blob)) {
                    $blob = stream_get_contents($blob) ?: null;
                }
                ($byId[(int) $id] ?? null)?->setVideoCover($blob);
            }
        }

        // Preserve the page order determined by the filtered/sorted query.
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return $ordered;
    }

    public function countFiltered(string $filter = 'all', string $search = ''): int
    {
        $qb = $this->createQueryBuilder('m')->select('COUNT(m.id)');
        $this->applyFilter($qb, $filter);
        $this->applySearch($qb, $search);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function applyFilter(QueryBuilder $qb, string $filter): void
    {
        if ($filter === 'favorites') {
            $qb->where('m.isFavorite = :fav')->setParameter('fav', true);
        } elseif ($filter === 'recent') {
            $latest = $this->getLatestUploadDate();
            if ($latest) {
                $date = $latest->format('Y-m-d');
                $qb->where("m.createdAt >= :start AND m.createdAt <= :end")
                   ->setParameter('start', $date . ' 00:00:00')
                   ->setParameter('end', $date . ' 23:59:59');
            }
        } elseif (str_starts_with($filter, 'year:')) {
            $year = substr($filter, 5);
            $qb->where('m.createdAt >= :start AND m.createdAt < :end')
               ->setParameter('start', $year . '-01-01 00:00:00')
               ->setParameter('end', ((int) $year + 1) . '-01-01 00:00:00');
        } elseif (str_starts_with($filter, 'month:')) {
            $ym = substr($filter, 6);
            [$year, $month] = explode('-', $ym);
            $nm = (int) $month === 12 ? '01' : str_pad((string) ((int) $month + 1), 2, '0', STR_PAD_LEFT);
            $ny = (int) $month === 12 ? (string) ((int) $year + 1) : $year;
            $qb->where('m.createdAt >= :start AND m.createdAt < :end')
               ->setParameter('start', "$year-$month-01 00:00:00")
               ->setParameter('end', "$ny-$nm-01 00:00:00");
        } elseif (str_starts_with($filter, 'tag:')) {
            $tagValue = substr($filter, 4);

            // Numeric → ID lookup; anything else → slug, then name fallback
            $tagId = (int) $tagValue;
            if (($tagId === 0 || ((string) $tagId !== $tagValue)) && self::$tagEntityClass !== null) {
                $tagRepo = $this->getEntityManager()->getRepository(self::$tagEntityClass);

                $tag = $tagRepo->findOneBy(['slug' => $tagValue]);

                if ($tag === null) {
                    $tag = $tagRepo->createQueryBuilder('t')
                        ->where('LOWER(t.name) = LOWER(:name)')
                        ->setParameter('name', $tagValue)
                        ->setMaxResults(1)
                        ->getQuery()
                        ->getOneOrNullResult();
                }

                $tagId = $tag?->getId() ?? 0;
            }

            if ($tagId === 0) {
                // Invalid tag, return no results
                $qb->andWhere('1 = 0');
            } else {
                $ids = $this->getEntityManager()
                    ->getConnection()
                    ->executeQuery(
                        'SELECT taggable_id FROM taggables WHERE taggable_type = ? AND tag_id = ?',
                        ['media', $tagId],
                    )
                    ->fetchFirstColumn();

                if (empty($ids)) {
                    $qb->andWhere('1 = 0');
                } else {
                    $qb->andWhere('m.id IN (:taggedIds)')
                       ->setParameter('taggedIds', array_map('intval', $ids));
                }
            }
        }
        // 'rated:X' → rating >= X
        if (str_starts_with($filter, 'rated:')) {
            $minRating = max(1, min(5, (int) substr($filter, 6)));
            $qb->andWhere('m.rating >= :minRating')->setParameter('minRating', $minRating);
        }

        // 'cluster:N' → images in a similarity cluster
        if (str_starts_with($filter, 'cluster:')) {
            $clusterId = (int) substr($filter, 8);
            $ids = $this->getEntityManager()
                ->getConnection()
                ->executeQuery('SELECT image_id FROM image_features WHERE cluster_id = ?', [$clusterId])
                ->fetchFirstColumn();
            if (empty($ids)) {
                $qb->andWhere('1 = 0');
            } else {
                $qb->andWhere('m.id IN (:clusterIds)')
                   ->setParameter('clusterIds', array_map('intval', $ids));
            }
        }

        // 'category:X' → images matching an image_features category
        if (str_starts_with($filter, 'category:')) {
            $category = substr($filter, 9);
            $ids = $this->getEntityManager()
                ->getConnection()
                ->executeQuery('SELECT image_id FROM image_features WHERE category = ?', [$category])
                ->fetchFirstColumn();
            if (empty($ids)) {
                $qb->andWhere('1 = 0');
            } else {
                $qb->andWhere('m.id IN (:categoryIds)')
                   ->setParameter('categoryIds', array_map('intval', $ids));
            }
        }

        // 'all' → no filter
    }

    private function applySearch(QueryBuilder $qb, string $search): void
    {
        if ($search === '') {
            return;
        }

        $term = '%' . $search . '%';
        $qb->andWhere(
            $qb->expr()->orX(
                $qb->expr()->like('LOWER(m.title)', ':search'),
                $qb->expr()->like('LOWER(m.originalFilename)', ':search'),
                $qb->expr()->like('LOWER(m.caption)', ':search'),
            )
        )->setParameter('search', mb_strtolower($term));
    }

    private function applySort(QueryBuilder $qb, string $sort): void
    {
        if ($sort === 'oldest') {
            $qb->orderBy('m.createdAt', 'ASC')->addOrderBy('m.id', 'ASC');
        } elseif ($sort === 'title') {
            $qb->orderBy('m.title', 'ASC')->addOrderBy('m.originalFilename', 'ASC');
        } elseif ($sort === 'filename') {
            $qb->orderBy('m.originalFilename', 'ASC');
        } elseif ($sort === 'largest') {
            $qb->orderBy('m.fileSize', 'DESC')->addOrderBy('m.id', 'DESC');
        } elseif ($sort === 'top_rated') {
            $qb->orderBy('m.rating', 'DESC')->addOrderBy('m.createdAt', 'DESC')->addOrderBy('m.id', 'DESC');
        } else {
            $qb->orderBy('m.createdAt', 'DESC')->addOrderBy('m.id', 'DESC');
        }
    }

    private function getLatestUploadDate(): ?\DateTimeImmutable
    {
        $result = $this->createQueryBuilder('m')
            ->select('MAX(m.createdAt)')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? new \DateTimeImmutable($result) : null;
    }

    public function getTotalCount(): int
    {
        return (int)$this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getImageCount(): int
    {
        return (int)$this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.mimeType LIKE :mimeType')
            ->setParameter('mimeType', 'image/%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findRecentAI(\Modufolio\Media\Contract\UploaderInterface $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.isGenerated = :true')
            ->andWhere('m.uploadedBy = :user')
            ->setParameter('true', true)
            ->setParameter('user', $user)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

}
