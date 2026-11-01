<?php

declare(strict_types=1);

namespace App\Tags;

use App\Modifiers\ApStyleDate;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Entry;
use Statamic\Fields\Value;
use Statamic\Query\OrderedQueryBuilder;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Build a dateline string for entries.
 *
 * Parameters:
 *   - location (string, optional): Dateline location text. Defaults to the
 *     configured field on the first location entry (from an augmented entry or
 *     query, augmented data, a stored entry id, or a slug in the collection).
 *   - location_field (string, optional): Entry field handle containing location
 *     entries. Defaults to `geographic_location`.
 *   - location_collection (string, optional): Collection searched when a location
 *     is given as a slug. Defaults to `geographic_locations`.
 *   - location_entry_field (string, optional): Field handle to read from the
 *     location entry. Defaults to `dateline`.
 *   - date (string, optional): Dateline date text. Defaults to current entry
 *     `date`, formatted using the `ap_style_date` modifier.
 *   - append_separator (mixed, optional): Whether to append
 *     ` — ` to the output. True values: `true`, `'true'`, `1`, `'1'`, `'yes'`.
 *     False values: `''`, `'null'`, `0`, `false`, `'false'`, `null`,
 *     `'undefined'`, `'no'`. Other values fall back to truthy/falsey semantics.
 *
 * Usage:
 *   - {{ dateline }}
 *   - {{ dateline location="Little Rock" }}
 *   - {{ dateline date="March 20, 2026" }}
 *   - {{ dateline location="Little Rock" date="March 20, 2026" }}
 *   - {{ dateline append_separator="true" }}
 *   - {{ dateline:separator }} (same as append_separator true; avoids Antlers treating
 *     `append_separator` as a modifier when nested, e.g. inside `ensure_left_bard`)
 */
class Dateline extends Tags
{
    /**
     * Handle for the tag.
     *
     * @var string
     */
    protected static $handle = 'dateline';

    /**
     * Dateline with trailing separator; use where `append_separator` would parse as a modifier.
     *
     * @return string|null The dateline output string
     */
    public function separator(): ?string
    {
        /** @var Parameters $params */
        $params = $this->params;
        $params['append_separator'] = true;

        return $this->index();
    }

