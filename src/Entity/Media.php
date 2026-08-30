<?php

declare(strict_types = 1);

namespace Modufolio\Media\Entity;

use Modufolio\Media\Contract\UploaderInterface;
use Modufolio\Media\Entity\Traits\Timestampable;
use Modufolio\Media\Repository\MediaRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(name: 'media')]
#[ORM\HasLifecycleCallbacks]
class Media
{
    use Timestampable;

    #[ORM\Id, ORM\Column(type: 'integer'), ORM\GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $uuid;

    #[ORM\Column(name: 'filename', type: 'string', length: 255, nullable: false)]
    private string $filename;

    #[ORM\Column(name: 'original_filename', type: 'string', length: 255, nullable: false)]
    private string $originalFilename;

    #[ORM\Column(name: 'file_path', type: 'string', length: 500, nullable: false)]
    private string $filePath;

    /**
     * The untouched upload, when the working master is no longer it.
     *
     * Uploads are downscaled and auto-oriented in place, which re-encodes the
     * pixels and drops EXIF — so for anything larger than the size cap the
     * bytes the photographer produced were gone the moment they arrived. The
     * original is now kept beside it and named here.
     *
     * Null means the master *is* the original: nothing rewrote it, so a second
     * copy would cost storage and prove nothing.
     */
    #[ORM\Column(name: 'original_path', type: 'string', length: 500, nullable: true)]
    private ?string $originalPath = null;

    #[ORM\Column(name: 'mime_type', type: 'string', length: 100, nullable: false)]
    private string $mimeType;

    #[ORM\Column(name: 'file_size', type: 'bigint', nullable: false)]
    private int $fileSize;

    #[ORM\Column(name: 'width', type: 'integer', nullable: true)]
    private ?int $width = null;

    #[ORM\Column(name: 'height', type: 'integer', nullable: true)]
    private ?int $height = null;

    #[ORM\Column(name: 'metadata', type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\ManyToOne(targetEntity: UploaderInterface::class)]
    #[ORM\JoinColumn(name: 'uploaded_by', referencedColumnName: 'id', nullable: true)]
    private ?UploaderInterface $uploadedBy = null;

    #[ORM\Column(name: 'title', type: 'string', length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(name: 'alt_text', type: 'string', length: 255, nullable: true)]
    private ?string $altText = null;

    #[ORM\Column(name: 'caption', type: 'text', nullable: true)]
    private ?string $caption = null;

    #[ORM\Column(name: 'is_public', type: 'boolean', nullable: false)]
    private bool $isPublic = true;

    #[ORM\Column(name: 'focus', type: 'string', length: 20, nullable: true)]
    private ?string $focus = null;

    #[ORM\Column(name: 'is_favorite', type: 'boolean', nullable: false)]
    private bool $isFavorite = false;

    /** Front-page curation, Koken-style: a flag plus an explicit position. */
    #[ORM\Column(name: 'is_featured', type: 'boolean', nullable: false, options: ['default' => false])]
    private bool $isFeatured = false;

    #[ORM\Column(name: 'featured_order', type: 'integer', nullable: true)]
    private ?int $featuredOrder = null;

    #[ORM\Column(name: 'is_generated', type: 'boolean', nullable: false)]
    private bool $isGenerated = false;

    #[ORM\Column(name: 'thumbhash', type: 'text', nullable: true)]
    private ?string $thumbhash = null;

    #[ORM\Column(name: 'phash', type: 'string', length: 64, nullable: true)]
    private ?string $phash = null;

    #[ORM\Column(name: 'video_cover', type: 'binary', length: 1048576, nullable: true)]
    private ?string $videoCover = null;

    #[ORM\Column(name: 'rating', type: 'integer', nullable: true)]
    private ?int $rating = null;

    /** sha1 of the stored master file (after downscale/orientation rewrites) */
    #[ORM\Column(name: 'checksum', type: 'string', length: 40, nullable: true)]
    private ?string $checksum = null;

    /** sha1 of the raw uploaded bytes, before any in-place processing */
    #[ORM\Column(name: 'original_checksum', type: 'string', length: 40, nullable: true)]
    private ?string $originalChecksum = null;

    public function __construct()
    {
        $this->uuid = Uuid::uuid4();
    }

