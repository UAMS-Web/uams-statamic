<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Render coverage for the `clinical_resources` archive's text search, location and
 * area of expertise facets, sort selector, result count and reset
 * (UAMS-Web/uams-statamic#1583): resources/views/clinical_resources/_listing.antlers.html
 * and the `clinical_resources_archive_filter` scope it runs beside the type and
 * provider scopes.
 *
 * Built in an isolated Stache so the `collection:clinical_resources` tag and the
 * scopes run against real entries. The query parameters reach the template through
 * the cascade `get` variable on a live request; here they are passed explicitly to
 * `Antlers::parse`.
 *
 * @command php artisan test --compact tests/Feature/Views/ClinicalResourcesArchiveFilterTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    // The resources collection is dated, so the default newest-first sort has dates to order by.
    foreach ([
        'clinical_resources' => ['Clinical Resources', true],
        'clinical_locations' => ['Clinical Locations', false],
        'clinical_expertise' => ['Areas of Expertise', false],
        'people' => ['People', false],
    ] as $handle => [$title, $dated]) {
        $collection = CollectionFacade::make($handle);
        $collection->title($title);
        $collection->dated($dated);
        $collection->save();
    }

    // Resource blueprints carrying the two relation fields the facets read.
    foreach (['article', 'video'] as $handle) {
        $blueprint = Blueprint::make($handle);
        $blueprint->setNamespace('collections.clinical_resources');
        $blueprint->setContents([
            'tabs' => ['main' => ['sections' => [['fields' => [
                ['handle' => 'title', 'field' => ['type' => 'text']],
                ['handle' => 'clinical_locations', 'field' => ['type' => 'entries', 'collections' => ['clinical_locations']]],
                ['handle' => 'clinical_expertise', 'field' => ['type' => 'entries', 'collections' => ['clinical_expertise']]],
            ]]]]],
        ]);
        $blueprint->save();
    }
    $expertiseBlueprint = Blueprint::make('clinical_expertise');
    $expertiseBlueprint->setNamespace('collections.clinical_expertise');
    $expertiseBlueprint->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
        ['handle' => 'title', 'field' => ['type' => 'text']],
    ]]]]]]);
    $expertiseBlueprint->save();

    // A content-page blueprint, for pages nested under an area that are not areas themselves.
    $pageBlueprint = Blueprint::make('page');
    $pageBlueprint->setNamespace('collections.clinical_expertise');
    $pageBlueprint->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
        ['handle' => 'title', 'field' => ['type' => 'text']],
    ]]]]]]);
    $pageBlueprint->save();

    // Two locations and two areas of expertise for the facets.
    foreach ([
        ['clinical_locations', 'loc-main', 'medical-center', 'UAMS Medical Center', null],
        ['clinical_locations', 'loc-north', 'north-clinic', 'North Clinic', null],
        ['clinical_expertise', 'aoe-lungs', 'pulmonology', 'Pulmonology', 'clinical_expertise'],
        ['clinical_expertise', 'aoe-cardio', 'cardiology', 'Cardiology', 'clinical_expertise'],
    ] as [$collection, $id, $slug, $title, $blueprint]) {
        /** @var Statamic\Entries\Entry $entry */
        $entry = Entry::make();
        $entry->collection($collection);
        if (is_string($blueprint)) {
            $entry->blueprint($blueprint);
        }
        $entry->id($id);
        $entry->slug($slug);
        $entry->published(true);
        $entry->data(['title' => $title]);
        $entry->saveQuietly();
    }

    // Four resources across both types, dates and relations.
    foreach ([
        'r-asthma' => ['Asthma Basics', 'article', '2026-01-10', ['loc-main'], ['aoe-lungs']],
        'r-breathing' => ['Breathing Exercises', 'video', '2026-03-05', ['loc-main', 'loc-north'], ['aoe-lungs']],
        'r-cardiac' => ['Cardiac Rehab Guide', 'article', '2026-02-01', ['loc-north'], ['aoe-cardio']],
        'r-diet' => ['Diet After Surgery', 'article', '2025-12-01', [], []],
    ] as $id => [$title, $blueprint, $date, $locations, $areas]) {
        /** @var Statamic\Entries\Entry $entry */
        $entry = Entry::make();
        $entry->collection('clinical_resources');
        $entry->blueprint($blueprint);
        $entry->id($id);
        $entry->slug(substr($id, 2));
        $entry->date($date);
        $entry->published(true);
        $entry->data(array_filter([
            'title' => $title,
            'clinical_locations' => $locations,
            'clinical_expertise' => $areas,
        ], static fn (mixed $value): bool => $value !== []));
        $entry->saveQuietly();
    }

    // A provider relating two of them, for the provider filter.
    /** @var Statamic\Entries\Entry $person */
    $person = Entry::make();
    $person->collection('people');
    $person->id('mccoy');
    $person->slug('mccoy');
    $person->published(true);
    $person->data(['title' => 'Leonard McCoy', 'clinical_resources' => ['r-asthma', 'r-cardiac']]);
    $person->saveQuietly();
});

