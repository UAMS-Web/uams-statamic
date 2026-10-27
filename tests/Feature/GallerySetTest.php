<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#188: the Image Gallery Bard set renders a config-driven
 * responsive grid (via the shared responsive picture component) with an
 * accessible Alpine lightbox, and no longer caps the image count. The lightbox
 * behavior itself is covered by resources/js/gallery-lightbox.test.js.
 *
 * @command  php artisan test --compact tests/Feature/GallerySetTest.php
 */
it('renders every image via the responsive picture component with no cap', function (): void {
    $set = File::get(resource_path('views/sets/_gallery.antlers.html'));

    expect($set)
        // The placeholder's hard-coded 6-image cap is gone.
        ->not->toContain('limit(6)')
        // Responsive renditions come from the shared picture component.
        ->toContain('partial:page_builder/components/gallery_image_picture')
        // Config-driven columns.
        ->toContain('data-columns="{{ $cols }}"');
});

it('wires the accessible Alpine lightbox', function (): void {
    $set = File::get(resource_path('views/sets/_gallery.antlers.html'));

    expect($set)
        ->toContain('x-data="galleryLightbox"')
        // Triggers carry the full-size src / alt / caption for the lightbox.
        ->toContain('data-gallery-full=')
        ->toContain('@click="show($el)"')
        // A modal dialog with prev/next/close controls + keyboard handling.
        ->toContain('role="dialog"')
        ->toContain('aria-modal="true"')
        ->toContain('@click="prev()"')
        ->toContain('@click="next()"')
        ->toContain('@keydown.escape.window="close()"')
        ->toContain('@keydown.tab="trapFocus($event)"');
});

it('registers the lightbox component before Alpine starts', function (): void {
    $siteJs = File::get(resource_path('js/site.js'));

    expect($siteJs)
        ->toContain("import { registerGalleryLightbox } from './gallery-lightbox.js'")
        ->toContain('registerGalleryLightbox(Alpine)');

    // Registered before the actual Alpine.start() call (strrpos skips the
    // "must run before Alpine.start()" comment earlier in the file).
    expect(strpos($siteJs, 'registerGalleryLightbox(Alpine)'))
        ->toBeLessThan((int) strrpos($siteJs, 'Alpine.start()'));
});

it('adds a columns option to the gallery set', function (): void {
    /** @var array<string, mixed> $fieldset */
    $fieldset = Yaml::parseFile(resource_path('fieldsets/uamsweb_set_gallery.yaml'));

    /** @var list<array<string, mixed>> $galleryFields */
    $galleryFields = $fieldset['fields'];
    /** @var array<string, mixed> $columns */
    $columns = collect($galleryFields)->firstWhere('handle', 'columns');
    expect($columns)->not->toBeNull();
    /** @var array<string, mixed> $columnsField */
    $columnsField = $columns['field'];
    expect($columnsField['type'])->toBe('select');
    /** @var list<array<string, mixed>> $columnsOptions */
    $columnsOptions = $columnsField['options'];
    expect(collect($columnsOptions)->pluck('key')->all())->toBe(['2', '3', '4']);
    expect($columnsField['default'])->toBe('3');
});
