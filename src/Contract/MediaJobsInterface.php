<?php

declare(strict_types = 1);

namespace Modufolio\Media\Contract;

/**
 * Image-job bookkeeping the media domain has to poke when a master goes away.
 *
 * Deleting a media record must also retire any processing jobs recorded for
 * its file — but the job store belongs to the application's image pipeline,
 * not to this package, so MediaModel talks to it through this one method.
 */
interface MediaJobsInterface
{
    /** @return int rows deleted */
    public function deleteByOriginalFilename(string $filename): int;
}
