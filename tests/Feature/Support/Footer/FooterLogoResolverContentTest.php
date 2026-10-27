<?php

declare(strict_types=1);

use App\Support\Footer\FooterLogoResolver;
use Statamic\Facades\GlobalSet;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Integration coverage for the footer-logo owner resolution (UAMS-Web/uams-statamic-archive#46) against the
 * real multisite hierarchy in the `content/` submodule. Self-skips without it,
 * like the other content-gated tests, and runs in CI.
 *
 * @covers \App\Support\Footer\FooterLogoResolver
 *
 * @command  php artisan test --compact tests/Feature/Support/Footer/FooterLogoResolverContentTest.php
 */

/**
 * Skip unless the multisite-settings global set is present (content submodule).
 */
function footerLogoRequireGlobals(): void
{
    if (! GlobalSet::find('uams_multisite_settings')) {
        ContentSubmodule::reportMissing('uams_multisite_settings global');
    }
}

it('resolves a root site that owns its logo to itself', function (): void {
    footerLogoRequireGlobals();

    expect((new FooterLogoResolver)->resolveOwnerHandle('default'))->toBe('default');
});

it('resolves a major-entity site that owns its logo to itself', function (): void {
    footerLogoRequireGlobals();

    expect((new FooterLogoResolver)->resolveOwnerHandle('health'))->toBe('health');
});

it('resolves a descendant without its own logo to the closest ancestor that has one', function (): void {
    footerLogoRequireGlobals();

    // medicine_otolaryngology (sub_entity, no own logo) → origin medicine (owns one).
    expect((new FooterLogoResolver)->resolveOwnerHandle('medicine_otolaryngology'))->toBe('medicine');
});
