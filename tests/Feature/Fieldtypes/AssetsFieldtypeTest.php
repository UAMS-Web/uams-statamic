<?php

declare(strict_types=1);

use App\Enums\MimeType;
use App\Fieldtypes\Assets;
use App\Rules\AssetMinimumDimensions;
use Illuminate\Http\Request;
use Statamic\Auth\File\User as FileUser;
use Statamic\Facades\User;
use Statamic\Fields\Field;
use Statamic\Fieldtypes\Assets\Assets as BaseAssets;
use Tests\TestCase;

use function Pest\Laravel\actingAs;

uses(TestCase::class);

/**
 * Tests for App\Fieldtypes\Assets: the file restriction config section, the
 * upload validation rules and accept attribute built from it, and the query
 * scopes it adds to the asset browser's preload config.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/AssetsFieldtypeTest.php
 */

/**
 * Build an Assets fieldtype bound to a Field with the given config.
 *
 * @param  array<string, mixed>  $config  Field config merged over the type handle and container
 */
function assetsFieldtype(array $config = []): Assets
{
    // Bind a Field on the images container so config() and preload resolve
    $fieldtype = new Assets;
    $fieldtype->setField(new Field('files', array_merge(['type' => 'assets', 'container' => 'images'], $config)));

    return $fieldtype;
}

/**
 * Call a protected Assets method via reflection.
 *
 * @param  array<int, mixed>  $args  Arguments forwarded to the method
 */
function assetsCall(string $method, array $args = []): mixed
{
    return (new ReflectionMethod(Assets::class, $method))->invoke(new Assets, ...$args);
}

/**
 * Read the config sections of an Assets fieldtype.
 *
 * @return array<int, array{display: string, fields: array<string, mixed>}>
 */
function assetsConfigSections(): array
{
    /** @var array<int, array{display: string, fields: array<string, mixed>}> $sections */
    $sections = (new ReflectionMethod(Assets::class, 'configFieldItems'))->invoke(new Assets);

    return $sections;
}

/**
 * The upload rules an Assets fieldtype builds for a config, as strings where they are strings.
 *
 * @param  array<string, mixed>  $config
 * @return array<int, mixed>
 */
function assetsUploadRules(array $config): array
{
    return (new Assets)->getValidationRules(null, $config);
}

it('uses the parent assets icon', function (): void {
    expect((new Assets)->icon())->toBe((new BaseAssets)->icon())
        ->and((new Assets)->icon())->toBe('fieldtype-assets');
});

it('appends a file restrictions section to the parent config', function (): void {
    $sections = assetsConfigSections();

    expect(array_column($sections, 'display'))->toBe(['Input Behavior', 'Appearance', 'Boundaries & Limits', 'Advanced', 'File Restrictions'])
        ->and(end($sections))->toBe([
            'display' => 'File Restrictions',
            'fields' => [
                'file_type_restrictions_section' => [
                    'display' => 'File Type',
                    'type' => 'subsection',
                ],
                'file_type_restrictions' => [
                    'display' => 'File Type Restrictions',
                    'instructions' => 'Choose how to restrict allowed file types.',
                    'type' => 'radio',
                    'options' => [
                        'all' => 'Allow all file types',
                        'mime_types' => 'Specify allowed file types',
                        'mime_categories' => 'Specify allowed file type categories',
                    ],
                    'default' => 'all',
                ],
                'mime_types' => [
                    'display' => 'Allowed File Types',
                    'instructions' => 'Select the specific file types that are allowed.',
                    'type' => 'select',
                    'preset' => 'mime_types',
                    'multiple' => true,
                    'clearable' => true,
                    'if' => [
                        'file_type_restrictions' => 'mime_types',
                    ],
                ],
                'mime_categories' => [
                    'display' => 'Allowed File Type Categories',
                    'instructions' => 'Select the file type categories that are allowed.',
                    'type' => 'select',
                    'preset' => 'mime_categories',
                    'multiple' => true,
                    'clearable' => true,
                    'if' => [
                        'file_type_restrictions' => 'mime_categories',
                    ],
                ],
                'file_size_restrictions_section' => [
                    'display' => 'File Size',
                    'type' => 'subsection',
                ],
                'min_file_size' => [
                    'display' => 'Minimum File Size',
                    'instructions' => 'Set the minimum file size **in megabytes** for each file uploaded.',
                    'type' => 'float',
                ],
                'max_file_size' => [
                    'display' => 'Maximum File Size',
                    'instructions' => 'Set the maximum file size **in megabytes** for each file uploaded.',
                    'type' => 'float',
                ],
                'image_dimensions_section' => [
                    'display' => 'Image Dimensions',
                    'type' => 'subsection',
                    'instructions' => 'Minimum pixel dimensions for uploaded **images**. Only enforced when the field is restricted to images (the browser reads image dimensions with `getimagesize`).',
                    'if' => [
                        'file_type_restrictions' => 'not all',
                    ],
                ],
                'min_width' => [
                    'display' => 'Minimum Width',
                    'instructions' => 'Reject uploaded images narrower than this **width in pixels**. Leave blank for no minimum.',
                    'type' => 'integer',
                    'if' => [
                        'file_type_restrictions' => 'not all',
                    ],
                ],
                'min_height' => [
                    'display' => 'Minimum Height',
                    'instructions' => 'Reject uploaded images shorter than this **height in pixels**. Leave blank for no minimum.',
                    'type' => 'integer',
                    'if' => [
                        'file_type_restrictions' => 'not all',
                    ],
                ],
            ],
        ]);
});

