<?php

declare(strict_types=1);

use App\Tags\GetGlobal;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Globals\GlobalSet as GlobalSetContract;
use Statamic\Contracts\Globals\Variables as GlobalVariablesContract;
use Statamic\Facades\Antlers;
use Statamic\Facades\GlobalSet;
use Statamic\Fields\Value;
use Statamic\Globals\Variables;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for the `get_global` Antlers tag.
 *
 * Mutation: 100% on App\Tags\GetGlobal (59 mutants, --covered-only; every line covered). Two
 * equivalents are annotated in the class.
 *
 * @command  php artisan test --compact tests/Feature/Tags/GetGlobalTagTest.php
 */
it('resolves antlers for global fields with antlers enabled', function () {
    // Build global variables for the current site
    $variables = Mockery::mock(GlobalVariablesContract::class);

    // Stub the requested variable with Antlers content
    $variables->shouldReceive('get')
        ->once()
        ->with('copyright_notice')
        ->andReturn('{{ copyright_holder }}');

    // Build a blueprint fieldtype mock with antlers enabled
    $fieldtype = Mockery::mock();
    $fieldtype->shouldReceive('config')
        ->once()
        ->with('antlers')
        ->andReturn(true);

    // Build a blueprint field mock
    $field = Mockery::mock();
    $field->shouldReceive('fieldtype')
        ->once()
        ->andReturn($fieldtype);

    // Build a blueprint mock with the requested field
    $blueprint = Mockery::mock();
    $blueprint->shouldReceive('hasField')
        ->once()
        ->with('copyright_notice')
        ->andReturn(true);
    $blueprint->shouldReceive('field')
        ->once()
        ->with('copyright_notice')
        ->andReturn($field);

    // Build a global set that resolves variables and blueprint
    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('inCurrentSite')->once()->andReturn($variables);
    $set->shouldReceive('blueprint')->once()->andReturn($blueprint);

    // Stub GlobalSet facade to return the configured set
    GlobalSet::shouldReceive('findByHandle')
        ->once()
        ->with('uams_multisite_settings')
        ->andReturn($set);

    // Render the get_global tag for the Antlers-enabled field
    $output = Antlers::parse(
        '{{ get_global handle="uams_multisite_settings" var="copyright_notice" }}',
        [],
        true
    );

    // Assert the value was parsed as Antlers (raw template is no longer returned)
    expect(trim((string) $output))->toBe('');
});

it('keeps raw value for global fields without antlers enabled', function () {
    // Build global variables for the current site
    $variables = Mockery::mock(GlobalVariablesContract::class);

    // Stub the requested variable with Antlers content
    $variables->shouldReceive('get')
        ->once()
        ->with('copyright_notice')
        ->andReturn('{{ copyright_holder }}');

    // Build a blueprint fieldtype mock with antlers disabled
    $fieldtype = Mockery::mock();
    $fieldtype->shouldReceive('config')
        ->once()
        ->with('antlers')
        ->andReturn(false);

    // Build a blueprint field mock
    $field = Mockery::mock();
    $field->shouldReceive('fieldtype')
        ->once()
        ->andReturn($fieldtype);

    // Build a blueprint mock with the requested field
    $blueprint = Mockery::mock();
    $blueprint->shouldReceive('hasField')
        ->once()
        ->with('copyright_notice')
        ->andReturn(true);
    $blueprint->shouldReceive('field')
        ->once()
        ->with('copyright_notice')
        ->andReturn($field);

    // Build a global set that resolves variables and blueprint
    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('inCurrentSite')->once()->andReturn($variables);
    $set->shouldReceive('blueprint')->once()->andReturn($blueprint);

    // Stub GlobalSet facade to return the configured set
    GlobalSet::shouldReceive('findByHandle')
        ->once()
        ->with('uams_multisite_settings')
        ->andReturn($set);

    // Render the get_global tag for the field without antlers enabled
    $output = Antlers::parse(
        '{{ get_global handle="uams_multisite_settings" var="copyright_notice" }}',
        [],
        true
    );

    // Assert the output remains raw and unparsed
    expect(trim((string) $output))->toBe('{{ copyright_holder }}');
});

/**
 * A blueprint double whose `$handle` field has the given fieldtype `antlers`
 * config, or that lacks the field when `$hasField` is false.
 */
