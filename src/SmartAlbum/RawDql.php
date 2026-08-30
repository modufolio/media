<?php

declare(strict_types = 1);

namespace Modufolio\Media\SmartAlbum;

use Modufolio\Media\SmartAlbum\Spec\Params;
use Modufolio\Media\SmartAlbum\Spec\Specification;

/**
 * Raw DQL fragment with `%a%` standing in for the root alias.
 *
 * The escape hatch for predicates the Criteria vocabulary does not cover.
 * Author-supplied code only — never built from user input.
 */
final class RawDql implements Specification
{
    /** @param array<int, mixed> $values Bound in order of the %s placeholders. */
    public function __construct(
        private readonly string $fragment,
        private readonly array $values = [],
    ) {
    }

    public function toDql(string $alias, Params $params): string
    {
        $dql = str_replace('%a%', $alias, $this->fragment);

        foreach ($this->values as $value) {
            $placeholder = $params->add($value);
            $pos = strpos($dql, '%s');
            if ($pos === false) {
                throw new \LogicException('RawDql: more values than %s placeholders.');
            }
            $dql = substr_replace($dql, $placeholder, $pos, 2);
        }

        return '(' . $dql . ')';
    }
}
