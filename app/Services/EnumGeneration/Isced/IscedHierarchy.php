<?php

declare(strict_types=1);

namespace App\Services\EnumGeneration\Isced;

/**
 * Shared hierarchy helpers for ISCED code lists.
 *
 * Encodes the prefix-based parent rule used by ISCED 2011 levels (P/A) and
 * ISCED-F 2013 fields, parameterized by the digit length of top-tier codes
 * (1 for ISCED-P / ISCED-A, 2 for ISCED-F broad fields).
 */
final class IscedHierarchy
{
    /**
     * Compute parent code by stripping the last character.
     *
     * Returns null when `$code` is already at or shorter than the top tier.
     */
    public static function parentCode(string $code, int $minTierLength): ?string
    {
        if (strlen($code) <= $minTierLength) {
            return null;
        }

        return substr($code, 0, -1);
    }

    /**
     * Tier number (1 = top tier) derived from code length.
     */
    public static function tier(string $code, int $minTierLength): int
    {
        return strlen($code) - ($minTierLength - 1);
    }

    /**
     * Ancestor codes from broadest (root) to immediate parent, filtered to
     * those that actually exist in `$existing`.
     *
     * @param  array<string, true>  $existing
     * @return array<int, string>
     */
    public static function ancestors(string $code, int $minTierLength, array $existing): array
    {
        $chain = [];
        $walk = $code;
        while (true) {
            $parent = self::parentCode($walk, $minTierLength);
            if ($parent === null) {
                break;
            }

            $chain[] = $parent;
            $walk = $parent;
        }

        return array_values(array_filter(
            array_reverse($chain),
            static fn (string $a): bool => isset($existing[$a]),
        ));
    }

    /**
     * Build a parent-keyed children map from a list of codes, sorted by
     * length-then-lex within each parent.
     *
     * @param  array<int, string>  $codes
     * @return array<string, array<int, string>>
     */
    public static function buildChildrenMap(array $codes, int $minTierLength): array
    {
        $existing = array_fill_keys($codes, true);
        $byParent = [];
        foreach ($codes as $c) {
            $p = self::parentCode($c, $minTierLength);
            if ($p === null) {
                continue;
            }

            if (! isset($existing[$p])) {
                continue;
            }

            $byParent[$p] ??= [];
            $byParent[$p][] = $c;
        }

        foreach (array_keys($byParent) as $p) {
            usort($byParent[$p], self::compareCodes(...));
        }

        return $byParent;
    }

    /**
     * Collect all transitive descendants of `$code`, sorted by length-then-lex.
     *
     * @param  array<string, array<int, string>>  $childrenByParent
     * @return array<int, string>
     */
    public static function descendants(string $code, array $childrenByParent): array
    {
        $out = [];
        $stack = $childrenByParent[$code] ?? [];
        $seen = [];
        while ($stack !== []) {
            $c = array_pop($stack);
            if (isset($seen[$c])) {
                continue;
            }

            $seen[$c] = true;
            $out[] = $c;
            foreach ($childrenByParent[$c] ?? [] as $next) {
                $stack[] = $next;
            }
        }

        usort($out, self::compareCodes(...));

        return $out;
    }

    /**
     * Sort comparator: shorter codes first, then lexical within the same length.
     */
    public static function compareCodes(string $a, string $b): int
    {
        $len = strlen($a) <=> strlen($b);
        if ($len !== 0) {
            return $len;
        }

        return strcmp($a, $b);
    }
}
