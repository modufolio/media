<?php

declare(strict_types = 1);

namespace Modufolio\Media\Database;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Modufolio\Media\Database\Trigger\MySqlAlbumTriggerAdapter;
use Modufolio\Media\Database\Trigger\PostgreSqlAlbumTriggerAdapter;
use Modufolio\Media\Database\Trigger\SqliteAlbumTriggerAdapter;
use Modufolio\Media\Database\Trigger\SqlServerAlbumTriggerAdapter;

/**
 * Resolves the AlbumTriggerAdapterInterface for a Doctrine DBAL platform,
 * the same instanceof-on-platform style panel's DoctrineTestCase already
 * uses for its own (much smaller) per-engine differences.
 */
final class AlbumTriggerAdapterFactory
{
    public static function forPlatform(AbstractPlatform $platform): AlbumTriggerAdapterInterface
    {
        return match (true) {
            $platform instanceof SQLitePlatform => new SqliteAlbumTriggerAdapter(),
            $platform instanceof AbstractMySQLPlatform => new MySqlAlbumTriggerAdapter(),
            $platform instanceof PostgreSQLPlatform => new PostgreSqlAlbumTriggerAdapter(),
            $platform instanceof SQLServerPlatform => new SqlServerAlbumTriggerAdapter(),
            default => throw new \InvalidArgumentException(
                'No AlbumTriggerAdapter for platform ' . $platform::class,
            ),
        };
    }
}
