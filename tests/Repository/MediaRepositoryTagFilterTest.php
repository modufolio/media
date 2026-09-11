<?php

declare(strict_types = 1);

namespace Modufolio\Media\Tests\Repository;

use Modufolio\Media\Repository\MediaRepository;
use Modufolio\Media\Tests\Fixture\TestTag;
use Modufolio\Media\Tests\Support\MediaTestCase;

/**
 * The `tag:` filter's boundary: tags are app-level polymorphic data, so the
 * repository resolves slugs only through the class the app registers with
 * useTagEntity() — and degrades to "matches nothing" when none is.
 */
final class MediaRepositoryTagFilterTest extends MediaTestCase
{
    private function taggablesTable(): void
    {
        // The taggables link table is app-side (polymorphic, no FK); the
        // filter reads it with raw SQL, so the test provides the table.
        $this->em->getConnection()->executeStatement(
            'CREATE TABLE taggables (id INTEGER PRIMARY KEY, tag_id INTEGER, taggable_type VARCHAR(50), taggable_id INTEGER)'
        );
    }

    public function testSlugFilterMatchesNothingWhenNoTagEntityIsRegistered(): void
    {
        $this->taggablesTable();
        $this->makeMedia();

        $items = $this->mediaRepo()->findPaginated(1, 10, 'newest', 'tag:weddings');
        $total = $this->mediaRepo()->countFiltered('tag:weddings');

        $this->assertSame([], $items);
        $this->assertSame(0, $total);
    }

    public function testSlugFilterNarrowsThroughTheRegisteredTagEntity(): void
    {
        $this->taggablesTable();
        MediaRepository::useTagEntity(TestTag::class);

        $tagged = $this->makeMedia('tagged.jpg');
        $this->makeMedia('untagged.jpg');

        $tag = (new TestTag())->setName('Weddings')->setSlug('weddings');
        $this->em->persist($tag);
        $this->em->flush();

        $this->em->getConnection()->executeStatement(
            'INSERT INTO taggables (id, tag_id, taggable_type, taggable_id) VALUES (1, ?, ?, ?)',
            [$tag->getId(), 'media', $tagged->getId()],
        );

        $items = $this->mediaRepo()->findPaginated(1, 10, 'newest', 'tag:weddings');

        $this->assertSame(1, $this->mediaRepo()->countFiltered('tag:weddings'));
        $this->assertSame('tagged.jpg', $items[0]->getFilename());
    }

    public function testNumericTagFilterNeedsNoEntityClass(): void
    {
        $this->taggablesTable();

        $tagged = $this->makeMedia('by-id.jpg');
        $this->em->getConnection()->executeStatement(
            'INSERT INTO taggables (id, tag_id, taggable_type, taggable_id) VALUES (1, 7, ?, ?)',
            ['media', $tagged->getId()],
        );

        $items = $this->mediaRepo()->findPaginated(1, 10, 'newest', 'tag:7');

        $this->assertSame(1, $this->mediaRepo()->countFiltered('tag:7'));
        $this->assertSame('by-id.jpg', $items[0]->getFilename());
    }
}
