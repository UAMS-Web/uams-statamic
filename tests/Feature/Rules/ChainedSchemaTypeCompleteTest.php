<?php

declare(strict_types=1);

use App\Fieldtypes\SchemaType;
use App\Rules\ChainedSchemaTypeComplete;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Translation\PotentiallyTranslatedString;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

covers(ChainedSchemaTypeComplete::class);

/**
 * Behavior of `ChainedSchemaTypeComplete`: a chained schema_type value passes
 * when its drill-down is resolved (nothing chosen, a leaf, or the stop sentinel
 * set at a non-leaf) and fails with the chained-incomplete message otherwise.
 *
 * @command  php artisan test --compact tests/Feature/Rules/ChainedSchemaTypeCompleteTest.php
 */

/**
 * Run the rule for a chained field starting at the given types and return every
 * message it failed with.
 *
 * @param  array<mixed>  $startingTypes
 * @return list<string>
 */
function chainedSchemaTypeMessages(array $startingTypes, mixed $value): array
{
    $fieldtype = new SchemaType;
    $fieldtype->setField(new Field('type', [
        'type' => 'schema_type',
        'mode' => 'chained',
        'starting_types' => $startingTypes,
    ]));

    $messages = [];

    (new ChainedSchemaTypeComplete($fieldtype))->validate(
        'type',
        $value,
        function (string $message, ?string $second = null) use (&$messages): PotentiallyTranslatedString {
            $messages[] = $message;

            return new PotentiallyTranslatedString($message, app(Translator::class));
        }
    );

    return $messages;
}

it('passes a resolved chain without failing', function (array $startingTypes, mixed $value): void {
    expect(chainedSchemaTypeMessages($startingTypes, $value))->toBe([]);
})->with([
    'nothing selected' => [['Thing'], ['selections' => []]],
    'null value' => [['Thing'], null],
    'stop sentinel at a non-leaf' => [['Thing'], ['selections' => ['Thing'], 'value' => 'Thing', 'chain_complete' => true]],
    'enumeration leaf' => [['MedicalSpecialty'], ['selections' => ['MedicalSpecialty', 'Anesthesia'], 'value' => 'Anesthesia']],
]);

it('fails an unresolved chain with the chained-incomplete message', function (array $startingTypes, mixed $value): void {
    expect(chainedSchemaTypeMessages($startingTypes, $value))
        ->toBe([__('statamic::validation.schema_type.chained_incomplete')]);
})->with([
    'non-leaf without the stop sentinel' => [['Thing'], ['selections' => ['Thing'], 'value' => 'Thing']],
    'non-leaf with the stop sentinel off' => [['Thing'], ['selections' => ['Thing'], 'value' => 'Thing', 'chain_complete' => false]],
]);

it('fails through the validator with the same message', function (): void {
    $fieldtype = new SchemaType;
    $fieldtype->setField(new Field('type', [
        'type' => 'schema_type',
        'mode' => 'chained',
        'starting_types' => ['Thing'],
    ]));

    $validator = validator(
        ['type' => ['selections' => ['Thing'], 'value' => 'Thing']],
        ['type' => [new ChainedSchemaTypeComplete($fieldtype)]]
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('type'))->toBe(__('statamic::validation.schema_type.chained_incomplete'));
});
