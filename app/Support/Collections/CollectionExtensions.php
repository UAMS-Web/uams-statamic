<?php

declare(strict_types=1);

namespace App\Support\Collections;

use Closure;
use Illuminate\Container\Attributes\Singleton;
use Statamic\Entries\Entry;

/**
 * The registration point through which a package adds collection behavior to site core (UAMS-Web/uams-statamic-archive#3180).
 *
 * Site core reads four kinds of per-collection behavior from here instead of naming the collections
 * that supply them: the entry class a collection's rows are built as, a sitemap inclusion rule, a
 * document title, and taking part in scheduled publishing. The `people` collection registers all four
 * through {@see App\Providers\PeopleCollectionServiceProvider}, so its code can move into
 * `uams-web/statamic-people` (#3181) and register the same behavior from there.
 *
 * Registration happens while providers register, before any provider boots, so every consumer reads
 * a complete set: the computed field registrars in `ComputedPropertiesProvider::boot()`, the Stache
 * when it builds entries, and the scheduled publishing job.
 */
#[Singleton]
final class CollectionExtensions
{
    /**
     * @var array<string, class-string<Entry>>
     */
    private array $entryClasses = [];

    /**
     * @var array<string, Closure(Entry, mixed): bool>
     */
    private array $sitemapInclusionRules = [];

    /**
     * @var array<string, Closure(Entry): string>
     */
    private array $documentTitles = [];

    /**
     * @var list<string>
     */
    private array $scheduledPublishingCollections = [];

    /**
     * Build the collection's entries as the given class.
     *
     * @param  class-string<Entry>  $class  A subclass of Statamic's entry.
     */
    public function entryClass(string $collection, string $class): void
    {
        $this->entryClasses[$collection] = $class;
    }

    /**
     * The class the collection's entries are built as, or null for Statamic's own entry.
     *
     * @return class-string<Entry>|null
     */
    public function entryClassFor(?string $collection): ?string
    {
        return $collection === null ? null : ($this->entryClasses[$collection] ?? null);
    }

    /**
     * Every registered entry class, for the cache's list of classes it may unserialize.
     *
     * @return list<class-string<Entry>>
     */
    public function entryClasses(): array
    {
        return array_values(array_unique($this->entryClasses));
    }

    /**
     * Decide whether the collection's entries appear in the XML sitemap.
     *
     * @param  Closure(Entry, mixed): bool  $rule  Receives the entry and the stored value, as a computed field does.
     */
    public function sitemapInclusionRule(string $collection, Closure $rule): void
    {
        $this->sitemapInclusionRules[$collection] = $rule;
    }

    /**
     * @return array<string, Closure(Entry, mixed): bool>
     */
    public function sitemapInclusionRules(): array
    {
        return $this->sitemapInclusionRules;
    }

    /**
     * Compose the `document_title` of the collection's entries.
     *
     * @param  Closure(Entry): string  $title
     */
    public function documentTitle(string $collection, Closure $title): void
    {
        $this->documentTitles[$collection] = $title;
    }

    /**
     * @return array<string, Closure(Entry): string>
     */
    public function documentTitles(): array
    {
        return $this->documentTitles;
    }

    /**
     * Include the collection in scheduled publishing. It must be undated, with `publish_at` and
     * `unpublish_at` on its blueprints.
     */
    public function scheduledPublishing(string $collection): void
    {
        if (! \in_array($collection, $this->scheduledPublishingCollections, true)) {
            $this->scheduledPublishingCollections[] = $collection;
        }
    }

    /**
     * @return list<string>
     */
    public function scheduledPublishingCollections(): array
    {
        return $this->scheduledPublishingCollections;
    }
}
