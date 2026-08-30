<?php

declare(strict_types = 1);

namespace Modufolio\Media\SmartAlbum\Spec;

/**
 * Leaf predicate: `alias.field <op> :param`.
 *
 * The field is a Media property name (DQL, not a column), whitelisted by the
 * Criteria builder — this class trusts its inputs and is not built from
 * user-supplied strings.
 */
final class Comparison implements Specification
{
    public function __construct(
        private readonly string $field,
        private readonly string $operator,
        private readonly mixed $value,
    ) {
    }

    public function toDql(string $alias, Params $params): string
    {
        return sprintf('%s.%s %s %s', $alias, $this->field, $this->operator, $params->add($this->value));
    }
}
