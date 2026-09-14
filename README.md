# modufolio/media

[![CI](https://img.shields.io/github/actions/workflow/status/modufolio/media/ci.yml?branch=main&style=flat-square&label=CI)](https://github.com/modufolio/media/actions/workflows/ci.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg?style=flat-square)](https://phpstan.org/)
[![License: MIT](https://img.shields.io/badge/License-MIT-brightgreen.svg?style=flat-square)](https://opensource.org/licenses/MIT)

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
// composer require modufolio/media

// config/doctrine.php
$orm->entities($projectDir . '/src/Entity', $projectDir . '/vendor/modufolio/media/src/Entity')
    ->addSubscriber($resolveTargetListener); // UploaderInterface => App\Entity\User

// config/repositories.php maps the package repositories to the package entities.
```

`AlbumTriggerAdapterFactory::forPlatform($connection->getDatabasePlatform())->install()`
must run against every database the entities live in — apply via a migration
in the app (and in test bootstraps).

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

Runs against an in-memory SQLite database by default. CI also runs the suite
against real MySQL, PostgreSQL and SQL Server instances on every push; see
[`docker-compose.yml`](docker-compose.yml) to reproduce that locally.

## Requirements

- PHP 8.4 or later
- Composer
- An [Appkit](https://github.com/modufolio/appkit) application, for the
  entity manager, the `UploaderInterface`/`AlbumTreeInterface`/
  `MediaJobsInterface` contracts it resolves, and (optionally) `FocusStoreInterface`
- Extensions: `curl`, `dom`, `exif`, `fileinfo`, `gd`, `intl`, `libxml`, `pdo`,
  `simplexml`, `sqlite3`, `zip`

See [`composer.json`](composer.json) for the canonical dependency list.

## License

MIT. See [LICENSE](LICENSE).
