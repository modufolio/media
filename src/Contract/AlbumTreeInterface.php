<?php

declare(strict_types = 1);

namespace Modufolio\Media\Contract;

/**
 * A repository that can answer for the album tree — the shape the public
 * site walks when it mirrors the library as pages.
 *
 * AlbumRepository implements it; site-level code that used to duck-check its
 * own pages interface checks this one for the album branch instead.
 */
interface AlbumTreeInterface
{
    /**
     * Direct children of a parent, or the root albums when parent is null.
     *
     * @return list<object>
     */
    public function children(?object $parent = null): array;
}
