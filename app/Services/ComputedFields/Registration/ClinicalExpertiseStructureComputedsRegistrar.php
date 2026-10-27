<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Structures\Page;

/**
 * Registers `clinical_expertise` structural computeds: the name and URL of an
 * Area of Expertise's structure-tree parent.
 *
 * The index card renders a "Part of {parent}" subtitle for a child Area of
 * Expertise (UAMS-Web/uams-statamic-archive#1467), mirroring the `clinical_locations` directory card (UAMS-Web/uams-statamic-archive#1465).
 * The parent is a structural (tree) relationship, not a stored field, so
 * `{{ parent }}` is not exposed in the collection-tag listing augmentation —
 * these computeds surface it as ordinary augmented keys the card can read in the
 * loop. A top-level Area of Expertise has no tree parent, so both resolve to
 * `null`.
 *
 * The tree parent of an Area of Expertise entry is another Area of Expertise
 * (content-page descendants — `basic_page` / `landing_page` — hang off the Areas,
 * they are never their ancestors), so the resolved name is always the parent
 * Area's title.
 */
final class ClinicalExpertiseStructureComputedsRegistrar
{
    /**
     * Register the parent-Area name and URL computeds on `clinical_expertise`.
     */
    public function register(): void
    {
        // The parent Area of Expertise's title, or null for a top-level Area.
        Collection::computed(
            'clinical_expertise',
            'parent_expertise_name',
            static fn (Entry $entry, mixed $_stored): ?string => self::parentName($entry)
        );

        // The parent Area of Expertise's URL, or null when it has no parent (or the
        // parent is not routable). Entry::url() is typed loosely, so narrow to a string.
        Collection::computed(
            'clinical_expertise',
            'parent_expertise_url',
            static function (Entry $entry, mixed $_stored): ?string {
                $url = self::parentEntry($entry)?->url();

                return is_string($url) ? $url : null;
            }
        );
    }

    /**
     * Resolve the parent Area of Expertise's title, or null when there is no parent.
     */
    private static function parentName(Entry $entry): ?string
    {
        $parent = self::parentEntry($entry);

        if (! $parent instanceof Entry) {
            return null;
        }

        $name = $parent->get('title');

        return is_string($name) ? $name : null;
    }

    /**
     * Resolve the structure-tree parent entry, unwrapping the `Page` the structure
     * returns; null for a top-level Area (or when the parent has no entry).
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
