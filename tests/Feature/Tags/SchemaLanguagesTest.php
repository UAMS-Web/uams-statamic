<?php

declare(strict_types=1);

use App\Tags\SchemaLanguages;
use Statamic\Facades\Antlers;
use Statamic\Fields\LabeledValue;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for the `schema_languages` Antlers tag.
 *
 * @command  php artisan test --compact tests/Feature/Tags/SchemaLanguagesTest.php
 */
function renderLanguages(mixed $languages): mixed
{
    return json_decode(trim((string) Antlers::parse(
        '{{ schema_languages :languages="languages" }}',
        ['languages' => $languages],
        true
    )), true);
}

it('exposes schema_languages as the tag handle', function (): void {
    expect(SchemaLanguages::handle())->toBe('schema_languages');
});

it('maps the augmented multi-select shape to Language nodes', function (): void {
    $languages = [
        ['key' => 'es', 'value' => 'es', 'label' => 'Spanish, Castilian'],
        ['key' => 'vi', 'value' => 'vi', 'label' => 'Vietnamese'],
    ];

    expect(renderLanguages($languages))->toBe([
        [
            '@type' => 'https://schema.org/Language',
            'name' => 'Spanish, Castilian',
            'alternateName' => 'es',
        ],
        [
            '@type' => 'https://schema.org/Language',
            'name' => 'Vietnamese',
            'alternateName' => 'vi',
        ],
    ]);
});

it('maps a LabeledValue to a Language node', function (): void {
    expect(renderLanguages([new LabeledValue('fr', 'French')]))->toBe([
        [
            '@type' => 'https://schema.org/Language',
            'name' => 'French',
            'alternateName' => 'fr',
        ],
    ]);
});

it('emits a code-only Language node for a plain code string', function (): void {
    expect(renderLanguages(['en']))->toBe([
        [
            '@type' => 'https://schema.org/Language',
            'alternateName' => 'en',
        ],
    ]);
});

it('omits the name when the label equals the code', function (): void {
    $languages = [['key' => 'en', 'value' => 'en', 'label' => 'en']];

    expect(renderLanguages($languages))->toBe([
        [
            '@type' => 'https://schema.org/Language',
            'alternateName' => 'en',
        ],
    ]);
});

it('de-duplicates repeated language codes', function (): void {
    $languages = [
        ['key' => 'es', 'value' => 'es', 'label' => 'Spanish, Castilian'],
        ['key' => 'es', 'value' => 'es', 'label' => 'Spanish, Castilian'],
    ];

    expect(renderLanguages($languages))->toHaveCount(1);
});

it('emits nothing for an empty list', function (): void {
    expect(trim((string) Antlers::parse(
        '{{ schema_languages :languages="languages" }}',
        ['languages' => []],
        true
    )))->toBe('');
});

it('emits nothing for a non-array value', function (): void {
    expect(trim((string) Antlers::parse(
        '{{ schema_languages :languages="languages" }}',
        ['languages' => 'es'],
        true
    )))->toBe('');
});