    /**
     * Build and return the dateline string.
     *
     * @return string|null The dateline output string
     */
    public function index(): ?string
    {
        // Narrow the tag parameters and context to their concrete types
        /** @var Parameters $params */
        $params = $this->params;

        /** @var Context $tagContext */
        $tagContext = $this->context;

        // Get the append-separator parameter with false as the default value
        $appendSeparatorRaw = $params->get(
            'append_separator',
            false
        );

        // Normalize the append-separator parameter into a boolean result
        $appendSeparator = $this->normalizeBoolValue($appendSeparatorRaw);

        // Get the location parameter if one is provided
        $datelineLocation = $params->get('location');

        // Get the location field handle parameter
        $locationFieldHandleRaw = $params->get(
            'location_field',
            'geographic_location'
        );

        // Normalize location field handle into a usable string
        $locationFieldHandle = \is_string($locationFieldHandleRaw) && $locationFieldHandleRaw !== ''
            ? $locationFieldHandleRaw
            : 'geographic_location';

        // Get the location collection handle parameter
        $locationCollectionHandleRaw = $params->get(
            'location_collection',
            'geographic_locations'
        );

        // Normalize location collection handle into a usable string
        $locationCollectionHandle = \is_string($locationCollectionHandleRaw) && $locationCollectionHandleRaw !== ''
            ? $locationCollectionHandleRaw
            : 'geographic_locations';

        // Get the location entry field handle parameter
        $locationEntryFieldHandleRaw = $params->get(
            'location_entry_field',
            'dateline'
        );

        // Normalize location entry field handle into a usable string
        $locationEntryFieldHandle = \is_string($locationEntryFieldHandleRaw) && $locationEntryFieldHandleRaw !== ''
            ? $locationEntryFieldHandleRaw
            : 'dateline';

        // Normalize the location parameter value into a usable string
        $datelineLocation = \is_string($datelineLocation) && $datelineLocation !== ''
            ? $datelineLocation
            : null;

        // Check if a location parameter was not provided
        if ($datelineLocation === null) {
            // Get the full tag context for fallback resolution
            /** @var array<string, mixed> $context */
            $context = $tagContext->all();

            // Resolve dateline text from the geographic_location entry
            $datelineLocation = $this->resolveGeographicLocationDatelineFromContext(
                $context,
                $locationFieldHandle,
                $locationCollectionHandle,
                $locationEntryFieldHandle
            );

            // Unset context variables after fallback resolution
            unset($context);
        }

        // Get the date parameter if one is provided
        $datelineDate = $params->get('date');

        // Normalize the date parameter value into a usable string
        $datelineDate = \is_string($datelineDate) && $datelineDate !== ''
            ? $datelineDate
            : null;

        // Check if a date parameter was not provided
        if ($datelineDate === null) {
            // Get the full tag context for date fallback resolution
            /** @var array<string, mixed> $context */
            $context = $tagContext->all();

            // Extract the raw entry date from context
            $contextDate = data_get(
                $context,
                'date'
            );

            // Normalize context date (Carbon, Value wrappers, strings, etc.)
            $normalizedContextDate = $this->normalizeContextDateForApStyle(
                $contextDate
            );

            // Unset raw context date after normalization
            unset($contextDate);

            // Check if a normalized date string is available for formatting
            if ($normalizedContextDate !== null) {
                // Create the AP style date modifier for date formatting
                $apStyleDate = new ApStyleDate;

                // Format the fallback date using AP style formatting rules
                $formattedDate = $apStyleDate->index(
                    $normalizedContextDate,
                    [],
                    $context
                );

                // Keep the formatted date only when it is not empty and not null
                $datelineDate = filled($formattedDate)
                    ? $formattedDate
                    : null;

                // Unset date formatting helper variables
                unset(
                    $apStyleDate,
                    $formattedDate
                );
            }

            // Unset context date variables after fallback resolution
            unset(
                $context,
                $normalizedContextDate
            );
        }

        // Build dateline with both location and date when available
        if (
            $datelineLocation
            && $datelineDate
        ) {
            // Initialize dateline string with location and parenthesized date
            $datelineString = $datelineLocation.' ('.$datelineDate.')';
        } elseif ($datelineLocation) {
            // Initialize dateline string with location only
            $datelineString = $datelineLocation;
        } elseif ($datelineDate) {
            // Initialize dateline string with date only
            $datelineString = $datelineDate;
        } else {
            // Set dateline string to null when both values are missing
            $datelineString = null;
        }

        // Append separator when enabled and a dateline string exists
        if (
            $appendSeparator
            && $datelineString !== null
        ) {
            // Initialize the trailing dateline separator
            $datelineSeparator = "\u{00A0}— ";

            // Append separator to the end of the dateline string
            $datelineString .= $datelineSeparator;

            // Unset the separator variable after appending
            unset($datelineSeparator);
        }

        // Unset local variables before returning the dateline string
        unset(
            $datelineLocation,
            $datelineDate,
            $appendSeparatorRaw,
            $locationFieldHandleRaw,
            $locationFieldHandle,
            $locationCollectionHandleRaw,
            $locationCollectionHandle,
            $locationEntryFieldHandleRaw,
            $locationEntryFieldHandle,
            $appendSeparator
        );

        // Return the final dateline output string
        return $datelineString;
    }

