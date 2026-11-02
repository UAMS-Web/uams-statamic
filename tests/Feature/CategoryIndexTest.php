<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for the bare `/category` browse-all index (UAMS-Web/uams-statamic-archive#1435): a plain `pages`
 * entry whose template lists the top-level `post_categories`, each linking into
 * its hierarchical archive. The index queries `site="*"` because the migrated
 * categories live on the microsites that surface them (`science_reports` /
 * `accardv`), not on the `default` site that hosts the index — a site-scoped
 * query would render permanently empty.
 *
 * The template wiring is asserted against the app-repo file; the end-to-end
 * render is gated on the migrated collection + the `/category` pages entry and
 * asserts against the real cross-site categories (no manufactured default-site
 * fixture, so nothing to tear down).
 *
 * @command  php artisan test --compact tests/Feature/CategoryIndexTest.php
 */
it('wires the browse-all index to the top-level scope, cross-site aggregation, and escaping', function (): void {
    $template = File::get(resource_path('views/pages/category_index.antlers.html'));

    expect($template)
        ->toContain('collection:post_categories')
        ->toContain('query_scope="top_level_post_categories"') // roots only, not the whole tree
        ->toContain('site="*"')                                // aggregate across the microsites that hold categories
        ->toContain('There are no categories yet')             // empty state, not a 404
        ->toContain('title | strip_tags | entities');          // editor/importer titles escaped (raw-by-default Antlers)
});

it('renders the index listing categories aggregated across the microsites', function (): void {
    if (! Collection::findByHandle('post_categories')) {
        ContentSubmodule::reportMissing('migrated post_categories collection');
    }

    // The index entry lives on the default site (findByUri's implicit default),
    // named explicitly to satisfy the facade's two-argument signature.
    /** @var Statamic\Sites\Site $defaultSite */
    $defaultSite = Site::default();
    /** @var string $defaultSiteHandle */
    $defaultSiteHandle = $defaultSite->handle();

    if (Entry::findByUri('/category', $defaultSiteHandle) === null) {
        ContentSubmodule::reportMissing('/category pages index entry');
    }

    // A real top-level category — the migrated ones live on science_reports / accardv,
    // never on the default site that hosts the index.
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'post_categories');

    /** @var EntryCollection $entries */
    $entries = $query->get();

    /** @var Statamic\Entries\Entry|null $category */
    $category = $entries->first(function ($e) {
        /** @var Statamic\Entries\Entry $e */
        return empty($e->value('parent'));
    });

    if ($category === null) {
        ContentSubmodule::reportMissing('migrated post_categories entries');
    }

    /** @var TestCase $test */
    $test = $this->withoutVite();
    /** @var string $html */
    $html = $test->get('/category')->assertOk()->getContent();

    // site="*" surfaces the microsite category on the default-hosted index; a
    // site-scoped query would render the empty state instead. Assert via the
    // category's own URL (the rendered `<a href>`) — a stable identifier that,
    // unlike the title, is not mangled by the escaping the template applies:
    // `{{ title | strip_tags | entities }}` entity-encodes e.g. an em-dash to
    // `&mdash;`, so a raw-title match would spuriously fail on a real category
    // such as "… — Meeting Minutes" even though it renders.
    expect($html)->toContain($category->url());
    expect($html)->not->toContain('There are no categories yet');

    // The listed category link resolves to its own site's archive.
    /** @var TestCase $test */
    $test = $this->withoutVite();
    /** @var string $categoryUrl */
    $categoryUrl = $category->url();
    $test->get($categoryUrl)->assertOk();
});
