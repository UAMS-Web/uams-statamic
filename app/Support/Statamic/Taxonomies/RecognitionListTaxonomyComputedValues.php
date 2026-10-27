<?php

declare(strict_types=1);

namespace App\Support\Statamic\Taxonomies;

use Statamic\Facades\Term;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;

/**
 * Builds the virtual `full_name` (recognition_lists_series) string from the
 * series term's publication + series names.
 *
 * The former `award_name` computed on the flat `recognition_lists` taxonomy was
 * retired with that taxonomy when recognition-list membership was re-modeled as a
 * structured `recognition_lists` collection (UAMS-Web/uams-statamic-archive#1546); a list is now a first-class
 * entry whose `title` is its display label. The `recognition_lists_series`
 * taxonomy (and this `full_name` computed) survives that re-model — the series
 * classification still rides on the list entry via its `series` field.
 */
final class RecognitionListTaxonomyComputedValues
{
    /**
     * Taxonomy handle for publication organization names.
     */
    private const string RECOGNITION_LISTS_PUBLICATIONS = 'recognition_lists_publications';

    /**
     * Build `Publication name` + `Series name` for a series localized term.
     *
     * @param  LocalizedTerm  $seriesLocalized  Series term in the active site
     * @return string Joined label, or empty string when parts cannot resolve
     */
    public static function fullNameForSeriesLocalizedTerm(LocalizedTerm $seriesLocalized): string
    {
        // Resolve the publication term id from the series `publication` field
        $publicationId = self::normalizeTermId(
            $seriesLocalized->value('publication'),
            self::RECOGNITION_LISTS_PUBLICATIONS
        );

        // Read the locale handle from the series term as a string
        /** @var string $seriesLocale */
        $seriesLocale = $seriesLocalized->locale();

        // Load the publication term in the same locale as the series term
        $publicationLocalized = self::findLocalizedTerm(
            $publicationId,
            $seriesLocale
        );

        // Read the publication display name when the term exists
        $publicationName = self::trimmedString(
            $publicationLocalized?->value('name')
        );

        // Read the series display name from the same localized term
        $seriesName = self::trimmedString($seriesLocalized->value('name'));

        // Join non-empty name segments with a single space
        return self::joinNameParts(
            [
                $publicationName,
                $seriesName,
            ]
        );
    }

    /**
     * Normalize a taxonomy terms field value to a Statamic term id.
     *
     * @param  mixed  $value  Slug, `taxonomy::slug`, or a single-element list
     * @param  string  $taxonomyHandle  Expected taxonomy when value is bare slug
     * @return string|null Term id, or null when empty
     */
    public static function normalizeTermId(mixed $value, string $taxonomyHandle): ?string
    {
        // Treat null and empty string as missing
        if ($value === null || $value === '') {
            return null;
        }

        // Unwrap a single-element array from the terms field
        if (is_array($value)) {
            $first = $value[0] ?? null;

            if ($first === null || $first === '') {
                return null;
            }

            $value = $first;
        }

        // Coerce non-string scalars to string ids when safe
        if (! is_string($value)) {
            return null;
        }

        // Keep values that already include the taxonomy namespace
        if (str_contains($value, '::')) {
            return $value;
        }

        // Build a canonical id from taxonomy handle and slug
        return sprintf('%s::%s', $taxonomyHandle, $value);
    }

    /**
     * Load a term by id and return it localized for the requested site handle.
     *
     * @param  string|null  $id  Term id (`taxonomy::slug`)
     * @param  string  $locale  Site handle (e.g. `default`)
     * @return LocalizedTerm|null Localized term, or null when missing
     */
    public static function findLocalizedTerm(?string $id, string $locale): ?LocalizedTerm
    {
        // Skip lookup when the id is empty
        if ($id === null || $id === '') {
            return null;
        }

        // Resolve the term from the Stache repository (the facade returns the concrete term)
        /** @var TaxonomyTerm|null $term */
        $term = Term::find($id);

        // Return null when the repository has no such term
        if ($term === null) {
            return null;
        }

        // Normalize base `Term` to `LocalizedTerm` for the requested site
        return self::toLocalizedTerm($term, $locale);
    }

    /**
     * Trim a scalar string to empty string when not a non-empty string.
     *
     * @param  mixed  $value  Raw blueprint value
     * @return string Trimmed string, or empty string
     */
    private static function trimmedString(mixed $value): string
    {
        // Return empty for non-strings
        if (! is_string($value)) {
            return '';
        }

        // Trim leading and trailing whitespace from the string. Equivalent: joinNameParts()
        // trims every part again.
        // @pest-mutate-ignore: UnwrapTrim
        $trimmed = trim($value);

        return $trimmed;
    }

    /**
     * Join trimmed non-empty segments with a single ASCII space.
     *
     * @param  list<string|null>  $parts  Ordered name fragments
     * @return string Single-line display string
     */
    private static function joinNameParts(array $parts): string
    {
        // Drop null, empty, and whitespace-only segments. Equivalent without array_values():
        // implode() ignores keys. And the map is equivalent too: every caller passes
        // trimmedString() output, which is already a trimmed string.
        // @pest-mutate-ignore: UnwrapArrayValues, UnwrapArrayMap
        $filtered = array_values(
            array_filter(
                array_map(
                    // @pest-mutate-ignore: EmptyStringToNotEmpty, UnwrapTrim
                    static fn (?string $part): string => is_string($part) ? trim($part) : '',
                    $parts
                ),
                static fn (string $part): bool => $part !== ''
            )
        );

        // Collapse segments with single spaces
        return implode(' ', $filtered);
    }

    /**
     * Convert a repository `Term` or `LocalizedTerm` to a `LocalizedTerm`.
     *
     * @param  LocalizedTerm|TaxonomyTerm  $term  Repository term instance
     * @param  string  $locale  Target site handle
     */
    private static function toLocalizedTerm(
        LocalizedTerm|TaxonomyTerm $term,
        string $locale
    ): LocalizedTerm {
        // Reuse the localized instance when it already matches the site handle
        if ($term instanceof LocalizedTerm && $term->locale() === $locale) {
            return $term;
        }

        // Localize a base or cross-site term for the requested site handle
        /** @var LocalizedTerm $localized */
        $localized = $term->in($locale);

        return $localized;
    }
}
