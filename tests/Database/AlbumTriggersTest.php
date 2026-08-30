<?php

declare(strict_types = 1);

namespace Modufolio\Media\Tests\Database;

use Modufolio\Media\Database\AlbumTriggers;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the real trigger DDL against a real in-memory SQLite engine.
 *
 * No mocks: the whole point of these triggers is their behaviour inside SQLite,
 * so a stubbed connection would prove nothing. We build the minimal tables the
 * triggers touch, install AlbumTriggers verbatim, and assert on observable
 * side-effects — exactly what a migrated production database would enforce.
 */
final class AlbumTriggersTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // recursive_triggers OFF is the documented assumption of album_media_reorder.
        // OFF is SQLite's default; we assert it here so a changed default can't
        // silently break the reorder logic.
        $this->db->exec('PRAGMA recursive_triggers = OFF');

        // Minimal schema — only the columns the triggers reference.
        $this->db->exec(<<<'SQL'
            CREATE TABLE albums (
                id INTEGER PRIMARY KEY,
                left_id INTEGER NOT NULL,
                right_id INTEGER NOT NULL,
                level INTEGER NOT NULL DEFAULT 1,
                media_count INTEGER NOT NULL DEFAULT 0,
                updated_at TEXT
            )
        SQL);

        $this->db->exec(<<<'SQL'
            CREATE TABLE album_media (
                id INTEGER PRIMARY KEY,
                album_id INTEGER NOT NULL,
                position INTEGER NOT NULL
            )
        SQL);

        foreach (AlbumTriggers::all() as $sql) {
            $this->db->exec($sql);
        }
    }

    // ---------------------------------------------------------------
    // Static DDL contract
    // ---------------------------------------------------------------

    public function testAllContainsEveryTriggerFromBothGroups(): void
    {
        $expected = count(AlbumTriggers::integrity()) + count(AlbumTriggers::position());

        $this->assertCount($expected, AlbumTriggers::all());
        $this->assertSame(8, $expected, 'Expected 6 integrity + 2 position triggers.');
    }

    public function testEveryTriggerIsInstalledInSqlite(): void
    {
        $names = $this->query("SELECT name FROM sqlite_master WHERE type = 'trigger' ORDER BY name")
            ->fetchAll(PDO::FETCH_COLUMN);

        $expected = [
            ...array_keys(AlbumTriggers::integrity()),
            ...array_keys(AlbumTriggers::position()),
        ];
        sort($expected);

        $this->assertSame($expected, $names);
    }

    public function testDropAllRemovesEveryTrigger(): void
    {
        foreach (AlbumTriggers::dropAll() as $sql) {
            $this->db->exec($sql);
        }

        $remaining = (int) $this->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'trigger'")
            ->fetchColumn();

        $this->assertSame(0, $remaining);
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

        $this->db->exec('DELETE FROM album_media WHERE id = 10');

        $this->assertSame(1, $this->mediaCountOf(1));
    }

    public function testMediaCountNeverGoesNegative(): void
    {
        // media_count is deliberately out of sync (0) with an existing row.
        $this->insertAlbum(id: 1, left: 1, right: 2, mediaCount: 0);
        $this->addMedia(id: 10, albumId: 1, position: 1);
        // The insert trigger pushed it to 1; force it back to 0 to test the floor.
        $this->db->exec('UPDATE albums SET media_count = 0 WHERE id = 1');

        $this->db->exec('DELETE FROM album_media WHERE id = 10');

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

        $this->db->exec('UPDATE albums SET right_id = 1 WHERE id = 1');
    }

    public function testInsertExceedingMaxDepthIsAborted(): void
    {
        $this->expectExceptionMessageMatches('/nesting depth/');

        $this->insertAlbum(id: 1, left: 1, right: 2, mediaCount: 0, level: 11);
    }

    public function testDepthOfExactlyTenIsAllowed(): void
    {
        $this->insertAlbum(id: 1, left: 1, right: 2, mediaCount: 0, level: 10);

        $this->assertSame(1, (int) $this->query('SELECT COUNT(*) FROM albums')->fetchColumn());
    }

    // ---------------------------------------------------------------
    // Position reorder + gap fill
    // ---------------------------------------------------------------

    public function testMovingItemUpShiftsIntermediateItemsDown(): void
    {
        $this->insertAlbum(id: 1, left: 1, right: 2, mediaCount: 0);
        $this->seedPositions(albumId: 1, count: 5); // ids 100..104 at positions 1..5

        // Move id 104 (position 5) up to position 2.
        $this->db->exec('UPDATE album_media SET position = 2 WHERE id = 104');

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
        $this->db->exec('UPDATE album_media SET position = 5 WHERE id = 101');

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

        $this->db->exec('UPDATE album_media SET position = 1 WHERE id = 102');

        // Album 2 untouched.
        $this->assertSame([200 => 1, 201 => 2], $this->positionsOf(2));
    }

    public function testDeletingItemClosesThePositionGap(): void
    {
        $this->insertAlbum(id: 1, left: 1, right: 2, mediaCount: 0);
        $this->seedPositions(albumId: 1, count: 4); // ids 100..103 at 1..4

        $this->db->exec('DELETE FROM album_media WHERE id = 101'); // position 2

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
        $stmt = $this->db->prepare(
            'INSERT INTO albums (id, left_id, right_id, level, media_count) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$id, $left, $right, $level, $mediaCount]);
    }

    private function addMedia(int $id, int $albumId, int $position): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO album_media (id, album_id, position) VALUES (?, ?, ?)'
        );
        $stmt->execute([$id, $albumId, $position]);
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
        $stmt = $this->db->prepare('SELECT media_count FROM albums WHERE id = ?');
        $stmt->execute([$albumId]);

        return (int) $stmt->fetchColumn();
    }

    /** @return array<int, int> id => position, ordered by id */
    private function positionsOf(int $albumId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, position FROM album_media WHERE album_id = ? ORDER BY id'
        );
        $stmt->execute([$albumId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /** PDO::query() returns false on failure; a bad statement should fail loudly. */
    private function query(string $sql): \PDOStatement
    {
        $stmt = $this->db->query($sql);
        \assert($stmt instanceof \PDOStatement);

        return $stmt;
    }
}
