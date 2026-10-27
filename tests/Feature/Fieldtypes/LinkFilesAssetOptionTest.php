<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Assets\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Fieldset;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;
use Statamic\Fieldtypes\Link\ArrayableLink;
use Tests\Support\AssetReferenceSweep;
use Tests\TestCase;

/**
 * UAMS-Web/uams-statamic-archive#16 — the shared `uamsweb_common_link.link_url` control offers the native
 * Statamic v6 Link "Asset" option, scoped to the `files` container, and a Link
 * field so configured resolves an `asset::files::…` reference to the asset's
 * URL on the front end.
 *
 * The 4th "Asset" option is a native core Link capability (enabled by a single
 * `container` config key), so the app-side change is config-only. These tests
 * pin BOTH halves of that claim: (1) the reusable link fieldset carries
 * `container: files` (regression guard on the config edit), and (2) a `link`
 * field with that config actually augments a stored `asset::<container>::<path>`
 * value to the asset URL — proving the mechanism, not just the option label.
 *
 * Runs in the `serial` group: `beforeEach` persists an asset container whose
 * disk is only registered in this process (the container-leak convention — see
 * AssetsMinimumDimensionsEnforcementTest, UAMS-Web/uams-statamic-archive#1120).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/LinkFilesAssetOptionTest.php
 */
uses(TestCase::class)->group('serial');

const LINK_FILES_CONTAINER = 'link_files_probe';
const LINK_FILES_DISK = 'link_files_probe_disk';
const LINK_FILES_URL = 'http://localhost/link-files-probe';

beforeEach(function (): void {
    // A file-backed disk WITH a url so the resolved asset has a real URL to
    // assert against (Storage::fake would discard the url, leaving url() null).
    config(['filesystems.disks.'.LINK_FILES_DISK => [
        'driver' => 'local',
        'root' => storage_path('framework/testing/'.LINK_FILES_DISK),
        'url' => LINK_FILES_URL,
        'visibility' => 'public',
    ]]);

    File::ensureDirectoryExists(storage_path('framework/testing/'.LINK_FILES_DISK));
    File::put(storage_path('framework/testing/'.LINK_FILES_DISK.'/brochure.pdf'), '%PDF-1.4 probe');

    /** @var Statamic\Assets\AssetContainer $container */
    $container = AssetContainer::make(LINK_FILES_CONTAINER);
    $container->disk(LINK_FILES_DISK);
    $container->title('Link Files Probe');
    $container->save();

    /** @var Statamic\Assets\AssetContainer $savedContainer */
    $savedContainer = AssetContainer::find(LINK_FILES_CONTAINER);
    /** @var Asset $asset */
    $asset = $savedContainer->makeAsset('brochure.pdf');
    $asset->save();
});

afterEach(function (): void {
    $container = AssetContainer::find(LINK_FILES_CONTAINER);

    // Delete without Statamic's asset reference sweep, which walks every entry,
    // term and global per delete and was most of this file's time (UAMS-Web/uams-statamic-archive#2500)
    AssetReferenceSweep::withoutSweep(function () use ($container): void {
        $container?->asset('brochure.pdf')?->delete();
        $container?->delete();
    });

    File::deleteDirectory(storage_path('framework/testing/'.LINK_FILES_DISK));
});

it('scopes the reusable link_url control to the files asset container', function (): void {
    /** @var Statamic\Fields\Fieldset $fieldset */
    $fieldset = Fieldset::find('uamsweb_common_link');

    expect($fieldset)->not->toBeNull('the uamsweb_common_link fieldset must exist');

    /** @var array{fields: array<int, array<string, mixed>>} $contents */
    $contents = $fieldset->contents();
    /** @var array{field: array{type: string, container: string}} $linkUrl */
    $linkUrl = collect($contents['fields'])->firstWhere('handle', 'link_url');

    expect($linkUrl['field']['type'])->toBe('link')
        ->and($linkUrl['field']['container'])->toBe('files');
});

it('augments an asset link reference to the asset URL', function (): void {
    $field = new Field('link_url', ['type' => 'link', 'container' => LINK_FILES_CONTAINER]);

    /** @var Statamic\Assets\AssetContainer $container */
    $container = AssetContainer::find(LINK_FILES_CONTAINER);
    /** @var Asset $asset */
    $asset = $container->asset('brochure.pdf');

    /** @var Fieldtype $fieldtype */
    $fieldtype = $field->fieldtype();
    /** @var ArrayableLink $augmented */
    $augmented = $fieldtype->augment('asset::'.LINK_FILES_CONTAINER.'::brochure.pdf');

    expect($asset->url())->not->toBeNull()
        ->and($augmented->url())->toBe($asset->url())
        ->and($augmented->url())->not->toBe('asset::'.LINK_FILES_CONTAINER.'::brochure.pdf');
});
