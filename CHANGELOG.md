# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2026-09-11

### Added

- Album integrity rules now run on MySQL, PostgreSQL and SQL Server, not
  just SQLite. Each engine gets its own `AlbumTriggerAdapterInterface`
  implementation (`src/Database/Trigger/`), resolved by
  `AlbumTriggerAdapterFactory::forPlatform()`. MySQL's adapter uses two
  stored procedures for the two rules that modify `album_media` from
  within a trigger fired on `album_media` itself — MySQL forbids that
  (`ER_CANT_UPDATE_USED_TABLE_IN_SF_OR_TRG`); the other three engines use
  real triggers throughout, same shape as the original SQLite-only version.
- CI runs the full test suite against MySQL 8.4, PostgreSQL 16 and SQL
  Server 2022 on every push, alongside the existing SQLite-default PHP
  version matrix. `docker-compose.yml` runs the same three engines locally
  (see README).
- `ui/` gets a test setup for the first time, mirroring `@modufolio/panel`'s
  exactly: Vitest + `@vue/test-utils` (happy-dom environment), a flat
  `eslint.config.js`, and `npm test`/`npm run lint` scripts. CI runs a new
  `ui` job (lint → type-check → test → build) on every push. Coverage
  starts with the composables/components touched by this release
  (`useAlbums` incl. the nested-set `albumTree`, `useTusUploadQueue`
  incl. its retry/error mapping, `MediaCard`, `MediaInspector`).
- `MediaInspector`'s EXIF parsing (`exifNumber`, `exifSummary`) moved out of
  the SFC into `exifUtils`, exported beside the other component helpers, so
  it can be tested as the pure logic it is.

### Changed

- **BREAKING**: `AlbumModel`/`MediaModel` no longer issue raw
  `album_media`/`albums` SQL directly for repositioning or cleanup — they
  resolve the active `AlbumTriggerAdapterInterface` from the entity
  manager's connection and call `repositionMedia()`/
  `removeMediaEverywhere()`, which is now the source of truth for that
  behaviour on every engine.
- **BREAKING**: `@modufolio/media` now requires `@modufolio/panel` `^0.8.0`
  as a peer (was `^0.1.0`) — the UI leans on panel's `niceSize`, `date`,
  `useQuery`/`invalidateQueries` and the optimistic-write helpers, none of
  which older panel releases ship.
- **BREAKING**: `AlbumTriggers::all()`/`dropAll()` are `@deprecated`
  SQLite-only shortcuts now, delegating to `SqliteAlbumTriggerAdapter`.
  Call `AlbumTriggerAdapterFactory::forPlatform(...)` instead — required
  for any migration targeting a non-SQLite database.
- `Album`'s three cover-media foreign keys no longer carry a DB-level
  `ON DELETE SET NULL` (SQL Server refuses more than one cascading FK from
  the same table to the same target); `MediaModel` clears all three
  explicitly on every media deletion path instead, portably.
- `Media::$videoCover` moved from Doctrine's `binary` type to a small
  custom `BinaryBlobType` (`src/Database/Type/`) so its ~1MB video-cover
  frame gets engine-correct DDL (MySQL's `VARBINARY` caps at 65,535 bytes,
  SQL Server's at 8,000) without changing its `?string` getter/setter
  contract.
- UI components now use `@modufolio/panel`'s `niceSize` and `date` helpers
  instead of local `formatBytes`/`formatDate` implementations in
  `MediaCard`, `MediaInspector`, and `useTusUploadQueue`.
- Album mutations (`addMediaToAlbum`, `removeMediaFromAlbum`,
  `removeMultipleFromAlbum`) now invalidate the library counts cache via
  `invalidateLibraryCounts()` after a successful request.
- UI README updated with Tailwind v4 `@source` guidance for scanning the
  package (auto-detection skips `node_modules`), with the v3 `content`
  glob kept as a fallback note.

### Fixed

- `Album::$layoutOptions`'s DB-level default (`options: ['default' =>
  '{}']` on a JSON column) failed schema creation on MySQL, which rejects
  a literal default on BLOB/TEXT/JSON columns. The entity already always
  sets a value, so the DB-level default was redundant; removed.

## [0.1.0] - 2026-08-30

### Added

- Initial extraction of the media domain from the Appkit portfolio sites:
  nested-set albums with SQLite integrity triggers, the media library
  (entities, repositories, models), upload processing with blurhash
  placeholders, and per-layout presentation settings stored as JSON.
- Front-page curation on `Media`: an `is_featured` flag plus a manual
  `featured_order` — featuring appends at the end of the curated order,
  unfeaturing clears the slot. `MediaRepository::findFeatured()` returns the
  picks in order.
- Smart albums: virtual albums whose membership is a rule, not curated rows.
  A composable specification language (`SmartAlbum\Criteria` with `anyOf`,
  `not`, `where` and comparison/date leaves, plus a `RawDql` escape hatch)
  compiles to DQL via `SmartAlbumRunner`; concrete albums implement
  `SmartAlbumInterface` and register through `SmartAlbumRegistry`.
  Applications define their own album rules — the package ships the engine
  only.
- PHPStan at level 8 (`composer stan`), with the phpunit and doctrine
  extensions.
- CI workflow: PHPUnit across PHP 8.2–8.5 and a PHPStan job, with the
  `modufolio/appkit` path dependency checked out alongside.
