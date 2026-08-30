<?php

declare(strict_types = 1);

namespace Modufolio\Media\Repository;

use Modufolio\Media\Entity\Album;
use Modufolio\Media\Contract\AlbumTreeInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Modufolio\Appkit\Toolkit\Func;
use Ramsey\Uuid\Uuid as RamseyUuid;

/**
 * @extends EntityRepository<Album>
 */
class AlbumRepository extends EntityRepository implements AlbumTreeInterface
{
    public function findOneByUuid(string $uuid): ?Album
    {
        try {
            $uuidObject = RamseyUuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $this->findOneBy(['uuid' => $uuidObject]);
    }

    /**
     * Resolve an ordered list of Album uuids to internal int ids in a
     * single query. Non-string entries and uuids that don't resolve to an
     * existing Album are silently skipped; the relative order of the
     * resolved ids is preserved, which matters for the reorder endpoints.
     *
     * @param mixed[] $uuids
     * @return int[]
     */
    public function resolveIdsByUuids(array $uuids): array
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

        if ($canonical === []) {
            return [];
        }

        $idByUuid = $this->getEntityManager()->getConnection()->fetchAllKeyValue(
            'SELECT uuid, id FROM albums WHERE uuid IN (?)',
            [array_unique($canonical)],
            [ArrayParameterType::STRING]
        );

        $ids = [];
        foreach ($canonical as $uuid) {
            if (isset($idByUuid[$uuid])) {
                $ids[] = (int)$idByUuid[$uuid];
            }
        }
        return $ids;
    }

    /**
     * Fallback upper bound for right_id when no parent exists (root-level siblings).
     * In nested-set trees, right_id values are always 2 × node_count, so this value
     * is safe for any realistic tree size.
     */
    private const ROOT_RIGHT_BOUND = 999999;
    // ========================================================================
    // ORM QUERIES (return Album entities)
    // ========================================================================

