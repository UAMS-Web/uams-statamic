<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\TestCase;

uses(TestCase::class, RequiresContentSubmodule::class);

/**
 * Feature coverage for the clinical_resources index (UAMS-Web/uams-statamic-archive#576) and show (UAMS-Web/uams-statamic-archive#577)
 * templates.
 *
 * Cards and related-entity lists render from entry scope, so they are exercised
 * hermetically. The listing query and the show template's type/syndication
 * branching are asserted against template source, with the NCI-embed
 * verbatim-rendering requirement (UAMS-Web/uams-statamic-archive#799) proven directly through the raw modifier.
 *
 * @command  php artisan test --compact tests/Feature/ClinicalResourcesTemplatesTest.php
 */

/**
 * Render the clinical resource card partial with the given entry scope.
 *
 * @param  array<string, mixed>  $resource
 */
function renderResourceCard(array $resource): string
{
    return (string) Antlers::parse('{{ partial:clinical_resources/card }}', $resource);
}

/** Render the resource-type filter control with the given active filter type (UAMS-Web/uams-statamic-archive#1468). */
function renderResourceTypeFilter(string $filterType = ''): string
{
    return (string) Antlers::parse(
        '{{ partial:clinical_resources/type_filter }}',
        ['filter_type' => $filterType, 'current_url' => '/health/clinical-resources'],
    );
}

it('renders a clinical resource card with type label, image, and description', function (): void {
    $html = renderResourceCard([
        'title' => 'Managing Diabetes',
        'url' => '/health/clinical-resources/managing-diabetes',
        'blueprint' => 'article',
        'cover_image_query' => true,
        'featured_image_16_9' => [['url' => '/img/diabetes.jpg', 'alt' => 'Chart']],
        'cover_image_alt_text_override' => null,
        'alt' => 'Chart',
        'intro' => 'What every patient should know.',
    ]);

    expect($html)
        ->toContain('Article')
        ->toContain('Managing Diabetes')
        ->toContain('What every patient should know.')
        ->toContain('<img src="/img/diabetes.jpg"')
        ->toContain('href="/health/clinical-resources/managing-diabetes"');
});

it('labels a document resource and uses its description when there is no intro', function (): void {
    $html = renderResourceCard([
        'title' => 'Intake Forms',
        'url' => '/health/clinical-resources/intake-forms',
        'blueprint' => 'document',
        'cover_image_query' => false,
        'intro' => null,
        'documents_description' => 'Forms to complete before your visit.',
    ]);

    expect($html)
        ->toContain('Document')
        ->toContain('Forms to complete before your visit.');
    expect($html)->not->toContain('<img');
});

it('escapes the editor-controlled title in the card content and aria-label (no XSS breakout)', function (): void {
    $html = renderResourceCard([
        'title' => '<script>alert(1)</script>"quote',
        'url' => '/x',
        'blueprint' => 'article',
    ]);

    expect($html)
        // The h2 content is escaped (was rendered raw), and the quote is encoded in
        // both the content and the aria-label (no attribute breakout).
        ->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;')
        ->toContain('&quot;quote');
});

it('escapes the image alt (override or asset) in the card (no attribute XSS)', function (): void {
    $html = renderResourceCard([
        'title' => 'Managing Diabetes',
        'url' => '/x',
        'blueprint' => 'article',
        'cover_image_query' => true,
        'featured_image_16_9' => [['url' => '/img/x.jpg', 'alt' => '"><img src=x onerror=alert(1)>']],
    ]);

    expect($html)
        ->not->toContain('"><img src=x onerror=alert(1)>')
        ->toContain('&lt;img src=x onerror=alert(1)&gt;');
});

it('wires the resources listing query, type filter, empty state, and pagination', function (): void {
    $template = File::get(resource_path('views/clinical_resources/_listing.antlers.html'));

    expect($template)
        ->toContain('collection:clinical_resources')
        // The ?type= filter is applied via the scope (an empty inline blueprint:is
        // matches nothing in Statamic 6, so "All" would render empty) (UAMS-Web/uams-statamic-archive#1468),
        // pipe-composed with the publish-dependency listing exclusion (UAMS-Web/uams-statamic-archive#1593) so a
        // suppressed dependent never renders as a card here.
        ->toContain('query_scope="clinical_resource_type|clinical_resources_for_provider|exclude_suppressed_dependents"')
        ->toContain('get:type')
        // The on-page resource-type control drives the ?type= query (UAMS-Web/uams-statamic-archive#1468).
        ->toContain('partial:clinical_resources/type_filter')
        ->toContain('no_results')
        ->toContain('resources-empty')
        ->toContain('paginate:total_pages > 1');
});

it('wires the provider filter, its notice, and the way back out (#1692)', function (): void {
    $template = File::get(resource_path('views/clinical_resources/_listing.antlers.html'));

    expect($template)
        // The ?provider= filter reaches the scope, and the notice's name comes from
        // the SAME lookup the scope uses, so the two cannot disagree about whether
        // the archive is filtered and to whom.
        ->toContain('provider="{{ get:provider }}"')
        ->toContain('clinical_resource_filter:provider_name')
        ->toContain('resources-provider-filter')
        // The clear link clears BOTH filters, matching its label. An earlier version
        // kept ?type=, so "Show all clinical resources" landed the reader on a list
        // of every video.
        ->toContain('<a class="resources-provider-filter-clear" href="{{ $archive_url }}">Show all clinical resources</a>')
        // Filter hrefs are built from the archive root, not `current_url`, which on
        // page 2 is `/health/resources/page/2` and would strand the reader there.
        ->toContain('$archive_url = url ?? current_url')
        // Only a RESOLVED slug is propagated into the type tabs; an unknown one would
        // otherwise be advertised by every tab on an archive showing everything.
        ->toContain(':filter_provider="$filter_provider_active"')
        // The paginator emits a bare `&` between query params, invalid in an href.
        ->toContain('paginate:prev_page | entities')
        ->toContain('paginate:next_page | entities');
});

