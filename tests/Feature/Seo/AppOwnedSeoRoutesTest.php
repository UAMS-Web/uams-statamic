<?php

declare(strict_types=1);

use App\Http\Controllers\Seo\RobotsController;
use App\Services\ComputedFields\Registration\SitemapInclusionComputedsRegistrar;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Assert;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Facades\YAML;
use Statamic\Globals\Variables;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

use function Pest\Laravel\get;

/**
 * Feature tests for the app-owned SEO routes that replace studio1902/statamic-peak-seo.
 *
 * `/robots.txt` (App\Http\Controllers\Seo\RobotsController) renders the `seo`
 * global's `robots` field and appends the `Sitemap:` pointer; `/sitemaps.xml`
 * (App\Http\Controllers\Seo\SitemapIndexController) is the multisite index
 * listing each site's mitydigital/sitemapamic-served `sitemap.xml`. The index
 * and the robots Sitemap-line are hermetic (they read only the sites config);
 * the stored-rules and per-site sitemap cases need the content submodule and
 * skip gracefully without it (UAMS-Web/uams-statamic-archive#856).
 */
uses(TestCase::class);

/**
 * Build an unsaved `pages` entry carrying the given data for computed-field assertions.
 *
 * @param  array<string, mixed>  $data
 */
function makePagesEntry(array $data): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('pages');
    $entry->data($data);

    return $entry;
}

/**
 * The pure body-composition seam is fully hermetic — assert both branches so
 * mutations to the rules/Sitemap-line logic are killed without content.
 */
it('composes the robots body with rules then a trailing Sitemap directive', function (): void {
    $body = RobotsController::buildBody(
        "User-agent: *\nDisallow:",
        'https://example.test/sitemaps.xml'
    );

    expect($body)->toBe("User-agent: *\nDisallow:\n\nSitemap: https://example.test/sitemaps.xml\n");
});

it('composes a robots body of only the Sitemap directive when no rules are stored', function (): void {
    $body = RobotsController::buildBody('   ', 'https://example.test/sitemaps.xml');

    expect($body)->toBe("Sitemap: https://example.test/sitemaps.xml\n");
});

it('serves /robots.txt as text/plain with the sitemap index pointer', function (): void {
    $response = get('/robots.txt');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/plain; charset=utf-8');

    // The Sitemap directive always points at the app-owned multisite index.
    /** @var Statamic\Sites\Site $defaultSite */
    $defaultSite = Site::default();
    /** @var string $defaultSiteUrl */
    $defaultSiteUrl = $defaultSite->absoluteUrl();
    $expectedSitemap = rtrim($defaultSiteUrl, '/').'/sitemaps.xml';
    $response->assertSee('Sitemap: '.$expectedSitemap, false);
});

it('renders the stored crawler rules on /robots.txt when the seo global is present', function (): void {
    $seo = GlobalSet::findByHandle('seo');
    /** @var Variables|null $seoVars */
    $seoVars = $seo?->inDefaultSite();
    $robots = $seoVars?->get('robots');

    if (blank($robots)) {
        ContentSubmodule::reportMissing('SEO global `robots` value');
    }

    $response = get('/robots.txt');

    $response->assertOk();
    $response->assertSee('User-agent: *', false);

    // The stored rules precede the appended Sitemap directive.
    $body = (string) $response->getContent();
    expect(strpos($body, 'User-agent: *'))->toBeLessThan((int) strpos($body, 'Sitemap:'));
});

