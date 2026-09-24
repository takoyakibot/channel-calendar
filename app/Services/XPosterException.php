<?php

namespace App\Services;

/** A post to X failed; the code is the HTTP status when one was received. */
class XPosterException extends \RuntimeException
{
    public function isRateLimited(): bool
    {
        return $this->getCode() === 429;
    }
}
