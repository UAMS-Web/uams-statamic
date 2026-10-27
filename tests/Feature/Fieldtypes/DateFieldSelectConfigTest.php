<?php

declare(strict_types=1);

use App\Fieldtypes\Date;
use App\Fieldtypes\DateFieldSelect;
use Statamic\Fields\Field;
use Tests\TestCase;

/**
 * UAMS-Web/uams-statamic-archive#1393 — the custom Date fieldtype's `earliest_date_field` / `latest_date_field`
 * boundary selects are backed by the CP-only `date_field_select` fieldtype, whose
 * Vue component populates the options from the blueprint's other date fields
 * client-side. The OPTION DERIVATION is unit-tested in
 * `resources/js/date-field-options.test.js`; these guard the PHP wiring — the
 * config points at the fieldtype, and the fieldtype is config-only.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/DateFieldSelectConfigTest.php
 */
uses(TestCase::class);

covers(DateFieldSelect::class);

/**
 * Find a config field by handle across all of the Date fieldtype's config sections.
 *
 * @return array<string, mixed>|null
 */
function dateConfigField(string $handle): ?array
{
    $reflection = new ReflectionMethod(Date::class, 'configFieldItems');
    /** @var array<string, array<string, mixed>> $sections */
    $sections = $reflection->invoke(new Date);

    foreach ($sections as $section) {
        $fields = $section['fields'] ?? null;

        if (\is_array($fields) && isset($fields[$handle]) && \is_array($fields[$handle])) {
            /** @var array<string, mixed> $field */
            $field = $fields[$handle];

            return $field;
        }
    }

    return null;
}

it('backs the boundary field selects with the date_field_select fieldtype', function (string $handle): void {
    $field = dateConfigField($handle);

    expect($field)->not->toBeNull();
    /** @var array<string, mixed> $field */
    expect($field['type'])->toBe('date_field_select');
    // The options are populated client-side, so no empty server-side options list
    // should linger (the dead `options: []` that left the select blank — UAMS-Web/uams-statamic-archive#1393).
    expect($field)->not->toHaveKey('options');
})->with([
    'earliest' => ['earliest_date_field'],
    'latest' => ['latest_date_field'],
]);

it('keeps the field-type `if` gate and required-if validation on the selects', function (string $handle, string $typeKey): void {
    $field = dateConfigField($handle);

    /** @var array<string, mixed> $field */
    expect($field['if'])->toBe([$typeKey => 'field']);
    expect($field['validate'])->toContain("required_if:{$typeKey},field");
})->with([
    'earliest' => ['earliest_date_field', 'earliest_date_type'],
    'latest' => ['latest_date_field', 'latest_date_type'],
]);

it('resolves the date_field_select type to the config-only fieldtype', function (): void {
    $fieldtype = (new Field('boundary_field', ['type' => 'date_field_select']))->fieldtype();

    expect($fieldtype)->toBeInstanceOf(DateFieldSelect::class);
});

it('keeps date_field_select out of the fieldtype selectors (config-only)', function (): void {
    $fieldtype = new DateFieldSelect;

    expect($fieldtype->selectable())->toBeFalse();
    expect($fieldtype->selectableInForms())->toBeFalse();
});
