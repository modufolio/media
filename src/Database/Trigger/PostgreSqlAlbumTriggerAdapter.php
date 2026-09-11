<?php

declare(strict_types = 1);

namespace Modufolio\Media\Database\Trigger;

use Doctrine\DBAL\Connection;
use Modufolio\Media\Database\AlbumTriggerAdapterInterface;

/**
 * PostgreSQL implementation of the album integrity rules.
 *
 * PostgreSQL, unlike MySQL, allows a row-level trigger to modify the table
 * that fired it and fires triggers normally for FK ON DELETE CASCADE
 * actions — both confirmed against a live postgres:16 container while
 * designing this adapter — so every rule is a real trigger, same shape as
 * SqliteAlbumTriggerAdapter. Each trigger needs its own PL/pgSQL function
 * (Postgres has no inline trigger body); functions are named after their
 * trigger with an `_fn` suffix.
 */
final class PostgreSqlAlbumTriggerAdapter implements AlbumTriggerAdapterInterface
{
    /** @return list<string> */
    public function install(): array
    {
        return [
            <<<'SQL'
                CREATE OR REPLACE FUNCTION albums_validate_boundaries_fn() RETURNS trigger AS $$
                BEGIN
                    IF NEW.left_id >= NEW.right_id THEN
                        RAISE EXCEPTION 'Invalid boundaries: left_id must be less than right_id';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
                SQL,
            <<<'SQL'
                CREATE TRIGGER albums_validate_boundaries
                BEFORE INSERT ON albums
                FOR EACH ROW EXECUTE FUNCTION albums_validate_boundaries_fn()
                SQL,

            <<<'SQL'
                CREATE TRIGGER albums_validate_boundaries_update
                BEFORE UPDATE ON albums
                FOR EACH ROW EXECUTE FUNCTION albums_validate_boundaries_fn()
                SQL,

            <<<'SQL'
                CREATE OR REPLACE FUNCTION albums_validate_depth_fn() RETURNS trigger AS $$
                BEGIN
                    IF NEW.level > 10 THEN
                        RAISE EXCEPTION 'Maximum nesting depth (10 levels) exceeded';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
                SQL,
            <<<'SQL'
                CREATE TRIGGER albums_validate_depth
                BEFORE INSERT ON albums
                FOR EACH ROW EXECUTE FUNCTION albums_validate_depth_fn()
                SQL,

            <<<'SQL'
                CREATE TRIGGER albums_validate_depth_update
                BEFORE UPDATE ON albums
                FOR EACH ROW EXECUTE FUNCTION albums_validate_depth_fn()
                SQL,

            <<<'SQL'
                CREATE OR REPLACE FUNCTION albums_media_count_insert_fn() RETURNS trigger AS $$
                BEGIN
                    UPDATE albums
                    SET media_count = media_count + 1,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = NEW.album_id;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
                SQL,
            <<<'SQL'
                CREATE TRIGGER albums_media_count_insert
                AFTER INSERT ON album_media
                FOR EACH ROW EXECUTE FUNCTION albums_media_count_insert_fn()
                SQL,

            <<<'SQL'
                CREATE OR REPLACE FUNCTION albums_media_count_delete_fn() RETURNS trigger AS $$
                BEGIN
                    UPDATE albums
                    SET media_count = CASE WHEN media_count > 0 THEN media_count - 1 ELSE 0 END,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = OLD.album_id;
                    RETURN OLD;
                END;
                $$ LANGUAGE plpgsql
                SQL,
            <<<'SQL'
                CREATE TRIGGER albums_media_count_delete
                AFTER DELETE ON album_media
                FOR EACH ROW EXECUTE FUNCTION albums_media_count_delete_fn()
                SQL,

            <<<'SQL'
                CREATE OR REPLACE FUNCTION album_media_reorder_fn() RETURNS trigger AS $$
                BEGIN
                    -- Unlike SQLite (recursive_triggers = OFF by default),
                    -- Postgres re-fires this trigger for the sibling shifts
                    -- below and for gap_fill's own position updates. Only
                    -- run the shift logic for the outermost, user-issued
                    -- UPDATE; deeper (nested) invocations are these knock-on
                    -- updates and must no-op.
                    IF pg_trigger_depth() > 1 THEN
                        RETURN NEW;
                    END IF;

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

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
                SQL,
            <<<'SQL'
                CREATE TRIGGER album_media_reorder
                BEFORE UPDATE OF position ON album_media
                FOR EACH ROW WHEN (NEW.position IS DISTINCT FROM OLD.position)
                EXECUTE FUNCTION album_media_reorder_fn()
                SQL,

            <<<'SQL'
                CREATE OR REPLACE FUNCTION album_media_gap_fill_fn() RETURNS trigger AS $$
                BEGIN
                    UPDATE album_media
                    SET position = position - 1
                    WHERE album_id = OLD.album_id
                      AND position > OLD.position;
                    RETURN OLD;
                END;
                $$ LANGUAGE plpgsql
                SQL,
            <<<'SQL'
                CREATE TRIGGER album_media_gap_fill
                AFTER DELETE ON album_media
                FOR EACH ROW EXECUTE FUNCTION album_media_gap_fill_fn()
                SQL,
        ];
    }

    /** @return list<string> */
    public function uninstall(): array
    {
        return [
            'DROP TRIGGER IF EXISTS albums_validate_boundaries ON albums',
            'DROP TRIGGER IF EXISTS albums_validate_boundaries_update ON albums',
            'DROP TRIGGER IF EXISTS albums_validate_depth ON albums',
            'DROP TRIGGER IF EXISTS albums_validate_depth_update ON albums',
            'DROP TRIGGER IF EXISTS albums_media_count_insert ON album_media',
            'DROP TRIGGER IF EXISTS albums_media_count_delete ON album_media',
            'DROP TRIGGER IF EXISTS album_media_reorder ON album_media',
            'DROP TRIGGER IF EXISTS album_media_gap_fill ON album_media',
            'DROP FUNCTION IF EXISTS albums_validate_boundaries_fn()',
            'DROP FUNCTION IF EXISTS albums_validate_depth_fn()',
            'DROP FUNCTION IF EXISTS albums_media_count_insert_fn()',
            'DROP FUNCTION IF EXISTS albums_media_count_delete_fn()',
            'DROP FUNCTION IF EXISTS album_media_reorder_fn()',
            'DROP FUNCTION IF EXISTS album_media_gap_fill_fn()',
        ];
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
