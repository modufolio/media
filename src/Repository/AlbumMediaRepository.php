<?php

declare(strict_types = 1);

namespace Modufolio\Media\Repository;

use Modufolio\Media\Entity\Album;
use Modufolio\Media\Entity\AlbumMedia;
use Modufolio\Media\Entity\Media;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<AlbumMedia>
 */
class AlbumMediaRepository extends EntityRepository
{
    public function countMediaForAlbum(Album $album): int
    {
        if ($album->isAlbum()) {
            return (int)$this->createQueryBuilder('am')
                ->select('COUNT(am.id)')
                ->where('am.album = :album')
                ->setParameter('album', $album)
                ->getQuery()
                ->getSingleScalarResult();
        }

        return (int)$this->getEntityManager()
            ->createQuery(
                'SELECT COUNT(DISTINCT m.id) FROM Modufolio\\Media\\Entity\\Media m
                 JOIN Modufolio\\Media\\Entity\\AlbumMedia am WITH am.media = m
                 JOIN Modufolio\\Media\\Entity\\Album a WITH am.album = a
                 WHERE a.leftId > :left AND a.rightId < :right AND a.albumType = 0'
            )
            ->setParameter('left', $album->getLeftId())
            ->setParameter('right', $album->getRightId())
            ->getSingleScalarResult();
    }

    /**
     * @return Media[]
     */
    public function findPaginatedMediaForAlbum(Album $album, int $page, int $perPage, string $sort = 'newest'): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        if ($album->isAlbum()) {
            $qb = $this->getEntityManager()->createQueryBuilder()
                ->select('DISTINCT m')
                ->from(Media::class, 'm')
                ->join(AlbumMedia::class, 'am', 'WITH', 'am.media = m')
                ->where('am.album = :album')
                ->setParameter('album', $album)
                ->setFirstResult($offset)
                ->setMaxResults($perPage);

            $this->applySort($qb, $sort, true);

            return $qb->getQuery()->getResult();
        }

        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT m')
            ->from(Media::class, 'm')
            ->join(AlbumMedia::class, 'am', 'WITH', 'am.media = m')
            ->join(Album::class, 'a', 'WITH', 'am.album = a')
            ->where('a.leftId > :left AND a.rightId < :right AND a.albumType = 0')
            ->setParameter('left', $album->getLeftId())
            ->setParameter('right', $album->getRightId())
            ->setFirstResult($offset)
            ->setMaxResults($perPage);

        $this->applySort($qb, $sort, false);

