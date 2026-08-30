<?php

declare(strict_types = 1);

namespace Modufolio\Media\SmartAlbum\Spec;

/** Negation of an inner specification. */
final class Not implements Specification
{
    public function __construct(private readonly Specification $inner)
    {
    }

    public function toDql(string $alias, Params $params): string
    {
        return 'NOT ' . $this->inner->toDql($alias, $params);
    }
}
