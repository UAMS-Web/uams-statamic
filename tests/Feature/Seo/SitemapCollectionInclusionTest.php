<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\SitemapInclusionComputedsRegistrar as Inclusion;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Assert;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\YAML;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/*
|--------------------------------------------------------------------------
| Sitemap collection inclusion (UAMS-Web/uams-statamic-archive#1402 / UAMS-Web/uams-statamic-archive#1403)
|--------------------------------------------------------------------------
|
| The app sitemap (mitydigital/sitemapamic) emits every routable, front-end
| collection — `pages`, `posts`, `people`, `clinical_expertise` (UAMS-Web/uams-statamic-archive#1402) and
| `knowledge_bases` (UAMS-Web/uams-statamic-archive#1403) — each gated by the `meta_include_in_xml_sitemap`
| computed so non-public / non-routable / orphaned entries never reach the
| public sitemap.
|
| Two hermetic layers (config + blueprint wiring, always run) and one behavioral
| layer over the registered computeds (skipped without the content submodule,
| which holds the collection configs the computeds attach to).
|
| @command php artisan test --compact tests/Feature/Seo/SitemapCollectionInclusionTest.php
*/

/** Every routable collection that must appear in the sitemap `defaults`. */
const SITEMAP_COLLECTIONS = ['pages', 'posts', 'people', 'clinical_expertise', 'knowledge_bases'];

/** Every blueprint that must surface the per-entry `include_in_xml_sitemap` toggle. */
const SITEMAP_TOGGLE_BLUEPRINTS = [
    'posts/post', 'posts/audio', 'posts/news_release', 'posts/video',
    'people/person',
    'clinical_expertise/basic_page', 'clinical_expertise/landing_page', 'clinical_expertise/clinical_expertise',
    'knowledge_bases/knowledge_base', 'knowledge_bases/topic', 'knowledge_bases/article',
];

it('lists every routable collection in the sitemap defaults with taxonomies off', function (): void {
    /** @var array<string, array<string, mixed>> $defaults */
    $defaults = config('sitemapamic.defaults', []);

    expect(array_keys($defaults))->toContain(...SITEMAP_COLLECTIONS);

    // Each collection emits its entries but not its attached-taxonomy terms — those
    // are handled by the global-taxonomies path (UAMS-Web/uams-statamic-archive#1346), so includeTaxonomies is off.
    foreach (SITEMAP_COLLECTIONS as $handle) {
        expect($defaults[$handle]['include'] ?? null)->toBeTrue()
            ->and($defaults[$handle]['includeTaxonomies'] ?? null)->toBeFalse();
    }
});

it('imports the sitemap toggle fieldset into every sitemapped blueprint', function (string $blueprint): void {
    // Without the import, editors get no per-entry opt-out and deleting the wiring
    // would leave every behavioral test green — so assert the import explicitly.
    $parsed = YAML::parse(File::get(resource_path("blueprints/collections/{$blueprint}.yaml")));

    /** @var array<array-key, array{sections?: array<array-key, array{fields?: array<array-key, array<string, mixed>>}>}> $tabs */
    $tabs = $parsed['tabs'] ?? [];

    $imports = collect($tabs)
        ->flatMap(fn ($tab): array => $tab['sections'] ?? [])
        ->flatMap(fn ($section): array => $section['fields'] ?? [])
        ->pluck('import')
        ->filter()
        ->all();

    expect($imports)->toContain('uamsweb_entry_sitemap');
})->with(SITEMAP_TOGGLE_BLUEPRINTS);