/**
 * Render the resources listing partial with the given `get` query params.
 *
 * @param  array<string, string>  $get
 */
function renderResourcesArchive(array $get = []): string
{
    return (string) Antlers::parse(
        '{{ partial:clinical_resources/listing }}',
        ['get' => $get, 'url' => '/health/resources'],
        true
    );
}

/**
 * The resource titles in the order the listing renders them.
 *
 * @return list<string>
 */
function renderedResourceTitles(string $html): array
{
    preg_match_all('~<h2 class="card-title h5"[^>]*>([^<]*)</h2>~', $html, $matches);

    return $matches[1];
}

/**
 * The text of the result count.
 */
function renderedResourceCount(string $html): string
{
    preg_match('~<span class="resources-result-count">([^<]*)</span>~', $html, $matches);

    return trim($matches[1] ?? '');
}

it('lists every resource newest first with the full count and no selections', function (): void {
    $html = renderResourcesArchive();

    expect(renderedResourceTitles($html))->toBe(['Breathing Exercises', 'Cardiac Rehab Guide', 'Asthma Basics', 'Diet After Surgery'])
        ->and(renderedResourceCount($html))->toBe('4 clinical resources')
        ->and($html)->not->toContain('resources-selections')
        ->and($html)->toContain('<option value="newest" selected>');
});

it('reorders the list for each sort option', function (string $sort, array $titles): void {
    $html = renderResourcesArchive(['sort' => $sort]);

    expect(renderedResourceTitles($html))->toBe($titles)
        ->and($html)->toContain('<option value="'.$sort.'" selected>');
})->with([
    'oldest' => ['oldest', ['Diet After Surgery', 'Asthma Basics', 'Cardiac Rehab Guide', 'Breathing Exercises']],
    'title A to Z' => ['title_asc', ['Asthma Basics', 'Breathing Exercises', 'Cardiac Rehab Guide', 'Diet After Surgery']],
    'title Z to A' => ['title_desc', ['Diet After Surgery', 'Cardiac Rehab Guide', 'Breathing Exercises', 'Asthma Basics']],
]);

it('falls back to newest first for an unknown sort key', function (): void {
    $html = renderResourcesArchive(['sort' => 'title:asc']);

    expect(renderedResourceTitles($html))->toBe(['Breathing Exercises', 'Cardiac Rehab Guide', 'Asthma Basics', 'Diet After Surgery']);
});

it('searches titles case-insensitively and counts the matches', function (): void {
    $html = renderResourcesArchive(['q' => 'BREATH']);

    expect(renderedResourceTitles($html))->toBe(['Breathing Exercises'])
        ->and(renderedResourceCount($html))->toBe('1 clinical resource')
        ->and($html)->toContain('<li class="resources-selection">Search: BREATH</li>');
});

it('narrows by location and names the selected location', function (): void {
    $html = renderResourcesArchive(['location' => 'north-clinic']);

    expect(renderedResourceTitles($html))->toBe(['Breathing Exercises', 'Cardiac Rehab Guide'])
        ->and(renderedResourceCount($html))->toBe('2 clinical resources')
        ->and($html)->toContain('<li class="resources-selection">Location: North Clinic</li>')
        ->and($html)->toContain('<option value="north-clinic" selected>North Clinic</option>');
});

it('narrows by area of expertise and names the selected area', function (): void {
    $html = renderResourcesArchive(['expertise' => 'pulmonology']);

    expect(renderedResourceTitles($html))->toBe(['Breathing Exercises', 'Asthma Basics'])
        ->and($html)->toContain('<li class="resources-selection">Area of expertise: Pulmonology</li>');
});

