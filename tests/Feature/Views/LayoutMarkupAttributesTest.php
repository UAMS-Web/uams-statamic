<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests for the layout markup attribute refinement (UAMS-Web/uams-statamic-archive#40):
 *
 *  - resources/views/layout/_article.antlers.html now emits context-aware
 *    `post_class()`-style type classes from the entry's collection (or a term's
 *    taxonomy) instead of a hardcoded `page type-page`, keeping `status-*`,
 *    `entry`, and the `article_class` pass-through.
 *  - resources/views/layout.antlers.html builds `body_class()`-style context
 *    classes (template / collection-or-taxonomy / singular-vs-listing) while
 *    keeping the load-bearing `page-template-default` / `page-template-page_landing`
 *    classes the theme SCSS keys off, and renames the placeholder `main` id
 *    `statamic-content` -> the semantic `main-content`, wired to the skip link and
 *    the gravity-forms styles.
 *
 * The article is rendered from the real partial. The body-class logic mirrors the
 * layout and is rendered in isolation (the full layout pulls in schema/vite/admin
 * tags that cannot render headlessly); the real layout's wiring is guarded at the
 * source.
 *
 * @command php artisan test --compact tests/Feature/Views/LayoutMarkupAttributesTest.php
 *
 * @param  array<string, mixed>  $data
 */
function renderArticle(array $data): string
{
    $out = (string) Antlers::parse('{{ partial:layout/_article }}x{{ /partial:layout/_article }}', $data, true);

    foreach (explode("\n", $out) as $line) {
        if (str_contains($line, 'class=')) {
            return trim($line);
        }
    }

    return $out;
}

/**
 * @param  array<string, mixed>  $data
 */
function renderBodyClasses(array $data): string
{
    // Mirrors the body-class logic AND the real $landing_page_templates array in
    // resources/views/layout.antlers.html (kept in sync; every array entry is
    // separately guarded at the source by the assertions below, so a divergence
    // or a silent removal is caught).
    $tpl = <<<'ANT'
{{ $landing_page_templates = ['clinical_resources/show', 'clinical_expertise/show', 'knowledge_bases/default', 'knowledge_bases/knowledge-base', 'knowledge_bases/topic', 'clinical_locations/show', 'pages/page_landing', 'clinical_providers/show'] }}
{{ $is_landing = ( $landing_page_templates | in_array(template) ) }}
{{ $body_template_class = template ? ( 'template-' + ( template | replace('/', '-') ) ) : null }}
{{ $body_type_class = collection ? ( 'collection-' + collection ) : ( taxonomy ? ( 'taxonomy-' + taxonomy ) : null ) }}
{{ $is_listing = ( template && ( template | ends_with('/index') ) ) }}
{{ $is_singular = ( ( collection || taxonomy ) && !$is_listing ) }}
{{ [
    'page-template-default' => !$is_landing,
    'page-template-page_landing' => $is_landing,
    $body_template_class,
    $body_type_class,
    'listing' => $is_listing,
    'singular' => $is_singular
] | classes | collapse_whitespace }}
ANT;

    return trim((string) Antlers::parse($tpl, $data, true));
}

it('emits article type classes from the collection (or taxonomy), not a hardcoded page type', function () {
    $cr = renderArticle(['collection' => 'clinical_resources', 'published' => true]);
    expect($cr)
        ->toContain('clinical_resources')
        ->toContain('type-clinical_resources')
        ->toContain('status-publish')
        ->toContain('entry')
        ->and($cr)->not->toContain('type-page');

    $term = renderArticle(['taxonomy' => 'clinical_expertise', 'published' => true]);
    expect($term)->toContain('type-clinical_expertise');

    // Pass-through still works.
    $withClass = renderArticle(['collection' => 'posts', 'published' => true, 'article_class' => 'featured']);
    expect($withClass)->toContain('type-posts')->toContain('featured');

    // Fallback preserves the original page type when there is no collection/taxonomy.
    $fallback = renderArticle(['published' => false]);
    expect($fallback)->toContain('page')->toContain('type-page')->toContain('status-draft');
});

