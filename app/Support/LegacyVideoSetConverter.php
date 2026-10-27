<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Converts stored legacy Bard `video` set blocks to the platform-specific
 * `youtube` / `vimeo` / `box` sets that UAMS-Web/uams-statamic-archive#589 introduced (UAMS-Web/uams-statamic-archive#834).
 *
 * UAMS-Web/uams-statamic-archive#589 removed the single `video` set from the picker but left existing entries
 * carrying `type: video` set blocks — orphaned in the CP though still rendering.
 * This converter rewrites each such block in place: routing by the `video_url`
 * host to a platform set, renaming `video_url` → `url`, defaulting `embed_mode` to
 * `simple` (preserving current behavior), and keeping `caption` / `aspect_ratio`
 * / `align`. A block whose host matches no known platform is left untouched and
 * reported, so the caller can flag it rather than silently drop it.
 */
class LegacyVideoSetConverter
{
    /**
     * Convert every legacy `video` set block in an entry-data structure.
     *
     * @param  mixed  $value  Raw entry data (ProseMirror nodes / field values)
     * @return array{0: mixed, 1: int, 2: int} The rewritten value, the number of
     *                                         blocks converted, and the number skipped
     *                                         (unrecognized host)
     */
    public static function apply(mixed $value): array
    {
        $converted = 0;
        $skipped = 0;
        $rewritten = self::walk($value, $converted, $skipped);

        return [$rewritten, $converted, $skipped];
    }

    /**
     * Map a video URL to its platform set handle, or null when unrecognized.
     */
    public static function platformFor(string $url): ?string
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));

        // Exact host or subdomain match — a substring test would mis-route
        // lookalikes (dropbox.com → box, youtube.com.evil.com → youtube).
        foreach ([
            'youtube.com' => 'youtube',
            'youtu.be' => 'youtube',
            'vimeo.com' => 'vimeo',
            'box.com' => 'box',
        ] as $domain => $platform) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return $platform;
            }
        }

        return null;
    }

    /**
     * Recursively rewrite legacy video set blocks, counting conversions + skips.
     */
    private static function walk(mixed $value, int &$converted, int &$skipped): mixed
    {
        if (! \is_array($value)) {
            return $value;
        }

        // A Bard set block: { type: 'set', attrs: { values: { type: 'video', … } } }.
        if (($value['type'] ?? null) === 'set' && \is_array($value['attrs'] ?? null)) {
            $values = $value['attrs']['values'] ?? null;

            if (\is_array($values) && ($values['type'] ?? null) === 'video') {
                $url = $values['video_url'] ?? '';
                $platform = \is_string($url) ? self::platformFor($url) : null;

                if ($platform !== null) {
                    // Route to the platform set, rename the url key, and preserve the rest.
                    $values['type'] = $platform;
                    $values['url'] = $values['video_url'] ?? '';
                    unset($values['video_url']);

                    if (! \array_key_exists('embed_mode', $values)) {
                        $values['embed_mode'] = 'simple';
                    }

                    $value['attrs']['values'] = $values;
                    $converted++;
                } else {
                    $skipped++;
                }
            }
        }

        // Recurse into every nested array (block children, marks, field values).
        foreach ($value as $key => $item) {
            if (\is_array($item)) {
                $value[$key] = self::walk($item, $converted, $skipped);
            }
        }

        return $value;
    }
}
