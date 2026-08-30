<?php

declare(strict_types = 1);

namespace Modufolio\Media\SmartAlbum\Spec;

/** Conjunction: every child must match. */
final class AndX implements Specification
{
    /** @var list<Specification> */
    private readonly array $specs;

    public function __construct(Specification ...$specs)
    {
        $this->specs = array_values($specs);
    }

    public function toDql(string $alias, Params $params): string
    {
        if ($this->specs === []) {
            return '1 = 1';
        }

        $parts = array_map(
            static fn (Specification $s) => $s->toDql($alias, $params),
            $this->specs
        );

        return '(' . implode(' AND ', $parts) . ')';
    }
}