it('gates posts and clinical_expertise by private / password / editor toggle', function (string $collection): void {
    if (! Collection::findByHandle($collection)) {
        ContentSubmodule::reportMissing("the `{$collection}` collection");
    }

    $include = Inclusion::INCLUDE_HANDLE;
    $toggle = Inclusion::EDITOR_TOGGLE_HANDLE;

    /** @var Statamic\Entries\Entry $public */
    $public = Entry::make();
    /** @var Statamic\Entries\Entry $public */
    $public = $public->collection($collection);
    /** @var Statamic\Entries\Entry $public */
    $public = $public->data(['status_and_visibility' => 'published']);

    /** @var Statamic\Entries\Entry $private */
    $private = Entry::make();
    /** @var Statamic\Entries\Entry $private */
    $private = $private->collection($collection);
    /** @var Statamic\Entries\Entry $private */
    $private = $private->data(['status_and_visibility' => 'private']);

    /** @var Statamic\Entries\Entry $protected */
    $protected = Entry::make();
    /** @var Statamic\Entries\Entry $protected */
    $protected = $protected->collection($collection);
    /** @var Statamic\Entries\Entry $protected */
    $protected = $protected->data(['status_and_visibility' => 'published', 'password_protected' => true]);

    /** @var Statamic\Entries\Entry $optedOut */
    $optedOut = Entry::make();
    /** @var Statamic\Entries\Entry $optedOut */
    $optedOut = $optedOut->collection($collection);
    /** @var Statamic\Entries\Entry $optedOut */
    $optedOut = $optedOut->data(['status_and_visibility' => 'published', $toggle => false]);

    expect($public->getComputed($include))->toBeTrue()
        ->and($private->getComputed($include))->toBeFalse()
        ->and($protected->getComputed($include))->toBeFalse()
        ->and($optedOut->getComputed($include))->toBeFalse();
})->with(['posts', 'clinical_expertise']);

it('gates people additionally by profile routability', function (): void {
    if (! Collection::findByHandle('people')) {
        ContentSubmodule::reportMissing('the `people` collection');
    }

    $include = Inclusion::INCLUDE_HANDLE;

    /** @var Statamic\Entries\Entry $routable */
    $routable = Entry::make();
    /** @var Statamic\Entries\Entry $routable */
    $routable = $routable->collection('people');
    /** @var Statamic\Entries\Entry $routable */
    $routable = $routable->data(['status_and_visibility' => 'published']);

    /** @var Statamic\Entries\Entry $nonRoutable */
    $nonRoutable = Entry::make();
    /** @var Statamic\Entries\Entry $nonRoutable */
    $nonRoutable = $nonRoutable->collection('people');
    /** @var Statamic\Entries\Entry $nonRoutable */
    $nonRoutable = $nonRoutable->data(['status_and_visibility' => 'published', 'profile_page_query' => false]);

    /** @var Statamic\Entries\Entry $private */
    $private = Entry::make();
    /** @var Statamic\Entries\Entry $private */
    $private = $private->collection('people');
    /** @var Statamic\Entries\Entry $private */
    $private = $private->data(['status_and_visibility' => 'private']);

    // A routable, public person is listed; a person with no front-end profile page
    // (profile_page_query === false, URI unset) is excluded even though published.
    expect($routable->getComputed($include))->toBeTrue()
        ->and($nonRoutable->getComputed($include))->toBeFalse()
        ->and($private->getComputed($include))->toBeFalse();
});

it('gates knowledge_bases by the editor toggle', function (): void {
    if (! Collection::findByHandle('knowledge_bases')) {
        ContentSubmodule::reportMissing('the `knowledge_bases` collection');
    }

    $include = Inclusion::INCLUDE_HANDLE;
    $toggle = Inclusion::EDITOR_TOGGLE_HANDLE;

    // A standalone entry has no unpublished ancestor, so it is included by default;
    // opting out via the editor toggle excludes it. (The orphaned-ancestor gate is
    // covered against a real ancestor chain in KnowledgeBaseCollectionComputedsRegistrarTest
    // and KnowledgeBasesUniversalFoldTest — UAMS-Web/uams-statamic-archive#1455 folded KB onto the universal resolver.)
    /** @var Statamic\Entries\Entry $default */
    $default = Entry::make();
    /** @var Statamic\Entries\Entry $default */
    $default = $default->collection('knowledge_bases');

    /** @var Statamic\Entries\Entry $optedOut */
    $optedOut = Entry::make();
    /** @var Statamic\Entries\Entry $optedOut */
    $optedOut = $optedOut->collection('knowledge_bases');
    /** @var Statamic\Entries\Entry $optedOut */
    $optedOut = $optedOut->data([$toggle => false]);

    expect($default->getComputed($include))->toBeTrue()
        ->and($optedOut->getComputed($include))->toBeFalse();
});
