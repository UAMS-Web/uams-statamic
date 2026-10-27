<?php

declare(strict_types=1);

namespace App\Support\Classifieds;

use Illuminate\Support\Collection;
use Statamic\Facades\Term;
use Statamic\Stache\Query\TermQueryBuilder;
use Statamic\Taxonomies\LocalizedTerm;

/**
 * The categories a classified listing may be filed under: the terms of the
 * `classified_categories` taxonomy (UAMS-Web/uams-statamic-archive#2519).
 *
 * The posting form lists them and the form request validates against them, so both
 * read this one query.
 */
final class ClassifiedCategories
{
    /**
     * Every category, ordered by title.
     *
     * @return list<array{slug: string, title: string}>
     */
    public function all(): array
    {
        /** @var TermQueryBuilder $query */
        $query = Term::query();
        $query->where('taxonomy', 'classified_categories');
        $query->orderBy('title');

        /** @var Collection<int, LocalizedTerm> $terms */
        $terms = $query->get();

        $categories = [];

        // Describe each term, skipping any without a usable slug
        foreach ($terms as $term) {
            $slug = $term->slug();
            $title = $term->title();
            if (! is_string($slug)) {
                continue;
            }

            if ($slug === '') {
                continue;
            }

            $categories[$slug] = ['slug' => $slug, 'title' => is_string($title) ? $title : $slug];
        }

        return array_values($categories);
    }

    /**
     * Every category slug.
     *
     * @return list<string>
     */
    public function slugs(): array
    {
        return array_column($this->all(), 'slug');
    }
}
