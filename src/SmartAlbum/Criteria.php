<?php

declare(strict_types = 1);

namespace Modufolio\Media\SmartAlbum;

use Modufolio\Media\SmartAlbum\Spec\AndX;
use Modufolio\Media\SmartAlbum\Spec\Comparison;
use Modufolio\Media\SmartAlbum\Spec\Not;
use Modufolio\Media\SmartAlbum\Spec\OrX;
use Modufolio\Media\SmartAlbum\Spec\Params;
use Modufolio\Media\SmartAlbum\Spec\Specification;

/**
 * Fluent specification language for smart albums.
 *
 * Chained calls AND together; anyOf()/not() nest combinators. Criteria is
 * itself a Specification, so sub-criteria compose freely:
 *
 *   Criteria::media()
 *       ->images()
 *       ->public()
 *       ->anyOf(
 *           static fn (Criteria $c) => $c->ratedAtLeast(4),
 *           static fn (Criteria $c) => $c->favorite(),
 *       )
 *       ->uploadedWithinDays(365)
 *
 * compiles to
 *
 *   (m.mimeType LIKE :sa_p0 AND m.isPublic = :sa_p1
 *     AND (m.rating >= :sa_p2 OR m.isFavorite = :sa_p3)
 *     AND m.createdAt >= :sa_p4)
 */
final class Criteria implements Specification
{
    /** @var list<Specification> */
    private array $specs = [];

    private function __construct()
    {
    }

    public static function media(): self
    {
        return new self();
    }

    // ── Leaves ──────────────────────────────────────────────

    public function images(): self
    {
        return $this->add(new Comparison('mimeType', 'LIKE', 'image/%'));
    }

    public function videos(): self
    {
        return $this->add(new Comparison('mimeType', 'LIKE', 'video/%'));
    }

    public function public(): self
    {
        return $this->add(new Comparison('isPublic', '=', true));
    }

    public function favorite(): self
    {
        return $this->add(new Comparison('isFavorite', '=', true));
    }

    public function featured(): self
    {
        return $this->add(new Comparison('isFeatured', '=', true));
    }

    public function ratedAtLeast(int $rating): self
    {
        return $this->add(new Comparison('rating', '>=', max(1, min(5, $rating))));
    }

    public function rated(int $rating): self
    {
        return $this->add(new Comparison('rating', '=', max(1, min(5, $rating))));
    }

    public function unrated(): self
    {
        return $this->add(new RawDql('%a%.rating IS NULL'));
    }

    public function uploadedAfter(\DateTimeImmutable $moment): self
    {
        return $this->add(new Comparison('createdAt', '>=', $moment));
    }

    public function uploadedWithinDays(int $days): self
    {
        return $this->uploadedAfter(new \DateTimeImmutable("-{$days} days"));
    }

    // ── Combinators ─────────────────────────────────────────

    /**
     * OR-group: each callable receives a fresh Criteria; the groups are
     * OR-ed together and the result AND-ed into this chain.
     *
     * @param callable(Criteria): Criteria ...$branches
     */
    public function anyOf(callable ...$branches): self
    {
        $specs = array_map(
            static fn (callable $branch): Specification => $branch(self::media()),
            $branches
        );

        return $this->add(new OrX(...$specs));
    }

    /** @param callable(Criteria): Criteria $branch */
    public function not(callable $branch): self
    {
        return $this->add(new Not($branch(self::media())));
    }

    /** Escape hatch for anything the vocabulary does not cover yet. */
    public function where(Specification $spec): self
    {
        return $this->add($spec);
    }

    // ── Specification ───────────────────────────────────────

    public function toDql(string $alias, Params $params): string
    {
        return (new AndX(...$this->specs))->toDql($alias, $params);
    }

    private function add(Specification $spec): self
    {
        $this->specs[] = $spec;

        return $this;
    }
}
