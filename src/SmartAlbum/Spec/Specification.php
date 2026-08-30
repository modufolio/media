<?php

declare(strict_types = 1);

namespace Modufolio\Media\SmartAlbum\Spec;

/**
 * A composable predicate over Media, compiled to a DQL WHERE fragment.
 *
 * Implementations return a self-contained boolean expression (parenthesised
 * where needed) against the given root alias, registering all values in the
 * shared Params bag. The runner wraps the fragment into a full DQL query.
 */
interface Specification
{
    public function toDql(string $alias, Params $params): string;
}
