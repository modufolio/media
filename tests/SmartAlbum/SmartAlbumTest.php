<?php

declare(strict_types = 1);

namespace Modufolio\Media\Tests\SmartAlbum;

use Modufolio\Media\SmartAlbum\Criteria;
use Modufolio\Media\SmartAlbum\SmartAlbumInterface;
use Modufolio\Media\SmartAlbum\SmartAlbumRegistry;
use Modufolio\Media\SmartAlbum\SmartAlbumRunner;
use Modufolio\Media\SmartAlbum\Spec\Params;
use Modufolio\Media\SmartAlbum\Spec\Specification;
use Modufolio\Media\Tests\Support\MediaTestCase;
use Modufolio\Media\Entity\Media;

/**
 * The smart-album specification language: compilation to DQL and execution
 * against the real in-memory database — combinators must translate to the
 * exact membership a curator would expect.
 */
final class SmartAlbumTest extends MediaTestCase
{
    private function mediaWith(
        ?int $rating = null,
        bool $favorite = false,
        bool $featured = false,
        bool $public = true,
        string $mime = 'image/jpeg',
    ): Media {
        $name = 'm-' . bin2hex(random_bytes(5)) . '.jpg';

        $media = new Media();
        $media->setFilename($name);
        $media->setOriginalFilename($name);
        $media->setFilePath('/uploads/' . $name);
        $media->setMimeType($mime);
        $media->setFileSize(1024);
        $media->setRating($rating);
        $media->setIsFavorite($favorite);
        $media->setIsFeatured($featured);
        $media->setIsPublic($public);

        $em = $this->em;
        $em->persist($media);
        $em->flush();

        return $media;
    }

    private function runner(): SmartAlbumRunner
    {
        return new SmartAlbumRunner($this->em);
    }

    /** An inline album for spec-focused tests. */
    private function album(Specification $spec, array $order = ['createdAt' => 'DESC'], ?int $limit = null): SmartAlbumInterface
    {
        return new class($spec, $order, $limit) implements SmartAlbumInterface {
            public function __construct(
                private readonly Specification $spec,
                private readonly array $order,
                private readonly ?int $limit,
            ) {
            }

            public function slug(): string
            {
                return 'inline';
            }

            public function title(): string
            {
                return 'Inline';
            }

            public function description(): ?string
            {
                return null;
            }

            public function specification(): Specification
            {
                return $this->spec;
            }

            public function orderBy(): array
            {
                return $this->order;
            }

            public function limit(): ?int
            {
                return $this->limit;
            }
        };
    }

    // ── Compilation ───────────────────────────────────────────────

    public function testChainedCriteriaCompileToParenthesisedAndsWithUniqueParams(): void
    {
        $params = new Params();
        $dql = Criteria::media()->images()->public()->ratedAtLeast(4)->toDql('m', $params);

        self::assertSame(
            '(m.mimeType LIKE :sa_p0 AND m.isPublic = :sa_p1 AND m.rating >= :sa_p2)',
            $dql
        );
        self::assertSame(
            ['sa_p0' => 'image/%', 'sa_p1' => true, 'sa_p2' => 4],
            $params->all()
        );
    }

    public function testAnyOfCompilesToOrGroup(): void
    {
        $params = new Params();
        $dql = Criteria::media()
            ->anyOf(
                static fn (Criteria $c) => $c->favorite(),
                static fn (Criteria $c) => $c->rated(5),
            )
            ->toDql('m', $params);

        self::assertSame('(((m.isFavorite = :sa_p0) OR (m.rating = :sa_p1)))', $dql);
    }

    public function testRunnerRejectsUnknownOrderField(): void
    {
        $this->expectException(\LogicException::class);

        $params = new Params();
        $this->runner()->dql(
            $this->album(Criteria::media()->public(), ['uuid' => 'ASC']),
            $params
        );
    }

    // ── Execution ─────────────────────────────────────────────────

    public function testConjunctionSelectsOnlyFullMatches(): void
    {
        $hit = $this->mediaWith(rating: 5);
        $this->mediaWith(rating: 4);
        $this->mediaWith(rating: 5, public: false);
        $this->mediaWith(rating: 5, mime: 'video/mp4');

        $result = $this->runner()->run($this->album(
            Criteria::media()->images()->public()->rated(5)
        ));

        self::assertCount(1, $result);
        self::assertSame($hit->getId(), $result[0]->getId());
    }

    public function testAnyOfUnionsItsBranches(): void
    {
        $featured = $this->mediaWith(featured: true);
        $favorite = $this->mediaWith(favorite: true);
        $rated    = $this->mediaWith(rating: 4);
        $this->mediaWith(rating: 3);
        $this->mediaWith();

        $spec = Criteria::media()->images()->public()->anyOf(
            static fn (Criteria $c) => $c->featured(),
            static fn (Criteria $c) => $c->favorite(),
            static fn (Criteria $c) => $c->ratedAtLeast(4),
        );
        $ids = array_map(static fn (Media $m) => $m->getId(), $this->runner()->run($this->album($spec)));

        sort($ids);
        $expected = [$featured->getId(), $favorite->getId(), $rated->getId()];
        sort($expected);
        self::assertSame($expected, $ids);
    }

    public function testNotExcludesMatches(): void
    {
        $plain = $this->mediaWith();
        $this->mediaWith(favorite: true);

        $spec = Criteria::media()->not(static fn (Criteria $c) => $c->favorite());
        $result = $this->runner()->run($this->album($spec));

        self::assertCount(1, $result);
        self::assertSame($plain->getId(), $result[0]->getId());
    }

    public function testLimitCapsResultAndCount(): void
    {
        $this->mediaWith(rating: 5);
        $this->mediaWith(rating: 5);
        $this->mediaWith(rating: 5);

        $album = $this->album(Criteria::media()->rated(5), limit: 2);

        self::assertCount(2, $this->runner()->run($album));
        self::assertSame(2, $this->runner()->count($album));
    }

    public function testRegistryIndexesBySlugAndRejectsDuplicates(): void
    {
        $a = $this->album(Criteria::media()->public());
        $registry = new SmartAlbumRegistry([$a]);

        self::assertSame('inline', $registry->get('inline')?->slug());
        self::assertNull($registry->get('nope'));

        $this->expectException(\LogicException::class);
        new SmartAlbumRegistry([$a, $this->album(Criteria::media()->favorite())]);
    }
}
