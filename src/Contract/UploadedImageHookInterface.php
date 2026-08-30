<?php

declare(strict_types = 1);

namespace Modufolio\Media\Contract;

/**
 * A hook the application may run over every freshly stored image master —
 * pre-generating thumbnails, priming caches, whatever its image pipeline
 * wants. Failures are swallowed by the upload flow: a master that stored
 * correctly must not be lost to a variant step.
 */
interface UploadedImageHookInterface
{
    public function onImageStored(string $absolutePath): void;
}
