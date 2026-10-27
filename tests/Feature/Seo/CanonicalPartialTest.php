<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

use function Pest\Laravel\get;
use function Pest\Laravel\withoutVite;

uses(TestCase::class);

/**
 * Coverage for the canonical URL head partial (UAMS-Web/uams-statamic-archive#189, Option C). A localization
 * with `canonical_from_origin` on points at its origin; every other case is
 * self-canonical. Default off = self-canonical, matching the project's norm
 * (localizations are customized, not duplicates).
 *
 * @command  php artisan test --compact tests/Feature/Seo/CanonicalPartialTest.php
 */

/**
 * Render the canonical partial in isolation with the given Antlers context.
 *
 * @param  array<string, mixed>  $context
 */
function renderCanonical(array $context): string
{
    return trim((string) Antlers::parse(
        (string) file_get_contents(resource_path('views/layout/header/_canonical.antlers.html')),
        $context,
        true
    ));
}

it('points the canonical at the syndication URL for a syndicated resource', function (): void {
    // A syndicated clinical resource's canonical is its external source (UAMS-Web/uams-statamic-archive#1099),
    // taking precedence over the origin/self logic below.
    $html = renderCanonical([
        'syndication_query' => true,
        'syndication_url' => 'https://www.cancer.gov/types/breast',
        'origin' => ['permalink' => 'https://x.test/origin'],
        'canonical_from_origin' => true,
        'permalink' => 'https://x.test/self',
    ]);

    expect($html)->toBe('<link rel="canonical" href="https://www.cancer.gov/types/breast">');
});

it('stays self-canonical when syndication is on but no URL is set', function (): void {
    // syndication_query true but the URL was cleared → fall through to self.
    $html = renderCanonical([
        'syndication_query' => true,
        'syndication_url' => null,
        'permalink' => 'https://x.test/self',
    ]);

    expect($html)->toBe('<link rel="canonical" href="https://x.test/self">');
});

it('ignores a syndication URL when the syndication toggle is off', function (): void {
    // A stale URL with the toggle off must not hijack the canonical.
    $html = renderCanonical([
        'syndication_query' => false,
        'syndication_url' => 'https://www.cancer.gov/types/breast',
        'permalink' => 'https://x.test/self',
    ]);

    expect($html)->toBe('<link rel="canonical" href="https://x.test/self">');
});

it('entity-escapes an editor-entered syndication URL so it cannot break out of the href', function (): void {
    $html = renderCanonical([
        'syndication_query' => true,
        'syndication_url' => 'https://evil.test/"><script>alert(1)</script>',
        'permalink' => 'https://x.test/self',
    ]);

    expect($html)->not->toContain('<script>');
    expect($html)->toContain('&quot;&gt;&lt;script&gt;');
});

it('points the canonical at the origin when a localization opts in', function (): void {
    $html = renderCanonical([
        'origin' => ['permalink' => 'https://x.test/origin'],
        'canonical_from_origin' => true,
        'permalink' => 'https://x.test/self',
    ]);

    expect($html)->toBe('<link rel="canonical" href="https://x.test/origin">');
});

it('is self-canonical for a localization that does not opt in', function (): void {
    $html = renderCanonical([
        'origin' => ['permalink' => 'https://x.test/origin'],
        'canonical_from_origin' => false,
        'permalink' => 'https://x.test/self',
    ]);

    expect($html)->toBe('<link rel="canonical" href="https://x.test/self">');
});

it('is self-canonical for an origin entry (no origin)', function (): void {
    $html = renderCanonical(['permalink' => 'https://x.test/self']);

    expect($html)->toBe('<link rel="canonical" href="https://x.test/self">');
});

it('falls back to the current URL on a non-entry route', function (): void {
    $html = renderCanonical(['current_url' => 'https://x.test/listing']);

    expect($html)->toBe('<link rel="canonical" href="https://x.test/listing">');
});

it('ignores the origin opt-in when there is no origin', function (): void {
    // canonical_from_origin set true but no origin present → self-canonical.
    $html = renderCanonical([
        'canonical_from_origin' => true,
        'permalink' => 'https://x.test/self',
    ]);

    expect($html)->toBe('<link rel="canonical" href="https://x.test/self">');
});

it('adds the canonical_from_origin field to every localized collection that carries SEO fields', function (string $blueprint): void {
    /** @var Statamic\Fields\Blueprint $found */
    $found = Blueprint::find($blueprint);

    expect($found)->not->toBeNull()
        ->and($found->hasField('canonical_from_origin'))->toBeTrue();
})->with([
    // The toggle is meaningless unless it is on every collection where a
    // localized duplicate can occur — the same set that carries the sibling
    // SEO robots fields (pages + posts), not just `pages` (UAMS-Web/uams-statamic-archive#189 / review fix).
    'collections.pages.basic_page',
    'collections.pages.landing_page',
    'collections.posts.post',
    'collections.posts.news_release',
    'collections.posts.audio',
    'collections.posts.video',
]);

it('emits a self-canonical link in the rendered layout head', function (): void {
    if (! Collection::find('pages')) {
        ContentSubmodule::reportMissing('pages collection');
    }

    withoutVite();
    $html = get('/about')->assertOk()->getContent();

    // `about` is an origin entry → self-canonical to its own URL.
    expect($html)->toContain('<link rel="canonical" href="')
        ->toContain('/about">');
});
