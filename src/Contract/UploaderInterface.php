<?php

declare(strict_types = 1);

namespace Modufolio\Media\Contract;

/**
 * The account an upload or album is attributed to.
 *
 * The package never names the application's user entity: entities target this
 * interface and the application maps it to its own class with Doctrine's
 * ResolveTargetEntityListener in config/doctrine.php:
 *
 *     $orm->addSubscriber(new ResolveTargetEntityListener())  // configured with
 *         UploaderInterface::class => \App\Entity\User::class
 *
 * Only what the package itself reads is declared here; the application's
 * presenters receive the concrete user and may use its full surface.
 */
interface UploaderInterface
{
    public function getId(): ?int;

    public function getName(): string;
}
