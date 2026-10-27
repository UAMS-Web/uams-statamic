<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Statamic\Assets\Asset;
use Statamic\Assets\AssetContainer as StatamicAssetContainer;
use Statamic\Facades\Antlers;
use Statamic\Facades\AssetContainer;
use Tests\TestCase;

// Serial because it persists a file-backed asset container into the shared content
// mirror, on a disk that is faked only in this process: the asset-container
// leak class (UAMS-Web/uams-statamic-archive#1120). The faked disk itself is per worker under
// `--parallel` (UAMS-Web/uams-statamic-archive#3111), so it is not the reason (UAMS-Web/uams-statamic-archive#3205).
uses(TestCase::class)->group('serial');

/**
 * The social-image half of the SEO head partial (UAMS-Web/uams-statamic-archive#2049).
 *
 * Split out of SeoMetaPartialTest because these two cases need a **resolvable asset** and
 * the rest of that file does not. They previously read whatever asset the checkout happened
 * to hold, which made them pass locally and skip in the gate permanently: the CI worktree is
 * runner-owned and never receives `storage/framework/cache/asset-metadata.sqlite`, which is
 * git-ignored and copied per-slot at provisioning time. A fix for a live 500 must not be
 * gated on ambient checkout state, so this builds the asset it needs.
 *
 * What is under test: `_seo_meta.antlers.html` assigns the image through a fallback chain,
 * and that Antlers assignment reduces the augmented Asset to its `toAugmentedArray()`.
 * Passing that array to `{{ glide }}` reaches `Path::extension()` -> `Str::before()` ->
 * `strstr()`, which throws a `TypeError` — an `\Error`, so glide's `catch (\Exception)` does
 * not catch it and it escapes as a 500 on every page carrying a social image. The partial
 * passes `:id` instead.
 *
 * Rendered TRUSTED, which is the whole reason this is testable at all: `Antlers::parse()`
 * defaults to the user-content sandbox, where the `{{ glide }}` tag is blocked outright — so
 * these assertions matched literals sitting outside the tag while it never ran (UAMS-Web/uams-statamic-archive#2040).
 *
 * @command  php artisan test --compact tests/Feature/Seo/SeoMetaSocialImageTest.php
 */
const SEO_SOCIAL_DISK = 'seo_social_probe';

const SEO_SOCIAL_CONTAINER = 'seo_social_probe';

beforeEach(function (): void {
    Storage::fake(SEO_SOCIAL_DISK);

    /** @var StatamicAssetContainer $container */
    $container = AssetContainer::make(SEO_SOCIAL_CONTAINER);
    /** @var StatamicAssetContainer $container */
    $container = $container->disk(SEO_SOCIAL_DISK);
    /** @var StatamicAssetContainer $container */
    $container = $container->title('SEO Social Probe');
    $container->save();

    $image = imagecreatetruecolor(1200, 630);
    ob_start();
    imagejpeg($image);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    Storage::disk(SEO_SOCIAL_DISK)->put('social.jpg', $bytes);

    /** @var StatamicAssetContainer $found */
    $found = AssetContainer::find(SEO_SOCIAL_CONTAINER);
    $found->makeAsset('social.jpg')->save();
});

afterEach(function (): void {
    $container = AssetContainer::find(SEO_SOCIAL_CONTAINER);

    if ($container instanceof StatamicAssetContainer) {
        $container->delete();
    }
});

/**
 * The augmented array of the fixture asset — the shape the partial's `{{ $seo_image = … }}`
 * assignment produces, and the shape that used to 500.
 *
 * Named for this file: Pest declares helpers in the GLOBAL namespace, so a bare
 * `socialImage()` would collide fatally with any sibling that declares one.
 *
 * @return array<array-key, mixed>
 */
function seoSocialFixtureImage(): array
{
    /** @var StatamicAssetContainer $container */
    $container = AssetContainer::find(SEO_SOCIAL_CONTAINER);
    $asset = $container->asset('social.jpg');

    // Narrowed by control flow rather than an inline `@var`: `asset()` is declared nullable,
    // and a missing fixture must fail loudly here rather than at the assertion (UAMS-Web/uams-statamic-archive#1733 / UAMS-Web/uams-statamic-archive#1865).
    if (! $asset instanceof Asset) {
        throw new RuntimeException('The '.SEO_SOCIAL_CONTAINER.' fixture asset was not created.');
    }

    $augmented = $asset->toAugmentedArray();

    return is_array($augmented) ? $augmented : [];
}

/**
 * Render the SEO meta partial trusted, as production does.
 *
 * @param  array<string, mixed>  $context
 */
function renderSeoMetaWithSocialImage(array $context): string
{
    return (string) Antlers::parse(
        (string) file_get_contents(resource_path('views/layout/header/_seo_meta.antlers.html')),
        $context,
        true
    );
}

it('sources the social image from the standard cover image featured_image_16_9', function (): void {
    $html = renderSeoMetaWithSocialImage([
        'featured_image_16_9' => seoSocialFixtureImage(),
        'title' => 'Page',
        'site' => ['name' => 'UAMS'],
        'permalink' => '/p',
    ]);

    // Asserting the tag NAMES alone is what passed while glide was blocked — those literals
    // sit outside the tag. Requiring a populated `content` is what makes this observe glide.
    expect($html)
        ->toContain('property="og:image"')
        ->toContain('name="twitter:image"')
        ->toContain('<meta name="twitter:card" content="summary_large_image">')
        ->not->toContain('property="og:image" content=""')
        ->not->toContain('name="twitter:image" content=""');
});

it('falls back to the legacy single featured_image for the social image', function (): void {
    // clinical_locations keeps its single featured_image; the shared partial must still find it.
    $html = renderSeoMetaWithSocialImage([
        'featured_image' => seoSocialFixtureImage(),
        'title' => 'Page',
        'site' => ['name' => 'UAMS'],
        'permalink' => '/p',
    ]);

    expect($html)
        ->toContain('property="og:image"')
        ->toContain('name="twitter:image"')
        ->not->toContain('property="og:image" content=""')
        ->not->toContain('name="twitter:image" content=""');
});
