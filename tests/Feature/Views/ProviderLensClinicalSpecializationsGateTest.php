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
 * The provider lens's "Specialties" row once `clinical_specializations` is an
 * `entries` relation (UAMS-Web/uams-statamic-archive#2596). The row and the "At a Glance" block are gated on
 * `| count`, which counts only the published entries the relation resolves, so a
 * draft entry opens nothing (UAMS-Web/uams-statamic-archive#1693, UAMS-Web/uams-statamic-archive#2422). These feed
 * the lens a value augmented through the `entries` fieldtype the person blueprint
 * uses, over entries saved in this test's private Stache.
 *
 * @command  php artisan test --compact tests/Feature/Views/ProviderLensClinicalSpecializationsGateTest.php
 */
beforeEach(function (): void {
    /** @var Collection $collection */
    $collection = CollectionFacade::make('clinical_specializations');
    $collection->title('Clinical Specializations');
    $collection->sites(['default']);
    $collection->structureContents(['root' => false]);
    $collection->save();
});

/**
 * Save a `clinical_specializations` entry, published or draft.
 */
function saveClinicalSpecializationForLens(string $slug, string $title, bool $published): StatamicEntry
{
    /** @var StatamicEntry $entry */
    $entry = Entry::make();
    $entry->collection('clinical_specializations');
    $entry->locale('default');
    $entry->slug($slug);
    $entry->published($published);
    $entry->data(['title' => $title]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * Render the lens body with a provider whose `clinical_specializations` stores these ids.
 *
 * @param  list<string>  $ids  Stored entry ids
 */
function renderLensWithClinicalSpecializations(array $ids): string
{
    // The person blueprint's field config, which ClinicalSpecializationsCollectionTest
    // pins; the people collection does not exist in this isolated Stache.
    $field = new Field('clinical_specializations', ['type' => 'entries', 'collections' => ['clinical_specializations'], 'select_across_sites' => true]);

    return (string) Antlers::parse('{{ partial:people/provider/profile }}', [
        'title' => 'Leonard McCoy',
        'display_name_full_with_credentials' => ['text' => 'Leonard McCoy, M.D.'],
        'display_name_short_with_prefix' => ['text' => 'Dr. McCoy'],
        'clinical_specializations' => new Value($ids, 'clinical_specializations', $field->fieldtype()),
    ]);
}

it('lists a published specialization through the entries relation', function (): void {
    $thoracic = saveClinicalSpecializationForLens('208g00000x', 'Thoracic Surgeon', true);

    $html = renderLensWithClinicalSpecializations([mustBeString($thoracic->id(), 'the thoracic id')]);

    expect($html)->toContain('<dt>Specialties</dt>')
        ->and($html)->toContain('<dd class="provider-specialization">Thoracic Surgeon</dd>');
});

it('renders no specialties row, and no At a Glance block, when the only stored specialization is a draft', function (): void {
    $draft = saveClinicalSpecializationForLens('zz-draft-specialization', 'Draft Specialization', false);

    $html = renderLensWithClinicalSpecializations([mustBeString($draft->id(), 'the draft id')]);

    // A bare `| count` counts the draft's stored id and opens a heading over nothing.
    expect($html)->not->toContain('Specialties')
        ->and($html)->not->toContain('Draft Specialization')
        ->and($html)->not->toContain('provider-at-a-glance');
});
