<?php

declare(strict_types=1);

use Tests\TestCase;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Radio;

uses(TestCase::class);

covers(Radio::class);

/**
 * The blueprint config the application `Radio` fieldtype builds on Statamic's own:
 * the preset and "other" option fields before the options, and the randomize and
 * per-choice limit fields after them (UAMS-Web/uams-statamic#312).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/RadioFieldtypeConfigTest.php
 */

/**
 * The field handles of each config section `Radio` builds, keyed by display name.
 *
 * @return array<string, list<string>>
 */
function radioConfigSectionHandles(): array
{
    $sections = new ReflectionMethod(Radio::class, 'configFieldItems')->invoke(new Radio);

    if (! is_array($sections)) {
        throw new UnexpectedValueException('Radio::configFieldItems() did not return an array.');
    }

    $handles = [];

    foreach ($sections as $section) {
        if (! is_array($section) || ! is_string($section['display'] ?? null) || ! is_array($section['fields'] ?? null)) {
            throw new UnexpectedValueException('A Radio config section has no display name or fields.');
        }

        $handles[$section['display']] = array_map(strval(...), array_keys($section['fields']));
    }

    return $handles;
}

it('places the preset and other-option fields before the options and the randomize and choice-limit fields after them', function (): void {
    expect(radioConfigSectionHandles()['Selection & Options'] ?? [])->toBe([
        'preset',
        'language_scope',
        'language_standard',
        'state_types',
        'mime_type_categories',
        'enable_other',
        'other_label',
        'other_placeholder',
        'options',
        'randomize_order',
        'enable_choice_limits',
        'choice_limits',
        'choice_limit_message',
    ]);
});
