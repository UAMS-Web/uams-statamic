<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Site;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for `{{ people_lens_url }}` (UAMS-Web/uams-statamic-archive#571) — the tag that resolves a canonical
 * `people` slug to the URL it renders at through a named lens on the CURRENT site.
 *
 * The clinical directory card uses it so `/provider/{slug}` is defined in exactly
 * one place (`config/people_lenses.php`, which also drives the route registration in
 * `routes/web.php`) rather than being hard-coded in a template where a config change
 * would silently break it.
 *
 * @command php artisan test --compact tests/Feature/Tags/PeopleLensUrlTagTest.php
 */
afterEach(function (): void {
    Site::setCurrent('default');
});

/**
 * Render the tag with the given Antlers scope.
 *
 * @param  array<string, mixed>  $scope
 */
function renderLensUrl(string $antlers, array $scope = []): string
{
    return \trim((string) Antlers::parse($antlers, $scope));
}

it('builds the clinical lens URL under the health site prefix', function (): void {
    Site::setCurrent('health');

    expect(renderLensUrl("{{ people_lens_url lens='provider' slug='leonard-mccoy' }}"))
        ->toBe('/health/provider/leonard-mccoy');
});

it('reads the slug from the surrounding scope when no slug param is given', function (): void {
    // This is how the directory card loop calls it — the `{{ providers }}` scope
    // already carries the entry's slug.
    Site::setCurrent('health');

    expect(renderLensUrl("{{ people_lens_url lens='provider' }}", ['slug' => 'christine-chapel']))
        ->toBe('/health/provider/christine-chapel');
});

it('returns an empty string on a site the lens is not active on', function (): void {
    // The clinical lens is registered for `health` only. On the canonical site the
    // route does not exist, so the tag must yield nothing and let the caller fall
    // back to the person's own permalink rather than emit a URL that would 404.
    Site::setCurrent('default');

    expect(renderLensUrl("{{ people_lens_url lens='provider' slug='leonard-mccoy' }}"))
        ->toBe('');
});

it('resolves an every-site lens at the canonical root prefix', function (): void {
    // The academic lens is registered on `['*']`, so it resolves wherever it is asked.
    Site::setCurrent('default');

    expect(renderLensUrl("{{ people_lens_url lens='academic' slug='spock' }}"))
        ->toBe('/faculty/spock');
});

it('returns an empty string for an unknown lens', function (): void {
    Site::setCurrent('health');

    expect(renderLensUrl("{{ people_lens_url lens='klingon' slug='leonard-mccoy' }}"))
        ->toBe('');
});

it('returns an empty string with no slug to build from', function (): void {
    Site::setCurrent('health');

    expect(renderLensUrl("{{ people_lens_url lens='provider' }}"))
        ->toBe('');
});

it('percent-encodes a slug so it cannot break out of the path', function (): void {
    // A CP-authored slug is already URL-safe; the WordPress importer writes raw YAML
    // that bypasses that validation, so the tag encodes defensively.
    Site::setCurrent('health');

    expect(renderLensUrl("{{ people_lens_url lens='provider' slug='mccoy/../..' }}"))
        ->toBe('/health/provider/mccoy%2F..%2F..');
});

it('tracks the configured route rather than a hard-coded path', function (): void {
    // The whole point of the tag: repoint the lens in config and the link follows.
    Site::setCurrent('health');

    config(['people_lenses.lenses.provider.route' => 'clinician/{slug}']);

    expect(renderLensUrl("{{ people_lens_url lens='provider' slug='leonard-mccoy' }}"))
        ->toBe('/health/clinician/leonard-mccoy');
});
