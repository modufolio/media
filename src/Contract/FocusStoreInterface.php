<?php

declare(strict_types = 1);

namespace Modufolio\Media\Contract;

use Modufolio\Media\Entity\Media;

/**
 * Where a media item's analysed features land when the panel saves them.
 *
 * Focal points and saliency features belong to the application's autofocus
 * pipeline (its entity, its clamping rules); the media domain only knows that
 * a partial update may carry a `features` payload and hands it over here.
 * Wire nothing and the payload is ignored.
 *
 * @see \Modufolio\Media\Model\MediaModel::updateFields()
 */
interface FocusStoreInterface
{
    /** @param array<string, mixed> $features */
    public function upsertFeatures(Media $media, array $features): void;
}
