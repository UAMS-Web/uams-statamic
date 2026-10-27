<?php

declare(strict_types=1);

namespace App\Support;

use App\Modifiers\RelNoopener;

/**
 * Rewrites stored Bard link marks that open in a new window ({@code target:
 * '_blank'}) but stored no {@code rel}, setting {@code rel} to
 * {@code 'noopener noreferrer'}.
 *
 * This cleans data at rest to complement the render-time {@see RelNoopener}
 * backstop — together they close the reverse-tabnabbing gap in stored content and
 * future authoring (UAMS-Web/uams-statamic-archive#724). Operates on raw ProseMirror entry data, so it walks
 * every nested field generically rather than assuming which fields are Bard.
 */
class BardRelNoopenerBackfill
{
    /**
     * Backfill new-window link marks in an entry-data structure.
     *
     * @param  mixed  $value  Raw entry data (arrays of ProseMirror nodes / field values)
     * @return array{0: mixed, 1: int} The rewritten value and the number of link marks fixed
     */
    public static function apply(mixed $value): array
    {
        $count = 0;
        $rewritten = self::walk($value, $count);

        return [$rewritten, $count];
    }

    /**
     * Recursively rewrite matching link marks, counting each fix.
     */
    private static function walk(mixed $value, int &$count): mixed
    {
        if (! \is_array($value)) {
            return $value;
        }

        // A Bard link mark: { type: 'link', attrs: { href, rel, target } }.
        $attrs = $value['attrs'] ?? null;
        if (($value['type'] ?? null) === 'link' && \is_array($attrs)) {
            $target = $attrs['target'] ?? null;
            $rel = $attrs['rel'] ?? null;

            // Only new-window links with no (or empty) rel are rewritten.
            if ($target === '_blank' && ($rel === null || $rel === '')) {
                $attrs['rel'] = 'noopener noreferrer';
                $value['attrs'] = $attrs;
                $count++;
            }
        }

        // Recurse into every nested array (node children, marks, field values).
        foreach ($value as $key => $item) {
            if (\is_array($item)) {
                $value[$key] = self::walk($item, $count);
            }
        }

        return $value;
    }
}
