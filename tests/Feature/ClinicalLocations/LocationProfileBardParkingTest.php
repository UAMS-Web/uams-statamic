<?php

declare(strict_types=1);

use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Parking instructions and directions stored as Bard content on a `clinical_locations`
 * profile (UAMS-Web/uams-statamic#1599).
 *
 * The Control Panel saves both fields as ProseMirror arrays, and the `resolved_*` computeds
 * return the stored value, so the partial printed an array and rendered an empty section.
 * Built in an isolated Stache so the computeds resolve through real entries: a parking
 * facility whose values a location inherits, a location that overrides them with its own
 * Bard content, and one that overrides them with a string, the shape the older tests use.
 *
 * @command  php artisan test --compact tests/Feature/ClinicalLocations/LocationProfileBardParkingTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * A Bard paragraph, as the Control Panel stores it.
 *
 * @return list<array<string, mixed>>
 */
function bardParkingParagraph(string $text): array
{
    return [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]];
}

/**
 * Save a collection with one blueprint holding the given fields.
 *
 * @param  array<string, array<string, mixed>>  $fields  Field configs, keyed by handle.
 */
function saveParkingCollection(string $collection, string $blueprint, array $fields): void
{
    $saved = CollectionFacade::make($collection);
    $saved->title($collection);
    $saved->save();

    $made = Blueprint::make($blueprint);
    $made->setNamespace('collections.'.$collection);
    $made->setContents(['tabs' => ['main' => ['sections' => [['fields' => array_map(
        static fn (string $handle, array $field): array => ['handle' => $handle, 'field' => $field],
        array_keys($fields),
        $fields
    )]]]]]);
    $made->save();
}

/**
 * Save an entry and return it.
 *
 * @param  array<string, mixed>  $data  The stored fields.
 */
function saveParkingEntry(string $id, string $collection, string $blueprint, array $data): StatamicEntry
{
    /** @var StatamicEntry $entry */
    $entry = Entry::make();
    $entry->id($id);
    $entry->collection($collection);
    $entry->blueprint($blueprint);
    $entry->slug($id);
    $entry->data(['title' => $id, ...$data]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * Render the profile details for a location, from its augmented values.
 */
function renderParkingProfile(string $id): string
{
    /** @var StatamicEntry $entry */
    $entry = Entry::find($id);

    // The augmented values, computeds included, as the profile view receives them
    $context = $entry->toAugmentedArray();
    if (! is_array($context)) {
        throw new UnexpectedValueException('The location did not augment to an array.');
    }

    return (string) Antlers::parse('{{ partial:clinical_locations/profile_details }}', $context);
}

beforeEach(function (): void {
    $bard = ['type' => 'bard', 'buttons' => ['italic', 'unorderedlist', 'orderedlist']];

    // A parking facility holding Bard instructions and directions.
    saveParkingCollection('facilities', 'parking_facility', [
        'title' => ['type' => 'text'],
        'parking_instructions' => $bard,
        'directions_from_parking' => $bard,
    ]);
    saveParkingEntry('deck', 'facilities', 'parking_facility', [
        'parking_instructions' => bardParkingParagraph('Park in Deck 3 INHERITED.'),
        'directions_from_parking' => [['type' => 'bulletList', 'content' => [
            ['type' => 'listItem', 'content' => bardParkingParagraph('Take the north elevator INHERITED.')],
        ]]],
    ]);

    // Standalone locations that pick the deck, each overriding its values or not.
    saveParkingCollection('clinical_locations', 'location', [
        'title' => ['type' => 'text'],
        'parking_query' => ['type' => 'toggle'],
        'parking_facility' => ['type' => 'entries', 'collections' => ['facilities'], 'max_items' => 1],
        'parking_instructions_inherit_query' => ['type' => 'toggle'],
        'parking_instructions' => $bard,
        'directions_from_parking_inherit_query' => ['type' => 'toggle'],
        'directions_from_parking' => $bard,
    ]);
    $picksDeck = ['parking_query' => true, 'parking_facility' => ['deck']];
    saveParkingEntry('inherits', 'clinical_locations', 'location', $picksDeck);
    saveParkingEntry('own-bard', 'clinical_locations', 'location', [
        ...$picksDeck,
        'parking_instructions_inherit_query' => false,
        'parking_instructions' => bardParkingParagraph('Use the surface lot OWN.'),
        'directions_from_parking_inherit_query' => false,
        'directions_from_parking' => bardParkingParagraph('Enter by the south doors OWN.'),
    ]);
    saveParkingEntry('own-string', 'clinical_locations', 'location', [
        ...$picksDeck,
        'parking_instructions_inherit_query' => false,
        'parking_instructions' => '<p>Park here STRING.</p>',
        'directions_from_parking_inherit_query' => false,
        'directions_from_parking' => '<p>Walk in STRING.</p>',
    ]);
});

it('renders inherited Bard parking instructions and directions as HTML', function (): void {
    $html = renderParkingProfile('inherits');

    expect($html)->toContain('<div class="location-parking-instructions"><p>Park in Deck 3 INHERITED.</p></div>')
        ->and($html)->toContain('<div class="location-directions-from-parking"><ul><li>Take the north elevator INHERITED.</li></ul></div>');
});

it('renders a location own Bard parking instructions and directions as HTML', function (): void {
    $html = renderParkingProfile('own-bard');

    expect($html)->toContain('<div class="location-parking-instructions"><p>Use the surface lot OWN.</p></div>')
        ->and($html)->toContain('<div class="location-directions-from-parking"><p>Enter by the south doors OWN.</p></div>')
        ->and($html)->not->toContain('INHERITED');
});

it('still renders parking values stored as a string', function (): void {
    $html = renderParkingProfile('own-string');

    expect($html)->toContain('<div class="location-parking-instructions"><p>Park here STRING.</p></div>')
        ->and($html)->toContain('<div class="location-directions-from-parking"><p>Walk in STRING.</p></div>');
});
