<?php

declare(strict_types=1);

use App\Tags\SchemaOrgTermUris;
use Statamic\Facades\Antlers;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Statamic\Tags\Parameters;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for the `schema_org_term_uris` Antlers tag.
 *
 * @command  php artisan test --compact tests/Feature/Tags/SchemaOrgTermUrisTest.php
 */
function renderTermUris(mixed $terms): mixed
{
    return json_decode(trim((string) Antlers::parse(
        '{{ schema_org_term_uris :terms="terms" }}',
        ['terms' => $terms],
        true
    )), true);
}

it('exposes schema_org_term_uris as the tag handle', function (): void {
    expect(SchemaOrgTermUris::handle())->toBe('schema_org_term_uris');
});

it('normalizes a list of bare term names to full schema.org URIs', function (): void {
    expect(renderTermUris(['Cardiovascular', 'Dermatology']))->toBe([
        'https://schema.org/Cardiovascular',
        'https://schema.org/Dermatology',
    ]);
});

it('emits a one-element array for a single term', function (): void {
    expect(renderTermUris(['Pediatric']))->toBe(['https://schema.org/Pediatric']);
});

it('reads names from the augmented multi-select shape', function (): void {
    $terms = [
        ['key' => 'Cardiovascular', 'value' => 'Cardiovascular', 'label' => 'Cardiovascular'],
        ['key' => 'Oncologic', 'value' => 'Oncologic', 'label' => 'Oncologic'],
    ];

    expect(renderTermUris($terms))->toBe([
        'https://schema.org/Cardiovascular',
        'https://schema.org/Oncologic',
    ]);
});

it('de-duplicates repeated terms while preserving order', function (): void {
    expect(renderTermUris(['Cardiovascular', 'Dermatology', 'Cardiovascular']))->toBe([
        'https://schema.org/Cardiovascular',
        'https://schema.org/Dermatology',
    ]);
});

it('preserves an already-qualified schema.org URI', function (): void {
    expect(renderTermUris(['https://schema.org/Cardiovascular']))->toBe([
        'https://schema.org/Cardiovascular',
    ]);
});

it('emits nothing for an empty list', function (): void {
    expect(trim((string) Antlers::parse(
        '{{ schema_org_term_uris :terms="terms" }}',
        ['terms' => []],
        true
    )))->toBe('');
});

it('emits nothing for a non-array value', function (): void {
    expect(trim((string) Antlers::parse(
        '{{ schema_org_term_uris :terms="terms" }}',
        ['terms' => 'Cardiovascular'],
        true
    )))->toBe('');
});

it('skips blank entries', function (): void {
    expect(renderTermUris(['Cardiovascular', '', '   ']))->toBe([
        'https://schema.org/Cardiovascular',
    ]);
});

/**
 * The tag's output for params built as given, without Parameters::make().
 *
 * make(), the route setParameters() takes, already unwraps a top-level Value, so
 * the tag's own unwrap of `terms` is reached only by params built directly.
 *
 * @param  array<string, mixed>  $parameters
 */
function termUrisFromParams(array $parameters): string
{
    $tag = new SchemaOrgTermUris;
    $tag->setContext([]);
    $tag->params = new Parameters($parameters);

    return $tag->index();
}

it('unwraps a terms list passed inside a Value', function (): void {
    expect(termUrisFromParams(['terms' => new Value(['Cardiovascular'])]))
        ->toBe('["https://schema.org/Cardiovascular"]');
});

it('reads names from Value and LabeledValue elements', function (): void {
    expect(renderTermUris([
        new Value('Cardiovascular'),
        new LabeledValue('Dermatology', 'Skin'),
        new Value(new LabeledValue('Oncologic', 'Cancer')),
    ]))->toBe([
        'https://schema.org/Cardiovascular',
        'https://schema.org/Dermatology',
        'https://schema.org/Oncologic',
    ]);
});

it('falls back to the option key, and trims names read from a row', function (): void {
    expect(renderTermUris([
        ['key' => 'Cardiovascular', 'label' => 'Heart'],
        ['value' => '  Dermatology  '],
    ]))->toBe([
        'https://schema.org/Cardiovascular',
        'https://schema.org/Dermatology',
    ]);
});

it('skips rows with no usable name and elements of any other type', function (): void {
    expect(renderTermUris([
        ['label' => 'No name'],
        ['value' => '   '],
        ['value' => 42],
        42,
        null,
        'Pediatric',
    ]))->toBe(['https://schema.org/Pediatric']);
});

it('emits nothing when every element is skipped', function (): void {
    expect(termUrisFromParams(['terms' => [42, null, '']]))->toBe('');
});

it('trims a plain string name before normalizing it', function (): void {
    expect(renderTermUris(['  Pediatric  ']))->toBe(['https://schema.org/Pediatric']);
});

it('emits the URIs with slashes and non-ASCII characters unescaped', function (): void {
    expect(termUrisFromParams(['terms' => ['https://schema.org/Cardiovascular', 'Café']]))
        ->toBe('["https://schema.org/Cardiovascular","https://schema.org/Café"]');
});

it('emits nothing when the URIs cannot be encoded as JSON', function (): void {
    // Invalid UTF-8 survives normalization into the URI and makes json_encode fail.
    expect(termUrisFromParams(['terms' => ["Bad\xB1Name"]]))->toBe('');
});
