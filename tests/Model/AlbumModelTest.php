<?php

declare(strict_types = 1);

namespace Modufolio\Media\Tests\Model;

use Modufolio\Media\Entity\Album;
use Modufolio\Media\Tests\Fixture\TestUploader;
use Modufolio\Media\Tests\Support\MediaTestCase;

/**
 * The album domain, exercised without any application: nested-set structure,
 * media attachment with trigger-maintained counts, and the uploader contract.
 */
final class AlbumModelTest extends MediaTestCase
{
    public function testCreateBuildsAValidNestedSet(): void
    {
        $model = $this->albumModel();

        $set = $model->create('Travel', null, 'public', Album::TYPE_SET);
        $child = $model->create('Alaska', null, 'public', Album::TYPE_ALBUM, $set);

        $this->assertTrue($set->isSet());
        $this->assertSame('travel', $set->getSlug());
        $this->assertTrue($set->getLeftId() < $child->getLeftId());
        $this->assertTrue($child->getRightId() < $set->getRightId());
        $this->assertSame($set->getLevel() + 1, $child->getLevel());
    }

    public function testTitleCollisionsGetUniqueSlugs(): void
    {
        $model = $this->albumModel();

        $first = $model->create('Summer', null, 'public', Album::TYPE_ALBUM);
        $second = $model->create('Summer', null, 'public', Album::TYPE_ALBUM);

        $this->assertSame('summer', $first->getSlug());
        $this->assertNotSame($first->getSlug(), $second->getSlug());
    }

    public function testAnAlbumCannotParentAnything(): void
    {
        $model = $this->albumModel();
        $album = $model->create('Loose album', null, 'public', Album::TYPE_ALBUM);

        $this->expectException(\InvalidArgumentException::class);
        $model->create('Child', null, 'public', Album::TYPE_ALBUM, $album);
    }

    public function testAddMediaAttachesAndTheTriggerCounts(): void
    {
        $model = $this->albumModel();
        $album = $model->create('Wedding', null, 'public', Album::TYPE_ALBUM);

        $a = $this->makeMedia('a.jpg');
        $b = $this->makeMedia('b.jpg');

        $attached = $model->addMedia($album, [$a->getId(), $b->getId(), $a->getId()]);
        $this->assertSame(2, $attached, 'Duplicate ids collapse to one attachment.');

        // media_count is maintained by the SQLite triggers, not by PHP — read
        // it straight from the row to prove the trigger fired.
        $count = $this->em->getConnection()->fetchOne(
            'SELECT media_count FROM albums WHERE id = ?',
            [$album->getId()],
        );
        $this->assertSame(2, (int) $count);

        // Re-adding is a no-op, in PHP and in the count.
        $this->assertSame(0, $model->addMedia($album, [$a->getId()]));
    }

    public function testMediaCannotAttachToASet(): void
    {
        $model = $this->albumModel();
        $set = $model->create('Sets only', null, 'public', Album::TYPE_SET);

        $this->expectException(\InvalidArgumentException::class);
        $model->addMedia($set, [$this->makeMedia()->getId()]);
    }

    public function testCreatedByAcceptsWhateverImplementsTheContract(): void
    {
        $user = (new TestUploader())->setName('Maarten');
        $this->em->persist($user);
        $this->em->flush();

        $album = $this->albumModel()->create('Mine', null, 'public', Album::TYPE_ALBUM, null, $user);
        $this->em->clear();

        $fresh = $this->albumRepo()->find($album->getId());
        $this->assertSame('Maarten', $fresh->getCreatedBy()?->getName());
    }

    public function testMoveReparentsWithinTheTree(): void
    {
        $model = $this->albumModel();
        $setA = $model->create('Set A', null, 'public', Album::TYPE_SET);
        $setB = $model->create('Set B', null, 'public', Album::TYPE_SET);
        $album = $model->create('Nomad', null, 'public', Album::TYPE_ALBUM, $setA);

        $model->move($album, $setB);
        $this->em->clear();

        $freshB = $this->albumRepo()->findBySlug('set-b');
        $children = $this->albumRepo()->children($freshB);

        $this->assertCount(1, $children);
        $this->assertSame('nomad', $children[0]->getSlug());
    }
}
