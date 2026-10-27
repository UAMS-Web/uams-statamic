<?php

declare(strict_types=1);

namespace App\Modifiers;

use Statamic\Modifiers\Modifier;
use Stringable;

/**
 * Injects {@code rel="noopener noreferrer"} into every anchor in an HTML string
 * that opens in a new window ({@code target="_blank"}) but carries no {@code rel}
 * attribute.
 *
 * Statamic's Bard link mark serializes stored {@code rel: null} verbatim, so a
 * {@code target="_blank"} link authored in the CP renders without any {@code rel}
 * — a reverse-tabnabbing gap the template-level sweep (UAMS-Web/uams-statamic-archive#603/UAMS-Web/uams-statamic-archive#723) never reached.
 * Applying this modifier at each Bard render site closes that gap defensively for
 * both stored content and future authoring. (UAMS-Web/uams-statamic-archive#724)
 *
 * Use on rendered Bard HTML, e.g. {@code {{ text | rel_noopener | widont }}}.
 * Anchors that already carry a {@code rel} are left untouched.
 */
class RelNoopener extends Modifier
{
    /**
     * Add the missing {@code rel} to new-window anchors in Bard HTML.
     *
     * @param  mixed  $value  Rendered Bard HTML (string / Stringable)
     * @param  array<int|string, mixed>  $params  Unused
     * @param  array<string, mixed>  $context  Unused Statamic modifier context
     * @return mixed The HTML with `rel` injected, or the value unchanged when it is not a string
     */
    public function index(
        mixed $value,
        array $params,
        array $context
    ): mixed {
        // Only operate on string-like HTML; a structured (non-Stringable) value passes through untouched.
        if (! \is_string($value)) {
            if (\is_scalar($value) || $value instanceof Stringable) {
                $value = (string) $value;
            } else {
                return $value;
            }
        }

        return $this->injectRel($value);
    }

    /**
     * Add {@code rel="noopener noreferrer"} to each {@code target="_blank"} anchor
     * that lacks a {@code rel}, leaving all other anchors byte-for-byte intact.
     */
    private function injectRel(string $html): string
    {
        // preg_replace_callback() returns null only when a pattern fails, which this fixed
        // pattern over a string cannot do.
        // @pest-mutate-ignore: RemoveStringCast
        return (string) preg_replace_callback(
            '/<a\b[^>]*>/i',
            static function (array $matches): string {
                $tag = $matches[0];

                // Only anchors that open in a new window are in scope. The
                // `(?<![\w-])` lookbehind keeps `data-target` from matching.
                if (preg_match('/(?<![\w-])target\s*=\s*["\']?_blank\b/i', $tag) !== 1) {
                    return $tag;
                }

                // An author-set rel (any value) is preserved as-is. The lookbehind
                // keeps `data-rel` (or any `*-rel`) from being read as a real rel.
                if (preg_match('/(?<![\w-])rel\s*=/i', $tag) === 1) {
                    return $tag;
                }

                // Inject the rel immediately before the closing angle bracket.
                // preg_replace() returns null only when a pattern fails, and `\s*>$` can match only
                // once, at the end of the tag, so a higher limit replaces the same.
                // @pest-mutate-ignore: RemoveStringCast, IncrementInteger
                return (string) preg_replace('/\s*>$/', ' rel="noopener noreferrer">', $tag, 1);
            },
            $html
        );
    }
}
