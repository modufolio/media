<?php

declare(strict_types = 1);

namespace Modufolio\Media\Tests\Support;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Modufolio\Media\Database\AlbumTriggerAdapterFactory;

/**
 * SQLite in-memory by default; any engine through DB_DRIVER/DB_HOST/DB_PORT/
 * DB_NAME/DB_USER/DB_PASSWORD — the same variables panel's own Database test
 * suite reads (see docker-compose.yml). Shared by MediaTestCase and
 * AlbumTriggersTest so the whole suite (not just one test) runs against
 * whichever engine CI points it at.
 */
trait DatabaseConnectionTrait
{
    /** @return array<string, mixed> */
    private static function connectionParams(): array
    {
        $driver = getenv('DB_DRIVER') ?: 'pdo_sqlite';

        if ($driver === 'pdo_sqlite') {
            return ['driver' => 'pdo_sqlite', 'memory' => true];
        }

        $params = [
            'driver' => $driver,
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'dbname' => getenv('DB_NAME') ?: 'media_test',
            'user' => getenv('DB_USER') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
        ];

        $port = getenv('DB_PORT');
        if ($port !== false && $port !== '') {
            $params['port'] = (int) $port;
        }

        if (str_contains($driver, 'sqlsrv')) {
            $params['driverOptions'] = ['TrustServerCertificate' => '1'];
        }

        return $params;
    }

    /**
     * Drops everything left over from a previous run against this (real,
     * persistent) database: the adapter's triggers/procedures/functions
     * first — DROP TABLE doesn't remove a Postgres trigger function or a
     * MySQL stored procedure, only what's directly attached to the table —
     * then every table, retrying in passes so FK-dependent tables drop
     * after whatever they reference, regardless of engine. SQLite is a
     * fresh in-memory database every time and never needs this.
     */
    private static function resetSchema(Connection $connection): void
    {
        if ($connection->getDatabasePlatform() instanceof SQLitePlatform) {
            return;
        }

        $adapter = AlbumTriggerAdapterFactory::forPlatform($connection->getDatabasePlatform());
        foreach ($adapter->uninstall() as $sql) {
            try {
                $connection->executeStatement($sql);
            } catch (\Throwable) {
                // Nothing to drop yet (first run against this database).
            }
        }

        $remaining = $connection->createSchemaManager()->listTableNames();
        while ($remaining !== []) {
            $droppedThisPass = false;

            foreach ($remaining as $table) {
                try {
                    $connection->executeStatement("DROP TABLE IF EXISTS {$table}");
                    $droppedThisPass = true;
                } catch (\Throwable) {
                    // Still referenced by another table; retry once it's gone.
                }
            }

            if (!$droppedThisPass) {
                break;
            }

            $remaining = $connection->createSchemaManager()->listTableNames();
        }
    }
}
