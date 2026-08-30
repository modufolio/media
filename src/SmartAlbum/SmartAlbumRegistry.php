<?php

declare(strict_types = 1);

namespace Modufolio\Media\SmartAlbum;

/**
 * The set of smart albums the application knows, from config/smart_albums.php.
 */
final class SmartAlbumRegistry
{
    /** @var array<string, SmartAlbumInterface> slug → album */
    private array $albums = [];

    /** @param iterable<SmartAlbumInterface> $albums */
    public function __construct(iterable $albums)
    {
        foreach ($albums as $album) {
            if (isset($this->albums[$album->slug()])) {
                throw new \LogicException(sprintf('Duplicate smart album slug "%s".', $album->slug()));
            }
            $this->albums[$album->slug()] = $album;
        }
    }

    /** @return list<SmartAlbumInterface> */
    public function all(): array
    {
        return array_values($this->albums);
    }

    public function get(string $slug): ?SmartAlbumInterface
    {
        return $this->albums[$slug] ?? null;
    }
}
