<?php

declare(strict_types=1);

namespace Modufolio\Media\Model;

use Modufolio\Media\Layout\LayoutSettingsFactory;
use Modufolio\Media\Database\AlbumTriggerAdapterFactory;
use Modufolio\Media\Database\AlbumTriggerAdapterInterface;
use Modufolio\Media\Entity\Album;
use Modufolio\Media\Entity\AlbumMedia;
use Modufolio\Media\Entity\Media;
use Modufolio\Media\Contract\UploaderInterface;
use Modufolio\Media\Repository\AlbumMediaRepository;
use Modufolio\Media\Repository\AlbumRepository;
use Modufolio\Media\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for Album business logic.
 *
 * Extracted from AlbumController to keep controllers thin and logic reusable.
 * Handles all album CRUD, nested-set hierarchy, media membership, ordering and covers.
 */
class AlbumModel
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AlbumRepository $albumRepo,
        private readonly AlbumMediaRepository $albumMediaRepo,
        private readonly MediaRepository $mediaRepo,
    ) {}

    private function adapter(): AlbumTriggerAdapterInterface
    {
        return AlbumTriggerAdapterFactory::forPlatform($this->em->getConnection()->getDatabasePlatform());
    }

    // ─── Album CRUD ───────────────────────────────────────────────

    /**
     * Create a new album and insert it into the nested-set tree.
     *
     * @throws \InvalidArgumentException
     */
    public function create(
        string $title,
        ?string $description,
        string $visibility,
        int $albumType,
        ?Album $parent = null,
        ?UploaderInterface $createdBy = null,
    ): Album {
        $album = new Album();
        $album->setTitle($title);
        $album->setSlug($this->albumRepo->generateUniqueSlug($title));
        $album->setDescription($description);
        $album->setVisibility($visibility);
        $album->setAlbumType($albumType);

        if ($createdBy !== null) {
            $album->setCreatedBy($createdBy);
        }

        if ($parent !== null && !$parent->isSet()) {
            throw new \InvalidArgumentException('Parent must be a set (album_type = 1)');
        }

        $album->setPosition(
            $parent
                ? $this->albumRepo->getMaxChildPosition($parent) + 1
                : $this->albumRepo->getMaxRootPosition() + 1
        );
        $this->albumRepo->insertNode($album, $parent);

        return $album;
    }

    /**
     * Update album metadata. Only updates fields present in $data.
     *
     * @param array<string, mixed> $data
     * @throws \InvalidArgumentException
     */
    public function update(Album $album, array $data): void
    {
        if (isset($data['title'])) {
            $title = trim((string) $data['title']);
            if ($title === '') {
                throw new \InvalidArgumentException('Title cannot be empty');
            }
            $album->setTitle($title);
        }

        if (array_key_exists('description', $data)) {
            $album->setDescription($data['description']);
        }

        if (array_key_exists('subtitle', $data)) {
            $subtitle = $data['subtitle'] !== null ? trim((string) $data['subtitle']) : null;
            $album->setSubtitle($subtitle === '' ? null : $subtitle);
        }

        // Comma-separated lists are normalised on write so the stored value is
        // always "a, b, c" — no blanks, no duplicates, no stray whitespace.
        if (array_key_exists('category', $data)) {
            $list = Album::splitList($data['category'] !== null ? (string) $data['category'] : null);
            $album->setCategory($list === [] ? null : implode(', ', $list));
        }

        if (array_key_exists('categories', $data)) {
            $list = Album::splitList($data['categories'] !== null ? (string) $data['categories'] : null);
            $album->setCategories($list === [] ? null : implode(', ', $list));
        }

        if (isset($data['visibility'])) {
            $album->setVisibility((string) $data['visibility']);
        }

        if (isset($data['fullwidth'])) {
            $album->setFullwidth(filter_var($data['fullwidth'], FILTER_VALIDATE_BOOL));
        }

        // Layout and its options move together: switching layout adopts that
        // layout's defaults, and any options supplied in the same request are
        // applied on top. Unknown option keys are ignored by the settings class,
        // and out-of-range numbers are clamped by it.
        if (isset($data['layout']) || isset($data['layout_options'])) {
            $layout = isset($data['layout'])
                ? (string) $data['layout']
                : $album->getLayout();

            if (!LayoutSettingsFactory::isLayout($layout)) {
                throw new \InvalidArgumentException(
                    'Invalid layout. Allowed: ' . implode(', ', LayoutSettingsFactory::layouts())
                );
            }

            // Carry the stored options over only when the layout is unchanged —
            // another layout's settings mean nothing under this one.
            $base = $layout === $album->getLayout() ? $album->getLayoutOptions() : [];
            $options = is_array($data['layout_options'] ?? null) ? $data['layout_options'] : [];

            $album->setSettings(LayoutSettingsFactory::make($layout, [...$base, ...$options]));
        }

        $this->em->flush();
    }

    /**
     * Update the slug of an album, validating format and uniqueness.
     *
     * @throws \InvalidArgumentException
     */
    public function updateSlug(Album $album, string $slug): void
    {
        $slug = trim(strtolower($slug));

        if ($slug === '') {
            throw new \InvalidArgumentException('Slug cannot be empty');
        }

        if (!Album::isValidSlug($slug)) {
            throw new \InvalidArgumentException('Slug may only contain lowercase letters, numbers, and hyphens');
        }

        if ($this->albumRepo->slugExists($slug, $album->getId())) {
            throw new \InvalidArgumentException('This slug is already in use');
        }

        $album->setSlug($slug);
        $this->em->flush();
    }

    /**
     * Delete an album and all its descendants, closing the nested-set gap.
     */
    public function delete(Album $album): void
    {
        $this->albumRepo->removeNode($album);
    }

    /**
     * Move an album to a new parent (or to root when parent is null).
     *
     * @throws \InvalidArgumentException
     */
    public function move(Album $album, ?Album $newParent): void
    {
        if ($newParent !== null && !$newParent->isSet()) {
            throw new \InvalidArgumentException('Target parent must be a set');
        }

        $this->albumRepo->moveNode($album, $newParent);
    }

    // ─── Album-Media Membership ───────────────────────────────────

    /**
     * Add media items to an album. Returns the count of newly added items.
     *
     * @param int[] $mediaIds
     * @throws \InvalidArgumentException
     */
    public function addMedia(Album $album, array $mediaIds): int
    {
        if ($album->isSet()) {
            throw new \InvalidArgumentException('Cannot add media to a set. Add to an album instead.');
        }

        $ids = array_values(array_unique(array_map('intval', $mediaIds)));
        if ($ids === []) {
            return 0;
        }

        // Two batched queries instead of two per media id: load all candidate
        // media, and the set of ids already in this album, keyed for O(1) lookup.
        $mediaById = [];
        foreach ($this->mediaRepo->findBy(['id' => $ids]) as $media) {
            $mediaById[$media->getId()] = $media;
        }

        $existing = [];
        foreach ($this->albumMediaRepo->findEntriesByMediaIds($album->getId(), $ids) as $entry) {
            // getMedia()->getId() reads the proxy identifier — no extra query.
            $existing[$entry->getMedia()->getId()] = true;
        }

        $maxPosition = $this->albumMediaRepo->getMaxPosition($album);
        $added = 0;

        // Iterate $ids (not the map) to preserve the caller-provided order.
        foreach ($ids as $mediaId) {
            $media = $mediaById[$mediaId] ?? null;
            if ($media === null || isset($existing[$mediaId])) {
                continue;
            }

            $maxPosition++;
            $albumMedia = new AlbumMedia();
            $albumMedia->setAlbum($album);
            $albumMedia->setMedia($media);
            $albumMedia->setPosition($maxPosition);
            $this->em->persist($albumMedia);
            $added++;

            // Auto-set cover if album has none yet
            if ($album->getCoverMedia() === null && $media->isImage()) {
                $album->setCoverMedia($media);
            }
        }

        if ($added > 0) {
            $this->em->flush();
            // media_count is maintained by SQLite triggers on album_media;
            // refresh to pick up the trigger-updated value.
            $this->em->refresh($album);
        }

        return $added;
    }

    /**
     * Remove a media item from an album.
     *
     * @throws \RuntimeException
     */
    public function removeMedia(Album $album, int $mediaId): void
    {
        $entry = $this->albumMediaRepo->findEntry($album->getId(), $mediaId);
        if ($entry === null) {
            throw new \RuntimeException('Media not found in this album');
        }

        // Clear cover if the removed item was the cover
        if ($album->getCoverMedia()?->getId() === $mediaId) {
            $album->setCoverMedia(null);
        }

        $this->em->remove($entry);
        $this->em->flush();
        // media_count is maintained by SQLite triggers on album_media;
        // refresh to pick up the trigger-updated value.
        $this->em->refresh($album);
    }

    /**
     * Remove multiple media items from an album in a single transaction.
     *
     * @param int[] $mediaIds
     */
    public function removeMultipleMedia(Album $album, array $mediaIds): int
    {
        $intIds = array_map('intval', $mediaIds);
        $entries = $this->albumMediaRepo->findEntriesByMediaIds($album->getId(), $intIds);

        if (empty($entries)) {
            return 0;
        }

        $removedIds = [];
        foreach ($entries as $entry) {
            $this->em->remove($entry);
            $removedIds[] = $entry->getMedia()->getId();
        }

        $this->em->flush();

        // Clear cover only after a successful flush to keep the album consistent
        if ($album->getCoverMedia() !== null && in_array($album->getCoverMedia()->getId(), $removedIds, true)) {
            $album->setCoverMedia(null);
            $this->em->flush();
        }

        // media_count is maintained by SQLite triggers on album_media;
        // refresh to pick up the trigger-updated value.
        $this->em->refresh($album);

        return count($removedIds);
    }

    // ─── Ordering ─────────────────────────────────────────────────

    /**
     * Move a single media item to a new position within an album.
     *
     * Delegates to the engine's AlbumTriggerAdapter so exactly one
     * album_media row changes and siblings shift atomically. Using
     * Doctrine's flush() for this would trigger the reorder handler once
     * per dirty entity in undefined order, producing incorrect
     * intermediate states.
     *
     * @throws \RuntimeException if the album-media entry is not found
     */
    public function moveMediaPosition(Album $album, int $mediaId, int $newPosition): void
    {
        $entry = $this->albumMediaRepo->findEntry($album->getId(), $mediaId);
        if ($entry === null) {
            throw new \RuntimeException("Media {$mediaId} not found in album {$album->getId()}");
        }

        $maxPos = max(0, $album->getMediaCount() - 1);
        $newPosition = max(0, min($newPosition, $maxPos));

        $this->adapter()->repositionMedia($this->em->getConnection(), $entry->getId(), $newPosition);
    }

    /**
     * Reorder an album's media to match the given ordered id list.
     *
     * Places items one at a time through the adapter's repositionMedia() so
     * each engine's sibling-shift handling fires per row (see
     * moveMediaPosition()). Placing position 0, 1, 2, … in ascending order
     * is safe: each shift only touches positions >= the one being placed,
     * so already-placed items are never disturbed. Ids not present in the
     * album are skipped.
     *
     * @param int[] $mediaIds Ordered list of media IDs
     */
    public function reorderMedia(Album $album, array $mediaIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $mediaIds)));

        // Preload every entry in one query, keyed by media id, instead of a
        // findEntry() lookup per id. The per-row reposition() call stays
        // (single-row updates — see moveMediaPosition()).
        $entryByMediaId = [];
        foreach ($this->albumMediaRepo->findEntriesByMediaIds($album->getId(), $ids) as $entry) {
            $entryByMediaId[$entry->getMedia()->getId()] = $entry;
        }

        $connection = $this->em->getConnection();
        $adapter = $this->adapter();
        $position = 0;

        foreach ($ids as $mediaId) {
            $entry = $entryByMediaId[$mediaId] ?? null;
            if ($entry === null) {
                continue;
            }

            $adapter->repositionMedia($connection, $entry->getId(), $position);
            $position++;
        }
    }

    /**
     * Reorder child albums within a set by updating their positions.
     *
     * @param int[] $albumIds Ordered list of album IDs
     */
    public function reorderChildren(Album $set, array $albumIds): void
    {
        $children = $this->albumRepo->findBy(['id' => array_map('intval', $albumIds)]);
        $indexed = [];
        foreach ($children as $child) {
            $indexed[$child->getId()] = $child;
        }
        foreach ($albumIds as $position => $albumId) {
            if (array_key_exists((int) $albumId, $indexed)) {
                $indexed[(int) $albumId]->setPosition($position);
            }
        }
        $this->em->flush();
    }

    /**
     * Reorder root-level albums (level = 1) by updating their positions.
     *
     * @param int[] $albumIds Ordered list of root album IDs
     */
    public function reorderRoot(array $albumIds): void
    {
        $albums = $this->albumRepo->findBy(['id' => array_map('intval', $albumIds), 'level' => 1]);
        $indexed = [];
        foreach ($albums as $album) {
            $indexed[$album->getId()] = $album;
        }
        foreach ($albumIds as $position => $albumId) {
            if (array_key_exists((int) $albumId, $indexed)) {
                $indexed[(int) $albumId]->setPosition($position);
            }
        }
        $this->em->flush();
    }

    // ─── Cover ───────────────────────────────────────────────────

    /**
     * Set the cover for an album.
     * Accepts multi-cover format { covers: [id, id, id] } or single { media_id: id }.
     *
     * @param array<string, mixed> $body
     * @throws \RuntimeException
     */
    public function setCover(Album $album, array $body): void
    {
        if (array_key_exists('covers', $body)) {
            $coverIds = array_slice(array_filter((array) $body['covers']), 0, 3);
            $covers = [];
            foreach ($coverIds as $mediaId) {
                $media = $this->mediaRepo->find((int) $mediaId);
                if ($media !== null) {
                    $covers[] = $media;
                }
            }
            $album->setCovers($covers);
        } else {
            $mediaId = $body['media_id'] ?? null;
            if ($mediaId === null) {
                $album->setCoverMedia(null);
            } else {
                $media = $this->mediaRepo->find((int) $mediaId);
                if ($media === null) {
                    throw new \RuntimeException('Media not found');
                }
                $album->setCoverMedia($media);
            }
        }

        $this->em->flush();
    }

    // ─── Media Query ─────────────────────────────────────────────

    /**
     * Get all Media entities for an album.
     * For sets: collects media from all descendant albums (de-duplicated by ID).
     *
     * @return Media[]
     */
    public function getMedia(Album $album): array
    {
        if ($album->isAlbum()) {
            $entries = $this->albumMediaRepo->findByAlbum($album);
            return array_map(fn (AlbumMedia $am) => $am->getMedia(), $entries);
        }

        // For sets: single JOIN query across all descendant albums (no N+1)
        return $this->albumMediaRepo->findMediaForDescendants(
            $album->getLeftId(),
            $album->getRightId(),
        );
    }
}
