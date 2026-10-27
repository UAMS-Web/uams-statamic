<?php

declare(strict_types=1);

namespace App\Services\GoogleBusinessProfile\Categories;

use Statamic\Facades\Term;
use Statamic\Taxonomies\LocalizedTerm;

/**
 * Compares a category list with the Google Business Profile category taxonomy by GCID (UAMS-Web/uams-statamic-archive#2615).
 *
 * UAMS-Web/uams-statamic-archive#2091 keyed the taxonomy by Google's category id because Google renames categories and
 * a slug is editable. The comparison only reads: it never creates, deletes or retitles a
 * term, and never touches `collections`, which curators own.
 */
final class CategoryDiff
{
    /**
     * The taxonomy the list is compared with.
     */
    public const string TAXONOMY = 'google_business_profile_categories';

    /**
     * Compare a list with the taxonomy's terms on the default site.
     */
    public function compare(CategoryList $list): CategoryDiffReport
    {
        $renamed = [];
        $removed = [];
        $unreadable = [];
        $withoutGcid = [];
        $keyed = [];
        $listedUnreadable = array_flip($list->unreadable);

        /** @var iterable<int, LocalizedTerm> $terms */
        $terms = Term::whereTaxonomy(self::TAXONOMY);

        foreach ($terms as $term) {
            $slug = $this->stringValue($term->slug());
            $title = $this->stringValue($term->value('title'));
            $gcid = $this->stringValue($term->value('gcid'));

            if ($gcid === '') {
                $withoutGcid[] = ['slug' => $slug, 'title' => $title];

                continue;
            }

            $keyed[] = $gcid;

            if (! \array_key_exists($gcid, $list->categories)) {
                // The list carries the GCID on a line it could not read: Google still
                // lists the category, so the term is not removed (UAMS-Web/uams-statamic-archive#2944)
                if (\array_key_exists($gcid, $listedUnreadable)) {
                    $unreadable[] = ['gcid' => $gcid, 'slug' => $slug, 'title' => $title];
                } else {
                    $removed[] = ['gcid' => $gcid, 'slug' => $slug, 'title' => $title];
                }
            } elseif ($list->categories[$gcid] !== $title) {
                $renamed[] = ['gcid' => $gcid, 'slug' => $slug, 'title' => $title, 'name' => $list->categories[$gcid]];
            }
        }

        $new = array_diff_key($list->categories, array_flip($keyed));
        ksort($new);

        return new CategoryDiffReport($new, $renamed, $removed, $withoutGcid, $list->malformed, $unreadable);
    }

    /**
     * A term value as a string, empty when it is missing or not a scalar.
     */
    private function stringValue(mixed $value): string
    {
        return \is_scalar($value) ? trim((string) $value) : '';
    }
}
