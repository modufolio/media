<?php

declare(strict_types = 1);

namespace Modufolio\Media\Tests\Model;

use Modufolio\Appkit\Image\StorageInterface;
use Modufolio\Media\Contract\FocusStoreInterface;
use Modufolio\Media\Contract\MediaJobsInterface;
use Modufolio\Media\Entity\Media;
use Modufolio\Media\Model\MediaModel;
use Modufolio\Media\Tests\Support\MediaTestCase;

/**
 * MediaModel against its two outward contracts: job cleanup on delete, and
 * the optional focus store for analysed features.
 */
final class MediaModelTest extends MediaTestCase
{
    /** @var list<string> */
    private array $deletedJobFiles = [];

    /** @var list<array{Media, array<string, mixed>}> */
    private array $storedFeatures = [];

    private function model(?FocusStoreInterface $focusStore = null): MediaModel
    {
        $jobs = new class($this->deletedJobFiles) implements MediaJobsInterface {
            /** @param list<string> $log */
            public function __construct(
                // Written here, read through the by-ref binding in the test.
                private array &$log, // @phpstan-ignore property.onlyWritten
            ) {}

            public function deleteByOriginalFilename(string $filename): int
            {
                $this->log[] = $filename;

                return 1;
            }
        };

        return new MediaModel(
            $this->em,
            $this->mediaRepo(),
            $jobs,
            $this->createStub(StorageInterface::class),
            $focusStore,
        );
    }

    private function focusStore(): FocusStoreInterface
    {
        return new class($this->storedFeatures) implements FocusStoreInterface {
            /** @param list<array{Media, array<string, mixed>}> $log */
            public function __construct(
                // Written here, read through the by-ref binding in the test.
                private array &$log, // @phpstan-ignore property.onlyWritten
            ) {}

            public function upsertFeatures(Media $media, array $features): void
            {
                $this->log[] = [$media, $features];
            }
        };
    }

    public function testUpdateFieldsWritesOnlyPresentKeys(): void
    {
        $media = $this->makeMedia();
        $media->setAltText('before');
        $this->em->flush();

        $this->model()->updateFields($media, ['title' => 'After']);

        $this->assertSame('After', $media->getTitle());
        $this->assertSame('before', $media->getAltText(), 'Absent keys must not be touched.');
    }

    public function testFeaturesReachTheFocusStoreWhenWired(): void
    {
        $media = $this->makeMedia();

        $this->model($this->focusStore())->updateFields($media, [
            'features' => ['x' => 0.4, 'y' => 0.6],
        ]);

        $this->assertCount(1, $this->storedFeatures);
        $this->assertSame($media, $this->storedFeatures[0][0]);
        $this->assertSame(['x' => 0.4, 'y' => 0.6], $this->storedFeatures[0][1]);
    }

    public function testFeaturesAreIgnoredWithoutAFocusStore(): void
    {
        $media = $this->makeMedia();

        // Wire nothing: the payload must be dropped, not fatal — the contract
        // is explicitly optional.
        $this->model()->updateFields($media, ['features' => ['x' => 0.5], 'title' => 'Still saved']);

        $this->assertSame('Still saved', $media->getTitle());
    }

    public function testDeleteMediaRemovesTheRowAndRetiresItsJobs(): void
    {
        $media = $this->makeMedia('doomed.jpg');
        $id = $media->getId();

        $filename = $this->model()->deleteMedia($media);

        $this->assertSame('doomed.jpg', $filename);
        $this->assertNull($this->mediaRepo()->find($id));
        $this->assertSame(
            ['tus/aa/doomed.jpg'],
            $this->deletedJobFiles,
            'The job store is asked by the path under /uploads/, per the contract.'
        );
    }
}
