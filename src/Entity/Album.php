<?php

declare(strict_types = 1);

namespace Modufolio\Media\Entity;

use Modufolio\Media\Contract\UploaderInterface;
use Modufolio\Media\Entity\Traits\Timestampable;
use Modufolio\Media\Repository\AlbumRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Modufolio\Media\Layout\LayoutSettingsInterface;
use Modufolio\Media\Layout\LayoutSettingsFactory;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: AlbumRepository::class)]
#[ORM\Table(name: 'albums')]
#[ORM\UniqueConstraint(name: 'UNIQ_ALBUM_SLUG', columns: ['slug'])]
#[ORM\Index(name: 'IDX_ALBUM_LEFT', columns: ['left_id'])]
#[ORM\Index(name: 'IDX_ALBUM_RIGHT', columns: ['right_id'])]
#[ORM\Index(name: 'IDX_ALBUM_LEVEL', columns: ['level'])]
#[ORM\Index(name: 'IDX_ALBUM_TYPE', columns: ['album_type'])]
#[ORM\HasLifecycleCallbacks]
class Album
{
    use Timestampable;

    public const TYPE_ALBUM = 0;
    public const TYPE_SET = 1;

    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    #[ORM\Id, ORM\Column(type: 'integer'), ORM\GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $uuid;

    #[ORM\Column(name: 'title', type: 'string', length: 255, nullable: false)]
    private string $title;

