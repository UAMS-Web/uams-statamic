<?php

declare(strict_types=1);

use Statamic\Facades\Blueprint;
use Statamic\Facades\Term;
use Statamic\Fields\Field;
use Statamic\Fields\Value;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\TestCase;

/**
 * Regression — the `official_website` Grid field on the `specialties_aoa` term
 * blueprint must not carry a scalar-string `default`.
 *
 * `official_website` resolves to `uamsweb_common.url_grid` (a `grid` fieldtype,
 * whose value is an array of URL rows). It previously carried
 * `default: 'https://certification.osteopathic.org/…'` — a bare string. When a
 * term is augmented with no *saved* value, that scalar default fills in and
 * `Grid::performAugmentation()` runs `collect('https://…')->map(fn ($row) =>
 * …->addValues($row))`, feeding a string where `Fields::addValues(array)` demands
 * an array → `TypeError`. This crashed JSON-LD emission for any AOA specialty term
 * lacking a saved `official_website` (surfaced by UAMS-Web/uams-statamic-archive#1067's `relevantSpecialty`).
 *
 * @command php artisan test --compact tests/Feature/SpecialtyAoaGridDefaultTest.php
 */
uses(TestCase::class);

it('augments a specialties_aoa term with no saved official_website without throwing', function () {
    /** @var Statamic\Taxonomies\Term $term */
    $term = Term::make();
    $term->taxonomy('specialties_aoa');
    $term->slug('regression-probe-spec');
    /** @var LocalizedTerm $localizedTerm */
    $localizedTerm = $term->inDefaultLocale();
    $localizedTerm->data(['title' => 'Regression Probe Specialty']);

    /** @var LocalizedTerm $termInHealth */
    $termInHealth = $term->in('health');

    // Grid augmentation on the (unsaved → default-filled) field must not throw,
    // and must resolve to an array of rows — never a scalar string.
    /** @var Value $augmented */
    $augmented = $termInHealth->augmentedValue('official_website');
    $value = $augmented->value();

    expect($value)->toBeArray();
});

it('keeps the official_website Grid default free of a scalar-string value', function () {
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::find('taxonomies.specialties_aoa.specialty_aoa');

    /** @var Field $field */
    $field = $blueprint->fields()->all()->get('official_website');

    expect($field)->not->toBeNull()
        ->and($field->type())->toBe('grid')
        // A Grid default must be null or an array of rows; a string is malformed.
        ->and($field->defaultValue())->not->toBeString();
});