function getGlobalBlueprint(string $handle, mixed $antlers, bool $hasField = true): object
{
    $fieldtype = Mockery::mock();
    $fieldtype->shouldReceive('config')->with('antlers')->andReturn($antlers);

    $field = Mockery::mock();
    $field->shouldReceive('fieldtype')->andReturn($fieldtype);

    $blueprint = Mockery::mock();
    $blueprint->shouldReceive('hasField')->with($handle)->andReturn($hasField);
    $blueprint->shouldReceive('field')->with($handle)->andReturn($field);

    return $blueprint;
}

/**
 * A global set double, found under `$handle`, whose current-site variables are `$variables`.
 */
function getGlobalSet(mixed $variables, mixed $blueprint = null, string $handle = 'settings'): GlobalSetContract
{
    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('inCurrentSite')->andReturn($variables);
    $set->shouldReceive('blueprint')->andReturn($blueprint);
    GlobalSet::shouldReceive('findByHandle')->with($handle)->andReturn($set);

    return $set;
}

/**
 * Current-site variables whose `$var` reads as `$value`.
 */
function getGlobalVariables(string $var, mixed $value): GlobalVariablesContract
{
    $variables = Mockery::mock(GlobalVariablesContract::class);
    $variables->shouldReceive('get')->with($var)->andReturn($value);

    return $variables;
}

/**
 * Run the tag with the given parameters.
 *
 * @param  array<mixed>  $parameters
 */
function getGlobalTag(array $parameters): mixed
{
    $tag = new GetGlobal;
    $tag->setContext([]);
    $tag->setParameters($parameters);

    return $tag->index();
}

it('returns null without a handle, and never looks a set up', function (mixed $handle) {
    GlobalSet::shouldReceive('findByHandle')->never();

    expect(getGlobalTag(['handle' => $handle, 'var' => 'x']))->toBeNull();
})->with([
    'missing' => [null],
    'empty' => [''],
]);

it('returns null for a var, or an empty array for the whole set, when the set is missing', function () {
    GlobalSet::shouldReceive('findByHandle')->with('missing')->andReturnNull();

    expect(getGlobalTag(['handle' => 'missing', 'var' => 'x']))->toBeNull()
        ->and(getGlobalTag(['handle' => 'missing']))->toBe([]);
});

it('looks a non-scalar handle up as an empty one', function () {
    GlobalSet::shouldReceive('findByHandle')->once()->with('')->andReturnNull();

    expect(getGlobalTag(['handle' => ['settings']]))->toBe([]);
});

it('reads the named site instead of the current one', function () {
    $variables = getGlobalVariables('subject', 'Health');
    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')->once()->with('health')->andReturn($variables);
    $set->shouldReceive('inCurrentSite')->never();
    $set->shouldReceive('blueprint')->andReturnNull();
    GlobalSet::shouldReceive('findByHandle')->with('settings')->andReturn($set);

    expect(getGlobalTag(['handle' => 'settings', 'var' => 'subject', 'site' => 'health']))->toBe('Health');
});

it('reads a non-scalar site as an empty site handle', function () {
    $variables = getGlobalVariables('subject', 'Default');
    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')->once()->with('')->andReturn($variables);
    $set->shouldReceive('blueprint')->andReturnNull();
    GlobalSet::shouldReceive('findByHandle')->with('settings')->andReturn($set);

    expect(getGlobalTag(['handle' => 'settings', 'var' => 'subject', 'site' => ['health']]))->toBe('Default');
});

it('returns the whole set when var is missing or empty', function (array $parameters) {
    $variables = Mockery::mock(Variables::class);
    $variables->shouldReceive('all')->once()->andReturn(['subject' => 'Health']);
    getGlobalSet($variables);

    expect(getGlobalTag(['handle' => 'settings', ...$parameters]))->toBe(['subject' => 'Health']);
})->with([
    'missing' => [[]],
    'empty' => [['var' => '']],
]);

it('parses an antlers field, trimming the template and unwrapping a Value', function (mixed $value) {
    getGlobalSet(getGlobalVariables('notice', $value), getGlobalBlueprint('notice', true));

    expect(getGlobalTag(['handle' => 'settings', 'var' => 'notice']))->toBe('Copyright UAMS');
})->with([
    'string' => ['  Copyright UAMS  '],
    'value' => [fn () => new Value('  Copyright UAMS  ')],
]);