it('offers attach to email only in a form blueprint', function (string $path, bool $attaches): void {
    app()->instance('request', Request::create($path));

    $appearance = collect(assetsConfigSections())->firstWhere('display', 'Appearance');
    $fields = is_array($appearance) ? $appearance['fields'] : [];

    expect(array_key_exists('attach_to_email', $fields))->toBe($attaches);

    if ($attaches) {
        expect($fields['attach_to_email'])->toBe([
            'display' => 'Attach to Email',
            'instructions' => 'Attach files from this field to email notifications. Note: Email notification settings in the form configuration must have "Attachments" enabled for files to be attached.',
            'type' => 'toggle',
            'default' => false,
        ]);
    }
})->with([
    'a form blueprint' => ['/cp/forms/contact/blueprint', true],
    'a collection blueprint' => ['/cp/collections/pages/blueprints/page/edit', false],
]);

it('adds no extra rules', function (): void {
    expect((new Assets)->extraRules())->toBe([]);
});

it('restricts uploads to the listed mime types', function (array $config, array $rules): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;

    expect(assetsUploadRules($typedConfig))->toBe(['array', ...$rules]);
})->with([
    'all types' => [[], []],
    'listed types' => [['file_type_restrictions' => 'mime_types', 'mime_types' => ['image/png', 'application/pdf']], ['mimetypes:image/png,application/pdf']],
    'one type as a string' => [['file_type_restrictions' => 'mime_types', 'mime_types' => 'image/png'], ['mimetypes:image/png']],
    'no listed types' => [['file_type_restrictions' => 'mime_types', 'mime_types' => []], []],
    'no listed categories' => [['file_type_restrictions' => 'mime_categories'], []],
    'an unknown category' => [['file_type_restrictions' => 'mime_categories', 'mime_categories' => ['nonsense']], []],
]);

it('restricts uploads to every mime type and extension in the listed categories', function (): void {
    $image = MimeType::findByCategories(['image']);
    $mimeTypes = array_map(fn (MimeType $type): string => $type->mimeType(), $image);
    $extensions = array_merge(...array_map(fn (MimeType $type): array => $type->extensions(), $image));

    expect(assetsUploadRules(['file_type_restrictions' => 'mime_categories', 'mime_categories' => 'image']))->toBe([
        'array',
        'mimetypes:'.implode(',', $mimeTypes),
        'extensions:'.implode(',', $extensions),
    ])->and($mimeTypes)->toContain('image/png')
        ->and($extensions)->toContain('png');
});

