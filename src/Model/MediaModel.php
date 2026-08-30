<?php

declare(strict_types=1);

namespace Modufolio\Media\Model;

use Modufolio\Media\Entity\Media;
use Modufolio\Media\Contract\FocusStoreInterface;
use Modufolio\Media\Contract\MediaJobsInterface;
use Modufolio\Media\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Modufolio\Appkit\Image\StorageInterface;

/**
 * Service for Media business logic.
 *
 * Extracted from MediaApiController (mirroring AlbumModel) to keep the
 * controller thin: it owns field mapping, file + orphan-job deletion, and the
 * bulk-mutation loops that were previously copy-pasted across five endpoints.
 * Controllers stay responsible for HTTP concerns (request parsing, validation,
 * presenters and JSON responses).
 */
class MediaModel
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MediaRepository $mediaRepo,
        private readonly MediaJobsInterface $imageJobRepo,
        private readonly StorageInterface $storage,
        private readonly ?FocusStoreInterface $focusStore = null,
    ) {}

    // ─── Single-item mutations ────────────────────────────────────

    /**
     * Apply a partial field update to a single media item. Only keys present in
     * $body are touched; `metadata` is merged into the existing value.
     *
     * @param array<string, mixed> $body
     */
    public function updateFields(Media $media, array $body): void
    {
        $this->applyTextFields($media, $body);

        if (array_key_exists('focus', $body)) {
            $media->setFocus($body['focus'] ?: null);
        }
        if (array_key_exists('is_favorite', $body)) {
            $media->setIsFavorite((bool) $body['is_favorite']);
        }
        if (array_key_exists('is_featured', $body)) {
            $this->setFeatured($media, (bool) $body['is_featured']);
        }
        if (array_key_exists('is_public', $body)) {
            $media->setIsPublic((bool) $body['is_public']);
        }
        if (array_key_exists('rating', $body)) {
            $media->setRating($body['rating'] === null ? null : (int) $body['rating']);
        }
        if (array_key_exists('metadata', $body) && is_array($body['metadata'])) {
            // Merge incoming keys into existing metadata rather than replacing it
            // wholesale, so callers can update individual sub-keys without knowing
            // the full payload.
            $existing = $media->getMetadata() ?? [];
            $media->setMetadata(array_merge($existing, $body['metadata']));
        }

        if (array_key_exists('features', $body) && is_array($body['features'])) {
            $this->focusStore?->upsertFeatures($media, $body['features']);
        }

        $this->em->flush();
    }

    /**
     * Feature/unfeature for the front page. Featuring appends at the end of
     * the current order (Koken's semantics); unfeaturing clears the slot.
     */
    public function setFeatured(Media $media, bool $featured): void
    {
        if ($featured && !$media->isFeatured()) {
            $max = (int) $this->em->createQueryBuilder()
                ->select('COALESCE(MAX(m.featuredOrder), 0)')
                ->from(Media::class, 'm')
                ->where('m.isFeatured = true')
                ->getQuery()
                ->getSingleScalarResult();
            $media->setIsFeatured(true)->setFeaturedOrder($max + 1);
        } elseif (!$featured) {
            $media->setIsFeatured(false);
        }
    }

    /**
     * Delete a single media item: its file on disk, any orphaned image job, and
     * the entity. Returns the stored filename (for the response payload).
     */
    public function deleteMedia(Media $media): string
    {
        $filename = $media->getFilename();

        $this->removeFile($media);
        $this->deleteJob($media);
        $this->em->remove($media);
        $this->em->flush();

        return $filename;
    }

    // ─── Bulk mutations ───────────────────────────────────────────

    /**
     * Delete every media item and its file on disk.
     *
     * @return array{deleted: int, errors: int} errors counts file-unlink failures
     */
    public function deleteAll(): array
    {
        $deleted = 0;
        $errors = 0;

        foreach ($this->mediaRepo->findAll() as $media) {
            if ($this->removeFile($media)) {
                $errors++;
            }
            $this->em->remove($media);
            $deleted++;
        }

        $this->em->flush();

        return ['deleted' => $deleted, 'errors' => $errors];
    }

    /**
     * Delete the media items resolved from the given uuids, each with its file
     * and orphaned image job. Non-string entries and unknown uuids are skipped.
     *
     * @param  array<int, mixed> $uuids
     * @return array{deleted: int, errors: int}
     */
    public function deleteByUuids(array $uuids): array
    {
        $deleted = 0;
        $errors = 0;

        foreach ($this->mediaRepo->findByUuids($uuids) as $media) {
            if ($this->removeFile($media)) {
                $errors++;
            }
            $this->deleteJob($media);
            $this->em->remove($media);
            $deleted++;
        }

        $this->em->flush();

        return ['deleted' => $deleted, 'errors' => $errors];
    }

    /**
     * Set (or clear, with null) the rating on every resolved media item.
     *
     * @param  array<int, mixed> $uuids
     * @return int number of items updated
     */
    public function bulkSetRating(array $uuids, ?int $rating): int
    {
        return $this->applyToEach($uuids, static fn (Media $m) => $m->setRating($rating));
    }

    /**
     * Update title/alt_text/caption on every resolved media item.
     *
     * @param  array<int, mixed>   $uuids
     * @param  array<string, mixed> $body
     * @return int number of items updated
     */
    public function bulkUpdateTextFields(array $uuids, array $body): int
    {
        return $this->applyToEach($uuids, fn (Media $m) => $this->applyTextFields($m, $body));
    }

    /**
     * Set the favorite flag on every resolved media item.
     *
     * @param  array<int, mixed> $uuids
     * @return int number of items updated
     */
    public function bulkSetFavorite(array $uuids, bool $favorite): int
    {
        return $this->applyToEach($uuids, static fn (Media $m) => $m->setIsFavorite($favorite));
    }

    // ─── Internals ────────────────────────────────────────────────

    /**
     * Resolve uuids to media, run $mutate on each, flush once. Returns the count.
     *
     * @param array<int, mixed>    $uuids
     * @param callable(Media): void $mutate
     */
    private function applyToEach(array $uuids, callable $mutate): int
    {
        $updated = 0;
        foreach ($this->mediaRepo->findByUuids($uuids) as $media) {
            $mutate($media);
            $updated++;
        }

        $this->em->flush();

        return $updated;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function applyTextFields(Media $media, array $body): void
    {
        if (array_key_exists('title', $body)) {
            $media->setTitle($body['title'] ?: null);
        }
        if (array_key_exists('alt_text', $body)) {
            $media->setAltText($body['alt_text'] ?: null);
        }
        if (array_key_exists('caption', $body)) {
            $media->setCaption($body['caption'] ?: null);
        }
    }

    /**
     * Delete the media file from disk, along with every generated rendition.
     * Returns true if an unlink error occurred.
     */
    private function removeFile(Media $media): bool
    {
        $filePath = BASE_DIR . '/' . ltrim($media->getFilePath(), '/');

        // Renditions live in a per-master directory keyed by md5 of the master's
        // absolute path (see Storage::mediaRoot / ThumbnailGenerator::buildUrl).
        // Compute it before unlinking so the key matches, then remove the whole
        // directory — otherwise generated variants are orphaned forever.
        $this->removeRenditionDir($filePath);

        if (file_exists($filePath)) {
            try {
                unlink($filePath);
            } catch (\Exception) {
                return true;
            }
        }

        return false;
    }

    private function removeRenditionDir(string $masterAbsolutePath): void
    {
        $dir = $this->storage->baseMediaRoot() . '/images/tus/' . md5($masterAbsolutePath);

        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            @unlink($dir . '/' . $entry);
        }
        @rmdir($dir);
    }

    private function deleteJob(Media $media): void
    {
        $jobFilename = ltrim(substr($media->getFilePath(), strlen('/uploads/')), '/');
        $this->imageJobRepo->deleteByOriginalFilename($jobFilename);
    }
}