it('combines search, facets, type and provider', function (): void {
    // Pulmonology at the medical center is asthma and breathing; the article type drops
    // the video, and the provider relates asthma.
    $html = renderResourcesArchive([
        'location' => 'medical-center',
        'expertise' => 'pulmonology',
        'type' => 'article',
        'provider' => 'mccoy',
        'q' => 'asthma',
    ]);

    expect(renderedResourceTitles($html))->toBe(['Asthma Basics'])
        ->and(renderedResourceCount($html))->toBe('1 clinical resource');

    // Dropping the type lets the video back in only when the provider does not exclude it.
    $withoutType = renderResourcesArchive(['location' => 'medical-center', 'expertise' => 'pulmonology']);
    expect(renderedResourceTitles($withoutType))->toBe(['Breathing Exercises', 'Asthma Basics']);
});

it('keeps the type and provider in the form and every filter in the type tabs', function (): void {
    $html = renderResourcesArchive([
        'type' => 'article',
        'provider' => 'mccoy',
        'q' => 'heart & lung',
        'location' => 'north-clinic',
        'sort' => 'oldest',
    ]);

    expect($html)->toContain('<input type="hidden" name="type" value="article">')
        ->and($html)->toContain('<input type="hidden" name="provider" value="mccoy">')
        ->and($html)->toContain('action="/health/resources"')
        ->and($html)->toContain('href="/health/resources?type=video&amp;provider=mccoy&amp;q=heart+%26+lung&amp;location=north-clinic&amp;sort=oldest"')
        ->and($html)->toContain('href="/health/resources?provider=mccoy&amp;q=heart+%26+lung&amp;location=north-clinic&amp;sort=oldest"');
});

it('carries the filters on the All tab without a provider', function (): void {
    $html = renderResourcesArchive(['expertise' => 'cardiology']);

    expect($html)->toContain('href="/health/resources?expertise=cardiology"')
        ->and($html)->toContain('href="/health/resources?type=article&amp;expertise=cardiology"');
});

it('shows the filtered empty state with a reset link when nothing matches', function (): void {
    $html = renderResourcesArchive(['q' => 'zzz', 'location' => 'medical-center']);

    expect(renderedResourceTitles($html))->toBe([])
        ->and(renderedResourceCount($html))->toBe('0 clinical resources')
        ->and($html)->toContain('No clinical resources match these filters.')
        ->and($html)->toContain('<a href="/health/resources" class="resources-filter-reset">Show all clinical resources</a>');
});

it('matches nothing for a facet slug that names no entry', function (): void {
    $html = renderResourcesArchive(['location' => 'nowhere']);

    expect(renderedResourceTitles($html))->toBe([])
        ->and($html)->toContain('No clinical resources match these filters.');
});

it('resets to the full archive from the reset control', function (): void {
    $filtered = renderResourcesArchive(['q' => 'asthma', 'type' => 'article']);

    // Reset links to the archive root, and the root lists everything.
    expect($filtered)->toContain('<a href="/health/resources" class="btn btn-outline-primary resources-filter-reset">Reset</a>')
        ->and(renderedResourceTitles(renderResourcesArchive()))->toHaveCount(4);
});

it('escapes a search term reflected from the query string', function (): void {
    $html = renderResourcesArchive(['q' => '"><script>alert(1)</script>']);

    expect($html)->not->toContain('<script>alert(1)')
        ->and($html)->not->toContain('value=""><script>');
});

it('offers published locations and areas of expertise only, in title order', function (): void {
    // An unpublished location and a content page under an area are not options.
    foreach ([
        ['clinical_locations', 'loc-closed', 'closed-site', 'Closed Site', null, false],
        ['clinical_expertise', 'aoe-page', 'about-cardiology', 'About Cardiology', 'page', true],
    ] as [$collection, $id, $slug, $title, $blueprint, $published]) {
        /** @var Statamic\Entries\Entry $entry */
        $entry = Entry::make();
        $entry->collection($collection);
        if (is_string($blueprint)) {
            $entry->blueprint($blueprint);
        }
        $entry->id($id);
        $entry->slug($slug);
        $entry->published($published);
        $entry->data(['title' => $title]);
        $entry->saveQuietly();
    }

    $html = renderResourcesArchive();
    preg_match_all('~<option value="([^"]*)"~', $html, $matches);

    expect($matches[1])->toBe(['', 'north-clinic', 'medical-center', '', 'cardiology', 'pulmonology', 'newest', 'oldest', 'title_asc', 'title_desc']);
});
