<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Fields\Field;
use Tests\Support\StacheIsolatedTestCase;

/**
 * The shared related-entity list gate (UAMS-Web/uams-statamic-archive#1693), over a REAL augmented `entries`
 * relation with a published/unpublished mix.
 *
 * `clinical_resources/related_list` gated on `{{ entries | length }}`, which is
 * the same defect as the bare `{{ entries | count }}` the ticket is named for:
 * `CoreModifiers::length()` opens with the identical query-builder branch, so it
 * forwards to the wrapped builder that `StatusQueryBuilder` never filtered —
 * only `get()` applies the published-only constraint. The partial therefore
 * opened a `<section>` and wrote its `<h2>` over a `<ul>` the loop left empty.
 *
 * It backs the related-entity asides on both the clinical-resource and
 * clinical-location show pages, so one fixture here covers every group on both.
 *
 * @command php artisan test --compact tests/Feature/Views/RelatedListCountGateTest.php
 */
uses(StacheIsolatedTestCase::class);

const RELATED_LIST_GATE_COLLECTION = 'clinical_resources';

beforeEach(function (): void {
    $collection = CollectionFacade::make(RELATED_LIST_GATE_COLLECTION);
    $collection->title('Clinical Resources');
    $collection->save();
});

/**
 * Persist one related entry and return its id.
 *
 * @param  string  $id  The entry id and slug.
 * @param  string  $title  The entry title.
 * @param  bool  $published  Whether the entry is published.
 * @return string The persisted entry's id.
 */
function relatedListGateEntry(string $id, string $title, bool $published): string
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection(RELATED_LIST_GATE_COLLECTION);
    $entry->id($id);
    $entry->slug($id);
    $entry->published($published);
    $entry->data(['title' => $title]);
    $entry->saveQuietly();

    return $id;
}

/**
 * Render the related-entity list with a real augmented relation over the ids.
 *
 * @param  list<string>  $ids  Entry ids the relation stores.
 * @return string The rendered markup.
 */
function renderRelatedListGate(array $ids): string
{
    $field = Blueprint::makeFromFields([
        'related' => [
            'type' => 'entries',
            'collections' => [RELATED_LIST_GATE_COLLECTION],
        ],
    ])->field('related');

    // `Blueprint::field()` and `Field::augment()` are untyped upstream, so narrow
    // both rather than asserting a shape PHPStan cannot see through.
    if (! $field instanceof Field) {
        throw new RuntimeException('The fixture blueprint did not yield a `related` field.');
    }

    $field->setValue($ids);
    $augmented = $field->augment();

    if (! $augmented instanceof Field) {
        throw new RuntimeException('The fixture `related` field did not augment.');
    }

    return (string) Antlers::parse(
        '{{ partial:clinical_resources/related_list :entries="related" heading="Related Resources" }}',
        ['related' => $augmented->value()],
        true
    );
}

it('renders no group when every related entry is unpublished', function (): void {
    relatedListGateEntry('embargoed', 'Embargoed Protocol', published: false);

    $html = renderRelatedListGate(['embargoed']);

    expect($html)->not->toContain('clinical-resource-related-group')
        ->and($html)->not->toContain('Related Resources')
        ->and($html)->not->toContain('Embargoed Protocol');
});

it('renders only the published entries on a mixed relation', function (): void {
    relatedListGateEntry('knee', 'Knee Replacement Recovery', published: true);
    relatedListGateEntry('embargoed', 'Embargoed Protocol', published: false);

    $html = renderRelatedListGate(['knee', 'embargoed']);

    expect($html)->toContain('clinical-resource-related-group')
        ->and($html)->toContain('Related Resources')
        ->and($html)->toContain('Knee Replacement Recovery')
        ->and($html)->not->toContain('Embargoed Protocol');
});
