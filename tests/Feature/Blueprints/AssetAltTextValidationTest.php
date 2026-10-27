<?php

declare(strict_types=1);

use Illuminate\Validation\Validator;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Tests\TestCase;

uses(TestCase::class);

/*
 * Guards WCAG 1.1.1 alt-text enforcement on the `images` asset blueprint (UAMS-Web/uams-statamic-archive#1049):
 * a content image must carry alt text unless explicitly flagged decorative via
 * `exempt_from_alt`. The rule (`validate: [required, sometimes]` gated by the
 * `if: exempt_from_alt equals false` display condition) is declared on
 * resources/blueprints/assets/images.yaml; `portraits` already enforced the same
 * and must stay unchanged. These assert the blueprint *validation rules*, so no
 * asset write or container is needed.
 *
 * @command php artisan test --compact tests/Feature/Blueprints/AssetAltTextValidationTest.php
 */

/**
 * Run the blueprint's field validator against a CP-shaped submission and return
 * the resulting Laravel validator. We assert only on the `alt` key, so other
 * required fields on the blueprint (digital_source_type, etc.) are irrelevant.
 *
 * @param  array<string, mixed>  $values
 */
function validateAssetBlueprint(string $handle, array $values): Validator
{
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::find($handle);

    /** @var Fields $fields */
    $fields = $blueprint->fields()->addValues($values);

    /** @var Statamic\Fields\Validator $fieldsValidator */
    $fieldsValidator = $fields->validator();

    /** @var Validator $validator */
    $validator = $fieldsValidator->validator();

    return $validator;
}

it('declares the alt field required+sometimes on the images blueprint', function (): void {
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::find('assets.images');
    /** @var Field $alt */
    $alt = $blueprint->fields()->get('alt');

    expect($alt)->not->toBeNull();
    /** @var array<string, mixed> $rules */
    $rules = $alt->rules();
    expect($rules['alt'])->toContain('required')->toContain('sometimes');
});

it('rejects a content image with no alt text when not exempt', function (): void {
    // The CP shows the alt field when exempt_from_alt is false, so it is
    // submitted (empty) — `sometimes` then enforces `required`.
    $errors = validateAssetBlueprint('assets.images', [
        'exempt_from_alt' => false,
        'alt' => '',
    ])->errors();

    expect($errors->has('alt'))->toBeTrue();
});

it('rejects whitespace-only alt when not exempt', function (string $whitespace): void {
    // Laravel's core `required` rule trims strings before the empty check
    // (ValidatesAttributes::validateRequired), so a blank-looking alt is rejected
    // — pinning that this isn't a backdoor around WCAG 1.1.1.
    $errors = validateAssetBlueprint('assets.images', [
        'exempt_from_alt' => false,
        'alt' => $whitespace,
    ])->errors();

    expect($errors->has('alt'))->toBeTrue();
})->with([
    'spaces' => ['   '],
    'tab and newline' => ["\t\n "],
]);

it('accepts a content image with alt text when not exempt', function (): void {
    $errors = validateAssetBlueprint('assets.images', [
        'exempt_from_alt' => false,
        'alt' => 'A radiologist reviews a chest X-ray on a backlit display.',
    ])->errors();

    expect($errors->has('alt'))->toBeFalse();
});

it('allows a decorative (exempt) image to save when the alt key is omitted', function (): void {
    // The `if: exempt_from_alt equals false` condition gates CP DISPLAY, not
    // validation — `required`/`sometimes` always run. When exempt is true the CP
    // hides the alt field, so the browser omits the alt key entirely; `sometimes`
    // then skips it and the asset saves with alt="". This is the real CP path.
    $errors = validateAssetBlueprint('assets.images', [
        'exempt_from_alt' => true,
    ])->errors();

    expect($errors->has('alt'))->toBeFalse();
});

it('still validates a present alt key even when exempt (if: does not gate validation)', function (): void {
    // Because the display condition does NOT suppress the rule, an explicitly
    // empty alt is rejected even when exempt — this only matters to non-CP
    // submitters (API/import); the CP never sends the key for a hidden field.
    expect(validateAssetBlueprint('assets.images', [
        'exempt_from_alt' => true,
        'alt' => '',
    ])->errors()->has('alt'))->toBeTrue();

    // A non-empty alt on an exempt image is accepted.
    expect(validateAssetBlueprint('assets.images', [
        'exempt_from_alt' => true,
        'alt' => 'Decorative flourish',
    ])->errors()->has('alt'))->toBeFalse();
});

it('leaves the portraits blueprint alt requirement unchanged', function (): void {
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::find('assets.portraits');
    /** @var Field $alt */
    $alt = $blueprint->fields()->get('alt');

    /** @var array<string, mixed> $rules */
    $rules = $alt->rules();
    expect($rules['alt'])->toContain('required')->toContain('sometimes');

    expect(validateAssetBlueprint('assets.portraits', [
        'exempt_from_alt' => false,
        'alt' => '',
    ])->errors()->has('alt'))->toBeTrue();

    expect(validateAssetBlueprint('assets.portraits', [
        'exempt_from_alt' => true,
    ])->errors()->has('alt'))->toBeFalse();
});
