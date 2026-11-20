<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Blueprint as StatamicBlueprint;
use Statamic\Fields\Field;
use Statamic\Fields\FieldtypeRepository;
use Tests\TestCase;
use UAMSWeb\Aether\People\Fieldtypes\TextPhoneticPair;

uses(TestCase::class);

/**
 * Pins what the site still owns of the `text_phonetic_pair` fieldtype once it comes from
 * `uams-web/aether-people` (UAMS-Web/uams-statamic#1000).
 *
 * The add-on's own suite tests the fieldtype. These cases pin the seams between it and
 * the site: the handle and the organization blueprint's field resolve to the add-on's
 * class, the old `App\` name still resolves, a form renders the add-on's view, the site's
 * Control Panel bundle no longer registers its own component, and the `name` and
 * `international_phonetic_alphabet` rules the add-on names are the site's.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/TextPhoneticPairFieldtypeTest.php
 */
it('resolves the handle to the add-on\'s class', function (): void {
    expect(get_debug_type(app(FieldtypeRepository::class)->find('text_phonetic_pair')))->toBe(TextPhoneticPair::class);
});

it('gives the organization blueprint\'s pronunciation field the add-on\'s fieldtype', function (): void {
    $blueprint = mustBe(StatamicBlueprint::class, Blueprint::find('collections.organizations.organization'), 'the organization blueprint');
    $field = mustBe(Field::class, $blueprint->field('name_pronunciation'), 'the name_pronunciation field');

    expect(get_debug_type($field->fieldtype()))->toBe(TextPhoneticPair::class);
});

it('resolves the old site class name to the add-on\'s class', function (): void {
    /** @var class-string $old */
    $old = 'App\\Fieldtypes\\TextPhoneticPair';

    expect(class_exists($old))->toBeTrue()
        ->and(new ReflectionClass($old)->getName())->toBe(TextPhoneticPair::class)
        ->and(is_file(app_path('Fieldtypes/TextPhoneticPair.php')))->toBeFalse();
});

it('renders a form field from the add-on\'s view', function (): void {
    $path = str_replace('\\', '/', View::getFinder()->find('statamic::forms.fields.text_phonetic_pair'));

    expect($path)->toEndWith('vendor/uams-web/aether-people/resources/field-views/forms/fields/text_phonetic_pair.antlers.html')
        ->and(is_file(resource_path('views/vendor/statamic/forms/fields/text_phonetic_pair.antlers.html')))->toBeFalse();
});

it('leaves the Control Panel component to the add-on\'s bundle', function (): void {
    $site = (string) file_get_contents(resource_path('js/cp.js'));
    $addon = (string) file_get_contents(base_path('vendor/uams-web/aether-people/resources/js/cp.js'));

    expect($addon)->toContain("register('text_phonetic_pair-fieldtype', TextPhoneticPairFieldtype)")
        ->and($site)->not->toContain('text_phonetic_pair-fieldtype')
        ->not->toContain('PhoneticTextInput')
        ->not->toContain('PhoneticTextInstructions');
});

it('validates the add-on\'s pair with the site\'s name and IPA rules', function (array $pair, bool $passes): void {
    $field = new Field('pronunciation', ['type' => 'text_phonetic_pair']);
    $fieldtype = mustBe(TextPhoneticPair::class, $field->fieldtype(), 'the pronunciation fieldtype');

    $rules = $fieldtype->extraRules();

    expect($rules)->toBe([
        'pronunciation.text' => ['nullable', 'string', 'name'],
        'pronunciation.phonetic_text' => ['nullable', 'string', 'international_phonetic_alphabet'],
    ])->and(Validator::make(['pronunciation' => $pair], $rules)->passes())->toBe($passes);
})->with([
    'a name and its IPA' => [['text' => 'Test', 'phonetic_text' => 'ˈtɛst'], true],
    'phonetic respelling in the IPA half' => [['text' => 'Test', 'phonetic_text' => 'TEST-uh'], false],
]);
