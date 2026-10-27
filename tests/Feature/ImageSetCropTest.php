<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#17: the Image Bard set offers a focal-crop aspect ratio
 * (None / 16:9 / 4:3 / 1:1), passed straight to the peak-tools picture
 * component (which crops focal-aware across responsive widths).
 *
 * @command  php artisan test --compact tests/Feature/ImageSetCropTest.php
 */
it('offers None / 16:9 / 4:3 / 1:1 crop ratios on the Image set', function (): void {
    /** @var array<string, mixed> $fieldset */
    $fieldset = Yaml::parseFile(resource_path('fieldsets/uamsweb_set_image.yaml'));

    /** @var list<array<string, mixed>> $fields */
    $fields = $fieldset['fields'];

    /** @var array{field: array{type: string, options: array<int|string, mixed>, default: string}} $crop */
    $crop = collect($fields)->firstWhere('handle', 'crop');
    expect($crop)->not->toBeNull();
    expect($crop['field']['type'])->toBe('select');

    // Keys are Glide-style ratios; None is the empty default (natural ratio).
    $keys = collect($crop['field']['options'])->pluck('key')->all();
    expect($keys)->toBe(['', '16/9', '4/3', '1/1']);
    expect($crop['field']['default'])->toBe('');
});

it('passes the selected ratio to the focal-crop picture component', function (): void {
    $set = File::get(resource_path('views/sets/_image.antlers.html'));

    expect($set)->toContain(':aspect_ratio="crop"');
});
