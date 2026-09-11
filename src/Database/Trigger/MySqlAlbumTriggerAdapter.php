<?php

declare(strict_types = 1);

namespace Modufolio\Media\Database\Trigger;

use Doctrine\DBAL\Connection;
use Modufolio\Media\Database\AlbumTriggerAdapterInterface;

/**
 * MySQL implementation of the album integrity rules.
 *
 * Six of the eight rules are real MySQL triggers: the two "abort the
 * write" rules (boundaries, depth) use SIGNAL SQLSTATE, and the media_count
 * maintenance triggers are fine as-is because they update `albums`, a
 * different table from the one that fired them.
 *
 * album_media_reorder and album_media_gap_fill cannot be triggers on
 * MySQL: both need to modify album_media from within a trigger fired BY a
 * statement on album_media, and MySQL categorically forbids that
 * (ER_CANT_UPDATE_USED_TABLE_IN_SF_OR_TRG — confirmed against a live
 * mysql:8.4 container while designing this adapter). They're stored
 * procedures instead, called explicitly by repositionMedia() and
 * removeMediaEverywhere() rather than fired implicitly. A plain DELETE
 * inside sp_album_media_delete still fires albums_media_count_delete
 * normally (it's an explicit statement, not an implicit invocation, and it
 * targets a different table), so the procedure only needs to handle the
 * gap-fill itself.
 */
final class MySqlAlbumTriggerAdapter implements AlbumTriggerAdapterInterface
{
    /** @return list<string> */
    public function install(): array
    {
        return [
            <<<'SQL'
                CREATE TRIGGER albums_validate_boundaries BEFORE INSERT ON albums
                FOR EACH ROW
                BEGIN
                    IF NEW.left_id >= NEW.right_id THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid boundaries: left_id must be less than right_id';
                    END IF;
                END
                SQL,

            <<<'SQL'
                CREATE TRIGGER albums_validate_boundaries_update BEFORE UPDATE ON albums
                FOR EACH ROW
                BEGIN
                    IF NEW.left_id >= NEW.right_id THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid boundaries: left_id must be less than right_id';
                    END IF;
                END
                SQL,

            <<<'SQL'
                CREATE TRIGGER albums_validate_depth BEFORE INSERT ON albums
                FOR EACH ROW
                BEGIN
                    IF NEW.level > 10 THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Maximum nesting depth (10 levels) exceeded';
                    END IF;
                END
                SQL,

            <<<'SQL'
                CREATE TRIGGER albums_validate_depth_update BEFORE UPDATE ON albums
                FOR EACH ROW
                BEGIN
                    IF NEW.level > 10 THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Maximum nesting depth (10 levels) exceeded';
                    END IF;
                END
                SQL,

            <<<'SQL'
                CREATE TRIGGER albums_media_count_insert AFTER INSERT ON album_media
                FOR EACH ROW
                BEGIN
                    UPDATE albums
                    SET media_count = media_count + 1,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = NEW.album_id;
                END
                SQL,

            <<<'SQL'
                CREATE TRIGGER albums_media_count_delete AFTER DELETE ON album_media
                FOR EACH ROW
                BEGIN
                    UPDATE albums
                    SET media_count = CASE WHEN media_count > 0 THEN media_count - 1 ELSE 0 END,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = OLD.album_id;
                END
                SQL,

            <<<'SQL'
                CREATE PROCEDURE sp_album_media_reposition(IN p_id INT, IN p_new_position INT)
                BEGIN
                    DECLARE v_album_id INT;
                    DECLARE v_old_position INT;

                    SELECT album_id, position INTO v_album_id, v_old_position
                    FROM album_media WHERE id = p_id;

                    IF v_album_id IS NOT NULL AND v_old_position <> p_new_position THEN
                        IF p_new_position < v_old_position THEN
                            -- Moving up (e.g. 5 → 2): push items in [new, old) down by 1
                            UPDATE album_media
                            SET position = position + 1
                            WHERE album_id = v_album_id
                              AND id <> p_id
                              AND position >= p_new_position
                              AND position < v_old_position;
                        ELSE
                            -- Moving down (e.g. 2 → 5): pull items in (old, new] up by 1
                            UPDATE album_media
                            SET position = position - 1
                            WHERE album_id = v_album_id
                              AND id <> p_id
                              AND position <= p_new_position
                              AND position > v_old_position;
                        END IF;

                        UPDATE album_media
                        SET position = p_new_position, updated_at = CURRENT_TIMESTAMP
                        WHERE id = p_id;
                    END IF;
                END
                SQL,

            <<<'SQL'
                CREATE PROCEDURE sp_album_media_delete(IN p_id INT)
                BEGIN
                    DECLARE v_album_id INT;
                    DECLARE v_position INT;

                    SELECT album_id, position INTO v_album_id, v_position
                    FROM album_media WHERE id = p_id;

                    DELETE FROM album_media WHERE id = p_id;

                    IF v_album_id IS NOT NULL THEN
                        UPDATE album_media
                        SET position = position - 1
                        WHERE album_id = v_album_id AND position > v_position;
                    END IF;
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
            'DROP PROCEDURE IF EXISTS sp_album_media_reposition',
            'DROP PROCEDURE IF EXISTS sp_album_media_delete',
        ];
    }

    public function repositionMedia(Connection $connection, int $albumMediaId, int $newPosition): void
    {
        $connection->executeStatement('CALL sp_album_media_reposition(?, ?)', [$albumMediaId, $newPosition]);
    }

    public function removeMediaEverywhere(Connection $connection, int $mediaId): void
    {
        $ids = $connection->fetchFirstColumn('SELECT id FROM album_media WHERE media_id = ?', [$mediaId]);

        foreach ($ids as $id) {
            $connection->executeStatement('CALL sp_album_media_delete(?)', [(int) $id]);
        }
    }
}