it('returns an antlers field unparsed when it is not text or is blank', function (mixed $value) {
    getGlobalSet(getGlobalVariables('notice', $value), getGlobalBlueprint('notice', true));

    expect(getGlobalTag(['handle' => 'settings', 'var' => 'notice']))->toBe($value);
})->with([
    'array' => [['a' => 1]],
    'blank' => ['   '],
    'null' => [null],
]);

it('returns the value unparsed unless the field enables antlers exactly', function (mixed $blueprint) {
    getGlobalSet(getGlobalVariables('notice', '{{ x }}'), $blueprint);

    expect(getGlobalTag(['handle' => 'settings', 'var' => 'notice']))->toBe('{{ x }}');
})->with([
    'no blueprint' => [null],
    'field missing' => [fn () => getGlobalBlueprint('notice', true, false)],
    'antlers is the string true' => [fn () => getGlobalBlueprint('notice', 'true')],
    'antlers unset' => [fn () => getGlobalBlueprint('notice', null)],
]);

it('returns the value unparsed when the blueprint has no field or fieldtype object', function () {
    $blueprint = Mockery::mock();
    $blueprint->shouldReceive('hasField')->andReturn(true);
    $blueprint->shouldReceive('field')->andReturnNull();
    getGlobalSet(getGlobalVariables('notice', '{{ x }}'), $blueprint);

    expect(getGlobalTag(['handle' => 'settings', 'var' => 'notice']))->toBe('{{ x }}');

    $field = Mockery::mock();
    $field->shouldReceive('fieldtype')->andReturnNull();
    $other = Mockery::mock();
    $other->shouldReceive('hasField')->andReturn(true);
    $other->shouldReceive('field')->andReturn($field);
    getGlobalSet(getGlobalVariables('other', '{{ y }}'), $other, 'other_settings');

    expect(getGlobalTag(['handle' => 'other_settings', 'var' => 'other']))->toBe('{{ y }}');
});

it('returns the value unparsed when reading the blueprint throws', function () {
    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('inCurrentSite')->andReturn(getGlobalVariables('notice', '{{ x }}'));
    $set->shouldReceive('blueprint')->andThrow(new RuntimeException('no blueprint'));
    GlobalSet::shouldReceive('findByHandle')->with('settings')->andReturn($set);

    expect(getGlobalTag(['handle' => 'settings', 'var' => 'notice']))->toBe('{{ x }}');
});

it('never reads the blueprint for a non-scalar var, which has no field handle', function () {
    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('inCurrentSite')->andReturn(getGlobalVariables('', '{{ x }}'));
    $set->shouldReceive('blueprint')->never();
    GlobalSet::shouldReceive('findByHandle')->with('settings')->andReturn($set);

    // Returned unparsed: with no field handle, antlers is never treated as enabled.
    expect(getGlobalTag(['handle' => 'settings', 'var' => ['notice']]))->toBe('{{ x }}');
});

it('parses a global as untrusted content, so an Antlers PHP block never runs', function () {
    getGlobalSet(getGlobalVariables('notice', 'A{{? echo "B"; ?}}C'), getGlobalBlueprint('notice', true));

    expect(getGlobalTag(['handle' => 'settings', 'var' => 'notice']))->toBe('AC');
});

it('casts numeric handle, site and var parameters to strings', function () {
    $variables = Mockery::mock(GlobalVariablesContract::class);
    $variables->shouldReceive('get')->once()->with('7')->andReturn('seven');
    $set = Mockery::mock(GlobalSetContract::class);
    // withArgs() compares strictly; with('6') would also accept the integer 6.
    $set->shouldReceive('in')->once()->withArgs(fn (mixed $site): bool => $site === '6')->andReturn($variables);
    $set->shouldReceive('blueprint')->andReturnNull();
    GlobalSet::shouldReceive('findByHandle')->once()->withArgs(fn (mixed $handle): bool => $handle === '5')->andReturn($set);

    expect(getGlobalTag(['handle' => 5, 'site' => 6, 'var' => 7]))->toBe('seven');
});
