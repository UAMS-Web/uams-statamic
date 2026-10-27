<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#836: the orphaned `video` set fieldset is gone, and each
 * platform video-segment set sources `digital_source_type` once (from the shared
 * `uamsweb_post_video_segment` import) — no redundant inline override survives
 * the UAMS-Web/uams-statamic-archive#589 restructure.
 *
 * @command  php artisan test --compact tests/Feature/VideoSetCleanupTest.php
 */
it('removes the orphaned video set fieldset', function (): void {
    expect(File::exists(resource_path('fieldsets/uamsweb_set_video.yaml')))->toBeFalse();
});

it('sources digital_source_type once per video segment set', function (): void {
    foreach (['uamsweb_video_segment_youtube', 'uamsweb_video_segment_vimeo', 'uamsweb_video_segment_box'] as $fieldset) {
        $yaml = File::get(resource_path("fieldsets/{$fieldset}.yaml"));

        // No inline duplicate — digital_source_type comes solely from the shared segment import.
        expect(substr_count($yaml, 'handle: digital_source_type'))->toBe(0);
        expect($yaml)->toContain('import: uamsweb_post_video_segment');
    }
});
