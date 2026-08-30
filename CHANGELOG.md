# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
