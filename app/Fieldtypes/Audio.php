<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use Statamic\Fieldtypes\Video as BaseVideo;

/**
 * Audio fieldtype: stores a media URL and previews it in the control panel with a
 * native `<audio>` element (UAMS-Web/uams-statamic-archive#590).
 *
 * Mirrors the vendor Video fieldtype's shape — a single URL string, `placeholder`
 * + `default` config, `media` category — because audio is the same "store a URL,
 * preview it" contract. Unlike {@see Video} (which supersedes the
 * core `video` handle), this registers a brand-new, additive `audio` handle and
 * uses `custom_audio-fieldtype` from `resources/js/cp.js`, which previews direct
 * file URLs with `<audio controls>`.
 *
 * v1 is direct-file audio only — no third-party embed/iframe (the decision
 * recorded on UAMS-Web/uams-statamic-archive#590). After Statamic upgrades, re-diff the vendor Video sources and
 * port relevant changes here and into
 * `resources/js/components/fieldtypes/AudioFieldtype.vue`:
 *
 * - `vendor/statamic/cms/src/Fieldtypes/Video.php`
 * - `vendor/statamic/cms/resources/js/components/fieldtypes/VideoFieldtype.vue`
 *
 * @see BaseVideo
 */
class Audio extends BaseVideo
{
    /**
     * Search keywords used to find this fieldtype in the Control Panel.
     *
     * @var array<string> Search keywords used to find this fieldtype in the Control Panel
     */
    protected $keywords = [
        'audio',
        'sound',
        'music',
        'mp3',
        'podcast',
    ];

    /**
     * The icon for this fieldtype in the Control Panel selector.
     *
     * There is no `audio` icon in the CP set, so point at an existing one
     * (`resources/svg/icons/media-music-notes.svg`).
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'media-music-notes';
    }

    /**
     * The name of the Vue component to use for this fieldtype (registered as
     * `custom_audio-fieldtype` in `resources/js/cp.js`).
     *
     * @return string The name of the Vue component to use for this fieldtype
     */
    #[\Override]
    public function component(): string
    {
        return 'custom_audio';
    }
}
