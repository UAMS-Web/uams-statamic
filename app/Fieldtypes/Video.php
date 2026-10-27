<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use Statamic\Fieldtypes\Video as BaseVideo;

/**
 * Application Video fieldtype that supersedes Statamic's `video` handle.
 *
 * PHP behavior and field config match the vendor Video fieldtype; the
 * control panel uses `custom_video-fieldtype` from `resources/js/cp.js`, which
 * previews direct file URLs with a native `<video>` element instead of an
 * iframe.
 *
 * After Statamic upgrades, re-diff these core files and port any relevant
 * changes into `resources/js/components/fieldtypes/VideoFieldtype.vue` (and
 * this class if `Video` PHP config changes):
 *
 * - `vendor/statamic/cms/src/Fieldtypes/Video.php`
 * - `vendor/statamic/cms/resources/js/components/fieldtypes/VideoFieldtype.vue`
 *
 * @see BaseVideo
 */
class Video extends BaseVideo
{
    /**
     * Vue component handle for the control panel (registered as
     * `custom_video-fieldtype` in `resources/js/cp.js`, mirroring custom Text).
     *
     * @return string Component name passed to the CP
     */
    #[\Override]
    public function component(): string
    {
        return 'custom_video';
    }
}
