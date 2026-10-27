<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Fields\Field;
use Tests\Support\StacheIsolatedTestCase;

/**
 * The related-clinical-resources section of the clinical provider lens (UAMS-Web/uams-statamic-archive#1684),
 * exercised against REAL entries through the real `entries` augmentation rather
 * than a hand-rolled scope array.
 *
 * That is the point of this file. `ProviderLensProfileTest` covers the section's
 * markup with plain fixtures, but two of the ticket's guarantees — that
 * unpublished resources never render, and that cards keep the order the editor
 * entered — are properties of `StatusQueryBuilder`, not of the template, and a
 * plain array asserts neither. An isolated Stache lets a published/draft mix be
 * persisted and augmented for real.
 *
 * @command php artisan test --compact tests/Feature/Views/ProviderLensResourcesTest.php
 */
uses(StacheIsolatedTestCase::class);

const PROVIDER_LENS_RESOURCES_COLLECTION = 'clinical_resources';

beforeEach(function (): void {
    $collection = CollectionFacade::make(PROVIDER_LENS_RESOURCES_COLLECTION);
    $collection->title('Clinical Resources');
    $collection->save();
});

/**
 * Persist one `clinical_resources` entry and return its id.
 */
function providerLensResource(string $id, string $title, bool $published): string
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection(PROVIDER_LENS_RESOURCES_COLLECTION);
    $entry->id($id);
    $entry->slug($id);
    $entry->published($published);
    $entry->data(['title' => $title]);
    $entry->saveQuietly();

    return $id;
}

/**
 * Augment an `entries` relation over the given ids the way the `people` blueprint
 * does, so the template sees the production value shape.
 *
 * @param  list<string>  $ids
 */
function providerLensResourceRelation(array $ids): mixed
{
    $field = Blueprint::makeFromFields([
        'clinical_resources' => [
            'type' => 'entries',
            'collections' => [PROVIDER_LENS_RESOURCES_COLLECTION],
        ],
    ])->field('clinical_resources');

    // `Blueprint::field()` and `Field::augment()` are untyped upstream, so narrow
    // both rather than asserting a shape PHPStan cannot see through.
    if (! $field instanceof Field) {
        throw new RuntimeException('The fixture blueprint did not yield a `clinical_resources` field.');
    }

    $field->setValue($ids);
    $augmented = $field->augment();

    if (! $augmented instanceof Field) {
        throw new RuntimeException('The fixture `clinical_resources` field did not augment.');
    }

    return $augmented->value();
}

/**
 * Render the lens body with the given relation in scope.
 */
function renderProviderLensWithResources(mixed $relation): string
{
    return (string) Antlers::parse('{{ partial:people/provider/profile }}', [
        'title' => 'Leonard McCoy',
        'display_name_full_with_credentials' => ['text' => 'Leonard McCoy, M.D.'],
        'display_name_short_with_prefix' => ['text' => 'Dr. McCoy'],
        'clinical_resources' => $relation,
    ]);
}

it('renders only the published resources', function (): void {
    providerLensResource('knee', 'Knee Replacement Recovery', published: true);
    providerLensResource('embargoed', 'Embargoed Protocol', published: false);
    providerLensResource('shoulder', 'Shoulder Arthroscopy', published: true);

    $html = renderProviderLensWithResources(
        providerLensResourceRelation(['knee', 'embargoed', 'shoulder'])
    );

    expect($html)->toContain('provider-resources')
        ->and($html)->toContain('Knee Replacement Recovery')
        ->and($html)->toContain('Shoulder Arthroscopy')
        ->and($html)->not->toContain('Embargoed Protocol');
});

