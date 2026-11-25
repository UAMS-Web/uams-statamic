<?php

declare(strict_types=1);

use App\Http\Controllers\CP\SchemaObjectReplicatorDataController;
use App\Services\SchemaObject\SchemaPropertyReplicatorSets;
use App\Tags\Schema\SchemaPropertyValidator;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Statamic\Auth\File\User as FileStatamicUser;
use Statamic\Facades\User;
use Tests\Support\StacheIsolatedTestCase;
use UAMSWeb\Aether\Enums\SchemaProperty;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withoutMiddleware;

uses(StacheIsolatedTestCase::class);

covers(SchemaObjectReplicatorDataController::class);

/**
 * `SchemaObjectReplicatorDataController` answers the schema_object fieldtype with the
 * replicator config and meta for each property of the selected schema types
 * (UAMS-Web/uams-statamic#848).
 *
 * The acting user is never saved, so nothing is written to the `users` submodule.
 *
 * @command  php artisan test --compact tests/Feature/Http/Controllers/CP/SchemaObjectReplicatorDataControllerTest.php
 */
beforeEach(function (): void {
    withoutMiddleware(PreventRequestForgery::class);
    config(['schema_object.property_blacklist' => []]);

    $user = mustBe(FileStatamicUser::class, User::make(), 'the acting user');
    $user->id('replicator-data-user');
    $user->email('replicator-data@example.test');
    $user->makeSuper();

    actingAs($user);
});

/**
 * The decoded answer to a request with the given input.
 *
 * @param  array<mixed>  $input
 * @return array<mixed>
 */
function replicatorDataFor(array $input): array
{
    $response = postJson(mustBeString(cp_route('schema-object.replicator-data'), 'the replicator data route'), $input);
    $response->assertOk();

    return mustBeArray($response->json(), 'the answer');
}

/**
 * The property names the answer lists for a request with the given input.
 *
 * @param  array<mixed>  $input
 */
function replicatorPropertyNames(array $input): mixed
{
    return replicatorDataFor($input)['property_names'] ?? null;
}

/**
 * A value as it reads after a trip through JSON.
 */
function replicatorDataJson(mixed $value): mixed
{
    return json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
}

it('answers each property with its replicator config and meta', function (): void {
    $answer = replicatorDataFor(['types' => ['Thing']]);
    $names = SchemaPropertyValidator::validPropertiesForType('Thing');
    $service = new SchemaPropertyReplicatorSets;

    $config = [];
    $meta = [];
    foreach ($names as $name) {
        $sets = $service->setsForRangeTypes(mustBe(SchemaProperty::class, SchemaProperty::tryFrom($name), $name)->rangeTypes());
        $config[$name] = ['sets' => $service->configSetsForVue($sets), 'button_label' => 'Add Value', 'collapse' => true];
        $meta[$name] = $service->metaForSets($sets);
    }

    expect($names)->toContain('name', 'url', 'image')
        ->and($answer)->toBe(replicatorDataJson([
            'config_by_property' => $config,
            'meta_by_property' => $meta,
            'property_names' => $names,
        ]))
        // Range types differ by property, so the sets do too
        ->and($config['name']['sets'])->not->toEqual($config['image']['sets']);
});

it('offers a property with no schema property case as text', function (): void {
    // The class name falls back to a Spatie type, whose deprecated setter has no case
    $answer = replicatorDataFor(['types' => ['\\Dataset']]);
    $service = new SchemaPropertyReplicatorSets;
    $sets = $service->setsForRangeTypes(['Text']);

    // `variablesMeasured` is not a case of SchemaProperty
    expect(array_column(SchemaProperty::cases(), 'value'))->not->toContain('variablesMeasured')
        ->and($answer['property_names'] ?? null)->toContain('variablesMeasured')
        ->and(data_get($answer, 'config_by_property.variablesMeasured.sets'))->toBe(replicatorDataJson($service->configSetsForVue($sets)))
        ->and(data_get($answer, 'meta_by_property.variablesMeasured'))->toBe(replicatorDataJson($service->metaForSets($sets)));
});

