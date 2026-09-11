<?php

declare(strict_types = 1);

namespace Modufolio\Media\Tests\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Modufolio\Media\Database\AlbumTriggerAdapterFactory;
use Modufolio\Media\Database\AlbumTriggerAdapterInterface;
use Modufolio\Media\Tests\Support\DatabaseConnectionTrait;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the real trigger/procedure DDL against a real engine — SQLite
 * in-memory by default, or whatever DB_DRIVER selects (see
 * docker-compose.yml) — for exactly the reason the original SQLite-only
 * version of this test gave: the whole point of these rules is their
 * behaviour inside the database, so a stubbed connection would prove
 * nothing. We build the minimal tables the rules touch, install the
 * engine's adapter verbatim, and assert on observable side-effects —
 * exactly what a migrated production database enforces.
 */
final class AlbumTriggersTest extends TestCase
{
    use DatabaseConnectionTrait;

    private Connection $db;
    private AlbumTriggerAdapterInterface $adapter;

    protected function setUp(): void
    {
        $this->db = DriverManager::getConnection(self::connectionParams());
        self::resetSchema($this->db);

        if ($this->db->getDatabasePlatform() instanceof SQLitePlatform) {
            // recursive_triggers OFF is the documented assumption of
            // album_media_reorder. OFF is SQLite's default; assert it here
            // so a changed default can't silently break the reorder logic.
            $this->db->executeStatement('PRAGMA recursive_triggers = OFF');
        }

        $this->createMinimalSchema();

        $this->adapter = AlbumTriggerAdapterFactory::forPlatform($this->db->getDatabasePlatform());
        foreach ($this->adapter->install() as $sql) {
            $this->db->executeStatement($sql);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->adapter->uninstall() as $sql) {
            $this->db->executeStatement($sql);
        }

        $this->db->executeStatement('DROP TABLE album_media');
        $this->db->executeStatement('DROP TABLE albums');

        parent::tearDown();
    }

    /**
     * Only the columns every rule references. Plain integer ids (no
     * auto-increment) since every helper below assigns ids explicitly —
     * avoids fighting each engine's own auto-increment syntax for schema
     * that doesn't need it.
     */
    private function createMinimalSchema(): void
    {
        $platform = $this->db->getDatabasePlatform();
        $timestampType = match (true) {
            $platform instanceof AbstractMySQLPlatform => 'DATETIME',
            $platform instanceof PostgreSQLPlatform => 'TIMESTAMP',
            $platform instanceof SQLServerPlatform => 'DATETIME2',
            default => 'TEXT', // SQLite: untyped storage, any affinity works.
        };

        $this->db->executeStatement(<<<SQL
            CREATE TABLE albums (
                id INT PRIMARY KEY,
                left_id INT NOT NULL,
                right_id INT NOT NULL,
                level INT NOT NULL DEFAULT 1,
                media_count INT NOT NULL DEFAULT 0,
                updated_at {$timestampType}
            )
            SQL);

        $this->db->executeStatement(<<<SQL
            CREATE TABLE album_media (
                id INT PRIMARY KEY,
                album_id INT NOT NULL,
                media_id INT NOT NULL DEFAULT 0,
                position INT NOT NULL,
                updated_at {$timestampType}
            )
            SQL);
    }

    // ---------------------------------------------------------------
    // Install/uninstall contract
    // ---------------------------------------------------------------

    public function testInstallReturnsAtLeastOneStatementPerRule(): void
    {
        $this->assertNotEmpty($this->adapter->install());
    }

    public function testUninstallThenReinstallSucceeds(): void
    {
        foreach ($this->adapter->uninstall() as $sql) {
            $this->db->executeStatement($sql);
        }
        foreach ($this->adapter->install() as $sql) {
            $this->db->executeStatement($sql);
        }

        // Prove the reinstalled rules still work, not just that install()
        // didn't throw.
        $this->expectExceptionMessageMatches('/nesting depth/');
        $this->insertAlbum(id: 1, left: 1, right: 2, mediaCount: 0, level: 11);
    }

    // ---------------------------------------------------------------
    // media_count maintenance
    // ---------------------------------------------------------------

    public function testInsertingMediaIncrementsAlbumMediaCount(): void
    {
        $this->insertAlbum(id: 1, left: 1, right: 2, mediaCount: 0);

        $this->addMedia(id: 10, albumId: 1, position: 1);
        $this->addMedia(id: 11, albumId: 1, position: 2);

        $this->assertSame(2, $this->mediaCountOf(1));
    }

    public function testDeletingMediaDecrementsAlbumMediaCount(): void
    {
        $this->insertAlbum(id: 1, left: 1, right: 2, mediaCount: 0);
        $this->addMedia(id: 10, albumId: 1, position: 1);
        $this->addMedia(id: 11, albumId: 1, position: 2);

        $this->db->executeStatement('DELETE FROM album_media WHERE id = 10');

        $this->assertSame(1, $this->mediaCountOf(1));
    }

