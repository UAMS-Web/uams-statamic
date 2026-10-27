<?php

declare(strict_types=1);

use App\Fieldtypes\Checkboxes;
use App\Fieldtypes\Radio;
use App\Fieldtypes\Select;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Statamic\Fields\Fieldtype;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Dynamic Default Value on the choice fieldtypes (UAMS-Web/uams-statamic-archive#2886, UAMS-Web/uams-statamic-archive#2924).
 *
 * UAMS-Web/uams-statamic-archive#2886 wired the setting through the public-form templates for a single Select and
 * for Radio, and removed it from Checkboxes, whose list binding calculation-engine.js
 * did not write. UAMS-Web/uams-statamic-archive#2924 teaches the engine a list field, emits the attributes on every
 * checkbox and on a multiple Select, and offers the setting on Checkboxes again. These
 * pin both halves: the templates emit the `data-calculation-*` attributes the engine
 * scans for, and the Control Panel offers the setting on exactly the fieldtypes whose
 * template applies it. The live effect in a browser is pinned by
 * tests/Browser/ChoiceDynamicDefaultPublicFormTest.php.
 *
 * @command  php artisan test --compact tests/Feature/Views/FormsFieldsChoiceDynamicDefaultTest.php
 */

/**
 * Render one choice-field template with a minimal field context.
 *
 * @param  array<string, mixed>  $overrides  Context keys to set on the field.
 */
function renderChoiceDynamicDefaultField(string $template, array $overrides = []): string
{
    return (string) Antlers::parse(
        File::get(resource_path("views/vendor/statamic/forms/fields/{$template}.antlers.html")),
        [
            'handle' => 'topic',
            'id' => 'form-topic',
            'visibility' => 'visible',
            'value' => null,
            'validate' => [],
            'error' => null,
            'instructions' => null,
            'placeholder' => null,
            'options' => ['a' => 'Aye', 'b' => 'Bee', 'c' => 'Cee'],
            ...$overrides,
        ],
        true,
    );
}

/**
 * The config-field handles a fieldtype offers in the Control Panel.
 *
 * @param  class-string<Fieldtype>  $class
 * @return list<string>
 */
function choiceDynamicDefaultConfigHandles(string $class): array
{
    $fieldtype = mustBe(Fieldtype::class, app($class), "the {$class} fieldtype");

    /** @var list<string> $handles */
    $handles = $fieldtype->configFields()->all()->keys()->all();

    return $handles;
}

it('emits the calculation attributes on a single select with a dynamic default', function (): void {
    $out = renderChoiceDynamicDefaultField('select', [
        'calculation_query' => true,
        'calculation_formula' => '[get_param:topic]',
    ]);

    expect($out)->toContain('data-calculation-type="text"')
        ->and($out)->toContain('data-calculation-formula="[get_param:topic]"')
        ->and($out)->toContain('data-calculation-decimals=""')
        ->and($out)->not->toContain('readonly');
});

it('emits no calculation attributes on a select without a dynamic default', function (array $overrides): void {
    /** @var array<string, mixed> $overrides */
    expect(renderChoiceDynamicDefaultField('select', $overrides))->not->toContain('data-calculation');
})->with([
    'setting off' => [['calculation_query' => false, 'calculation_formula' => '[get_param:topic]']],
    'setting absent' => [[]],
]);

it('emits the calculation attributes on a multiple select, named as a list', function (): void {
    $out = renderChoiceDynamicDefaultField('select', [
        'calculation_query' => true,
        'calculation_formula' => '[get_param:topic]',
        'multiple' => true,
    ]);

    // The `[]` name is what tells the engine to write a list (UAMS-Web/uams-statamic-archive#2924)
    expect($out)->toContain('name="topic[]"')
        ->and($out)->toContain('data-calculation-type="text"')
        ->and($out)->toContain('data-calculation-formula="[get_param:topic]"')
        ->and($out)->not->toContain('readonly');
});

it('emits the calculation attributes on every checkbox with a dynamic default', function (): void {
    $out = renderChoiceDynamicDefaultField('checkboxes', [
        'calculation_query' => true,
        'calculation_formula' => '[get_param:topic]',
    ]);

    // Three boxes carry them; the hidden placeholder input does not
    expect(substr_count($out, 'type="checkbox"'))->toBe(3)
        ->and(substr_count($out, 'data-calculation-type="text"'))->toBe(3)
        ->and(substr_count($out, 'data-calculation-formula="[get_param:topic]"'))->toBe(3)
        ->and($out)->toContain('<input type="hidden" name="topic[]">')
        ->and($out)->not->toContain('readonly');
});

it('emits no calculation attributes on checkboxes without a dynamic default', function (array $overrides): void {
    /** @var array<string, mixed> $overrides */
    expect(renderChoiceDynamicDefaultField('checkboxes', $overrides))->not->toContain('data-calculation');
})->with([
    'setting off' => [['calculation_query' => false, 'calculation_formula' => '[get_param:topic]']],
    'setting absent' => [[]],
]);

it('emits the calculation attributes on every radio with a dynamic default', function (): void {
    $out = renderChoiceDynamicDefaultField('radio', [
        'calculation_query' => true,
        'calculation_formula' => '[get_param:topic]',
    ]);

    expect(substr_count($out, 'type="radio"'))->toBe(3)
        ->and(substr_count($out, 'data-calculation-type="text"'))->toBe(3)
        ->and(substr_count($out, 'data-calculation-formula="[get_param:topic]"'))->toBe(3)
        ->and($out)->not->toContain('readonly');
});

it('emits no calculation attributes on a radio without a dynamic default', function (): void {
    expect(renderChoiceDynamicDefaultField('radio', ['calculation_query' => false]))->not->toContain('data-calculation');
});

it('offers the Dynamic Default Value setting only where the public form applies it', function (string $class, bool $offered): void {
    /** @var class-string<Fieldtype> $class */
    $handles = choiceDynamicDefaultConfigHandles($class);

    expect(in_array('calculation_query', $handles, true))->toBe($offered)
        ->and(in_array('calculation_formula', $handles, true))->toBe($offered);
})->with([
    'Select' => [Select::class, true],
    'Radio' => [Radio::class, true],
    'Checkboxes' => [Checkboxes::class, true],
]);
