<?php

declare(strict_types = 1);

namespace Modufolio\Media\Entity;

use Modufolio\Media\Entity\Traits\Timestampable;
use Modufolio\Media\Repository\AlbumMediaRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: AlbumMediaRepository::class)]
#[ORM\Table(name: 'album_media')]
#[ORM\UniqueConstraint(name: 'UNIQ_ALBUM_MEDIA', columns: ['album_id', 'media_id'])]
#[ORM\Index(name: 'IDX_ALBUM_MEDIA_POS', columns: ['album_id', 'position'])]
#[ORM\HasLifecycleCallbacks]
class AlbumMedia
{
    use Timestampable;

    #[ORM\Id, ORM\Column(type: 'integer'), ORM\GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $uuid;

    #[ORM\ManyToOne(targetEntity: Album::class, inversedBy: 'albumMedia')]
    #[ORM\JoinColumn(name: 'album_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Album $album;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(name: 'media_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Media $media;

    #[ORM\Column(name: 'position', type: 'integer', nullable: false)]
    private int $position = 0;

    public function __construct()
    {
        $this->uuid = Uuid::uuid4();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUuid(): UuidInterface
    {
        return $this->uuid;
    }

    public function getAlbum(): Album
    {
        return $this->album;
    }

    public function setAlbum(Album $album): self
    {
        $this->album = $album;
        return $this;
    }

    public function getMedia(): Media
    {
        return $this->media;
    }

    public function setMedia(Media $media): self
    {
        $this->media = $media;
        return $this;
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
}
