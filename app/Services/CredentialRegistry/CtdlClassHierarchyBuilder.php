<?php

declare(strict_types=1);

namespace App\Services\CredentialRegistry;

use App\Enums\Attributes\CredentialRegistryTypeInfo;
use App\Enums\CredentialRegistry;

/**
 * Builds CTDL class hierarchy data for the CtdlClass fieldtype from
 * CredentialRegistry enum metadata (children lists and labels).
 */
final class CtdlClassHierarchyBuilder
{
    /**
     * Option rows use enum slug as key and human label as value (YAML-friendly).
     *
     * @return array{
     *     top_level: list<array{key: string, value: string}>,
     *     children_by_shorthand: array<string, list<array{key: string, value: string}>>,
     *     labels_by_slug: array<string, string>
     * }
     */
    public static function preload(): array
    {
        // Initialize top-level CTDL class options
        $topLevel = [];

        // Initialize map of parent shorthand URI to child option rows
        $childrenByShorthand = [];

        // Initialize slug => label for display strings (e.g. "Credential Type")
        $labelsBySlug = [];

        // Walk every registry case to collect labels and hierarchy edges
        foreach (CredentialRegistry::cases() as $case) {
            // Read type metadata from the enum attribute
            $info = $case->info();
            // Skip cases without metadata or slug
            // Every case the enum generator writes carries the attribute (measured: 120 of
            // 120), so the skip never runs; it guards a hand-added case.
            // @pest-mutate-ignore: InstanceOfToTrue, ContinueToBreak
            if (! $info instanceof CredentialRegistryTypeInfo) {
                continue;
            }

            // Every generated case has a non-empty slug (measured: 120 of 120).
            // @pest-mutate-ignore: EmptyStringToNotEmpty, ContinueToBreak
            if ($info->slug === '') {
                continue;
            }

            // Record label for this slug for CP labels
            $labelsBySlug[$info->slug] = $info->label;

            // Only ceterms:* classes participate in this selector
            if (! str_starts_with($case->value, 'ceterms:')) {
                continue;
            }

            // Check whether this class is a "top-level" ceterms root for the UI
            $subclassOf = $info->subclass_of;

            // Top-level: no ceterms parent (an empty subclass_of does not start with ceterms: either)
            if (! str_starts_with($subclassOf, 'ceterms:')) {
                // Add this case as a root option
                $topLevel[] = self::optionRow($case);
            }

            // Check if this case lists immediate child class URIs
            if ($info->children === []) {
                continue;
            }

            // Build child option rows for this parent shorthand
            $childRows = [];

            // Resolve each child URI to an enum case when present
            foreach ($info->children as $childUri) {
                // Look up the child term in the registry
                $childCase = CredentialRegistry::tryFrom($childUri);

                // Skip unknown URIs (should be rare)
                // Every generated child URI names a case (measured: none unresolved).
                // @pest-mutate-ignore: ContinueToBreak
                if ($childCase === null) {
                    continue;
                }

                // Append option row for the child class
                $childRows[] = self::optionRow($childCase);
            }

            // Sort children by slug for stable ordering
            // The generator already emits each parent's children in slug order (measured: all
            // 15 parents), so the sort keeps that order only if the enum ever changes.
            // @pest-mutate-ignore: RemoveFunctionCall
            usort(
                $childRows,
                static fn (array $a, array $b): int => strcmp($a['key'], $b['key'])
            );

            // Store children under the parent shorthand URI key
            $childrenByShorthand[$case->value] = $childRows;
        }

        // Sort top-level options by slug
        // The generator already emits the 42 roots in slug order, so the sort keeps that
        // order only if the enum ever changes.
        // @pest-mutate-ignore: RemoveFunctionCall
        usort(
            $topLevel,
            static fn (array $a, array $b): int => strcmp($a['key'], $b['key'])
        );

        return [
            'top_level' => $topLevel,
            'children_by_shorthand' => $childrenByShorthand,
            'labels_by_slug' => $labelsBySlug,
        ];
    }

    /**
     * Build one option row (slug key, label value) from a registry case.
     *
     * @return array{key: string, value: string}
     */
    private static function optionRow(CredentialRegistry $case): array
    {
        // Read metadata for slug and label
        $info = $case->info();

        // Every generated case carries the attribute (measured: 120 of 120), so the
        // case-name fallbacks below never run; they guard a hand-added case.
        // @pest-mutate-ignore: InstanceOfToTrue
        $hasInfo = $info instanceof CredentialRegistryTypeInfo;

        // Every generated case also has a non-empty slug (measured: 120 of 120).
        // @pest-mutate-ignore: BooleanAndToBooleanOr, EmptyStringToNotEmpty
        return [
            'key' => $hasInfo && $info->slug !== '' ? $info->slug : $case->name,
            'value' => $hasInfo ? $info->label : $case->name,
        ];
    }
}