it('answers with nothing when no type is named', function (array $input): void {
    expect(replicatorDataFor($input))->toBe([
        'config_by_property' => [],
        'meta_by_property' => [],
        'property_names' => [],
    ]);
})->with([
    'no input' => [[]],
    'an empty list' => [['types' => []]],
    'a list of nothing usable' => [['types' => ['', 5, null, ['Thing']]]],
    'an empty type' => [['type' => '']],
    'a type that is not a string' => [['type' => 5]],
    'types that are not a list' => [['types' => 'Thing']],
    'a type nothing knows' => [['type' => 'NoSuchSchemaType']],
]);

it('reads one type from the older input, unless a list is given', function (): void {
    $thing = SchemaPropertyValidator::validPropertiesForType('Thing');

    expect(replicatorPropertyNames(['type' => 'Thing']))->toBe($thing)
        ->and(replicatorPropertyNames(['types' => 'Place', 'type' => 'Thing']))->toBe($thing)
        ->and(replicatorPropertyNames(['types' => [], 'type' => 'Thing']))->toBe([]);
});

it('reads the types in order, once each, skipping what is not a type name', function (): void {
    expect(replicatorPropertyNames(['types' => ['', 'Rating', 5, 'Thing', 'Rating', 'Thing']]))
        ->toBe(replicatorPropertyNames(['types' => ['Rating', 'Thing']]));
});

it('lists each type\'s properties in turn, each property once', function (): void {
    $thing = SchemaPropertyValidator::validPropertiesForType('Thing');
    $rating = SchemaPropertyValidator::validPropertiesForType('Rating');

    expect(array_diff($rating, $thing))->not->toBe([])
        ->and(replicatorPropertyNames(['types' => ['Thing', 'Rating']]))->toBe([...$thing, ...array_values(array_diff($rating, $thing))])
        ->and(replicatorPropertyNames(['types' => ['Rating', 'Thing']]))->toBe($rating);
});

it('hides the properties the configuration hides for every type, and for any selected type', function (): void {
    config(['schema_object.property_blacklist' => [
        'general' => ['name', 'sameAs'],
        'specific' => ['Rating' => ['url', 'author'], 'Thing' => ['image'], 'Person' => ['description']],
    ]]);

    $thing = SchemaPropertyValidator::validPropertiesForType('Thing');
    $rating = SchemaPropertyValidator::validPropertiesForType('Rating');

    expect(replicatorPropertyNames(['types' => ['Thing']]))->toBe(array_values(array_diff($thing, ['name', 'sameAs', 'image'])))
        ->and(replicatorPropertyNames(['types' => ['Rating']]))->toBe(array_values(array_diff($rating, ['name', 'sameAs', 'url', 'author'])))
        ->and(replicatorPropertyNames(['types' => ['Thing', 'Rating']]))->toBe([
            ...array_values(array_diff($thing, ['name', 'sameAs', 'url', 'author', 'image'])),
            ...array_values(array_diff($rating, $thing, ['author'])),
        ]);
});

it('hides nothing when the configuration has no blacklist, or an incomplete one', function (array $config): void {
    config(['schema_object' => $config]);

    expect(replicatorPropertyNames(['types' => ['Thing']]))->toBe(SchemaPropertyValidator::validPropertiesForType('Thing'));
})->with([
    'no blacklist' => [[]],
    'no general list' => [['property_blacklist' => ['specific' => ['Person' => ['name']]]]],
    'no lists' => [['property_blacklist' => []]],
]);

it('hides the general properties when no type has a list of its own', function (): void {
    config(['schema_object.property_blacklist' => ['general' => ['name']]]);

    expect(replicatorPropertyNames(['types' => ['Thing']]))
        ->toBe(array_values(array_diff(SchemaPropertyValidator::validPropertiesForType('Thing'), ['name'])));
});

it('hides a type\'s own properties when there is no general list', function (): void {
    config(['schema_object.property_blacklist' => ['specific' => ['Thing' => ['name']]]]);

    expect(replicatorPropertyNames(['types' => ['Thing']]))
        ->toBe(array_values(array_diff(SchemaPropertyValidator::validPropertiesForType('Thing'), ['name'])));
});
