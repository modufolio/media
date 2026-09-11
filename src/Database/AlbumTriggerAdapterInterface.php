<?php

declare(strict_types = 1);

namespace Modufolio\Media\Database;

use Doctrine\DBAL\Connection;

/**
 * Engine-specific implementation of the album integrity rules: nested-set
 * boundary/depth guards, media_count maintenance, and album_media position
 * reorder/gap-fill.
 *
 * SQLite, PostgreSQL and SQL Server implement all eight rules as real
 * triggers, so repositionMedia()/removeMediaEverywhere() just issue the bare
 * statement a trigger intercepts. MySQL cannot: a MySQL trigger is not
 * permitted to modify the table that fired it (ER_CANT_UPDATE_USED_TABLE_IN_SF_OR_TRG),
 * which rules out a trigger-based album_media_reorder/album_media_gap_fill
 * on album_media; its adapter installs stored procedures for those two
 * instead and calls them explicitly.
 */
interface AlbumTriggerAdapterInterface
{
    /**
     * Every statement needed to install this engine's rules, in
     * dependency-safe order (e.g. a Postgres trigger function before the
     * trigger that uses it).
     *
     * @return list<string>
     */
    public function install(): array;

    /**
     * Reverse of install(): removes every trigger/procedure/function it
     * created.
     *
     * @return list<string>
     */
    public function uninstall(): array;

    /**
     * Move one album_media row to $newPosition, shifting siblings to match.
     */
    public function repositionMedia(Connection $connection, int $albumMediaId, int $newPosition): void;

    /**
     * Remove every album_media row for $mediaId, maintaining media_count and
     * closing position gaps in whatever albums it was removed from.
     *
     * Never relies on album_media.media_id's ON DELETE CASCADE: MySQL/InnoDB
     * does not fire row triggers for cascade-deleted rows, so cleanup must
     * always be this direct, trigger-firing call instead.
     */
    public function removeMediaEverywhere(Connection $connection, int $mediaId): void;
}