    /**
     * Get all albums ordered by nested set left_id (tree order).
     */
    public function findAllAsTree(): array
    {
        return $this->withCovers($this->createQueryBuilder('a'))
            ->orderBy('a.leftId', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get root-level albums only (level = 1).
     */
    public function findRootAlbums(): array
    {
        return $this->withCovers($this->createQueryBuilder('a'))
            ->where('a.level = 1')
            ->orderBy('a.position', 'ASC')
            ->addOrderBy('a.leftId', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get direct children of a set.
     */
    public function findChildren(Album $parent): array
    {
        return $this->withCovers($this->createQueryBuilder('a'))
            ->where('a.leftId > :left')
            ->andWhere('a.rightId < :right')
            ->andWhere('a.level = :level')
            ->setParameter('left', $parent->getLeftId())
            ->setParameter('right', $parent->getRightId())
            ->setParameter('level', $parent->getLevel() + 1)
            ->orderBy('a.position', 'ASC')
            ->addOrderBy('a.leftId', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Load albums by internal id in a single cover-fetch-joined query, for
     * presenter paths that would otherwise call find() per id in a loop.
     *
     * @param  int[] $ids
     * @return Album[]
     */
    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->withCovers($this->createQueryBuilder('a'))
            ->where('a.id IN (:ids)')
            ->setParameter('ids', array_map('intval', $ids))
            ->getQuery()
            ->getResult();
    }

    /**
     * Direct children of several parent sets in a single cover-fetch-joined
     * query (instead of findChildren() once per set). The caller groups the
     * result back to each parent via nested-set boundaries.
     *
     * @param  Album[] $parents
     * @return Album[]
     */
    public function findChildrenForParents(array $parents): array
    {
        if ($parents === []) {
            return [];
        }

        $qb = $this->withCovers($this->createQueryBuilder('a'));
        $or = $qb->expr()->orX();

        foreach (array_values($parents) as $i => $parent) {
            $or->add($qb->expr()->andX(
                $qb->expr()->gt('a.leftId', ":pl$i"),
                $qb->expr()->lt('a.rightId', ":pr$i"),
                $qb->expr()->eq('a.level', ":plvl$i"),
            ));
            $qb->setParameter("pl$i", $parent->getLeftId())
               ->setParameter("pr$i", $parent->getRightId())
               ->setParameter("plvl$i", $parent->getLevel() + 1);
        }

        return $qb->where($or)
            ->orderBy('a.position', 'ASC')
            ->addOrderBy('a.leftId', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Eager-fetch the three cover ToOne relations the AlbumPresenter always
     * reads, so presenting a collection of albums doesn't fire a lazy proxy
     * load per album per cover (3N queries over the tree). All three are
     * to-one, so joining them causes no row multiplication.
     */
    private function withCovers(QueryBuilder $qb): QueryBuilder
    {
        return $qb
            ->leftJoin('a.coverMedia', 'c1')->addSelect('c1')
            ->leftJoin('a.coverMedia2', 'c2')->addSelect('c2')
            ->leftJoin('a.coverMedia3', 'c3')->addSelect('c3');
    }

    public function children(?object $parent = null): array
    {
        if ($parent === null) {
            return $this->findAllAsTree();
        }
        return $this->findChildren($parent);
    }

    public function getMaxRootPosition(): int
    {
        $result = $this->createQueryBuilder('a')
            ->select('MAX(a.position)')
            ->where('a.level = 1')
            ->getQuery()
            ->getSingleScalarResult();

        return (int)($result ?? -1);
    }

    public function getMaxChildPosition(Album $parent): int
    {
        $result = $this->createQueryBuilder('a')
            ->select('MAX(a.position)')
            ->where('a.leftId > :left')
            ->andWhere('a.rightId < :right')
            ->andWhere('a.level = :level')
            ->setParameter('left', $parent->getLeftId())
            ->setParameter('right', $parent->getRightId())
            ->setParameter('level', $parent->getLevel() + 1)
            ->getQuery()
            ->getSingleScalarResult();

        return (int)($result ?? -1);
    }

    /**
     * Get all descendants of a set (any depth).
     */
    public function findDescendants(Album $parent): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.leftId > :left')
            ->andWhere('a.rightId < :right')
            ->setParameter('left', $parent->getLeftId())
            ->setParameter('right', $parent->getRightId())
            ->orderBy('a.leftId', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get ancestors of an album (path from root).
     */
    public function findAncestors(Album $album): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.leftId < :left')
            ->andWhere('a.rightId > :right')
            ->setParameter('left', $album->getLeftId())
            ->setParameter('right', $album->getRightId())
            ->orderBy('a.leftId', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find album by slug.
     */
    public function findBySlug(string $slug): ?Album
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Get the maximum right_id value (for inserting new root nodes).
     */
    public function getMaxRight(): int
    {
        $result = $this->createQueryBuilder('a')
            ->select('MAX(a.rightId)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int)($result ?? 0);
    }

    /**
     * Get total album count.
     */
    public function getAlbumCount(): int
    {
        return (int)$this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    // ========================================================================
    // DBAL CTE QUERIES (return raw arrays — Doctrine DBAL ^4 with() support)
    // ========================================================================

    private function getConnection(): Connection
    {
        return $this->getEntityManager()->getConnection();
    }

    /**
     * Get full album tree with hierarchical paths using recursive CTE.
     *
     * Adapted from Koken's getTree() — uses DBAL QueryBuilder with() for CTEs.
     *
     * @return list<array<string, mixed>>
     */
    public function getTreeCTE(?int $maxDepth = null): array
    {
        $conn = $this->getConnection();

        $recursiveSql = 'SELECT c.id, c.title, c.slug, c.level, c.left_id, c.right_id,'
            . ' p.root_id, p.depth_from_root + 1 AS depth_from_root,'
            . " p.path || ' > ' || c.title AS path"
            . ' FROM albums c'
            . ' INNER JOIN album_hierarchy p'
            . ' ON c.left_id > p.left_id AND c.right_id < p.right_id AND c.level = p.level + 1';

        $params = [];
        $paramIndex = 0;

        if ($maxDepth !== null) {
            $recursiveSql .= ' WHERE p.depth_from_root < ?';
            $params[$paramIndex++] = $maxDepth;
        }

        $cteSql = 'SELECT id, title, slug, level, left_id, right_id,'
            . ' id AS root_id, 0 AS depth_from_root, title AS path'
            . ' FROM albums WHERE level = 1'
            . ' UNION ALL ' . $recursiveSql;

        $qb = $conn->createQueryBuilder()
            ->with('RECURSIVE album_hierarchy', $cteSql)
            ->select('*')
            ->from('album_hierarchy')
            ->orderBy('left_id', 'ASC');

        foreach ($params as $i => $value) {
            $qb->setParameter($i, $value);
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Get breadcrumb path (ancestors) for an album using CTE.
     *
     * Adapted from Koken's getBreadcrumb().
     *
     * @return list<array<string, mixed>>
     */
    public function getBreadcrumbCTE(int $albumId): array
    {
        $conn = $this->getConnection();

        $cteSql = 'SELECT parent.id, parent.title, parent.slug, parent.level, parent.left_id'
            . ' FROM albums target'
            . ' INNER JOIN albums parent'
            . ' ON parent.left_id <= target.left_id AND parent.right_id >= target.right_id'
            . ' WHERE target.id = ?';

        $qb = $conn->createQueryBuilder()
            ->with('album_path', $cteSql)
            ->select('*')
            ->from('album_path')
            ->orderBy('level', 'ASC')
            ->setParameter(0, $albumId);

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Get subtree (all descendants) with optional depth limit using CTE.
     *
     * Adapted from Koken's getSubtree().
     *
     * @return list<array<string, mixed>>
     */
    public function getSubtreeCTE(int $parentId, ?int $maxDepth = null): array
    {
        $conn = $this->getConnection();

        $cteSql = 'SELECT left_id, right_id, level FROM albums WHERE id = ?';

        $mainSql = 'SELECT c.id, c.title, c.slug, c.level,'
            . ' c.level - p.level AS depth_from_parent,'
            . ' c.left_id, c.right_id, c.media_count, c.album_type'
            . ' FROM albums c, parent_info p'
            . ' WHERE c.left_id > p.left_id AND c.right_id < p.right_id';

        $params = [0 => $parentId];

        if ($maxDepth !== null) {
            $mainSql .= ' AND c.level <= p.level + ?';
            $params[1] = $maxDepth;
        }

        $qb = $conn->createQueryBuilder()
            ->with('parent_info', $cteSql)
            ->select('sub.*')
            ->from('(' . $mainSql . ')', 'sub')
            ->orderBy('sub.left_id', 'ASC');

        foreach ($params as $i => $value) {
            $qb->setParameter($i, $value);
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Get siblings of an album using CTE.
     *
     * Adapted from Koken's getSiblings().
     *
     * @return list<array<string, mixed>>
     */
    public function getSiblingsCTE(int $albumId, bool $includeSelf = false): array
    {
        $conn = $this->getConnection();

        $cteSql = 'SELECT a.id, a.left_id, a.right_id, a.level,'
            . ' p.id AS parent_id, p.left_id AS parent_left, p.right_id AS parent_right'
            . ' FROM albums a'
            . ' LEFT JOIN albums p'
            . ' ON p.left_id < a.left_id AND p.right_id > a.right_id AND p.level = a.level - 1'
            . ' WHERE a.id = ?';

        $mainWhere = 's.left_id > COALESCE(t.parent_left, 0)'
            . ' AND s.right_id < COALESCE(t.parent_right, ' . self::ROOT_RIGHT_BOUND . ')'
            . ' AND s.level = t.level';

        if (!$includeSelf) {
            $mainWhere .= ' AND s.id != t.id';
        }

        $qb = $conn->createQueryBuilder()
            ->with('target_info', $cteSql)
            ->select(
                's.id',
                's.title',
                's.slug',
                's.level',
                's.media_count',
                'CASE WHEN s.id = t.id THEN 1 ELSE 0 END AS is_current'
            )
            ->from('albums', 's')
            ->innerJoin('s', 'target_info', 't', '1=1')
            ->where($mainWhere)
            ->orderBy('s.left_id', 'ASC')
            ->setParameter(0, $albumId);

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Get leaf albums (albums with no children) using nested-set property.
     *
     * From Koken: right_id = left_id + 1 means no children (O(1) detection).
     *
     * @return list<array<string, mixed>>
     */
    public function getLeaves(): array
    {
        $conn = $this->getConnection();

        return $conn->createQueryBuilder()
            ->select('id', 'title', 'slug', 'level', 'media_count', 'left_id', 'right_id')
            ->from('albums')
            ->where('right_id = left_id + 1')
            ->orderBy('left_id', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Get effective media counts for every album using nested-set boundaries.
     *
     * Albums (type 0) return their direct media_count.
     * Sets (type 1) return the sum of media_count of all descendant albums.
     *
     * Uses a correlated subquery so the aggregation runs entirely in SQL.
     *
     * @return array<string, int> Map of album uuid → effective media count.
     *                            Keyed by uuid (not the internal int id) since that's what the
     *                            frontend addresses albums by everywhere else.
     */
    public function getEffectiveCounts(): array
    {
        $conn = $this->getConnection();

        $rows = $conn->createQueryBuilder()
            ->select(
                'a.uuid',
                'CASE WHEN a.album_type = 0 THEN a.media_count'
                . ' ELSE COALESCE((SELECT SUM(d.media_count) FROM albums d'
                . ' WHERE d.album_type = 0 AND d.left_id > a.left_id AND d.right_id < a.right_id), 0)'
                . ' END AS effective_count'
            )
            ->from('albums', 'a')
            ->executeQuery()
            ->fetchAllAssociative();

        return Func::reduce(static function (array $map, array $row): array {
            $map[(string)$row['uuid']] = (int)$row['effective_count'];
            return $map;
        }, $rows, []);
    }

    // ========================================================================
    // WRITE OPERATIONS (use raw DBAL for nested-set manipulation)
    // ========================================================================

    /**
     * Insert a new node into the nested set tree.
     *
     * If $parent is null, insert as a new root node.
     * If $parent is provided, insert as the last child of that parent.
     *
     * Inspired by Koken: wrapped in transaction + depth limit validation.
     */
    public function insertNode(Album $album, ?Album $parent = null): void
    {
        $em = $this->getEntityManager();
        $conn = $em->getConnection();

        $conn->beginTransaction();

        try {
            if ($parent === null) {
                // Insert as root: place at the end of the tree
                $maxRight = $this->getMaxRight();
                $album->setLeftId($maxRight + 1);
                $album->setRightId($maxRight + 2);
                $album->setLevel(1);
            } else {
                $newLevel = $parent->getLevel() + 1;

                // Depth limit (from Koken nested-set helpers)
                if ($newLevel > 10) {
                    throw new \InvalidArgumentException('Maximum nesting depth (10 levels) exceeded');
                }

                // Insert as last child of parent
                $parentRight = $parent->getRightId();

                // Shift all nodes with right_id >= parent's right_id
                $conn->executeStatement(
                    'UPDATE albums SET right_id = right_id + 2 WHERE right_id >= :parentRight',
                    ['parentRight' => $parentRight]
                );
                $conn->executeStatement(
                    'UPDATE albums SET left_id = left_id + 2 WHERE left_id > :parentRight',
                    ['parentRight' => $parentRight]
                );

                // Refresh parent entity to get updated values
                $em->refresh($parent);

                $album->setLeftId($parentRight);
                $album->setRightId($parentRight + 1);
                $album->setLevel($newLevel);
            }

            $em->persist($album);
            $em->flush();

            $conn->commit();
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * Remove a node (and all its descendants) from the nested set tree.
     *
     * Wrapped in transaction for data integrity (from Koken pattern).
     */
    public function removeNode(Album $album): void
    {
        $em = $this->getEntityManager();
        $conn = $em->getConnection();

        $left = $album->getLeftId();
        $right = $album->getRightId();
        $width = $right - $left + 1;

        $conn->beginTransaction();

        try {
            // Delete the node and all descendants
            $conn->executeStatement(
                'DELETE FROM albums WHERE left_id >= :left AND right_id <= :right',
                ['left' => $left, 'right' => $right]
            );

            // Also delete album_media entries for removed albums
            $conn->executeStatement(
                'DELETE FROM album_media WHERE album_id NOT IN (SELECT id FROM albums)'
            );

            // Close the gap: shift left values
            $conn->executeStatement(
                'UPDATE albums SET left_id = left_id - :width WHERE left_id > :right',
                ['width' => $width, 'right' => $right]
            );

            // Close the gap: shift right values
            $conn->executeStatement(
                'UPDATE albums SET right_id = right_id - :width WHERE right_id > :right',
                ['width' => $width, 'right' => $right]
            );

            $conn->commit();
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * Move a node (and its subtree) to become a child of a new parent.
     * If $newParent is null, move to root level.
     *
     * Uses a "park offset" instead of negation to temporarily displace the
     * moving subtree. Negation would violate the database trigger that enforces
     * left_id < right_id (e.g. -4 >= -5 fires the constraint). Adding a large
     * constant preserves left_id < right_id throughout all intermediate steps.
     */
    public function moveNode(Album $album, ?Album $newParent = null): void
    {
        $em   = $this->getEntityManager();
        $conn = $em->getConnection();

        $left     = $album->getLeftId();
        $right    = $album->getRightId();
        $width    = $right - $left + 1;
        $oldLevel = $album->getLevel();

        if ($newParent === null) {
            $newLevel = 1;
        } else {
            if ($newParent->getId() === $album->getId()) {
                throw new \InvalidArgumentException('Cannot move a node into itself.');
            }
            if ($newParent->getLeftId() >= $left && $newParent->getRightId() <= $right) {
                throw new \InvalidArgumentException('Cannot move a node into one of its own descendants.');
            }
            $newLevel = $newParent->getLevel() + 1;
        }

        $levelDiff = $newLevel - $oldLevel;

        // Depth guard (mirrors insertNode()): reject a move that would push the
        // deepest node of the moving subtree past the 10-level limit *before* it
        // happens, with a friendly exception. Otherwise the per-row
        // albums_validate_depth_update trigger aborts the transaction mid-flight
        // and surfaces a raw SQLSTATE instead of a usable message. The trigger
        // stays as the backstop; this is the UX layer.
        if ($levelDiff > 0) {
            $maxSubtreeLevel = (int)$conn->fetchOne(
                'SELECT MAX(level) FROM albums WHERE left_id >= :left AND right_id <= :right',
                ['left' => $left, 'right' => $right]
            );
            if ($maxSubtreeLevel + $levelDiff > 10) {
                throw new \InvalidArgumentException('Maximum nesting depth (10 levels) exceeded');
            }
        }

        // Park offset: large enough that no real left/right value will ever reach it.
        // Adding the same constant to both left_id and right_id keeps left_id < right_id.
        $park = 1_000_000;

        $conn->beginTransaction();

        try {
            // Step 1: Park the moving subtree — shift both ids by +$park.
            // Parked nodes are identified later by left_id >= $park.
            $conn->executeStatement(
                'UPDATE albums SET left_id = left_id + :park, right_id = right_id + :park
                  WHERE left_id >= :left AND right_id <= :right',
                ['park' => $park, 'left' => $left, 'right' => $right]
            );

            // Step 2: Close the gap in the remaining (non-parked) tree.
            // Single statement: updating left_id and right_id separately would let the
            // BEFORE UPDATE trigger see a temporarily inconsistent state per row.
            $conn->executeStatement(
                'UPDATE albums
                    SET left_id  = CASE WHEN left_id  > :right THEN left_id  - :width ELSE left_id  END,
                        right_id = CASE WHEN right_id > :right THEN right_id - :width ELSE right_id END
                  WHERE (left_id > :right OR right_id > :right) AND left_id < :park',
                ['width' => $width, 'right' => $right, 'park' => $park]
            );

            // Step 3: Determine the insertion point after gap closure.
            if ($newParent === null) {
                $result   = $conn->fetchOne(
                    'SELECT COALESCE(MAX(right_id), 0) FROM albums WHERE right_id < :park',
                    ['park' => $park]
                );
                $insertAt = ((int)$result) + 1;
            } else {
                $em->refresh($newParent);   // reload to get post-gap-closure right_id
                $insertAt = $newParent->getRightId();
            }

            // Step 4: Make room at the insertion point in the remaining tree.
            // Single statement: updating left_id and right_id separately would let the
            // BEFORE UPDATE trigger see a temporarily inconsistent state per row.
            $conn->executeStatement(
                'UPDATE albums
                    SET left_id  = CASE WHEN left_id  >= :insertAt THEN left_id  + :width ELSE left_id  END,
                        right_id = CASE WHEN right_id >= :insertAt THEN right_id + :width ELSE right_id END
                  WHERE (left_id >= :insertAt OR right_id >= :insertAt) AND left_id < :park',
                ['width' => $width, 'insertAt' => $insertAt, 'park' => $park]
            );

            // Step 5 & 6: Unpark the subtree directly into its final position.
            // new_left  = (original_left  + park) - park + offset = original_left  + offset
            // new_right = (original_right + park) - park + offset = original_right + offset
            $offset = $insertAt - $left;

            $conn->executeStatement(
                'UPDATE albums
                    SET left_id  = left_id  - :park + :offset,
                        right_id = right_id - :park + :offset,
                        level    = level + :levelDiff
                  WHERE left_id >= :park',
                ['park' => $park, 'offset' => $offset, 'levelDiff' => $levelDiff]
            );

            $conn->commit();

            // Refresh only the moved entities so their PHP state reflects
            // the raw DBAL changes. Using $em->clear() would detach every
            // entity and break lazy relations elsewhere in the request.
            $em->refresh($album);
            if ($newParent !== null) {
                $em->refresh($newParent);
            }
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * Check if a slug already exists, optionally excluding a specific album.
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId !== null) {
            $qb->andWhere('a.id != :id')
                ->setParameter('id', $excludeId);
        }

        return (int)$qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Generate a unique slug from a title.
     */
    public function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $baseSlug = Album::slugify($title);

        if (empty($baseSlug)) {
            $baseSlug = 'album';
        }

        $slug = $baseSlug;
        $counter = 1;

        while ($this->slugExists($slug, $excludeId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
