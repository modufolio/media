<?php

declare(strict_types = 1);

namespace Modufolio\Media\Database;

use Modufolio\Media\Database\Trigger\SqliteAlbumTriggerAdapter;

/**
 * @deprecated SQLite-only entry point kept for the app's existing
 * `Version20260829180000` migration. New code — including migrations
 * targeting a non-SQLite database — should call
 * `AlbumTriggerAdapterFactory::forPlatform(...)` instead, which resolves
 * the right engine's triggers (SqliteAlbumTriggerAdapter,
 * MySqlAlbumTriggerAdapter, PostgreSqlAlbumTriggerAdapter or
 * SqlServerAlbumTriggerAdapter — see AlbumTriggerAdapterInterface).
 */
final class AlbumTriggers
{
    /** @deprecated Use AlbumTriggerAdapterFactory::forPlatform(...)->install() instead.
     * @return list<string> */
    public static function all(): array
    {
        return (new SqliteAlbumTriggerAdapter())->install();
    }

    /** @deprecated Use AlbumTriggerAdapterFactory::forPlatform(...)->uninstall() instead.
     * @return list<string> */
    public static function dropAll(): array
    {
        return (new SqliteAlbumTriggerAdapter())->uninstall();
    }
}