    /**
     * Build a detached Media from a raw `media` table row (snake_case columns).
     *
     * For read-only listing paths that fetch column arrays via DBAL and
     * deliberately skip the heavy `video_cover` blob — the video cover, when
     * needed, is attached separately with setVideoCover(). The returned instance
     * is NOT managed by the EntityManager: it is a value carrier for presenters,
     * never persist or flush it.
     *
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $media = new self();

        $media->id               = (int) $row['id'];
        $media->uuid             = Uuid::fromString((string) $row['uuid']);
        $media->filename         = (string) $row['filename'];
        $media->originalFilename = (string) $row['original_filename'];
        $media->filePath         = (string) $row['file_path'];
        $media->originalPath     = isset($row['original_path']) ? (string) $row['original_path'] : null;
        $media->mimeType         = (string) $row['mime_type'];
        $media->fileSize         = (int) $row['file_size'];
        $media->width            = isset($row['width']) ? (int) $row['width'] : null;
        $media->height           = isset($row['height']) ? (int) $row['height'] : null;
        $media->metadata         = self::decodeJsonColumn($row['metadata'] ?? null);
        $media->title            = isset($row['title']) ? (string) $row['title'] : null;
        $media->altText          = isset($row['alt_text']) ? (string) $row['alt_text'] : null;
        $media->caption          = isset($row['caption']) ? (string) $row['caption'] : null;
        $media->focus            = isset($row['focus']) ? (string) $row['focus'] : null;
        $media->isPublic         = (bool) $row['is_public'];
        $media->isFavorite       = (bool) $row['is_favorite'];
        $media->isFeatured       = (bool) ($row['is_featured'] ?? false);
        $media->featuredOrder    = isset($row['featured_order']) ? (int) $row['featured_order'] : null;
        $media->thumbhash        = isset($row['thumbhash']) ? (string) $row['thumbhash'] : null;
        $media->rating           = isset($row['rating']) ? (int) $row['rating'] : null;
        $media->createdAt        = new \DateTimeImmutable((string) $row['created_at']);
        $media->updatedAt        = new \DateTimeImmutable((string) $row['updated_at']);

        return $media;
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>|null
     */
    private static function decodeJsonColumn(mixed $raw): ?array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUuid(): UuidInterface
    {
        return $this->uuid;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): self
    {
        $this->originalFilename = $originalFilename;
        return $this;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /** The true original: the preserved copy, or the master when untouched. */
    public function getOriginalPath(): string
    {
        return $this->originalPath ?? $this->filePath;
    }

    /** Null when the master was never rewritten and so is itself the original. */
    public function getPreservedOriginalPath(): ?string
    {
        return $this->originalPath;
    }

    public function setOriginalPath(?string $originalPath): self
    {
        $this->originalPath = $originalPath;

        return $this;
    }

    public function setFilePath(string $filePath): self
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): self
    {
        $this->fileSize = $fileSize;
        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(?int $width): self
    {
        $this->width = $width;
        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): self
    {
        $this->height = $height;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getUploadedBy(): ?UploaderInterface
    {
        return $this->uploadedBy;
    }

    public function setUploadedBy(?UploaderInterface $uploadedBy): self
    {
        $this->uploadedBy = $uploadedBy;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getAltText(): ?string
    {
        return $this->altText;
    }

    public function setAltText(?string $altText): self
    {
        $this->altText = $altText;
        return $this;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(?string $caption): self
    {
        $this->caption = $caption;
        return $this;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): self
    {
        $this->isPublic = $isPublic;
        return $this;
    }

    public function getFocus(): ?string
    {
        return $this->focus;
    }

    public function setFocus(?string $focus): self
    {
        $this->focus = $focus;
        return $this;
    }

    public function isFavorite(): bool
    {
        return $this->isFavorite;
    }

    public function setIsFavorite(bool $isFavorite): self
    {
        $this->isFavorite = $isFavorite;
        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): self
    {
        $this->isFeatured = $isFeatured;
        if (!$isFeatured) {
            $this->featuredOrder = null;
        }
        return $this;
    }

    public function getFeaturedOrder(): ?int
    {
        return $this->featuredOrder;
    }

    public function setFeaturedOrder(?int $featuredOrder): self
    {
        $this->featuredOrder = $featuredOrder;
        return $this;
    }

    public function isGenerated(): bool
    {
        return $this->isGenerated;
    }

    public function setIsGenerated(bool $isGenerated): self
    {
        $this->isGenerated = $isGenerated;
        return $this;
    }

    public function getThumbhash(): ?string
    {
        return $this->thumbhash;
    }

    public function setThumbhash(?string $thumbhash): self
    {
        $this->thumbhash = $thumbhash;
        return $this;
    }

    public function getPhash(): ?string
    {
        return $this->phash;
    }

    public function setPhash(?string $phash): self
    {
        $this->phash = $phash;
        return $this;
    }

    public function getVideoCover(): ?string
    {
        return $this->videoCover;
    }

    public function setVideoCover(?string $videoCover): self
    {
        $this->videoCover = $videoCover;
        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(?int $rating): self
    {
        $this->rating = $rating !== null ? max(1, min(5, $rating)) : null;
        return $this;
    }

    public function getChecksum(): ?string
    {
        return $this->checksum;
    }

    public function setChecksum(?string $checksum): self
    {
        $this->checksum = $checksum;
        return $this;
    }

    public function getOriginalChecksum(): ?string
    {
        return $this->originalChecksum;
    }

    public function setOriginalChecksum(?string $originalChecksum): self
    {
        $this->originalChecksum = $originalChecksum;
        return $this;
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mimeType, 'video/');
    }

    public function getUrl(): string
    {
        return '/uploads/tus/' . $this->filename;
    }

    public function getThumbnailUrl(?int $width = 300, ?int $height = 300): string
    {
        if ($this->isVideo() && $this->videoCover !== null) {
            return 'data:image/jpeg;base64,' . base64_encode($this->videoCover);
        }
        return $this->getUrl();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'original_filename' => $this->originalFilename,
            'file_path' => $this->filePath,
            'original_path' => $this->originalPath,
            'mime_type' => $this->mimeType,
            'file_size' => $this->fileSize,
            'width' => $this->width,
            'height' => $this->height,
            'metadata' => $this->metadata,
            'title' => $this->title,
            'alt_text' => $this->altText,
            'caption' => $this->caption,
            'is_public' => $this->isPublic,
            'focus' => $this->focus,
            'is_favorite' => $this->isFavorite,
            'is_featured' => $this->isFeatured,
            'featured_order' => $this->featuredOrder,
            'thumbhash' => $this->thumbhash,
            'video_cover_base64' => $this->videoCover ? base64_encode($this->videoCover) : null,
            'is_image' => $this->isImage(),
            'url' => $this->getUrl(),
            'thumbnail_url' => $this->getThumbnailUrl(),
            'created_at' => $this->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $this->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