it('composes the empty state as one clause per filter combination (#1692)', function (): void {
    // Concatenating the two conditional fragments produced the ungrammatical
    // "No clinical resources found for this type for this provider."
    $template = File::get(resource_path('views/clinical_resources/_listing.antlers.html'));

    expect($template)
        ->toContain('No clinical resources of this type found for {{ $filter_provider_name | sanitize }}.')
        ->toContain('No clinical resources found for {{ $filter_provider_name | sanitize }}.')
        ->toContain('No clinical resources found for this type.');

    // Separate chain: one `->not` per Pest expectation chain.
    expect($template)->not->toContain('for this type{{ /if }}{{ if $filter_provider_name }} for this provider');
});

it('renders the resource-type control with an All tab and the five subtype tabs', function (): void {
    $html = renderResourceTypeFilter();

    expect($html)
        ->toContain('aria-label="Filter clinical resources by type"')
        ->toContain('href="/health/clinical-resources?type=article"')
        ->toContain('>Article</a>')
        ->toContain('href="/health/clinical-resources?type=audio"')
        ->toContain('>Audio</a>')
        ->toContain('href="/health/clinical-resources?type=document"')
        ->toContain('>Document</a>')
        ->toContain('href="/health/clinical-resources?type=infographic"')
        ->toContain('>Infographic</a>')
        ->toContain('href="/health/clinical-resources?type=video"')
        ->toContain('>Video</a>')
        ->toContain('>All</a>');
    // provider_spotlight is a Q&A format, not a browsable resource category.
    expect($html)->not->toContain('provider_spotlight');
});

it('marks only the All tab active when no type is selected', function (): void {
    $html = renderResourceTypeFilter('');

    expect($html)
        ->toContain('href="/health/clinical-resources" aria-current="page"')
        ->and(substr_count($html, 'aria-current="page"'))->toBe(1);
});

it('marks only the selected subtype tab active', function (): void {
    $html = renderResourceTypeFilter('video');

    expect($html)
        ->toContain('href="/health/clinical-resources?type=video" aria-current="page"')
        ->and(substr_count($html, 'aria-current="page"'))->toBe(1)
        // The All tab is no longer the active one.
        ->and($html)->not->toContain('href="/health/clinical-resources" aria-current="page"');
});

it('renders a syndicated NCI embed verbatim — markup and scripts intact', function (): void {
    // The NCI embed is operator-controlled `code` (set only by trusted CP
    // editors) and must render exactly as imported so the widget script runs;
    // UAMS-Web/uams-statamic-archive#799 forbids routing it through Bard or a sanitizer.
    $embed = '<div class="nci-widget" data-id="x"></div><script src="https://www.cancer.gov/w.js"></script>';

    $html = (string) Antlers::parse('{{ nci_embed | raw }}', ['nci_embed' => $embed]);

    expect($html)
        ->toContain('<div class="nci-widget" data-id="x"></div>')
        ->toContain('<script src="https://www.cancer.gov/w.js"></script>');
    expect($html)->not->toContain('&lt;script');
});

it('gates the verbatim NCI embed on the syndication flag in the show template', function (): void {
    $show = File::get(resource_path('views/clinical_resources/show.antlers.html'));

    expect($show)
        ->toContain('{{ if syndication_query && nci_embed }}')
        ->toContain('{{ nci_embed | raw }}')
        // Type-specific bodies and the related-entities section.
        ->toContain("blueprint == 'article'")
        ->toContain("blueprint == 'document'")
        ->toContain('partial:clinical_resources/segments')
        ->toContain('partial:clinical_resources/related');
});

it('renders a related-entity list with guarded links, and nothing when empty', function (): void {
    $withEntries = (string) Antlers::parse(
        '{{ partial:clinical_resources/related_list :entries="entries" heading="Related Providers" }}',
        ['entries' => [
            ['title' => 'Dr. Smith', 'url' => '/providers/smith'],
            ['title' => 'Dr. Jones', 'url' => null],
        ]],
    );

    expect($withEntries)
        ->toContain('Related Providers')
        ->toContain('<a href="/providers/smith">Dr. Smith</a>')
        // No URL → plain text, no anchor.
        ->toContain('Dr. Jones');
    expect($withEntries)->not->toContain('href="">');

    $empty = (string) Antlers::parse(
        '{{ partial:clinical_resources/related_list :entries="entries" heading="Related Providers" }}',
        ['entries' => []],
    );

    expect(trim($empty))->toBe('');
});

it('renders the resources listing structure without error against real content', function (): void {
    $this->requireContentCollection('clinical_resources');

    $html = (string) Antlers::parse('{{ partial:clinical_resources/listing }}', []);

    expect($html)->toContain('class="container-fluid"');
    expect($html)->not->toContain('{{');
});