it('serves /sitemaps.xml as a well-formed multisite sitemap index', function (): void {
    $response = get('/sitemaps.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml');

    $body = (string) $response->getContent();

    // Parses as XML and is a <sitemapindex> in the sitemaps.org namespace.
    /** @var SimpleXMLElement $xml */
    $xml = simplexml_load_string($body);
    expect($xml)->not->toBeFalse();
    expect($xml->getName())->toBe('sitemapindex');
    expect($xml->getDocNamespaces())->toContain('http://www.sitemaps.org/schemas/sitemap/0.9');

    // One <sitemap> per configured site — including the default (root) site and
    // a nested non-default site — each pointing at its own sitemap.xml.
    $locs = [];
    foreach ($xml->sitemap as $sitemap) {
        $locs[] = (string) $sitemap->loc;
    }

    /** @var Illuminate\Support\Collection<string, Statamic\Sites\Site> $allSites */
    $allSites = Site::all();
    expect(count($locs))->toBe($allSites->count());

    /** @var Statamic\Sites\Site $defaultSite */
    $defaultSite = Site::default();
    /** @var string $defaultSiteUrl */
    $defaultSiteUrl = $defaultSite->absoluteUrl();
    $defaultSitemap = rtrim($defaultSiteUrl, '/').'/sitemap.xml';
    expect($locs)->toContain($defaultSitemap);

    // Every loc is an absolute URL ending in /sitemap.xml.
    foreach ($locs as $loc) {
        expect($loc)->toEndWith('/sitemap.xml');
        expect($loc)->toStartWith('http');
    }
});

it('serves a per-site sitemap.xml via mitydigital/sitemapamic', function (): void {
    if (! Collection::findByHandle('pages')) {
        ContentSubmodule::reportMissing('the `pages` collection');
    }

    $response = get('/sitemap.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml');

    // Sitemapamic emits a <urlset> even when the site has no published entries.
    expect($response->getContent())->toContain('<urlset');
});

it('serves a nested non-default site sitemap.xml (multisite AC)', function (): void {
    if (! Collection::findByHandle('pages')) {
        ContentSubmodule::reportMissing('the `pages` collection');
    }

    // Pick a non-default site and derive its site-relative sitemap path — this is
    // the AC-mandated "one non-default site" case, and a nested path proves the
    // per-site route resolves at variable depth (e.g. /health/ar-impact/).
    /** @var Illuminate\Support\Collection<string, Statamic\Sites\Site> $allSites */
    $allSites = Site::all();
    /** @var Statamic\Sites\Site $defaultSite */
    $defaultSite = Site::default();
    /** @var Statamic\Sites\Site $nonDefault */
    $nonDefault = $allSites
        ->reject(fn ($site) => $site->handle() === $defaultSite->handle())
        ->first();
    /** @var string $nonDefaultUrl */
    $nonDefaultUrl = $nonDefault->absoluteUrl();
    $pathComponent = parse_url($nonDefaultUrl, PHP_URL_PATH);
    $path = rtrim(is_string($pathComponent) ? $pathComponent : '/', '/').'/sitemap.xml';

    $response = get($path);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml');
    expect($response->getContent())->toContain('<urlset');
});

it('excludes private and password-protected pages from the sitemap (security)', function (): void {
    if (! Collection::findByHandle('pages')) {
        ContentSubmodule::reportMissing('`pages` collection computeds');
    }

    // The computed Sitemapamic consults (via config mappings.include) must drop
    // non-public pages: a `private` entry is published (so Sitemapamic's own
    // published() gate passes it) but 404s for anonymous visitors, and a
    // password-protected entry is gated — neither URL may be advertised.
    $handle = SitemapInclusionComputedsRegistrar::INCLUDE_HANDLE;

    $private = makePagesEntry(['status_and_visibility' => 'private']);
    $protected = makePagesEntry(['status_and_visibility' => 'published', 'password_protected' => true]);
    $public = makePagesEntry(['status_and_visibility' => 'published']);

    expect($private->getComputed($handle))->toBeFalse();
    expect($protected->getComputed($handle))->toBeFalse();
    expect($public->getComputed($handle))->toBeTrue();
});

it('honors the per-entry include_in_xml_sitemap editor toggle', function (): void {
    if (! Collection::findByHandle('pages')) {
        ContentSubmodule::reportMissing('`pages` collection computeds');
    }

    // The editor toggle (UAMS-Web/uams-statamic-archive#1345) feeds the same computed via its distinct handle:
    // a public page opted out is dropped; a page with the toggle explicitly on,
    // or with no toggle value at all (absent → included), is kept.
    $includeHandle = SitemapInclusionComputedsRegistrar::INCLUDE_HANDLE;
    $toggleHandle = SitemapInclusionComputedsRegistrar::EDITOR_TOGGLE_HANDLE;

    $optedOut = makePagesEntry(['status_and_visibility' => 'published', $toggleHandle => false]);
    $optedIn = makePagesEntry(['status_and_visibility' => 'published', $toggleHandle => true]);
    $default = makePagesEntry(['status_and_visibility' => 'published']);

    expect($optedOut->getComputed($includeHandle))->toBeFalse();
    expect($optedIn->getComputed($includeHandle))->toBeTrue();
    expect($default->getComputed($includeHandle))->toBeTrue();
});

