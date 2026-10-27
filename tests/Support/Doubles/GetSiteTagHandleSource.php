<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

/**
 * Object whose handle() method returns a string key, exercising the
 * GetSite::normalizeSiteHandle() object-with-handle-method branch.
 */
class GetSiteTagHandleSource
{
    public function handle(): string
    {
        return 'default';
    }
}
