<?php

declare(strict_types = 1);

namespace Modufolio\Media\Tests\Fixture;

use Doctrine\ORM\Mapping as ORM;
use Modufolio\Media\Contract\UploaderInterface;

/**
 * The application's user, as far as this package is concerned: whatever the
 * ResolveTargetEntityListener maps UploaderInterface to. The tests map it to
 * this minimal entity, which doubles as proof the contract is sufficient —
 * if the package needed more from a user than the interface declares, this
 * fixture would fail to compile the mapping.
 */
#[ORM\Entity]
#[ORM\Table(name: 'users')]
class TestUploader implements UploaderInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }
}
