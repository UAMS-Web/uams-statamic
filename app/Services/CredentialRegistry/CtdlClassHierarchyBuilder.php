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
            if (! $info instanceof CredentialRegistryTypeInfo) {
                continue;
            }

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

            // Top-level: no ceterms parent (empty or non-ceterms subclass_of)
            if ($subclassOf === '' || ! str_starts_with($subclassOf, 'ceterms:')) {
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
                if ($childCase === null) {
                    continue;
                }

                // Append option row for the child class
                $childRows[] = self::optionRow($childCase);
            }

            // Sort children by slug for stable ordering
            usort(
                $childRows,
                static fn (array $a, array $b): int => strcmp($a['key'], $b['key'])
            );

            // Store children under the parent shorthand URI key
            $childrenByShorthand[$case->value] = $childRows;
        }

        // Sort top-level options by slug
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

        return [
            'key' => $info instanceof CredentialRegistryTypeInfo && $info->slug !== ''
                ? $info->slug
                : $case->name,
            'value' => $info instanceof CredentialRegistryTypeInfo ? $info->label : $case->name,
        ];
    }
}
