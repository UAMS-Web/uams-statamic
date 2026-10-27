<?php

declare(strict_types=1);

use Statamic\Entries\Collection;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Fields\Field;
use Statamic\Fields\Value;
use Tests\Support\StacheIsolatedTestCase;

uses(StacheIsolatedTestCase::class);

/**
 * The provider lens's patient-types row once `patient_types` is an `entries`
 * relation (UAMS-Web/uams-statamic-archive#2598). The row is gated on `| count`, which counts only the
 * published entries the relation resolves, so a draft entry opens nothing
 * (UAMS-Web/uams-statamic-archive#1693, UAMS-Web/uams-statamic-archive#2422). These feed the lens a
 * value augmented through the `entries` fieldtype the person blueprint uses, over
 * entries saved in this test's private Stache.
 *
 * @command  php artisan test --compact tests/Feature/Views/ProviderLensPatientTypesGateTest.php
 */
beforeEach(function (): void {
    /** @var Collection $collection */
    $collection = CollectionFacade::make('patient_types');
    $collection->title('Patient Types');
    $collection->structureContents(['root' => false]);
    $collection->save();
});

/**
 * Save a `patient_types` entry, published or draft.
 */
function savePatientTypeForLens(string $slug, string $title, bool $published): StatamicEntry
{
    /** @var StatamicEntry $entry */
    $entry = Entry::make();
    $entry->collection('patient_types');
    $entry->slug($slug);
    $entry->published($published);
    $entry->data(['title' => $title]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * Render the lens body with a provider whose `patient_types` stores these ids.
 *
 * @param  list<string>  $ids  Stored entry ids
 */
function renderLensWithPatientTypes(array $ids): string
{
    // The person blueprint's field config, which PatientTypesCollectionTest pins; the
    // people collection does not exist in this isolated Stache, so it is built here.
    $field = new Field('patient_types', ['type' => 'entries', 'collections' => ['patient_types'], 'select_across_sites' => true]);

    return (string) Antlers::parse('{{ partial:people/provider/profile }}', [
        'title' => 'Leonard McCoy',
        'display_name_full_with_credentials' => ['text' => 'Leonard McCoy, M.D.'],
        'display_name_short_with_prefix' => ['text' => 'Dr. McCoy'],
        'patient_types' => new Value($ids, 'patient_types', $field->fieldtype()),
    ]);
}

it('lists a published patient type through the entries relation', function (): void {
    $adults = savePatientTypeForLens('adults', 'Adults', true);

    $html = renderLensWithPatientTypes([mustBeString($adults->id(), 'the adults id')]);

    expect($html)->toContain('<dt>Patient types</dt>')
        ->and($html)->toContain('<dd class="provider-patient-type">Adults</dd>');
});

it('renders no patient-types row when the only stored type is a draft', function (): void {
    $draft = savePatientTypeForLens('zz-draft-band', 'Draft band', false);

    $html = renderLensWithPatientTypes([mustBeString($draft->id(), 'the draft id')]);

    // A bare `| count` counts the draft's stored id and opens a heading over nothing.
    expect($html)->not->toContain('Patient types')
        ->and($html)->not->toContain('Draft band');
});
