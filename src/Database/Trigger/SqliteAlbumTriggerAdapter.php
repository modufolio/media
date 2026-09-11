<?php

declare(strict_types = 1);

namespace Modufolio\Media\Database\Trigger;

use Doctrine\DBAL\Connection;
use Modufolio\Media\Database\AlbumTriggerAdapterInterface;

/**
 * SQLite triggers guarding album integrity — the original implementation,
 * applied to production/dev databases by the Doctrine migration
 * Version20260829180000 and to the in-memory test database by
 * MediaTestCase::setUp().
 *
 * Note on `recursive_triggers`: album_media_reorder relies on SQLite's
 * default `recursive_triggers = OFF`, so its inner UPDATEs do not re-fire
 * the trigger.
 */
final class SqliteAlbumTriggerAdapter implements AlbumTriggerAdapterInterface
{
    /**
     * Nested-set boundary/depth integrity + media-count maintenance.
     *
     * @return array<string, string> triggerName => CREATE TRIGGER statement
     */
    public static function integrity(): array
    {
        return [
            'albums_validate_boundaries' => <<<'SQL'
                CREATE TRIGGER IF NOT EXISTS albums_validate_boundaries
                BEFORE INSERT ON albums
                FOR EACH ROW
                WHEN NEW.left_id >= NEW.right_id
                BEGIN
                    SELECT RAISE(ABORT, 'Invalid boundaries: left_id must be less than right_id');
                END
                SQL,

            'albums_validate_boundaries_update' => <<<'SQL'
                CREATE TRIGGER IF NOT EXISTS albums_validate_boundaries_update
                BEFORE UPDATE ON albums
                FOR EACH ROW
                WHEN NEW.left_id >= NEW.right_id
                BEGIN
                    SELECT RAISE(ABORT, 'Invalid boundaries: left_id must be less than right_id');
                END
                SQL,

            'albums_validate_depth' => <<<'SQL'
                CREATE TRIGGER IF NOT EXISTS albums_validate_depth
                BEFORE INSERT ON albums
                FOR EACH ROW
                WHEN NEW.level > 10
                BEGIN
                    SELECT RAISE(ABORT, 'Maximum nesting depth (10 levels) exceeded');
                END
                SQL,

            'albums_validate_depth_update' => <<<'SQL'
                CREATE TRIGGER IF NOT EXISTS albums_validate_depth_update
                BEFORE UPDATE ON albums
                FOR EACH ROW
                WHEN NEW.level > 10
                BEGIN
                    SELECT RAISE(ABORT, 'Maximum nesting depth (10 levels) exceeded');
                END
                SQL,

            'albums_media_count_insert' => <<<'SQL'
                CREATE TRIGGER IF NOT EXISTS albums_media_count_insert
                AFTER INSERT ON album_media
                FOR EACH ROW
                BEGIN
                    UPDATE albums
                    SET media_count = media_count + 1,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = NEW.album_id;
                END
                SQL,

            'albums_media_count_delete' => <<<'SQL'
                CREATE TRIGGER IF NOT EXISTS albums_media_count_delete
                AFTER DELETE ON album_media
                FOR EACH ROW
                BEGIN
                    UPDATE albums
                    SET media_count = CASE WHEN media_count > 0 THEN media_count - 1 ELSE 0 END,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = OLD.album_id;
                END
                SQL,
        ];
    }

    /**
     * album_media single-item reorder + gap-fill on delete.
     *
     * @return array<string, string> triggerName => CREATE TRIGGER statement
     */
    public static function position(): array
    {
        return [
            'album_media_reorder' => <<<'SQL'
                CREATE TRIGGER IF NOT EXISTS album_media_reorder
                BEFORE UPDATE OF position ON album_media
                FOR EACH ROW
                WHEN NEW.position != OLD.position
                BEGIN
                    -- Moving up (e.g. 5 → 2): push items in [new, old) down by 1
                    UPDATE album_media
                    SET position = position + 1
                    WHERE album_id = OLD.album_id
                      AND id != NEW.id
                      AND position >= NEW.position
                      AND position < OLD.position;

                    -- Moving down (e.g. 2 → 5): pull items in (old, new] up by 1
                    UPDATE album_media
                    SET position = position - 1
                    WHERE album_id = OLD.album_id
                      AND id != NEW.id
                      AND position <= NEW.position
                      AND position > OLD.position;
                END
                SQL,

            'album_media_gap_fill' => <<<'SQL'
                CREATE TRIGGER IF NOT EXISTS album_media_gap_fill
                AFTER DELETE ON album_media
                FOR EACH ROW
                BEGIN
                    UPDATE album_media
                    SET position = position - 1
                    WHERE album_id = OLD.album_id
                      AND position > OLD.position;
                END
                SQL,
        ];
    }

    public function install(): array
    {
        return array_values([...self::integrity(), ...self::position()]);
    }

    public function uninstall(): array
    {
        $names = [...array_keys(self::integrity()), ...array_keys(self::position())];

        return array_map(
            static fn (string $name): string => "DROP TRIGGER IF EXISTS {$name}",
            $names,
        );
    }

    public function repositionMedia(Connection $connection, int $albumMediaId, int $newPosition): void
    {
        $connection->executeStatement(
            'UPDATE album_media SET position = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$newPosition, $albumMediaId],
        );
    }

    public function removeMediaEverywhere(Connection $connection, int $mediaId): void
    {
        $connection->executeStatement('DELETE FROM album_media WHERE media_id = ?', [$mediaId]);
    }
}
