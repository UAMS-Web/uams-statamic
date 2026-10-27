<?php

declare(strict_types=1);

namespace App\Scopes;

use App\Enums\MimeType;
use Illuminate\Support\Arr;
use Statamic\Assets\QueryBuilder;
use Statamic\Query\Scopes\Scope;

/**
 * Asset query scope that filters assets by file type.
 *
 * Resolves file type filtering from encoded query scope handles, such as:
 * - assets_by_file_type_audio
 * - assets_by_file_type_image_jpeg
 *
 * Used with the Assets fieldtype so the asset browser can show only allowed
 * file types without query-scope parameter transport.
 */
class AssetsByFileType extends Scope
{
    /**
     * Scope handle for registration and config.
     *
     * @var string
     */
    protected static $handle = 'assets_by_file_type';

    /**
     * Register dynamic aliases so file type rules can be encoded in scope
     * handles.
     *
     * Examples:
     * - assets_by_file_type_audio
     * - assets_by_file_type_image_jpeg
     *
     * @return array<int, string>
     */
    public static function aliases(): array
    {
        // Initialize the common handle prefix used by all aliases.
        $prefix = static::$handle.'_';

        // Build aliases for all MIME categories.
        $categoryAliases = array_map(
            static fn (string $category): string => $prefix.strtolower($category),
            MimeType::getCategories()
        );

        // Build aliases for all enum case values (e.g. AUDIO_MP3 -> audio_mp3).
        $mimeTypeAliases = array_map(
            static fn (MimeType $mimeType): string => $prefix.strtolower($mimeType->value),
            MimeType::cases()
        );

        // Merge and dedupe aliases for registration.
        $aliases = array_values(array_unique([
            ...$categoryAliases,
            ...$mimeTypeAliases,
        ]));

        // Clean up variables to free up memory.
        unset(
            $prefix,
            $categoryAliases,
            $mimeTypeAliases
        );

        // Return all dynamic aliases.
        return $aliases;
    }

    /**
     * Apply the scope to the asset query.
     *
     * Applies extension-based filtering derived from encoded query scope
     * handles in request values.
     *
     * No-op when no encoded scope handles are present.
     *
     * @param  QueryBuilder  $query  Asset query builder (has whereIn() from parent)
     * @param  array<string, mixed>  $values  Request values (e.g. queryScopes)
     */
    public function apply(
        $query,
        $values
    ): void {
        // Build allowed extensions from encoded query scope handles.
        $extensions = $this->allowedExtensionsFromScopeHandles($values);

        // Check if no filter input was provided and skip query changes.
        if ($extensions === []) {
            // Clean up variables to free up memory.
            unset($extensions);

            // Return early when no filter is available.
            return;
        }

        // Apply extension-based filtering when extensions are available.
        //
        // Must be whereIn(), not where('extension', 'in', …): Statamic's query
        // builder has no `in` operator, so the where() form silently rewrites to
        // where('extension', '=', 'in') and matches nothing (UAMS-Web/uams-statamic-archive#1364).
        $query->whereIn(
            'extension',
            array_map(strtolower(...), $extensions)
        );

        // Clean up variables to free up memory.
        unset($extensions);
    }

    /**
     * Build allowed extensions from encoded query scope handles.
     *
     * Supports handles in the format:
     * - assets_by_file_type_{category}
     * - assets_by_file_type_{enum_case_in_lower_snake}
     *
     * @param  array<string, mixed>  $values
     * @return array<int, string>
     */
    private function allowedExtensionsFromScopeHandles(array $values): array
    {
        // Extract encoded handle suffixes from incoming query scopes.
        $suffixes = $this->encodedScopeSuffixes($values);

        // Check if no encoded suffixes were provided.
        if ($suffixes === []) {
            // Return early
            return [];
        }

        // Initialize category collection
        $categories = [];

        // Initialize enum-case collection
        $mimeTypeCases = [];

        // Parse each suffix as either category or enum case.
        foreach ($suffixes as $suffix) {
            // Check if the suffix denotes an enum case candidate.
            if (str_contains($suffix, '_')) {
                // Resolve enum case from lower snake suffix.
                $mimeTypeCase = MimeType::tryFrom(strtoupper($suffix));

                // Check if the enum case was resolved.
                if ($mimeTypeCase !== null) {
                    // Add resolved enum case to filter list.
                    $mimeTypeCases[] = $mimeTypeCase;
                }
            } else {
                // Add category suffix as category filter.
                $categories[] = strtolower($suffix);
            }
        }

        // Initialize extensions from parsed scope handle values.
        $extensions = [];

        // Resolve and collect extensions from category filters.
        if ($categories !== []) {
            // Find MIME types matching any configured category.
            $mimeTypesFromCategories = MimeType::findByCategories($categories);

            // Loop through each matched MIME type
            foreach ($mimeTypesFromCategories as $mimeType) {
                // Merge extensions from each matched MIME type.
                $extensions = [
                    ...$extensions,
                    ...$mimeType->extensions(),
                ];
            }

            // Clean up category-derived MIME type collection.
            unset($mimeTypesFromCategories);
        }

        // Resolve and collect extensions from enum case filters.
        foreach ($mimeTypeCases as $mimeTypeCase) {
            // Merge extensions for this enum case.
            $extensions = [
                ...$extensions,
                ...$mimeTypeCase->extensions(),
            ];
        }

        // Dedupe and normalize the final extension list.
        $extensions = array_values(array_unique(array_filter($extensions)));

        // Clean up variables to free up memory.
        unset(
            $suffixes,
            $categories,
            $mimeTypeCases
        );

        // Return extensions parsed from encoded handles.
        return $extensions;
    }

    /**
     * Extract encoded suffixes from query scope handles.
     *
     * @param  array<string, mixed>  $values
     * @return array<int, string>
     */
    private function encodedScopeSuffixes(array $values): array
    {
        // Extract query scope handles from contextual values.
        $queryScopes = Arr::wrap($values['queryScopes'] ?? []);

        // Initialize the encoded-handle prefix.
        $prefix = static::$handle.'_';

        // Initialize extracted suffix collection.
        $suffixes = [];

        // Parse each query scope and collect matching encoded suffixes.
        foreach ($queryScopes as $queryScope) {
            // Check if the current value is not a string.
            if (! \is_string($queryScope)) {
                // Skip non-string values
                continue;
            }

            // Check if the query scope starts with the encoded prefix.
            if (! str_starts_with($queryScope, $prefix)) {
                // Skip values that do not start with the encoded prefix
                continue;
            }

            // Extract suffix portion from the query scope.
            $suffix = substr($queryScope, \strlen($prefix));

            // Check if suffix is empty.
            if ($suffix === '') {
                // Skip empty suffixes
                continue;
            }

            // Add suffix to the collection.
            $suffixes[] = strtolower($suffix);
        }

        // Dedupe suffixes for stable parsing.
        $suffixes = array_values(array_unique($suffixes));

        // Clean up variables to free up memory.
        unset(
            $queryScopes,
            $prefix
        );

        // Return extracted encoded suffixes.
        return $suffixes;
    }
}
