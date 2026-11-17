<?php

declare(strict_types=1);

use App\Fieldtypes\Select;
use Tests\TestCase;

uses(TestCase::class);

covers(Select::class);

/**
 * The blueprint config the application `Select` fieldtype builds on Statamic's own:
 * the preset field before the options, the randomize and per-choice limit fields
 * after them, and `max_items` shown only for a multiple select
 * (UAMS-Web/uams-statamic#312).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/SelectFieldtypeConfigTest.php
 */

/**
 * The config sections `Select` builds, keyed by display name.
 *
 * @return array<string, array<string, mixed>>
 */
function selectConfigSections(): array
{
    $sections = new ReflectionMethod(Select::class, 'configFieldItems')->invoke(new Select);

    if (! is_array($sections)) {
        throw new UnexpectedValueException('Select::configFieldItems() did not return an array.');
    }

    $keyed = [];

    foreach ($sections as $section) {
        if (! is_array($section) || ! is_string($section['display'] ?? null) || ! is_array($section['fields'] ?? null)) {
            throw new UnexpectedValueException('A Select config section has no display name or fields.');
        }

        /** @var array<string, mixed> $fields */
        $fields = $section['fields'];
        $keyed[$section['display']] = $fields;
    }

    return $keyed;
}

it('places the preset fields before the options and the randomize and choice-limit fields after them', function (): void {
    expect(array_keys(selectConfigSections()['Selection & Options'] ?? []))->toBe([
        'preset',
        'language_scope',
        'language_standard',
        'state_types',
        'mime_type_categories',
        'options',
        'taggable',
        'randomize_order',
        'enable_choice_limits',
        'choice_limits',
        'choice_limit_message',
    ]);
});

it('shows the maximum item count only for a multiple select', function (): void {
    $maxItems = selectConfigSections()['Boundaries & Limits']['max_items'] ?? null;

    expect($maxItems)->toBeArray()
        ->and(is_array($maxItems) ? ($maxItems['if'] ?? null) : null)->toBe(['multiple' => 'equals true']);
});