it('converts the file size bounds from megabytes to kilobytes', function (array $config, array $rules): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;

    expect(assetsUploadRules($typedConfig))->toBe(['array', ...$rules]);
})->with([
    'both bounds' => [['min_file_size' => 0.5, 'max_file_size' => '2'], ['between:500,2000']],
    'a minimum only' => [['min_file_size' => 1.25], ['min:1250']],
    'a zero minimum' => [['min_file_size' => 0], ['min:0']],
    'a maximum only' => [['max_file_size' => 3], ['max:3000']],
    'a fractional minimum, truncated' => [['min_file_size' => 0.0015], ['min:1']],
    'a fractional maximum, truncated' => [['max_file_size' => '0.0025'], ['max:2']],
    'non-numeric bounds' => [['min_file_size' => 'small', 'max_file_size' => null], []],
]);

it('checks image dimensions only when a minimum is set and the field takes images alone', function (array $config, ?array $dimensions): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;
    $objects = array_values(array_filter(assetsUploadRules($typedConfig), is_object(...)));

    if ($dimensions === null) {
        expect($objects)->toBe([]);

        return;
    }

    /** @var array{int, int, list<string>} $typedDimensions */
    $typedDimensions = $dimensions;

    expect($objects)->toEqual([new AssetMinimumDimensions($typedDimensions[0], $typedDimensions[1], $typedDimensions[2])]);
})->with([
    'a width on images' => [['file_type_restrictions' => 'mime_categories', 'mime_categories' => ['image'], 'min_width' => '800', 'container' => 'images'], [800, 0, ['images']]],
    'a height on png' => [['file_type_restrictions' => 'mime_types', 'mime_types' => ['image/png'], 'min_height' => 600], [0, 600, []]],
    'a negative width clamped' => [['file_type_restrictions' => 'mime_types', 'mime_types' => ['image/png'], 'min_width' => -5, 'min_height' => 10], [0, 10, []]],
    'a height as text' => [['file_type_restrictions' => 'mime_types', 'mime_types' => ['image/png'], 'min_height' => '600'], [0, 600, []]],
    'a negative height clamped' => [['file_type_restrictions' => 'mime_types', 'mime_types' => ['image/png'], 'min_width' => 10, 'min_height' => -5], [10, 0, []]],
    'a zero height' => [['file_type_restrictions' => 'mime_types', 'mime_types' => ['image/png'], 'min_width' => 10, 'min_height' => '0'], [10, 0, []]],
    'a one-pixel width' => [['file_type_restrictions' => 'mime_types', 'mime_types' => ['image/png'], 'min_width' => 1], [1, 0, []]],
    'a one-pixel height' => [['file_type_restrictions' => 'mime_types', 'mime_types' => ['image/png'], 'min_height' => 1], [0, 1, []]],
    'no minimum' => [['file_type_restrictions' => 'mime_types', 'mime_types' => ['image/png']], null],
    'a non-numeric minimum' => [['file_type_restrictions' => 'mime_types', 'mime_types' => ['image/png'], 'min_width' => 'wide'], null],
    'all file types' => [['min_width' => 800], null],
    'a mixed type list' => [['file_type_restrictions' => 'mime_types', 'mime_types' => ['image/png', 'application/pdf'], 'min_width' => 800], null],
]);