    #[ORM\Column(name: 'slug', type: 'string', length: 255, nullable: false)]
    private string $slug;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'subtitle', type: 'string', length: 255, nullable: true)]
    private ?string $subtitle = null;

    /** Comma-separated categories this album belongs to (albums). */
    #[ORM\Column(name: 'category', type: 'string', length: 255, nullable: true)]
    private ?string $category = null;

    /** Comma-separated category vocabulary offered to children (sets). */
    #[ORM\Column(name: 'categories', type: 'text', nullable: true)]
    private ?string $categories = null;

    #[ORM\Column(name: 'visibility', type: 'string', length: 20, nullable: false)]
    private string $visibility = 'public';

    #[ORM\Column(name: 'album_type', type: 'smallint', nullable: false)]
    private int $albumType = self::TYPE_ALBUM;

    #[ORM\Column(name: 'level', type: 'integer', nullable: false)]
    private int $level = 1;

    #[ORM\Column(name: 'left_id', type: 'integer', nullable: false)]
    private int $leftId;

    #[ORM\Column(name: 'right_id', type: 'integer', nullable: false)]
    private int $rightId;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(name: 'cover_media_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Media $coverMedia = null;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(name: 'cover_media_2_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Media $coverMedia2 = null;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(name: 'cover_media_3_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Media $coverMedia3 = null;

    /**
     * Drops the content width cap so the album fills the page gutter.
     * A container concern rather than a layout option, so it is kept here and
     * survives a layout switch.
     */
    #[ORM\Column(name: 'fullwidth', type: 'boolean', nullable: false)]
    private bool $fullwidth = false;

    /** Which layout renders this album: grid | slider | list. */
    #[ORM\Column(name: 'layout', type: 'string', length: 20, nullable: false)]
    private string $layout = 'grid';

    /**
     * Presentation options for the current layout, shaped by its LayoutSettingsInterface
     * class. Stored as JSON so a layout can gain options — or a new layout can
     * be added — without touching the schema.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(name: 'layout_options', type: 'json', nullable: false, options: ['default' => '{}'])]
    private array $layoutOptions = [];


    #[ORM\Column(name: 'position', type: 'integer', nullable: false)]
    private int $position = 0;

    #[ORM\Column(name: 'media_count', type: 'integer', nullable: false)]
    private int $mediaCount = 0;

    #[ORM\ManyToOne(targetEntity: UploaderInterface::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: true)]
    private ?UploaderInterface $createdBy = null;

    /** @var Collection<int, AlbumMedia> */
    #[ORM\OneToMany(targetEntity: AlbumMedia::class, mappedBy: 'album', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $albumMedia;

    public function __construct()
    {
        $this->uuid = Uuid::uuid4();
        $this->albumMedia = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUuid(): UuidInterface
    {
        return $this->uuid;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function setVisibility(string $visibility): self
    {
        $this->visibility = $visibility;
        return $this;
    }

    public function getAlbumType(): int
    {
        return $this->albumType;
    }

    public function setAlbumType(int $albumType): self
    {
        $this->albumType = $albumType;
        return $this;
    }

    public function isSet(): bool
    {
        return $this->albumType === self::TYPE_SET;
    }

    public function isAlbum(): bool
    {
        return $this->albumType === self::TYPE_ALBUM;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): self
    {
        $this->level = $level;
        return $this;
    }

    public function getLeftId(): int
    {
        return $this->leftId;
    }

    public function setLeftId(int $leftId): self
    {
        $this->leftId = $leftId;
        return $this;
    }

    public function getRightId(): int
    {
        return $this->rightId;
    }

    public function setRightId(int $rightId): self
    {
        $this->rightId = $rightId;
        return $this;
    }

    public function getCoverMedia(): ?Media
    {
        return $this->coverMedia;
    }

    public function setCoverMedia(?Media $coverMedia): self
    {
        $this->coverMedia = $coverMedia;
        return $this;
    }

    public function getCoverMedia2(): ?Media
    {
        return $this->coverMedia2;
    }

    public function setCoverMedia2(?Media $coverMedia2): self
    {
        $this->coverMedia2 = $coverMedia2;
        return $this;
    }

    public function getCoverMedia3(): ?Media
    {
        return $this->coverMedia3;
    }

    public function setCoverMedia3(?Media $coverMedia3): self
    {
        $this->coverMedia3 = $coverMedia3;
        return $this;
    }

    /**
     * Return all assigned cover media items (up to 3), skipping nulls.
     * @return Media[]
     */
    public function getCovers(): array
    {
        return array_values(array_filter([
            $this->coverMedia,
            $this->coverMedia2,
            $this->coverMedia3,
        ]));
    }

    /**
     * Set covers from an array of Media entities (up to 3).
     * @param Media[] $covers
     */
    public function setCovers(array $covers): self
    {
        $this->coverMedia  = $covers[0] ?? null;
        $this->coverMedia2 = $covers[1] ?? null;
        $this->coverMedia3 = $covers[2] ?? null;
        return $this;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): self
    {
        $this->subtitle = $subtitle;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;
        return $this;
    }

    /**
     * The album's categories as a trimmed list.
     *
     * @return string[]
     */
    public function getCategoryList(): array
    {
        return self::splitList($this->category);
    }

    public function getCategories(): ?string
    {
        return $this->categories;
    }

    public function setCategories(?string $categories): self
    {
        $this->categories = $categories;
        return $this;
    }

    /**
     * The set's category vocabulary as a trimmed list.
     *
     * @return string[]
     */
    public function getCategoriesList(): array
    {
        return self::splitList($this->categories);
    }

    /**
     * Split a comma-separated field into a list, dropping blanks and duplicates.
     *
     * @return string[]
     */
    public static function splitList(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $parts = array_map('trim', explode(',', $value));

        return array_values(array_unique(array_filter($parts, static fn (string $p) => $p !== '')));
    }

    public function isFullwidth(): bool
    {
        return $this->fullwidth;
    }

    public function setFullwidth(bool $fullwidth): self
    {
        $this->fullwidth = $fullwidth;
        return $this;
    }

    public function getLayout(): string
    {
        return $this->layout;
    }

    /**
     * Switching layout resets the options to that layout's defaults — the
     * previous layout's settings are meaningless under the new one.
     * Use setSettings() to switch layout and supply options together.
     */
    public function setLayout(string $layout): self
    {
        if (!LayoutSettingsFactory::isLayout($layout)) {
            throw new \InvalidArgumentException(
                'Invalid layout. Allowed: ' . implode(', ', LayoutSettingsFactory::layouts())
            );
        }

        if ($layout !== $this->layout) {
            $this->layout = $layout;
            $this->layoutOptions = LayoutSettingsFactory::make($layout)->toArray();
        }

        return $this;
    }

    /** The typed options for whichever layout this album currently uses. */
    public function getSettings(): LayoutSettingsInterface
    {
        return LayoutSettingsFactory::make($this->layout, $this->layoutOptions);
    }

    public function setSettings(LayoutSettingsInterface $settings): self
    {
        $this->layout = $settings::layout();
        $this->layoutOptions = $settings->toArray();

        return $this;
    }

    /** @return array<string, mixed> */
    public function getLayoutOptions(): array
    {
        return $this->layoutOptions;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function getMediaCount(): int
    {
        return $this->mediaCount;
    }

    public function setMediaCount(int $mediaCount): self
    {
        $this->mediaCount = $mediaCount;
        return $this;
    }

    public function incrementMediaCount(int $by = 1): self
    {
        $this->mediaCount += $by;
        return $this;
    }

    public function decrementMediaCount(int $by = 1): self
    {
        $this->mediaCount = max(0, $this->mediaCount - $by);
        return $this;
    }

    public function getCreatedBy(): ?UploaderInterface
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?UploaderInterface $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    /** @return Collection<int, AlbumMedia> */
    public function getAlbumMedia(): Collection
    {
        return $this->albumMedia;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'visibility' => $this->visibility,
            'album_type' => $this->albumType,
            'level' => $this->level,
            'left_id' => $this->leftId,
            'right_id' => $this->rightId,
            'media_count' => $this->mediaCount,
            'cover' => $this->coverMedia?->toArray(),
            'created_at' => $this->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $this->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Check if this node has children in the nested-set tree.
     * A leaf node always has rightId === leftId + 1.
     */
    public function hasChildren(): bool
    {
        return ($this->rightId - $this->leftId) > 1;
    }

    /**
     * Check if the album has displayable content.
     * - Albums: must have at least one image (mediaCount > 0)
     * - Sets:   must have at least one child album (hasChildren)
     */
    public function hasImages(): bool
    {
        if ($this->isSet()) {
            return $this->hasChildren();
        }

        return $this->mediaCount > 0;
    }

    /**
     * Check whether a string is a valid album slug
     * (lowercase letters, numbers, and single hyphens).
     */
    public static function isValidSlug(string $slug): bool
    {
        return preg_match(self::SLUG_PATTERN, $slug) === 1;
    }

    /**
     * Generate a slug from the title.
     */
    public static function slugify(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        return trim($slug, '-');
    }
}
