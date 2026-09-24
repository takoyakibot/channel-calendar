<?php

namespace App\Services;

/** A post to X failed; the code is the HTTP status when one was received. */
class XPosterException extends \RuntimeException
{
    public function isRateLimited(): bool
    {
        return $this->getCode() === 429;
    }

    /** 402: the pay-per-use credits are used up — nothing else will post either. */
    public function isOutOfCredits(): bool
    {
        return $this->getCode() === 402;
    }

    /** Failures that affect every post, so the run should stop and retry later. */
    public function affectsWholeRun(): bool
    {
        return $this->isRateLimited() || $this->isOutOfCredits();
    }
}