it('treats a field as images only when every listed category or type is an image', function (array $config, bool $imagesOnly): void {
    expect(assetsCall('restrictsToImagesOnly', [$config]))->toBe($imagesOnly);
})->with([
    'all file types' => [[], false],
    'the image category' => [['file_type_restrictions' => 'mime_categories', 'mime_categories' => ['image']], true],
    'the image category as a string' => [['file_type_restrictions' => 'mime_categories', 'mime_categories' => 'image'], true],
    'image and video categories' => [['file_type_restrictions' => 'mime_categories', 'mime_categories' => ['image', 'video']], false],
    'no categories' => [['file_type_restrictions' => 'mime_categories', 'mime_categories' => []], false],
    'only non-text categories' => [['file_type_restrictions' => 'mime_categories', 'mime_categories' => [['image']]], false],
    'a non-text category beside image' => [['file_type_restrictions' => 'mime_categories', 'mime_categories' => [['x'], 'image']], true],
    'a numeric category' => [['file_type_restrictions' => 'mime_categories', 'mime_categories' => [5]], false],
    'a non-string type beside an image type' => [['file_type_restrictions' => 'mime_types', 'mime_types' => [5, 'image/png']], true],
    'one image type as a string' => [['file_type_restrictions' => 'mime_types', 'mime_types' => 'image/png'], true],
    'image types' => [['file_type_restrictions' => 'mime_types', 'mime_types' => ['image/png', 'image/jpeg']], true],
    'an image type after a non-image one' => [['file_type_restrictions' => 'mime_types', 'mime_types' => ['application/pdf', 'image/png']], false],
    'no types' => [['file_type_restrictions' => 'mime_types', 'mime_types' => []], false],
    'only non-string types' => [['file_type_restrictions' => 'mime_types', 'mime_types' => [5]], false],
    'an unknown restriction' => [['file_type_restrictions' => 'other', 'mime_types' => ['image/png']], false],
]);

it('lists the configured containers that are named', function (mixed $container, array $containers): void {
    expect(assetsCall('configuredContainers', [['container' => $container]]))->toBe($containers);
})->with([
    'one container' => ['images', ['images']],
    'a list with blanks and non-strings' => [['images', '', 5, 'files'], ['images', 'files']],
    'none' => [null, []],
]);

it('adds the dimension rule to the field rules, and no string upload rules', function (): void {
    $rules = assetsFieldtype(['file_type_restrictions' => 'mime_types', 'mime_types' => ['image/png'], 'min_width' => 100, 'max_file_size' => 2])->rules();
    $base = new BaseAssets;
    $base->setField(new Field('files', ['type' => 'assets', 'container' => 'images']));

    expect($rules)->toEqual([...$base->rules(), new AssetMinimumDimensions(100, 0, ['images'])]);
});

it('builds the upload accept attribute from the restriction', function (array $config, ?string $accept): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;

    expect((new Assets)->getAcceptAttribute($typedConfig))->toBe($accept);
})->with([
    'all types' => [[], null],
    'listed types' => [['file_type_restrictions' => 'mime_types', 'mime_types' => ['image/png', 'application/pdf']], 'image/png,application/pdf'],
    'no listed types' => [['file_type_restrictions' => 'mime_types'], null],
    'one type as a string' => [['file_type_restrictions' => 'mime_types', 'mime_types' => 'image/png'], 'image/png'],
    'listed categories' => [['file_type_restrictions' => 'mime_categories', 'mime_categories' => ['image', 'video']], 'image/*,video/*'],
    'one category as a string' => [['file_type_restrictions' => 'mime_categories', 'mime_categories' => 'audio'], 'audio/*'],
    'no listed categories' => [['file_type_restrictions' => 'mime_categories', 'mime_categories' => []], null],
    'an unknown restriction' => [['file_type_restrictions' => 'other'], null],
]);

