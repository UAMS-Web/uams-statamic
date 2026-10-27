<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Integration coverage for the clinical_resources index resource-type filter
 * (UAMS-Web/uams-statamic-archive#1468): the on-page control (clinical_resources/_type_filter) drives the
 * listing's `?type=` query, which narrows the grid to the selected blueprint
 * subtype via `blueprint:is="$filter_type"`; an empty/absent type lists every
 * subtype.
 *
 * Built in an isolated Stache with per-subtype blueprints so the
 * `collection:clinical_resources` tag + `blueprint:is` run against real,
 * differently-blueprinted entries. `get:type` reaches the template through the
 * cascade `get` variable, passed explicitly to `Antlers::parse` here. Control
 * rendering (tabs, active state, escaping) is covered hermetically in
 * ClinicalResourcesTemplatesTest.
 *
 * @command php artisan test --compact tests/Feature/Views/ClinicalResourcesTypeFilterTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    // Dated, mirroring the real collection — the listing sorts `date:desc`.
    $collection = CollectionFacade::make('clinical_resources');
    $collection->title('Clinical Resources');
    $collection->dated(true);
    $collection->save();

    // Per-subtype blueprints so `blueprint:is="article"` / `"video"` resolve on the
    // entry's blueprint handle (a bare collection gives every entry the default
    // handle, so the type filter could not tell them apart).
    foreach (['article', 'video'] as $handle) {
        $blueprint = Blueprint::make($handle);
        $blueprint->setNamespace('collections.clinical_resources');
        $blueprint->setContents([
            'tabs' => ['main' => ['sections' => [['fields' => [
                ['handle' => 'title', 'field' => ['type' => 'text']],
                ['handle' => 'intro', 'field' => ['type' => 'textarea']],
            ]]]]],
        ]);
        $blueprint->save();
    }

    /** @var Statamic\Entries\Entry $article */
    $article = Entry::make();
    $article->collection('clinical_resources');
    $article->blueprint('article');
    $article->slug('managing-diabetes');
    $article->date('2026-01-02');
    $article->published(true);
    $article->data(['title' => 'Managing Diabetes', 'intro' => 'A written guide.']);
    $article->saveQuietly();

    /** @var Statamic\Entries\Entry $video */
    $video = Entry::make();
    $video->collection('clinical_resources');
    $video->blueprint('video');
    $video->slug('surgery-overview');
    $video->date('2026-01-01');
    $video->published(true);
    $video->data(['title' => 'Surgery Overview', 'intro' => 'A short film.']);
    $video->saveQuietly();
});

/**
 * Render the resources listing partial with the given `get` query params.
 *
 * @param  array<string, string>  $get
 */
function renderResourcesTypeFilterListing(array $get = []): string
{
    return (string) Antlers::parse('{{ partial:clinical_resources/listing }}', ['get' => $get], true);
}

it('lists every subtype when no type filter is active', function (): void {
    $out = renderResourcesTypeFilterListing();

    expect($out)->toContain('Managing Diabetes')->toContain('Surgery Overview');
});

it('narrows the listing to only entries of the selected subtype', function (): void {
    $out = renderResourcesTypeFilterListing(['type' => 'article']);

    expect($out)->toContain('Managing Diabetes');
    expect($out)->not->toContain('Surgery Overview');
});

it('renders the on-page type-filter control alongside the listing', function (): void {
    $out = renderResourcesTypeFilterListing();

    expect($out)
        ->toContain('resources-type-filter')
        ->toContain('?type=article')
        ->toContain('?type=video')
        ->toContain('>All</a>');
});

it('marks the active type tab through the real listing → partial cascade', function (): void {
    // Pins the get:type => $filter_type (listing) => type_filter partial active-state
    // path end to end — not just the hermetically-injected partial scope.
    $out = renderResourcesTypeFilterListing(['type' => 'article']);

    expect($out)
        ->toContain('href="?type=article" aria-current="page"')
        // The All tab is not the active one when a type is selected.
        ->and(substr_count($out, 'aria-current="page"'))->toBe(1);
});