it('keeps the order the editor entered rather than a collection order', function (): void {
    providerLensResource('knee', 'Knee Replacement Recovery', published: true);
    providerLensResource('shoulder', 'Shoulder Arthroscopy', published: true);

    // Entered shoulder-first, which is the REVERSE of both the id order and the
    // save order — so an implementation that fell back to either would fail here.
    $html = renderProviderLensWithResources(
        providerLensResourceRelation(['shoulder', 'knee'])
    );

    expect((int) strpos($html, 'Shoulder Arthroscopy'))
        ->toBeLessThan((int) strpos($html, 'Knee Replacement Recovery'));
});

// The gate counts published entries, not stored ids: a provider whose only related
// resource is unpublished must get no empty heading over an empty list (UAMS-Web/uams-statamic-archive#1693). The
// count itself is pinned by the dataset test at the end of this file.
it('renders no section when every related resource is unpublished', function (): void {
    providerLensResource('embargoed', 'Embargoed Protocol', published: false);

    $html = renderProviderLensWithResources(
        providerLensResourceRelation(['embargoed'])
    );

    expect($html)->not->toContain('provider-resources')
        ->and($html)->not->toContain('Embargoed Protocol')
        ->and($html)->not->toContain('Leonard McCoy, M.D.</a>');
});

// The same gate backs all four clinical ontology sections (UAMS-Web/uams-statamic-archive#571), which predate this
// ticket and carried the bare `| count`. Locations stands in for the set.
it('omits an ontology section whose only related entry is unpublished', function (): void {
    $collection = CollectionFacade::make('clinical_locations');
    $collection->title('Clinical Locations');
    $collection->save();

    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('clinical_locations');
    $entry->id('sickbay');
    $entry->slug('sickbay');
    $entry->published(false);
    $entry->data(['title' => 'Deck 7 Sickbay']);
    $entry->saveQuietly();

    $field = Blueprint::makeFromFields([
        'clinical_locations' => ['type' => 'entries', 'collections' => ['clinical_locations']],
    ])->field('clinical_locations');

    if (! $field instanceof Field) {
        throw new RuntimeException('The fixture blueprint did not yield a `clinical_locations` field.');
    }

    $field->setValue(['sickbay']);
    $augmented = $field->augment();

    if (! $augmented instanceof Field) {
        throw new RuntimeException('The fixture `clinical_locations` field did not augment.');
    }

    $html = (string) Antlers::parse('{{ partial:people/provider/profile }}', [
        'title' => 'Leonard McCoy',
        'display_name_full_with_credentials' => ['text' => 'Leonard McCoy, M.D.'],
        'display_name_short_with_prefix' => ['text' => 'Dr. McCoy'],
        'clinical_locations' => $augmented->value(),
    ]);

    expect($html)->not->toContain('profile-locations')
        ->and($html)->not->toContain('Deck 7 Sickbay')
        ->and($html)->not->toContain('Leonard McCoy, M.D.</a>');
});

// UAMS-Web/uams-statamic-archive#2422. statamic/cms#15546 (first released in the v6.35 line) makes `StatusQueryBuilder`
// apply its published filter to `count()` as well as `get()`, so a bare `| count` now counts
// the entries that resolve rather than the stored ids. These pin that, because the provider
// lens and the card templates gate on a bare count on the strength of it.
it('counts only the published entries of a relation, whichever way it is counted', function (string $ids, string $expected): void {
    providerLensResource('knee', 'Knee Replacement Recovery', published: true);
    providerLensResource('shoulder', 'Shoulder Arthroscopy', published: true);
    providerLensResource('embargoed', 'Embargoed Protocol', published: false);

    $counts = (string) Antlers::parse(
        '{{ r | count }}|{{ r | pluck:\'id\' | count }}|{{ r | length }}',
        ['r' => providerLensResourceRelation(explode(',', $ids))]
    );

    expect(trim($counts))->toBe($expected);
})->with([
    'all published' => ['knee,shoulder', '2|2|2'],
    'one published, one draft' => ['knee,embargoed', '1|1|1'],
    'only a draft' => ['embargoed', '0|0|0'],
]);
