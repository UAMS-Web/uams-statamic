<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Assets\AssetContainer as StatamicAssetContainer;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\YAML;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

/**
 * Every container that accepts uploads must declare `validate:` rules (UAMS-Web/uams-statamic-archive#1739).
 *
 * Five of the eleven containers accepted uploads with no rules at all, so the
 * only file-type gate on them was whatever the consuming blueprint field
 * happened to declare — and a blueprint field gates what a *field* accepts, not
 * what the *container* stores. Container rules are enforced in exactly two
 * places, `AssetsController::store()` and `validateCroppedFile()`, which is why
 * this is the right level to assert.
 *
 * The rules themselves live in the `content/` submodule
 * (`content/assets/*.yaml`), so this test is what keeps the app and that
 * submodule honest about each other.
 *
 * @command  php artisan test --compact tests/Feature/Assets/AssetContainerUploadRulesTest.php
 */
uses(TestCase::class);

/**
 * The `validate:` rules declared by a container, as a list of rule strings.
 *
 * @param  string  $handle  The asset container handle
 * @return list<string> The declared rules
 */
function assetContainerUploadRulesFor(
    string $handle
): array {
    // Resolve the container from the content submodule
    $container = AssetContainer::find($handle);

    // Fail loudly rather than vacuously passing when the container is missing
    if ($container === null) {
        throw new RuntimeException("asset container [{$handle}] was not found");
    }

    // Narrow each declared rule to a string for assertion
    return array_values(array_map(
        static fn ($rule): string => is_scalar($rule) ? (string) $rule : '',
        (array) $container->validationRules()
    ));
}

it('declares upload validation rules on every container that accepts uploads', function (): void {
    // ---
    // Read the container YAML directly rather than going through the hydrated
    // container objects. `Statamic\Stache\Stores\AssetContainersStore` never
    // maps `allow_uploads` onto the container, so `allowUploads()` falls
    // through to the augmentation and throws — the YAML is the source of truth
    // for this question, and reading it keeps the test independent of that.
    // ---
    $known = AssetContainer::find('logos');

    // Fail loudly rather than vacuously passing when the content submodule is absent
    if (! $known instanceof StatamicAssetContainer) {
        throw new RuntimeException('the logos asset container was not found');
    }

    // Derive the container directory from a container's own config path
    $knownPath = $known->path();
    $directory = is_string($knownPath) ? dirname($knownPath) : '';

    // Collect every container config on disk
    $configs = collect(File::files($directory))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'yaml')
        ->values();

    // Guard against an uninitialized content submodule silently passing
    expect($configs)->not->toBeEmpty("no asset container configs were found in [{$directory}]");

    // ---
    // Enumerated from the configs themselves rather than hard-coded, so a
    // container added later is covered the day it lands instead of the day
    // someone remembers to extend a list here.
    // ---
    $missing = $configs
        ->map(fn (SplFileInfo $file): array => [
            'handle' => $file->getFilenameWithoutExtension(),
            'data' => (array) YAML::file($file->getPathname())->parse(),
        ])
        // ---
        // Default to TRUE when the key is absent, which inverts the reading a
        // glance at the YAML suggests. `AssetsController::store()` gates on the
        // `store` policy and applies `validationRules()` unconditionally — it
        // never consults `allow_uploads` — so a container that simply omits the
        // key still accepts uploads. Defaulting to false would exempt exactly
        // the container most likely to have been added carelessly.
        // ---
        ->filter(fn (array $container): bool => ($container['data']['allow_uploads'] ?? true) !== false)
        ->reject(fn (array $container): bool => ($container['data']['validate'] ?? []) !== [])
        ->map(fn (array $container): string => $container['handle'])
        ->values()
        ->all();

    expect($missing)->toBe([], 'these containers accept uploads but declare no validate: rules');
});

it('restricts the five containers closed by 1739 to their intended types', function (
    string $handle,
    string $expectedExtensions
): void {
    // Read the container's declared rules
    $rules = assetContainerUploadRulesFor($handle);

    // Assert the extension allowlist matches exactly what the container is for
    expect($rules)->toContain("extensions:{$expectedExtensions}");

    // Assert a size cap is declared alongside it
    expect(collect($rules)->contains(fn (string $rule): bool => str_starts_with($rule, 'max:')))
        ->toBeTrue("container [{$handle}] declares no max: size cap");
})->with([
    // `logos` is 64/64 SVG and #49's work order is entirely SVG.
    'logos' => ['logos', 'svg'],
    // Matches the MIME list `logo_list_item.yaml` declares.
    'logos_unrestricted' => ['logos_unrestricted', 'svg,png'],
    // The raster web formats it holds, plus mainstream siblings.
    'images' => ['images', 'jpg,jpeg,png,gif,webp'],
    // Documents and images its CP browser is for.
    'form_entry_uploads' => ['form_entry_uploads', 'pdf,doc,docx,jpg,jpeg,png'],
]);

it('keeps script-capable types out of the public files bucket', function (): void {
    // Read the general-purpose bucket's rules
    $rules = assetContainerUploadRulesFor('files');

    // Isolate the extension allowlist from the declared rules
    $extensions = collect($rules)
        ->first(fn (string $rule): bool => str_starts_with($rule, 'extensions:'));

    // Fail when the container declares no extension allowlist at all
    expect($extensions)->not->toBeNull('the files container declares no extensions: rule');

    // Split the allowlist into individual extensions
    $allowed = explode(',', str_replace('extensions:', '', (string) $extensions));

    // ---
    // This disk is served straight off `public/`, so the web server returns
    // these files without PHP ever running — no CSP header, no middleware.
    // An `.svg` or `.html` here would be a same-origin script vector, which is
    // the whole point of UAMS-Web/uams-statamic-archive#1739. `logos` is where SVG lives, and it is scanned.
    // ---
    expect($allowed)->not->toContain('svg')
        ->and($allowed)->not->toContain('html')
        ->and($allowed)->not->toContain('htm')
        ->and($allowed)->not->toContain('xhtml')
        ->and($allowed)->not->toContain('xml');
});