    /**
     * Normalize entry `date` from Antlers context for AP style formatting.
     *
     * Entry dates are often `Carbon` instances, which are not scalars; unwrap
     * `Statamic\Fields\Value` when present.
     *
     * @param  mixed  $contextDate  Raw `date` from context
     * @return string|null A string `Carbon::parse` accepts, or null
     */
    private function normalizeContextDateForApStyle(
        mixed $contextDate
    ): ?string {
        // Unwrap Statamic field Value objects
        if ($contextDate instanceof Value) {
            // Replace wrapper with underlying raw value
            $contextDate = $contextDate->value();
        }

        // Format date/time objects for the AP style modifier input
        if ($contextDate instanceof DateTimeInterface) {
            // Serialize to an unambiguous string for parsing
            return $contextDate->format('Y-m-d H:i:s');
        }

        // Accept string and numeric scalar dates from context
        if (
            \is_scalar($contextDate)
            && (string) $contextDate !== ''
        ) {
            return (string) $contextDate;
        }

        return null;
    }

    /**
     * Resolve dateline text from the entry's `geographic_location` entries field.
     *
     * The field references `geographic_locations` entries (UAMS-Web/uams-statamic-archive#2597). It reaches this
     * tag in several shapes, depending on whether Antlers has augmented it: an
     * entries query or collection, a single entry, augmented array data, or the
     * stored entry id. The first location wins.
     *
     * @param  array<string, mixed>  $context  Current Antlers context
     * @param  string  $locationFieldHandle  Location field handle in entry context
     * @param  string  $locationCollectionHandle  Collection handle for a slug fallback lookup
     * @param  string  $locationEntryFieldHandle  Field handle to read from the location entry
     * @return string|null The dateline string, or null when not resolvable
     */
    private function resolveGeographicLocationDatelineFromContext(
        array $context,
        string $locationFieldHandle,
        string $locationCollectionHandle,
        string $locationEntryFieldHandle
    ): ?string {
        // Read the geographic_location field from entry context
        $geographicLocation = data_get(
            $context,
            $locationFieldHandle
        );

        // Unwrap a Statamic field Value wrapper
        if ($geographicLocation instanceof Value) {
            // Replace wrapped value object with its underlying value
            $geographicLocation = $geographicLocation->value();
        }

        // Resolve the first entry when the field augments to an ordered query
        if ($geographicLocation instanceof OrderedQueryBuilder) {
            // Take the first entry via explicit get() (OrderedQueryBuilder has no first())
            /** @var Collection<array-key, mixed> $orderedEntries */
            $orderedEntries = $geographicLocation->get();
            $geographicLocation = $orderedEntries->first();
        }

        // Normalize a collection into a plain array list
        if ($geographicLocation instanceof Collection) {
            // Convert collection values into an array list
            $geographicLocation = $geographicLocation->values()->all();
        }

        // Take the first item of a list, leaving an associative array (augmented data) whole
        if (\is_array($geographicLocation) && array_is_list($geographicLocation)) {
            // Extract the first geographic location item
            $geographicLocation = $geographicLocation[0] ?? null;
        }

        // Unwrap a first-item Statamic Value wrapper
        if ($geographicLocation instanceof Value) {
            // Replace wrapped first item with its underlying value
            $geographicLocation = $geographicLocation->value();
        }

        // Resolve the location to an entry, or read augmented data directly
        $locationEntry = $this->resolveLocationEntry(
            $geographicLocation,
            $locationCollectionHandle
        );

        // Read the dateline from augmented array data when no entry was resolved
        if (! $locationEntry instanceof StatamicEntry) {
            // Read the configured field from augmented data
            $augmentedDateline = \is_array($geographicLocation)
                ? ($geographicLocation[$locationEntryFieldHandle] ?? null)
                : null;

            // Unwrap a Value around the augmented field
            if ($augmentedDateline instanceof Value) {
                // Replace wrapped value with its underlying value
                $augmentedDateline = $augmentedDateline->value();
            }

            return \is_string($augmentedDateline) && $augmentedDateline !== ''
                ? $augmentedDateline
                : null;
        }

        // Read the configured field from the location entry
        $entryDateline = $locationEntry->value($locationEntryFieldHandle);

        return \is_string($entryDateline) && $entryDateline !== ''
            ? $entryDateline
            : null;
    }

