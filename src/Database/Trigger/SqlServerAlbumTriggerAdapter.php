<?php

declare(strict_types = 1);

namespace Modufolio\Media\Database\Trigger;

use Doctrine\DBAL\Connection;
use Modufolio\Media\Database\AlbumTriggerAdapterInterface;

/**
 * SQL Server implementation of the album integrity rules.
 *
 * SQL Server has no BEFORE trigger, only AFTER and INSTEAD OF, so the two
 * "abort the write" rules (boundaries, depth) run AFTER the row already
 * landed and roll the transaction back when it's invalid — the standard
 * SQL Server idiom for a trigger-enforced constraint, observably identical
 * to SQLite's BEFORE-time abort (the statement fails, nothing is
 * committed). Every trigger is statement-level (fires once per statement,
 * not once per row, unlike SQLite/PostgreSQL), so the maintenance triggers
 * are set-based over `inserted`/`deleted` rather than a single-row body.
 * Confirmed against a live mssql/server:2022 container while designing this
 * adapter that both self-table modification and FK ON DELETE CASCADE firing
 * work as needed, same as PostgreSQL.
 */
final class SqlServerAlbumTriggerAdapter implements AlbumTriggerAdapterInterface
{
    /** @return list<string> */
    public function install(): array
    {
        return [
            <<<'SQL'
                CREATE TRIGGER albums_validate_boundaries ON albums AFTER INSERT AS
                BEGIN
                    SET NOCOUNT ON;
                    IF EXISTS (SELECT 1 FROM inserted WHERE left_id >= right_id)
                    BEGIN
                        RAISERROR('Invalid boundaries: left_id must be less than right_id', 16, 1);
                        ROLLBACK TRANSACTION;
                        RETURN;
                    END
                END
                SQL,

            <<<'SQL'
                CREATE TRIGGER albums_validate_boundaries_update ON albums AFTER UPDATE AS
                BEGIN
                    SET NOCOUNT ON;
                    IF EXISTS (SELECT 1 FROM inserted WHERE left_id >= right_id)
                    BEGIN
                        RAISERROR('Invalid boundaries: left_id must be less than right_id', 16, 1);
                        ROLLBACK TRANSACTION;
                        RETURN;
                    END
                END
                SQL,

            <<<'SQL'
                CREATE TRIGGER albums_validate_depth ON albums AFTER INSERT AS
                BEGIN
                    SET NOCOUNT ON;
                    IF EXISTS (SELECT 1 FROM inserted WHERE level > 10)
                    BEGIN
                        RAISERROR('Maximum nesting depth (10 levels) exceeded', 16, 1);
                        ROLLBACK TRANSACTION;
                        RETURN;
                    END
                END
                SQL,

            <<<'SQL'
                CREATE TRIGGER albums_validate_depth_update ON albums AFTER UPDATE AS
                BEGIN
                    SET NOCOUNT ON;
                    IF EXISTS (SELECT 1 FROM inserted WHERE level > 10)
                    BEGIN
                        RAISERROR('Maximum nesting depth (10 levels) exceeded', 16, 1);
                        ROLLBACK TRANSACTION;
                        RETURN;
                    END
                END
                SQL,

            <<<'SQL'
                CREATE TRIGGER albums_media_count_insert ON album_media AFTER INSERT AS
                BEGIN
                    SET NOCOUNT ON;
                    UPDATE a
                    SET a.media_count = a.media_count + t.cnt,
                        a.updated_at = CURRENT_TIMESTAMP
                    FROM albums a
                    INNER JOIN (SELECT album_id, COUNT(*) AS cnt FROM inserted GROUP BY album_id) t
                        ON t.album_id = a.id;
                END
                SQL,

            <<<'SQL'
                CREATE TRIGGER albums_media_count_delete ON album_media AFTER DELETE AS
                BEGIN
                    SET NOCOUNT ON;
                    UPDATE a
                    SET a.media_count = CASE WHEN a.media_count > t.cnt THEN a.media_count - t.cnt ELSE 0 END,
                        a.updated_at = CURRENT_TIMESTAMP
                    FROM albums a
                    INNER JOIN (SELECT album_id, COUNT(*) AS cnt FROM deleted GROUP BY album_id) t
                        ON t.album_id = a.id;
                END
                SQL,

            <<<'SQL'
                CREATE TRIGGER album_media_reorder ON album_media AFTER UPDATE AS
                BEGIN
                    SET NOCOUNT ON;
                    IF NOT UPDATE(position) RETURN;

                    ; WITH chg AS (
                        SELECT i.id, i.album_id, i.position AS new_position, d.position AS old_position
                        FROM inserted i
                        INNER JOIN deleted d ON d.id = i.id
                        WHERE i.position <> d.position
                    )
                    -- Moving up (e.g. 5 → 2): push items in [new, old) down by 1
                    UPDATE s
                    SET s.position = s.position + 1
                    FROM album_media s
                    INNER JOIN chg ON chg.album_id = s.album_id AND s.id <> chg.id
                    WHERE s.position >= chg.new_position AND s.position < chg.old_position;

                    ; WITH chg AS (
                        SELECT i.id, i.album_id, i.position AS new_position, d.position AS old_position
                        FROM inserted i
                        INNER JOIN deleted d ON d.id = i.id
                        WHERE i.position <> d.position
                    )
                    -- Moving down (e.g. 2 → 5): pull items in (old, new] up by 1
                    UPDATE s
                    SET s.position = s.position - 1
                    FROM album_media s
                    INNER JOIN chg ON chg.album_id = s.album_id AND s.id <> chg.id
                    WHERE s.position <= chg.new_position AND s.position > chg.old_position;
                END
                SQL,

            <<<'SQL'
                CREATE TRIGGER album_media_gap_fill ON album_media AFTER DELETE AS
                BEGIN
                    SET NOCOUNT ON;

                    ; WITH shift AS (
                        SELECT s2.id, COUNT(*) AS cnt
                        FROM deleted d
                        INNER JOIN album_media s2 ON s2.album_id = d.album_id AND s2.position > d.position
                        GROUP BY s2.id
                    )
                    UPDATE s
                    SET s.position = s.position - shift.cnt
                    FROM album_media s
                    INNER JOIN shift ON shift.id = s.id;
                END
                SQL,
        ];
    }

    /** @return list<string> */
    public function uninstall(): array
    {
        return [
            'DROP TRIGGER IF EXISTS albums_validate_boundaries',
            'DROP TRIGGER IF EXISTS albums_validate_boundaries_update',
            'DROP TRIGGER IF EXISTS albums_validate_depth',
            'DROP TRIGGER IF EXISTS albums_validate_depth_update',
            'DROP TRIGGER IF EXISTS albums_media_count_insert',
            'DROP TRIGGER IF EXISTS albums_media_count_delete',
            'DROP TRIGGER IF EXISTS album_media_reorder',
            'DROP TRIGGER IF EXISTS album_media_gap_fill',
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
