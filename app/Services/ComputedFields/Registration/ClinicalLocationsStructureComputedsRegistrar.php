<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Structures\Page;

/**
 * Registers `clinical_locations` structural computeds: the patient-facing name and
 * profile URL of a location's structure-tree parent.
 *
 * The directory card renders a "Part of {parent}" subtitle for a child location
 * (UAMS-Web/uams-statamic-archive#1465). The parent is a structural (tree) relationship, not a stored field, so
 * `{{ parent }}` is not exposed in the collection-tag listing augmentation — these
 * computeds surface it as ordinary augmented keys the card can read in the loop. A
 * top-level location has no tree parent, so both resolve to `null`.
 */
final class ClinicalLocationsStructureComputedsRegistrar
{
    /**
     * Register the parent-location name and URL computeds on `clinical_locations`.
     */
    public function register(): void
    {
        // The parent location's patient-facing name (the clean `generic_name` over
        // the disambiguated `title`), or null for a top-level location.
        Collection::computed(
            'clinical_locations',
            'parent_location_name',
            static fn (Entry $entry, mixed $_stored): ?string => self::parentName($entry)
        );

        // The parent location's profile URL, or null when it has no parent (or the
        // parent is not routable). Entry::url() is typed loosely, so narrow to a string.
        Collection::computed(
            'clinical_locations',
            'parent_location_url',
            static function (Entry $entry, mixed $_stored): ?string {
                $url = self::parentEntry($entry)?->url();

                return is_string($url) ? $url : null;
            }
        );
    }

    /**
     * Resolve the parent location's patient-facing name, or null when there is no parent.
     */
    private static function parentName(Entry $entry): ?string
    {
        $parent = self::parentEntry($entry);

        if (! $parent instanceof Entry) {
            return null;
        }

        $name = $parent->get('generic_name') ?: $parent->get('title');

        return is_string($name) ? $name : null;
    }

    /**
     * Resolve the structure-tree parent entry, unwrapping the `Page` the structure
     * returns; null for a top-level location (or when the parent has no entry).
     */
    private static function parentEntry(Entry $entry): ?Entry
    {
        $parent = $entry->parent();

        if ($parent instanceof Page) {
            $parent = $parent->entry();
        }

        return $parent instanceof Entry ? $parent : null;
    }
}