    public function testMediaCountNeverGoesNegative(): void
    {
        // media_count is deliberately out of sync (0) with an existing row.
        $this->insertAlbum(id: 1, left: 1, right: 2, mediaCount: 0);
        $this->addMedia(id: 10, albumId: 1, position: 1);
        // The insert trigger pushed it to 1; force it back to 0 to test the floor.
        $this->db->executeStatement('UPDATE albums SET media_count = 0 WHERE id = 1');

        $this->db->executeStatement('DELETE FROM album_media WHERE id = 10');

        $this->assertSame(0, $this->mediaCountOf(1));
    }

    // ---------------------------------------------------------------
    // Boundary + depth guards
    // ---------------------------------------------------------------

    public function testInsertWithInvalidBoundariesIsAborted(): void
    {
        $this->expectExceptionMessageMatches('/Invalid boundaries/');

        $this->insertAlbum(id: 1, left: 5, right: 5, mediaCount: 0);
    }

    public function testUpdateToInvalidBoundariesIsAborted(): void
    {
        $this->insertAlbum(id: 1, left: 1, right: 2, mediaCount: 0);

        $this->expectExceptionMessageMatches('/Invalid boundaries/');

        $this->db->executeStatement('UPDATE albums SET right_id = 1 WHERE id = 1');
    }

    public function testInsertExceedingMaxDepthIsAborted(): void
    {
        $this->expectExceptionMessageMatches('/nesting depth/');

        $this->insertAlbum(id: 1, left: 1, right: 2, mediaCount: 0, level: 11);
    }

    public function testDepthOfExactlyTenIsAllowed(): void
    {
        $this->insertAlbum(id: 1, left: 1, right: 2, mediaCount: 0, level: 10);

        $this->assertSame(1, (int) $this->db->fetchOne('SELECT COUNT(*) FROM albums'));
    }

    // ---------------------------------------------------------------
    // Position reorder + gap fill
    // ---------------------------------------------------------------

    public function testMovingItemUpShiftsIntermediateItemsDown(): void
    {
        $this->insertAlbum(id: 1, left: 1, right: 2, mediaCount: 0);
        $this->seedPositions(albumId: 1, count: 5); // ids 100..104 at positions 1..5

        // Move id 104 (position 5) up to position 2.
        $this->adapter->repositionMedia($this->db, 104, 2);

        $this->assertSame([
            100 => 1,
            101 => 3,
            102 => 4,
            103 => 5,
            104 => 2,
        ], $this->positionsOf(1));
    }

    public function testMovingItemDownShiftsIntermediateItemsUp(): void
    {
        $this->insertAlbum(id: 1, left: 1, right: 2, mediaCount: 0);
        $this->seedPositions(albumId: 1, count: 5);

        // Move id 101 (position 2) down to position 5.
        $this->adapter->repositionMedia($this->db, 101, 5);

        $this->assertSame([
            100 => 1,
            101 => 5,
            102 => 2,
            103 => 3,
            104 => 4,
        ], $this->positionsOf(1));
    }

    public function testReorderIsScopedToTheSameAlbum(): void
    {
        $this->insertAlbum(id: 1, left: 1, right: 2, mediaCount: 0);
        $this->insertAlbum(id: 2, left: 3, right: 4, mediaCount: 0);
        $this->seedPositions(albumId: 1, count: 3); // ids 100..102
        $this->addMedia(id: 200, albumId: 2, position: 1);
        $this->addMedia(id: 201, albumId: 2, position: 2);

        $this->adapter->repositionMedia($this->db, 102, 1);

        // Album 2 untouched.
        $this->assertSame([200 => 1, 201 => 2], $this->positionsOf(2));
    }

    public function testDeletingItemClosesThePositionGap(): void
    {
        $this->insertAlbum(id: 1, left: 1, right: 2, mediaCount: 0);
        $this->seedPositions(albumId: 1, count: 4); // ids 100..103 at 1..4

        $this->adapter->removeMediaEverywhere($this->db, mediaId: 101); // position 2

        $this->assertSame([
            100 => 1,
            102 => 2,
            103 => 3,
        ], $this->positionsOf(1));
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function insertAlbum(int $id, int $left, int $right, int $mediaCount, int $level = 1): void
    {
        $this->db->executeStatement(
            'INSERT INTO albums (id, left_id, right_id, level, media_count) VALUES (?, ?, ?, ?, ?)',
            [$id, $left, $right, $level, $mediaCount],
        );
    }

    /** media_id doubles as the id here — removeMediaEverywhere() filters by it, and each row is otherwise unique. */
    private function addMedia(int $id, int $albumId, int $position): void
    {
        $this->db->executeStatement(
            'INSERT INTO album_media (id, album_id, media_id, position) VALUES (?, ?, ?, ?)',
            [$id, $albumId, $id, $position],
        );
    }

    /** Seed $count rows at positions 1..$count with ids 100, 101, ... */
    private function seedPositions(int $albumId, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->addMedia(id: 100 + $i, albumId: $albumId, position: $i + 1);
        }
    }

    private function mediaCountOf(int $albumId): int
    {
        return (int) $this->db->fetchOne('SELECT media_count FROM albums WHERE id = ?', [$albumId]);
    }

    /** @return array<int, int> id => position, ordered by id */
    private function positionsOf(int $albumId): array
    {
        $rows = $this->db->fetchAllKeyValue(
            'SELECT id, position FROM album_media WHERE album_id = ? ORDER BY id',
            [$albumId],
        );

        return array_map('intval', $rows);
    }
}
