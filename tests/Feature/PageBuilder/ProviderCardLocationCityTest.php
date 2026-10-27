<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint as BlueprintFacade;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The location name and city on a provider archive card (UAMS-Web/uams-statamic-archive#1225).
 *
 * Template-only. The city's RESOLUTION — standalone, contained within a facility, or nested
 * under a parent location — belongs to `LocationInheritedAddressService` and is covered by
 * its own suite; what is checked here is what the card does with the value it is handed,
 * including the case the ticket calls out: no orphaned separator when there is no city.
 *
 * The context is supplied directly rather than built from entries because the
 * `clinical_locations` collection is empty in a fresh checkout, and standing up a location
 * tree would test the resolver a second time rather than this partial.
 *
 * @command  php artisan test --compact tests/Feature/PageBuilder/ProviderCardLocationCityTest.php
 */

/**
 * Render the provider card with a given set of associated locations.
 *
 * @param  list<array<string, mixed>>  $locations  Location rows as the augmented relation yields them
 * @return string The rendered markup
 */
function providerCardRender(array $locations): string
{
    $context = [
        'display_name_full_with_credentials' => ['text' => 'Jane Doe, MD'],
        'clinical_locations' => $locations,
    ];

    return (string) Antlers::parse(
        '{{ partial:page_builder/components/fad_provider_card }}',
        $context,
        true
    );
}

it('depends on clinical_locations being an entries field, so it augments', function () {
    // The card reads `clinical_locations` as augmented rows -- iterating for a title and
    // plucking for the gate. That only holds because the person blueprint declares the
    // relation as an `entries` field; a collection that does not declare it leaves the value
    // a raw array of id strings, and `pluck` then calls `->get()` on a string.
    //
    // Asserted because nothing else pins it and the failure is remote from the cause: the
    // card throws a ViewException from inside a core modifier, naming neither this partial
    // nor the blueprint.
    $blueprint = mustBe(
        Blueprint::class,
        BlueprintFacade::find('collections/people/person'),
        'the people person blueprint'
    );

    $field = $blueprint->fields()->all()->get('clinical_locations');

    expect($field)->not->toBeNull('the person blueprint must declare clinical_locations');

    /** @var Field $field */
    expect($field->type())->toBe('entries');
});

it('shows each location name with its city beside it', function () {
    $html = providerCardRender([
        ['id' => 'loc1', 'title' => 'Family Medical Center', 'resolved_city' => 'Fort Smith'],
    ]);

    expect($html)->toContain('Family Medical Center')
        ->and($html)->toContain('Fort Smith')
        ->and($html)->toContain('location-separator');
});

it('renders the name alone, with no orphaned separator, when there is no city', function () {
    // The ticket's second criterion. A location with no resolvable city must not leave a
    // dangling dash behind its name.
    $html = providerCardRender([
        ['id' => 'loc1', 'title' => 'Annex Clinic', 'resolved_city' => null],
    ]);

    expect($html)->toContain('Annex Clinic')
        ->and($html)->not->toContain('location-separator')
        ->and($html)->not->toContain('&#8212;');
});

it('shows a city beside each of several locations, and a separator only where there is one', function () {
    // The third criterion, plus the mixed case that a single-location test cannot reach:
    // one location with a city and one without, in the same card.
    $html = providerCardRender([
        ['id' => 'loc1', 'title' => 'Family Medical Center', 'resolved_city' => 'Fort Smith'],
        ['id' => 'loc2', 'title' => 'Annex Clinic', 'resolved_city' => null],
        ['id' => 'loc3', 'title' => 'North Clinic', 'resolved_city' => 'Springdale'],
    ]);

    expect(substr_count($html, 'card-location"'))->toBe(3)
        ->and(substr_count($html, 'location-separator'))->toBe(2)
        ->and($html)->toContain('Springdale');
});

it('renders no list at all for a provider with no locations', function () {
    $html = providerCardRender([]);

    expect($html)->not->toContain('card-locations')
        ->and($html)->toContain('Jane Doe, MD');
});

it('escapes a location name and a city carrying markup', function () {
    // Both are editor-controlled, and Antlers is raw by default.
    $html = providerCardRender([
        [
            'id' => 'loc1',
            'title' => 'Clinic <img src=x onerror=alert(1)>',
            'resolved_city' => 'Town <script>alert(2)</script>',
        ],
    ]);

    expect($html)->not->toContain('<img src=x')
        ->and($html)->not->toContain('<script>alert(2)');
});
