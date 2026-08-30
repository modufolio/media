<?php

declare(strict_types = 1);

namespace Modufolio\Media\SmartAlbum\Spec;

/**
 * Parameter bag for compiled specifications.
 *
 * Every leaf registers its values here and gets back a unique placeholder
 * name, so composed specs never collide (two ratedAtLeast() calls in one
 * tree each get their own :sa_p{n}).
 */
final class Params
{
    /** @var array<string, mixed> */
    private array $values = [];

    private int $counter = 0;

    /** Register $value and return its DQL placeholder (":sa_p3"). */
    public function add(mixed $value): string
    {
        $name = 'sa_p' . $this->counter++;
        $this->values[$name] = $value;

        return ':' . $name;
    }

    /** @return array<string, mixed> name → value, without the colon. */
    public function all(): array
    {
        return $this->values;
    }
}
