<?php

declare(strict_types=1);

use App\Support\People\TrainingAccreditorRegistry;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests for `TrainingAccreditorRegistry` config-driven, taxonomy-derived accreditor blocks.
 *
 * Mutation: 100% on App\Support\People\TrainingAccreditorRegistry (45 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Support/People/TrainingAccreditorRegistryTest.php
 */
it('resolves the configured block for a specialty term id', function (string $termId, ?string $expectedKey) {
    $expected = $expectedKey === null
        ? null
        : config("schema_templates.training_accreditors.organizations.{$expectedKey}");

    expect(TrainingAccreditorRegistry::forSpecialtyTermId($termId))->toBe($expected);
})->with([
    'acgme specialty' => ['specialties_acgme::orthopaedic-surgery', 'acgme'],
    'acgme research specialty' => ['research_specialties_acgme::immunology', 'acgme'],
    'aoa specialty' => ['specialties_aoa::family-medicine', 'aoa'],
    'non-accredited specialty' => ['specialties_non_accredited::adult-reconstructive-orthopaedic-surgery', null],
    'non-accredited research specialty' => ['research_specialties_non_accredited::bioinformatics', null],
    'other specialty' => ['other_specialties::something', null],
    'bare slug without taxonomy prefix' => ['orthopaedic-surgery', null],
    'empty string' => ['', null],
]);

it('exposes the expected shape for the ACGME block', function () {
    $block = TrainingAccreditorRegistry::forSpecialtyTermId('specialties_acgme::orthopaedic-surgery');

    expect(data_get($block, '@type'))->toBe('Organization');
    expect(data_get($block, 'alternateName'))->toBe('ACGME');
    expect(data_get($block, 'url'))->toBe('https://www.acgme.org/');
});

it('returns a decodable object with an unescaped url for an accredited specialty', function () {
    $json = TrainingAccreditorRegistry::jsonForSpecialtyTermId('specialties_acgme::orthopaedic-surgery');

    expect($json)->toBeString();
    expect($json)->toContain('https://www.acgme.org/');

    if (is_string($json)) {
        expect(json_decode($json, true))->toBe(config('schema_templates.training_accreditors.organizations.acgme'));
    }
});

it('returns null json for a non-accredited specialty', function () {
    expect(TrainingAccreditorRegistry::jsonForSpecialtyTermId('specialties_non_accredited::total-joint-replacement'))->toBeNull();
});