    /**
     * Resolve one geographic location value to its entry.
     *
     * Accepts an entry, a stored entry id, augmented data carrying an `id`, or a
     * slug within the location collection.
     *
     * @param  mixed  $location  The first geographic location value
     * @param  string  $locationCollectionHandle  Collection to search when the value is a slug
     * @return StatamicEntry|null The location entry, or null when none matches
     */
    private function resolveLocationEntry(
        mixed $location,
        string $locationCollectionHandle
    ): ?StatamicEntry {
        // Use an entry as it is
        if ($location instanceof StatamicEntry) {
            return $location;
        }

        // Read the id from augmented data
        if (\is_array($location)) {
            // Take the id, unwrapping a Value
            $location = $location['id'] ?? null;
            $location = $location instanceof Value ? $location->value() : $location;
        }

        // Only a non-empty string can name an entry
        if (! \is_string($location) || $location === '') {
            return null;
        }

        // Find the entry by its id
        $entry = Entry::find($location);

        // Fall back to a slug within the location collection
        if (! $entry instanceof StatamicEntry) {
            /** @var EntryQueryBuilder $query */
            $query = Entry::query();
            $query->where('collection', $locationCollectionHandle);
            $query->where('slug', $location);
            $entry = $query->first();
        }

        return $entry instanceof StatamicEntry ? $entry : null;
    }

    /**
     * Normalize append_separator input into a boolean value.
     *
     * @param  mixed  $value  The append_separator input value
     * @return bool True when the separator should be appended
     */
    private function normalizeBoolValue(
        mixed $value
    ): bool {
        // Normalize string values for consistent true/false checks
        $normalizedStringValue = \is_string($value)
            ? \strtolower(\trim($value))
            : null;

        // Define the explicit true strings matched against the normalized value
        $explicitTrueStringValues = [
            'true',
            '1',
            'yes',
        ];

        // Define explicit true values for append-separator normalization
        $explicitTrueValues = [
            true,
            1,
            ...$explicitTrueStringValues,
        ];

        // Define the explicit false strings matched against the normalized value
        $explicitFalseStringValues = [
            '',
            'null',
            'false',
            'undefined',
            'no',
        ];

        // Define explicit false values for append-separator normalization
        $explicitFalseValues = [
            0,
            false,
            null,
            ...$explicitFalseStringValues,
        ];

        // Check if the raw value is one of the explicit true values
        $isExplicitTrueValue = \in_array(
            $value,
            $explicitTrueValues,
            true
        );

        // Check if the normalized string value is one of the explicit true values
        $isExplicitTrueStringValue = $normalizedStringValue !== null
            && \in_array(
                $normalizedStringValue,
                $explicitTrueStringValues,
                true
            );

        // Check if the raw value is one of the explicit false values
        $isExplicitFalseValue = \in_array(
            $value,
            $explicitFalseValues,
            true
        );

        // Check if the normalized string value is one of the explicit false values
        $isExplicitFalseStringValue = $normalizedStringValue !== null
            && \in_array(
                $normalizedStringValue,
                $explicitFalseStringValues,
                true
            );

        // Check if either explicit true condition is matched
        if (
            $isExplicitTrueValue
            || $isExplicitTrueStringValue
        ) {
            // Set normalized result to true for explicit true values
            $normalizedResult = true;
        } elseif (
            $isExplicitFalseValue
            || $isExplicitFalseStringValue
        ) {
            // Set normalized result to false for explicit false values
            $normalizedResult = false;
        } else {
            // Fall back to PHP truthy/falsey evaluation for all other values
            $normalizedResult = (bool) $value;
        }

        // Unset local normalization variables before returning the result
        unset(
            $normalizedStringValue,
            $explicitTrueValues,
            $explicitFalseValues,
            $isExplicitTrueValue,
            $isExplicitTrueStringValue,
            $isExplicitFalseValue,
            $isExplicitFalseStringValue
        );

        // Return the normalized append-separator value
        return $normalizedResult;
    }
}
