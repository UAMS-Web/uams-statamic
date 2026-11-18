<?php

declare(strict_types=1);

use App\Http\Requests\Classifieds\ClassifiedListingRequest;
use App\Support\Classifieds\ClassifiedCategories;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\In;
use Statamic\Assets\AssetContainer;
use Statamic\Facades\AssetContainer as AssetContainers;
use Tests\TestCase;

uses(TestCase::class);

covers(ClassifiedListingRequest::class);

/**
 * The rules and messages of the classified listing request, read directly rather than
 * through a posted form, so every rule string is pinned without the posting flow.
 * The posting flow itself is in `tests/Feature/Classifieds/`.
 *
 * The images container is replaced with a fake on the facade, so no test reads or
 * writes `content/assets`.
 *
 * @command  php artisan test --compact tests/Feature/Http/Requests/Classifieds/ClassifiedListingRequestTest.php
 */

/**
 * An unsaved images container carrying the given upload rules.
 *
 * @param  array<int, mixed>  $rules
 */
function classifiedImagesContainer(array $rules): AssetContainer
{
    $container = new AssetContainer;
    $container->handle('zz_classified_images');
    $container->validationRules($rules);

    return $container;
}

/**
 * The request's rules, with the category `in` rule rendered as its string form so
 * the whole array compares with `toBe`.
 *
 * @return array<string, mixed>
 */
function classifiedListingRules(): array
{
    $rules = (new ClassifiedListingRequest)->rules();

    /** @var list<mixed> $categoryRules */
    $categoryRules = $rules['classified_categories.*'];
    $rules['classified_categories.*'] = array_map(
        fn (mixed $rule): mixed => $rule instanceof In ? (string) $rule : $rule,
        $categoryRules,
    );

    return $rules;
}

/**
 * Set one classifieds image setting, or remove it for null so the default applies.
 */
function setClassifiedImagesConfig(string $key, mixed $value): void
{
    /** @var array<string, mixed> $settings */
    $settings = config('classifieds.images', []);

    if ($value === null) {
        unset($settings[$key]);
    } else {
        $settings[$key] = $value;
    }

    Config::set('classifieds.images', $settings);
}

it('authorizes any caller that reaches it', function () {
    expect((new ClassifiedListingRequest)->authorize())->toBeTrue();
});

it('accepts exactly the five web image types', function () {
    expect(ClassifiedListingRequest::IMAGE_EXTENSIONS)->toBe(['jpg', 'jpeg', 'png', 'gif', 'webp']);
});

it('declares every field rule, with the images container rules after the type check', function () {
    setClassifiedImagesConfig('container', 'images');
    setClassifiedImagesConfig('max_files', 7);
    AssetContainers::shouldReceive('find')->once()->with('images')->andReturn(
        classifiedImagesContainer(['extensions:jpg,png', 'max:2048'])
    );

    expect(classifiedListingRules())->toBe([
        'title' => ['required', 'string', 'max:200', 'not_regex:/[<>]/'],
        'description' => ['nullable', 'string', 'max:10000'],
        'price' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        'classified_categories' => ['nullable', 'array'],
        'classified_categories.*' => ['string', (string) new In(app(ClassifiedCategories::class)->slugs())],
        'contact_name' => ['required', 'string', 'max:200'],
        'contact_email' => ['required', 'string', 'email', 'max:200'],
        'contact_phone' => ['nullable', 'string', 'max:50'],
        'location' => ['nullable', 'string', 'max:200'],
        'images' => ['nullable', 'array', 'max:7'],
        'images.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp', 'extensions:jpg,png', 'max:2048'],
    ]);
});

it('keeps only the string rules of the images container, in order', function () {
    AssetContainers::shouldReceive('find')->once()->with('images')->andReturn(
        classifiedImagesContainer([3 => 'extensions:png', 7 => new In(['x']), 9 => 'max:100'])
    );

    expect((new ClassifiedListingRequest)->rules()['images.*'])
        ->toBe(['file', 'mimes:jpg,jpeg,png,gif,webp', 'extensions:png', 'max:100']);
});

it('falls back to a 10 MB limit when the images container is missing', function () {
    AssetContainers::shouldReceive('find')->once()->with('images')->andReturnNull();

    expect((new ClassifiedListingRequest)->rules()['images.*'])
        ->toBe(['file', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240']);
});

it('reads the images container named in config, and the images container otherwise', function (mixed $configured, string $handle) {
    setClassifiedImagesConfig('container', $configured);
    AssetContainers::shouldReceive('find')->once()->with($handle)->andReturn(
        classifiedImagesContainer(['max:1'])
    );

    expect((new ClassifiedListingRequest)->rules()['images.*'])->toContain('max:1');
})->with([
    'a named container' => ['listing_photos', 'listing_photos'],
    'unset' => [null, 'images'],
    'not a string' => [42, 'images'],
]);

it('caps images at the configured count, or 12', function (mixed $configured, int $max) {
    setClassifiedImagesConfig('max_files', $configured);

    expect(ClassifiedListingRequest::maxImages())->toBe($max);
})->with([
    'a count' => [3, 3],
    'unset' => [null, 12],
    'a numeric string' => ['3', 12],
]);

it('names the rules a poster is most likely to meet', function () {
    expect((new ClassifiedListingRequest)->messages())->toBe([
        'title.not_regex' => 'The title cannot contain the characters < or >.',
        'images.max' => 'A listing can carry at most :max images.',
        'images.*.mimes' => 'Each image must be a JPEG, PNG, GIF or WebP image.',
        'classified_categories.*.in' => 'Choose categories from the list.',
    ]);
});

it('refuses markup in a title with its own message', function (string $title, bool $passes) {
    AssetContainers::shouldReceive('find')->andReturnNull();
    $request = new ClassifiedListingRequest;
    $validator = Validator::make(
        ['title' => $title, 'contact_name' => 'Nyota Uhura', 'contact_email' => 'uhura@example.com'],
        $request->rules(),
        $request->messages(),
    );

    expect($validator->passes())->toBe($passes);

    if (! $passes) {
        expect($validator->errors()->first('title'))->toBe('The title cannot contain the characters < or >.');
    }
})->with([
    'plain text' => ['Communicator, lightly used', true],
    'an opening bracket' => ['<b>Communicator</b>', false],
    'a closing bracket only' => ['Communicator > phaser', false],
]);

it('reports the configured image cap in its message', function () {
    setClassifiedImagesConfig('max_files', 1);
    AssetContainers::shouldReceive('find')->andReturnNull();
    $request = new ClassifiedListingRequest;
    $validator = Validator::make(
        ['images' => ['a', 'b']],
        ['images' => $request->rules()['images']],
        $request->messages(),
    );

    expect($validator->errors()->first('images'))->toBe('A listing can carry at most 1 images.');
});