it('lets the automatic gate win over an editor include=true (a private page opted in stays out)', function (): void {
    if (! Collection::findByHandle('pages')) {
        ContentSubmodule::reportMissing('`pages` collection computeds');
    }

    // An editor cannot advertise a non-public URL: a `private` page with the
    // sitemap toggle explicitly on must still be excluded by the computed.
    $includeHandle = SitemapInclusionComputedsRegistrar::INCLUDE_HANDLE;
    $toggleHandle = SitemapInclusionComputedsRegistrar::EDITOR_TOGGLE_HANDLE;

    $privateOptedIn = makePagesEntry(['status_and_visibility' => 'private', $toggleHandle => true]);

    expect($privateOptedIn->getComputed($includeHandle))->toBeFalse();
});

it('leaves the seo global blueprint app-owned with no Peak SEO imports (#1117 AC)', function (): void {
    /** @var array{tabs?: array<string, array{sections?: array<int, array{fields?: array<int, array<string, mixed>>}>}>} $blueprint */
    $blueprint = YAML::parse(File::get(resource_path('blueprints/globals/seo.yaml')));

    // The blueprint keeps the app-owned `robots` field the robots.txt route reads.
    $handles = collect($blueprint['tabs'] ?? [])
        ->flatMap(fn ($tab) => $tab['sections'] ?? [])
        ->flatMap(fn ($section) => $section['fields'] ?? [])
        ->pluck('handle')
        ->all();
    expect($handles)->toContain('robots');

    // No `statamic-peak-seo::` fieldset import survives anywhere in the blueprint.
    expect(File::get(resource_path('blueprints/globals/seo.yaml')))
        ->not->toContain('statamic-peak-seo::');
});

it('imports the sitemap fieldset into the pages blueprints and defines the toggle (#1345)', function (): void {
    // Hermetic guard on the editor-facing wiring: the feature tests above prove
    // the computed reads the toggle value, but only this asserts the toggle is
    // actually surfaced to editors — the fieldset is imported into both pages
    // blueprints and defines the expected field. Without it, deleting the import
    // or the fieldset would leave every other test green.
    foreach (['basic_page', 'landing_page'] as $blueprint) {
        /** @var array{tabs?: array<string, array{sections?: array<int, array{fields?: array<int, array<string, mixed>>}>}>} $parsed */
        $parsed = YAML::parse(File::get(resource_path("blueprints/collections/pages/{$blueprint}.yaml")));

        $imports = collect($parsed['tabs'] ?? [])
            ->flatMap(fn ($tab) => $tab['sections'] ?? [])
            ->flatMap(fn ($section) => $section['fields'] ?? [])
            ->pluck('import')
            ->filter()
            ->all();

        expect($imports)->toContain('uamsweb_entry_sitemap');
    }

    /** @var array{fields: array<int, array<string, mixed>>} $fieldset */
    $fieldset = YAML::parse(File::get(resource_path('fieldsets/uamsweb_entry_sitemap.yaml')));
    /** @var array{field: array<string, mixed>} $field */
    $field = collect($fieldset['fields'])
        ->firstWhere('handle', SitemapInclusionComputedsRegistrar::EDITOR_TOGGLE_HANDLE);

    // The toggle defaults to included and is per-site overridable; a `false`
    // default would silently drop untouched pages, and a non-localizable field
    // would deny per-localization control.
    expect($field)->not->toBeNull();
    expect($field['field']['type'])->toBe('toggle');
    expect($field['field']['default'])->toBeTrue();
    expect($field['field']['localizable'])->toBeTrue();
});
