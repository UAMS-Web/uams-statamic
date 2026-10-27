<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

/**
 * Stand-in site object exposing a public variable read dynamically by
 * GetSite::index() via $site->{$var} (e.g. name, url, locale).
 */
class GetSiteTagSiteWithName
{
    public function __construct(public string $name) {}
}
