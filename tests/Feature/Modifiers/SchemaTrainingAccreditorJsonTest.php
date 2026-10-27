<?php

declare(strict_types=1);

use App\Modifiers\SchemaTrainingAccreditorJson;
use Statamic\Fields\Value;
use Tests\TestCase;

covers(SchemaTrainingAccreditorJson::class);

uses(TestCase::class);

/**
 * Behavior of the `schema_training_accreditor_json` Antlers modifier
 * (App\Modifiers\SchemaTrainingAccreditorJson): resolve a specialty term's
 * canonical `taxonomy::slug` id to its accreditor block JSON (via
 * TrainingAccreditorRegistry), unwrapping a Value wrapper and trimming first,
 * and emit an empty string for non-accredited or non-string input.
 *
 * Mutation: tracked under App\Modifiers\SchemaTrainingAccreditorJson (--covered-only).
 *
 * @command php artisan test --compact tests/Feature/Modifiers/SchemaTrainingAccreditorJsonTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

it('returns the accreditor JSON for an accredited specialty', function () {
    $result = (new SchemaTrainingAccreditorJson)->index('specialties_acgme::orthopaedic-surgery', [], []);

    expect(json_decode($result, true))
        ->toBe(config('schema_templates.training_accreditors.organizations.acgme'));
});

it('unwraps a Value wrapper before resolving', function () {
    $result = (new SchemaTrainingAccreditorJson)->index(
        new Value('specialties_acgme::orthopaedic-surgery'),
        [],
        []
    );

    expect(json_decode($result, true))
        ->toBe(config('schema_templates.training_accreditors.organizations.acgme'));
});

it('trims surrounding whitespace before resolving', function () {
    $result = (new SchemaTrainingAccreditorJson)->index('  specialties_acgme::orthopaedic-surgery  ', [], []);

    expect(json_decode($result, true))
        ->toBe(config('schema_templates.training_accreditors.organizations.acgme'));
});

it('returns an empty string for non-accredited or non-string input', function (mixed $value) {
    expect((new SchemaTrainingAccreditorJson)->index($value, [], []))->toBe('');
})->with([
    'non-accredited specialty' => ['specialties_non_accredited::total-joint-replacement'],
    'bare value without a taxonomy prefix' => ['orthopaedic-surgery'],
    'null' => [null],
    'integer' => [42],
]);
