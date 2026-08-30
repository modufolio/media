# modufolio/media

Album and media management for Appkit portfolio sites: nested-set albums, the
media library, upload processing (slugging, sharding, downscaling, blurhash,
checksums) and the SQLite triggers that guard album integrity.

Extracted from `appkit-portfolio`, which is its first consumer (composer path
repository, symlinked).

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

`AlbumTriggers::all()` must run against every database the entities live in —
apply via a migration in the app (and in test bootstraps), exactly as
`appkit-portfolio`'s `Version20260829180000` does.

## Tests

```
composer install && vendor/bin/phpunit
```
