<?php

declare(strict_types = 1);

namespace Modufolio\Media\SmartAlbum;

use Modufolio\Media\SmartAlbum\Spec\Specification;

/**
 * A smart album: a virtual album whose members are defined in code by a
 * specification instead of curated rows in album_media. Registered in
 * config/smart_albums.php; surfaces in the panel with a special mark.
 */
interface SmartAlbumInterface
{
    /** Stable identifier, used in URLs ("best-of"). Lowercase kebab-case. */
    public function slug(): string;

    public function title(): string;

    public function description(): ?string;

    /** The membership rule. Compiled to DQL by SmartAlbumRunner. */
    public function specification(): Specification;

    /**
     * Sort order as Media property → direction.
     *
     * @return array<string, 'ASC'|'DESC'>
     */
    public function orderBy(): array;

    /** Cap on members, or null for all. */
    public function limit(): ?int;
}