        return $qb->getQuery()->getResult();
    }

    private function applySort(\Doctrine\ORM\QueryBuilder $qb, string $sort, bool $allowManual): void
    {
        if ($sort === 'manual' && $allowManual) {
            $qb->orderBy('am.position', 'ASC')
                ->addOrderBy('m.id', 'ASC');
            return;
        }

        if ($sort === 'oldest') {
            $qb->orderBy('m.createdAt', 'ASC')->addOrderBy('m.id', 'ASC');
            return;
        }

        if ($sort === 'title') {
            $qb->orderBy('m.title', 'ASC')->addOrderBy('m.originalFilename', 'ASC');
            return;
        }

        if ($sort === 'filename') {
            $qb->orderBy('m.originalFilename', 'ASC')->addOrderBy('m.id', 'ASC');
            return;
        }

        if ($sort === 'largest') {
            $qb->orderBy('m.fileSize', 'DESC')->addOrderBy('m.id', 'DESC');
            return;
        }

        $qb->orderBy('m.createdAt', 'DESC')->addOrderBy('m.id', 'DESC');
    }

    /**
     * Find all media entries for an album, ordered by position.
     *
     * @return list<AlbumMedia>
     */
    public function findByAlbum(Album $album): array
    {
        return $this->createQueryBuilder('am')
            ->where('am.album = :album')
            ->setParameter('album', $album)
            ->orderBy('am.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all media entries for an album by album ID, ordered by position.
     *
     * @return list<AlbumMedia>
     */
    public function findByAlbumId(int $albumId): array
    {
        return $this->createQueryBuilder('am')
            ->join('am.media', 'm')
            ->where('am.album = :albumId')
            ->setParameter('albumId', $albumId)
            ->orderBy('am.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get the maximum position value for items in an album.
     */
    public function getMaxPosition(Album $album): int
    {
        $result = $this->createQueryBuilder('am')
            ->select('MAX(am.position)')
            ->where('am.album = :album')
            ->setParameter('album', $album)
            ->getQuery()
            ->getSingleScalarResult();

        return (int)($result ?? 0);
    }

    /**
     * Find the previous item in an album (lower position).
     */
    public function findPreviousInAlbum(Album $album, int $currentPosition, int $currentId): ?AlbumMedia
    {
        return $this->createQueryBuilder('am')
            ->where('am.album = :album')
            ->andWhere('am.position < :position OR (am.position = :position AND am.id < :id)')
            ->setParameter('album', $album)
            ->setParameter('position', $currentPosition)
            ->setParameter('id', $currentId)
            ->orderBy('am.position', 'DESC')
            ->addOrderBy('am.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find the next item in an album (higher position).
     */
    public function findNextInAlbum(Album $album, int $currentPosition, int $currentId): ?AlbumMedia
    {
        return $this->createQueryBuilder('am')
            ->where('am.album = :album')
            ->andWhere('am.position > :position OR (am.position = :position AND am.id > :id)')
            ->setParameter('album', $album)
            ->setParameter('position', $currentPosition)
            ->setParameter('id', $currentId)
            ->orderBy('am.position', 'ASC')
            ->addOrderBy('am.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Album ids containing a specific media item.
     *
     * @return list<int>
     */
    public function findAlbumsByMedia(Media $media): array
    {
        $ids = $this->createQueryBuilder('am')
            ->select('IDENTITY(am.album)')
            ->where('am.media = :media')
            ->setParameter('media', $media)
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_map(static fn ($id) => (int) $id, $ids));
    }

    /**
     * Get all distinct Media for descendant albums of a set in a single query.
     *
     * @return Media[]
     */
    public function findMediaForDescendants(int $parentLeftId, int $parentRightId): array
    {
        return $this->getEntityManager()
            ->createQuery(
                'SELECT DISTINCT m FROM Modufolio\Media\Entity\Media m
                 JOIN Modufolio\Media\Entity\AlbumMedia am WITH am.media = m
                 JOIN Modufolio\Media\Entity\Album a WITH am.album = a
                 WHERE a.leftId > :left AND a.rightId < :right AND a.albumType = 0'
            )
            ->setParameter('left', $parentLeftId)
            ->setParameter('right', $parentRightId)
            ->getResult();
    }

    /**
     * Find public image Media for an album, ordered by position.
     *
     * @return Media[]
     */
    public function findPublicImagesForAlbum(Album $album): array
    {
        return $this->getEntityManager()
            ->createQuery(
                'SELECT m FROM Modufolio\Media\Entity\Media m
                 JOIN Modufolio\Media\Entity\AlbumMedia am WITH am.media = m
                 WHERE am.album = :album
                 AND m.mimeType LIKE :image
                 AND m.isPublic = true
                 ORDER BY am.position ASC'
            )
            ->setParameter('album', $album)
            ->setParameter('image', 'image/%')
            ->getResult();
    }

    /**
     * Find a specific album-media entry.
     */
    public function findEntry(int $albumId, int $mediaId): ?AlbumMedia
    {
        return $this->createQueryBuilder('am')
            ->where('am.album = :albumId')
            ->andWhere('am.media = :mediaId')
            ->setParameter('albumId', $albumId)
            ->setParameter('mediaId', $mediaId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find multiple album-media entries for a given album and set of media IDs in one query.
     *
     * @param int[] $mediaIds
     * @return AlbumMedia[]
     */
    public function findEntriesByMediaIds(int $albumId, array $mediaIds): array
    {
        if (empty($mediaIds)) {
            return [];
        }

        return $this->createQueryBuilder('am')
            ->where('am.album = :albumId')
            ->andWhere('am.media IN (:mediaIds)')
            ->setParameter('albumId', $albumId)
            ->setParameter('mediaIds', $mediaIds)
            ->getQuery()
            ->getResult();
    }

    /**
     * First media (lowest position) for each of the given album ids, in one
     * query — for cover fallbacks that would otherwise call
     * getAlbumMedia()->first() lazily per album. Media type is not filtered
     * here; the caller decides whether the first item is a usable cover.
     *
     * @param int[] $albumIds
     * @return array<int, \Modufolio\Media\Entity\Media> albumId => first Media
     */
    public function findFirstMediaPerAlbum(array $albumIds): array
    {
        return array_map(
            static fn (array $media): \Modufolio\Media\Entity\Media => $media[0],
            $this->findMediaPerAlbum($albumIds, 1)
        );
    }

    /**
     * The lowest-positioned media of each album, capped per album, in one
     * query — for card previews that would otherwise call
     * getAlbumMedia()->slice() lazily per album, hydrating a whole join
     * collection and then a Media proxy per row.
     *
     * Media type is not filtered here; the caller decides what is usable.
     *
     * @param int[] $albumIds
     * @return array<int, list<\Modufolio\Media\Entity\Media>> albumId => media, position ascending
     */
    public function findMediaPerAlbum(array $albumIds, int $limit): array
    {
        if ($albumIds === [] || $limit < 1) {
            return [];
        }

        // Rank each album's rows by position and keep only the first $limit —
        // "top N per group", which plain SQL cannot express: LIMIT bounds the
        // whole result and GROUP BY collapses the rows we want.
        //
        // Doing the cap here rather than in PHP is what makes this bounded:
        // selecting every row to discard all but three ties the cost to the
        // size of the library instead of the size of the page. The subquery is
        // required because WHERE is evaluated before window functions, so `rn`
        // cannot be filtered in the same SELECT that produces it.
        //
        // Deliberately SQLite-specific (window functions need 3.25+). The
        // (album_id, position) index lets SQLite walk each partition in order.
        // media_id breaks ties so repeated calls agree, even though the
        // reorder triggers already keep positions unique within an album.
        $sql = <<<'SQL'
            SELECT album_id, media_id FROM (
                SELECT album_id,
                       media_id,
                       ROW_NUMBER() OVER (
                           PARTITION BY album_id
                           ORDER BY position, media_id
                       ) AS rn
                FROM album_media
                WHERE album_id IN (:ids)
            )
            WHERE rn <= :limit
            ORDER BY album_id, rn
            SQL;

        $pairs = $this->getEntityManager()->getConnection()->executeQuery(
            $sql,
            [
                'ids'   => array_map('intval', $albumIds),
                'limit' => $limit,
            ],
            [
                'ids'   => ArrayParameterType::INTEGER,
                'limit' => ParameterType::INTEGER,
            ],
        )->fetchAllAssociative();

        if ($pairs === []) {
            return [];
        }

        // One query for the media themselves. findBy() returns them in its own
        // order, so they are indexed by id and re-read in the ranked order.
        $mediaById = [];

        foreach ($this->getEntityManager()->getRepository(Media::class)->findBy(
            ['id' => array_map('intval', array_column($pairs, 'media_id'))]
        ) as $media) {
            $mediaById[$media->getId()] = $media;
        }

        $map = [];

        foreach ($pairs as $pair) {
            $media = $mediaById[(int)$pair['media_id']] ?? null;

            if ($media !== null) {
                $map[(int)$pair['album_id']][] = $media;
            }
        }

        return $map;
    }
}