it('builds context-aware body classes while keeping the load-bearing page-template-* classes', function () {
    // Single landing entry.
    expect(renderBodyClasses(['template' => 'clinical_resources/show', 'collection' => 'clinical_resources']))
        ->toContain('page-template-page_landing')
        ->toContain('template-clinical_resources-show')
        ->toContain('collection-clinical_resources')
        ->toContain('singular');

    // Listing template.
    $listing = renderBodyClasses(['template' => 'clinical_resources/index', 'collection' => 'clinical_resources']);
    expect($listing)
        ->toContain('page-template-default')
        ->toContain('listing')
        ->and($listing)->not->toContain('singular');

    // Term page on a landing template (clinical_expertise/show is in the real
    // landing array, so it carries the load-bearing page-template-page_landing).
    expect(renderBodyClasses(['template' => 'clinical_expertise/show', 'taxonomy' => 'clinical_expertise']))
        ->toContain('page-template-page_landing')
        ->toContain('taxonomy-clinical_expertise')
        ->toContain('singular');

    // A template merely CONTAINING 'index' (not a `/index` listing) stays singular.
    $reindexing = renderBodyClasses(['template' => 'pages/reindexing', 'collection' => 'pages']);
    expect($reindexing)
        ->toContain('singular')
        ->and($reindexing)->not->toContain('listing');

    // No entity (e.g. 404): default + template hook only, no singular/listing.
    $noEntity = renderBodyClasses(['template' => 'errors/404']);
    expect($noEntity)
        ->toContain('page-template-default')
        ->toContain('template-errors-404')
        ->and($noEntity)->not->toContain('singular')
        ->and($noEntity)->not->toContain('listing');
});

it('renames the placeholder main id to a semantic main-content wired to the skip link and gravity-forms', function () {
    $layout = file_get_contents(resource_path('views/layout.antlers.html'));
    expect($layout)
        ->toContain('id="main-content"')
        ->toContain('href="#main-content"')
        ->and($layout)->not->toContain('statamic-content');

    $gravityForms = file_get_contents(
        resource_path('scss/bootstrap/uams_2020/uams-supporting/plugins/gravity-forms.scss')
    );
    expect($gravityForms)
        ->toContain('#main-content .gform_wrapper')
        ->and($gravityForms)->not->toContain('statamic-content');
});

it('wires the context-aware body class helpers in the real layout (and removes the resolved to-do block)', function () {
    $layout = file_get_contents(resource_path('views/layout.antlers.html'));
    expect($layout)
        // The bare class-array elements (comma-suffixed) — distinct from the
        // `$body_* = ...` definitions, so removing them from the array is caught.
        ->toContain('$body_template_class,')
        ->toContain('$body_type_class,')
        ->toContain("'singular' => \$is_singular")
        ->toContain("'listing' => \$is_listing")
        // Listing detection matches the `/index` basename precisely (not a
        // substring), so a template merely containing 'index' is not mis-flagged.
        ->toContain("ends_with('/index')")
        ->and($layout)->not->toContain("contains('index')")
        // Load-bearing classes preserved.
        ->toContain('page-template-default')
        ->toContain('page-template-page_landing')
        // The three resolved to-do items are gone.
        ->and($layout)->not->toContain('To-Do List');

    // The landing-template array is load-bearing — the theme SCSS keys off
    // page-template-page_landing, so dropping an entry would silently flip those
    // pages to page-template-default. Guard every entry so a removal is caught.
    foreach ([
        'clinical_resources/show', 'clinical_expertise/show', 'knowledge_bases/default',
        'knowledge_bases/knowledge-base', 'knowledge_bases/topic', 'clinical_locations/show',
        'pages/page_landing', 'clinical_providers/show',
    ] as $landingTemplate) {
        expect($layout)->toContain("'{$landingTemplate}'");
    }
});
