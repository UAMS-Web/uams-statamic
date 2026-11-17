<?php

declare(strict_types=1);

use Statamic\Facades\Blueprint;
use Statamic\Fields\Blueprint as BlueprintInstance;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The provider-exclusion toggle on the `clinical_locations` `location` blueprint
 * (UAMS-Web/uams-statamic#773). Business Profile sync in `aether-listings-management`
 * reads it to leave a location's providers out of bulk listing updates, as WordPress's
 * `location_gmb_exclude_providers` did. The handle follows the rule that made
 * `location_gmb_exclude` into `google_business_profile_exclude_query`, and the toggle
 * sits beside that one in the Google Business Profile Details section.
 *
 * @command php artisan test --compact tests/Feature/Blueprints/ClinicalLocationExcludeProvidersFieldTest.php
 */
const EXCLUDE_PROVIDERS_HANDLE = 'google_business_profile_exclude_providers_query';

/** The resolved `location` blueprint. */
function excludeProvidersBlueprint(): BlueprintInstance
{
    return mustBe(BlueprintInstance::class, Blueprint::find('collections/clinical_locations/location'), 'the location blueprint');
}

/** The provider-exclusion field, resolved through its fieldset import. */
function excludeProvidersField(): Field
{
    return mustBe(Field::class, excludeProvidersBlueprint()->field(EXCLUDE_PROVIDERS_HANDLE), 'the provider-exclusion field');
}

it('adds a required yes-or-no toggle that defaults to including the providers', function (): void {
    $field = excludeProvidersField();

    expect($field->type())->toBe('true_false')
        ->and($field->defaultValue())->toBeFalse()
        ->and($field->rules())->toBe([EXCLUDE_PROVIDERS_HANDLE => ['required']])
        ->and($field->display())->toBe('Should providers at this location be excluded from any bulk imports and updates in Google Business Profiles?');
});

it("places the toggle directly after the location's own exclusion toggle, in the same section", function (): void {
    $source = mustBeString(file_get_contents(resource_path('blueprints/collections/clinical_locations/location.yaml')), 'the location blueprint source');

    preg_match_all('/^\s+handle: (\S+)$/m', $source, $matches);
    $handles = $matches[1];
    $position = array_search('google_business_profile_exclude_query', $handles, true);

    // Next in the file, and the section header (`display:` at section depth) cannot fall between them
    expect($position)->toBeInt()
        ->and($handles[(int) $position + 1] ?? null)->toBe(EXCLUDE_PROVIDERS_HANDLE)
        ->and($source)->toMatch('/handle: google_business_profile_exclude_query\n(?:            .*\n|              .*\n|                .*\n)*          -\n            handle: '.EXCLUDE_PROVIDERS_HANDLE.'\n/');
});

// `EntryData::isOn()` in aether-listings-management reads the stored value and counts
// only `true`, `1`, `'1'` and `'true'` as on, so a switched-on toggle must store `true`
it('stores a switched-on toggle as true and a switched-off one as false', function (mixed $submitted, bool $stored): void {
    $field = excludeProvidersField();
    $field->setValue($submitted);

    expect(mustBe(Field::class, $field->process(), 'the processed field')->value())->toBe($stored);
})->with([
    'on' => [true, true],
    'on, as the form posts it' => ['true', true],
    'off' => [false, false],
    'off, as the form posts it' => ['false', false],
]);
