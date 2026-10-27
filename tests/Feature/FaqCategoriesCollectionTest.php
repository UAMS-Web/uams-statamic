<?php

declare(strict_types=1);

use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Fields\Blueprint;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\CollectionTree;
use Symfony\Component\Finder\Finder;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\Support\CollectionTreeIsolatedTestCase;

// Tree-isolated: saving an entry into a structured collection appends it to that
// collection's tree, which must not touch the shared `faq_categories` tree.
uses(CollectionTreeIsolatedTestCase::class, RequiresContentSubmodule::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#2535: `faq_categories` was a flat taxonomy, and a flat term exists
 * on every site its taxonomy is enabled for, so the importer titled each term with
 * its site name. `UAMS-Web/wordpress-importer#1215` settled that FAQ categories
 * import as entries in a site-scoped collection, following UAMS-Web/uams-statamic-archive#1863, UAMS-Web/uams-statamic-archive#2532, UAMS-Web/uams-statamic-archive#2533 and
 * UAMS-Web/uams-statamic-archive#2534.
 *
 * The taxonomy had no terms on disk, and no blueprint or fieldset in this
 * repository referenced it, so there was nothing to migrate and no field to
 * convert. These pin the collection's configuration, that absence, and the site
 * scoping an importer-written category will rely on. Fixtures are saved and deleted
 * quietly: nothing here reads a search index.
 *
 * @command  php artisan test --compact tests/Feature/FaqCategoriesCollectionTest.php
 */
const FAQ_CATEGORIES_TEST_SLUG = 'zz-faq-categories-scope-test';

afterEach(function (): void {
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'faq_categories');
    $query->where('slug', FAQ_CATEGORIES_TEST_SLUG);

    /** @var Illuminate\Support\Collection<int, StatamicEntry> $entries */
    $entries = $query->get();

    $entries->each(fn (StatamicEntry $entry) => $entry->deleteQuietly());

    // Undo the in-memory site added by the scoping test, so a cached collection
    // instance cannot carry it into a later test in this worker.
    Collection::findByHandle('faq_categories')?->sites(['default']);
});

/**
 * Count `faq_categories` entries matching one column, narrowed to the test slug.
 *
 * @param  string  $column  The column to match, such as `site` or `origin`.
 * @param  mixed  $value  The value it must hold.
 */
function countFaqCategories(string $column, mixed $value): int
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'faq_categories');
    $query->where($column, $value);
    $query->where('slug', FAQ_CATEGORIES_TEST_SLUG);

    // `count()` comes off the builder as `mixed`; narrowed by control flow
    $count = $query->count();

    if (! is_int($count)) {
        throw new RuntimeException('the entry query returned a non-integer count');
    }

    return $count;
}

it('is a structured, site-scoped collection with its blueprint, and the taxonomy is retired', function (): void {
    $this->requireContentCollection('faq_categories');

    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::findByHandle('faq_categories');

    expect($collection->hasStructure())->toBeTrue()
        // Site-scoped: an entry is authored on one site and never copied to the others.
        ->and($collection->propagate())->toBeFalse()
        // The taxonomy's one site.
        ->and(mustBe(Illuminate\Support\Collection::class, $collection->sites(), 'faq_categories sites')->all())
        ->toBe(['default'])
        // Categories have no public page, as the terms had none.
        ->and($collection->route('default'))->toBeNull()
        // The committed blueprint, by its title: with no file, Statamic would build
        // a fallback under the same handle, so a non-null check alone proves nothing.
        ->and(mustBe(Blueprint::class, $collection->entryBlueprint('faq_category'), 'the faq_category blueprint')->title())
        ->toBe('FAQ, Category');

    // The flat taxonomy is gone, so nothing can resolve the old source.
    expect(Taxonomy::findByHandle('faq_categories'))->toBeNull();
});

it('starts empty, because the taxonomy held no terms', function (): void {
    $this->requireContentCollection('faq_categories');

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'faq_categories');

    expect($query->count())->toBe(0);
});

it('is referenced by no blueprint or fieldset, so no field needed converting', function (): void {
    // Neither the taxonomy nor the collection is named anywhere a field could
    // point at it. A field added later that still says `taxonomies: [faq_categories]`
    // would name a taxonomy that no longer exists, and fails here.
    $mentions = [];

    foreach ((new Finder)->files()->in([resource_path('blueprints'), resource_path('fieldsets')])->name('*.yaml') as $file) {
        if (str_contains($file->getContents(), 'faq_categories')) {
            $mentions[] = $file->getRelativePathname();
        }
    }

    expect($mentions)->toBe([]);
});

it('keeps a category on the site it was authored on', function (): void {
    $this->requireContentCollection('faq_categories');

    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::findByHandle('faq_categories');

    // The collection is enabled on one site, so a second is added in memory only,
    // never saved: what is under test is the committed `propagate: false`, which
    // decides whether an entry saved on one site is copied to the collection's
    // other sites.
    $collection->sites(['default', 'news']);

    // A structured collection keeps one tree per site; the added site needs its
    // own before an entry can be placed there. This base roots trees in a private
    // directory, so the write never reaches shared content.
    $structure = mustBe(CollectionStructure::class, $collection->structure(), 'the faq_categories structure');
    mustBe(CollectionTree::class, $structure->makeTree('news'), 'the news tree')->save();

    /** @var StatamicEntry $entry */
    $entry = Entry::make();
    $entry->collection($collection);
    $entry->locale('news');
    $entry->published(true);
    $entry->slug(FAQ_CATEGORIES_TEST_SLUG);
    $entry->data(['title' => 'ZZ FAQ category']);
    $entry->saveQuietly();

    // Present on the site it was authored on, absent from the other, and never
    // localized there. `saveQuietly()` still propagates when the collection does.
    expect(countFaqCategories('site', 'news'))->toBe(1)
        ->and(countFaqCategories('site', 'default'))->toBe(0)
        ->and(countFaqCategories('origin', $entry->id()))->toBe(0);
});
