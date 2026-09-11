# modufolio/media

Album and media management for Appkit portfolio sites: nested-set albums, the
media library, upload processing (slugging, sharding, downscaling, blurhash,
checksums) and the triggers that guard album integrity — on SQLite, MySQL,
PostgreSQL and SQL Server.

## What the application provides

The package names no application classes. Four small contracts cover the seams:

| Contract | The app wires |
|---|---|
| `UploaderInterface` | its user entity, via Doctrine's `ResolveTargetEntityListener` in `config/doctrine.php` |
| `AlbumTreeInterface` | nothing — `AlbumRepository` implements it; site code duck-checks it when walking the album tree as pages |
| `MediaJobsInterface` | its image-job repository, so deleting media retires processing jobs |
| `FocusStoreInterface` | (optional) where analysed focus/saliency features land — the autofocus pipeline's entity stays app-side |

Tag-slug filters (`tag:<slug>`) resolve through
`MediaRepository::useTagEntity(App\Entity\Tag::class)`, called once at boot —
tags are polymorphic app-level data this package cannot name.

## Wiring

```php
// composer.json (app):  "repositories": [{"type": "path", "url": "../media"}]
// composer require modufolio/media:@dev

// config/doctrine.php
$orm->entities($projectDir . '/src/Entity', $projectDir . '/vendor/modufolio/media/src/Entity')
    ->addSubscriber($resolveTargetListener); // UploaderInterface => App\Entity\User

// config/repositories.php maps the package repositories to the package entities.
```

`AlbumTriggerAdapterFactory::forPlatform($connection->getDatabasePlatform())->install()`
must run against every database the entities live in — apply via a migration
in the app (and in test bootstraps), exactly as the consuming app's
`Version20260829180000` does. `AlbumTriggers::all()` still works as a
`@deprecated` SQLite-only shortcut for that same migration, kept so it
doesn't need to change; new code, and any migration targeting a non-SQLite
database, should call the factory directly.

Each engine's rules live in their own `AlbumTriggerAdapterInterface`
implementation (`src/Database/Trigger/`) — see
[`AlbumTriggerAdapterInterface`](src/Database/AlbumTriggerAdapterInterface.php)
for what an adapter is responsible for and why MySQL's differs structurally
from the other three (a MySQL trigger can't modify the table that fired it).
`AlbumModel`/`MediaModel` resolve the right adapter from the entity
manager's connection and go through it — `repositionMedia()`,
`removeMediaEverywhere()` — rather than relying on raw SQL or FK cascades,
so application behaviour is identical across engines.

## Tests

```
composer install && vendor/bin/phpunit
```

Runs against an in-memory SQLite database by default. To also run against a
real MySQL, PostgreSQL or SQL Server instance (the same suite CI runs on
every push):

```
docker compose up -d mysql postgres sqlserver
DB_DRIVER=pdo_mysql  DB_PORT=3310 DB_USER=root     DB_PASSWORD=secret composer test
DB_DRIVER=pdo_pgsql  DB_PORT=5436 DB_USER=postgres DB_PASSWORD=secret composer test
DB_DRIVER=pdo_sqlsrv DB_PORT=1437 DB_USER=sa       DB_PASSWORD='Secret_1234' composer test
```

SQL Server needs its database created once before the first run (no
`MSSQL_DATABASE`-equivalent env var exists):

```
sqlcmd -S 127.0.0.1,1437 -U sa -P 'Secret_1234' -Q "CREATE DATABASE media_test"
```
