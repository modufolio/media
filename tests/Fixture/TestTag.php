<?php

declare(strict_types = 1);

namespace Modufolio\Media\Tests\Fixture;

use Doctrine\ORM\Mapping as ORM;

/**
 * A minimal tag entity for MediaRepository::useTagEntity(): the package only
 * ever reads id, slug and name from whatever class the app registers.
 */
#[ORM\Entity]
#[ORM\Table(name: 'tags')]
class TestTag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name = '';

    #[ORM\Column(length: 120)]
    private string $slug = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }
}