it('adds the file type and size scopes to the preload config', function (array $config, ?array $scopes): void {
    $user = mustBe(FileUser::class, User::make(), 'user');
    $user->email('assets-fieldtype@example.com');
    $user->makeSuper();
    actingAs($user);

    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;

    /** @var array<string, mixed> $preloadConfig */
    $preloadConfig = assetsFieldtype($typedConfig)->preload()['config'];

    expect($preloadConfig['query_scopes'] ?? null)->toBe($scopes)
        ->and($preloadConfig['container'])->toBe('images');
})->with([
    'no restrictions' => [[], null],
    'categories, with existing scopes kept once' => [[
        'file_type_restrictions' => 'mime_categories',
        'mime_categories' => ['Image', '', 5, 'image'],
        'query_scopes' => ['existing', '', 'assets_by_file_type'],
    ], ['existing', 'assets_by_file_type', 'assets_by_file_type_image']],
    'types, unknown ones skipped' => [[
        'file_type_restrictions' => 'mime_types',
        'mime_types' => ['image/png', 'image/x-not-real', '', 7],
    ], ['assets_by_file_type', 'assets_by_file_type_image_png']],
    'a non-string restriction' => [['file_type_restrictions' => ['mime_types'], 'mime_types' => ['image/png']], null],
    'an unknown restriction' => [['file_type_restrictions' => 'other'], ['assets_by_file_type']],
    'size bounds' => [['min_file_size' => 1, 'max_file_size' => '2.5', 'query_scopes' => ['existing']], ['existing', 'assets_by_file_size', 'assets_by_file_size_min_1000000', 'assets_by_file_size_max_2500000']],
    'non-positive size bounds' => [['min_file_size' => 0, 'max_file_size' => -1], null],
    'a zero maximum' => [['max_file_size' => 0], null],
    'one-byte bounds' => [['min_file_size' => 0.000001, 'max_file_size' => 0.000001], ['assets_by_file_size', 'assets_by_file_size_min_1', 'assets_by_file_size_max_1']],
    'fractional byte bounds, truncated' => [['min_file_size' => 0.0000015, 'max_file_size' => 0.0000025], ['assets_by_file_size', 'assets_by_file_size_min_1', 'assets_by_file_size_max_2']],
    'existing scopes as a string, with a size' => [['max_file_size' => 1, 'query_scopes' => 'existing'], ['existing', 'assets_by_file_size', 'assets_by_file_size_max_1000000']],
    'odd existing scopes, with a size' => [['max_file_size' => 1, 'query_scopes' => ['', ['x'], 5, 'assets_by_file_size']], ['', '5', 'assets_by_file_size', 'assets_by_file_size_max_1000000']],
    'a blank existing scope, with a size' => [['max_file_size' => 1, 'query_scopes' => ['', 'existing']], ['existing', 'assets_by_file_size', 'assets_by_file_size_max_1000000']],
    'a blank existing scope, with a category' => [['file_type_restrictions' => 'mime_categories', 'mime_categories' => ['video'], 'query_scopes' => ['', 'existing']], ['existing', 'assets_by_file_type', 'assets_by_file_type_video']],
    'existing scopes as a string, with a category' => [['file_type_restrictions' => 'mime_categories', 'mime_categories' => ['video'], 'query_scopes' => 'existing'], ['existing', 'assets_by_file_type', 'assets_by_file_type_video']],
    'odd existing scopes, with a category' => [['file_type_restrictions' => 'mime_categories', 'mime_categories' => ['video'], 'query_scopes' => [['x'], 5]], ['', '5', 'assets_by_file_type', 'assets_by_file_type_video']],
    'one category as a string' => [['file_type_restrictions' => 'mime_categories', 'mime_categories' => 'video'], ['assets_by_file_type', 'assets_by_file_type_video']],
    'a blank category before a real one' => [['file_type_restrictions' => 'mime_categories', 'mime_categories' => ['', 'video']], ['assets_by_file_type', 'assets_by_file_type_video']],
    'a non-string category before a real one' => [['file_type_restrictions' => 'mime_categories', 'mime_categories' => [5, 'video']], ['assets_by_file_type', 'assets_by_file_type_video']],
    'one type as a string' => [['file_type_restrictions' => 'mime_types', 'mime_types' => 'image/png'], ['assets_by_file_type', 'assets_by_file_type_image_png']],
    'a non-string type before a real one' => [['file_type_restrictions' => 'mime_types', 'mime_types' => [7, 'image/png']], ['assets_by_file_type', 'assets_by_file_type_image_png']],
    'a blank type before a real one' => [['file_type_restrictions' => 'mime_types', 'mime_types' => ['', 'image/png']], ['assets_by_file_type', 'assets_by_file_type_image_png']],
    'an unknown type before a real one' => [['file_type_restrictions' => 'mime_types', 'mime_types' => ['image/x-not-real', 'image/png']], ['assets_by_file_type', 'assets_by_file_type_image_png']],
    'a type and a size' => [['file_type_restrictions' => 'mime_categories', 'mime_categories' => ['video'], 'max_file_size' => 5], ['assets_by_file_type', 'assets_by_file_type_video', 'assets_by_file_size', 'assets_by_file_size_max_5000000']],
]);
