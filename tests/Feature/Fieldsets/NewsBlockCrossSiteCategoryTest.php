<?php

declare(strict_types=1);

use App\Support\Statamic\AugmentedEntryIds;
use Illuminate\Support\Collection;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Entry;
use Statamic\Facades\Fieldset;
use Statamic\Fields\Field;
use Statamic\Fields\Value;
use Statamic\Sites\Site;
use Tests\TestCase;

uses(TestCase::class);

/*
 * UAMS-Web/uams-statamic-archive#2165 — the news block's `category` field must offer and keep categories belonging to
 * the sites it syndicates from.
 *
 * `post_categories` is `propagate: false` and its per-site entries carry no `origin`, so
 * without `select_across_sites` a stored cross-site id is DROPPED ON READ — not rejected,
 * dropped. The block then looks filtered in its YAML and renders completely unfiltered,
 * which is why `wordpress-importer#628` omits the field rather than writing one.
 *
 * These assert through the REAL fieldset rather than a synthetic field, so a change to
 * `uamsweb_block_news.yaml` is what they are testing.
 */

/**
 * The `category` field's configuration, as the fieldset actually declares it.
 *
 * @return array<string, mixed>
 */
function newsBlockCategoryConfig(): array
{
    $fieldset = mustBe(Statamic\Fields\Fieldset::class, Fieldset::find('uamsweb_block_news'), 'the news block fieldset');

    $field = mustBe(Field::class, $fieldset->fields()->get('category'), 'the category field');

    /** @var array<string, mixed> $config */
    $config = $field->config();

    return $config;
}

/**
 * Every `post_categories` entry.
 *
 * `Entry::whereCollection()` rather than `Entry::query()->where(...)`: the facade declares
 * the former as returning a typed `EntryCollection`, while `query()` returns a contract
 * that does not declare `where()` at all — so the query form leaves every downstream call
 * `mixed` and needs an annotation at each of them.
 */
function allCategories(): EntryCollection
{
    return Entry::whereCollection('post_categories');
}

/** An entry's site handle, narrowed — `Entry::site()` is `mixed` to static analysis. */
function siteHandle(Statamic\Entries\Entry $entry): string
{
    // Neither `Site::handle()` nor `Entry::id()` declares a return type in the vendor, so
    // narrowing the OBJECT is not enough — the returned scalar is what has to be narrowed.
    // That is why these two helpers exist rather than the calls being inlined.
    $site = mustBe(Site::class, $entry->site(), 'the entry site');

    return mustBeString($site->handle(), 'the site handle');
}

/** An entry's id, narrowed — `Entry::id()` declares no return type either. */
function entryId(Statamic\Entries\Entry $entry): string
{
    return mustBeString($entry->id(), 'the entry id');
}

/** The first `post_categories` entry, narrowed. */
function anyCategory(): Statamic\Entries\Entry
{
    return mustBe(Statamic\Entries\Entry::class, allCategories()->first(), 'any category');
}

/** A `post_categories` entry belonging to a site other than $notThisSite. */
function categoryFromAnotherSite(string $notThisSite): Statamic\Entries\Entry
{
    // The closure is untyped and narrows inside: EntryCollection::first() is declared as
    // Collection<(int|string), mixed>, so a typed parameter conflicts with its signature
    // rather than helping.
    $entry = allCategories()->first(
        fn ($e): bool => $e instanceof Statamic\Entries\Entry && siteHandle($e) !== $notThisSite
    );

    return mustBe(Statamic\Entries\Entry::class, $entry, 'a category from another site');
}

/**
 * The augmented, non-empty values a config yields for one entry id.
 *
 * @param  array<string, mixed>  $config
 * @return Collection<int|string, mixed>
 */
function augmentCategory(array $config, string $id): Collection
{
    $field = new Field('category', $config);

    // `setValue()` and `augment()` both return `$this` and neither declares a return type,
    // so `augment()` is narrowed rather than chained. Note what is NOT done here: an extra
    // `->value()` unwrap. `$field->augment()->value()` is already the augmented list;
    // unwrapping once more returned three entries for a single id and broke three tests
    // while every type-check passed.
    $field->setValue([$id]);
    $augmented = mustBe(Field::class, $field->augment(), 'the augmented field');

    // The @var sits on the AUGMENTED VALUE rather than on the collection: `collect()`
    // cannot resolve its own templates from a `mixed` argument, so annotating its result
    // leaves the call itself unresolved. Typing the input fixes both.
    /** @var iterable<int|string, mixed> $value */
    $value = $augmented->value();

    return collect($value)->filter();
}

it('declares select_across_sites on the category field', function (): void {
    expect(newsBlockCategoryConfig()['select_across_sites'] ?? null)->toBeTrue();
});

it('keeps a category id belonging to another site', function (): void {
    $other = categoryFromAnotherSite(siteHandle(anyCategory()));

    expect(augmentCategory(newsBlockCategoryConfig(), entryId($other)))->toHaveCount(1);
});

it('drops a cross-site id when the flag is absent, which is the defect', function (): void {
    // The negative control, and it carries as much weight as the assertion above: without
    // it, "the id survived" is equally consistent with the flag doing nothing and the id
    // having been fine all along. This is the behavior UAMS-Web/uams-statamic-archive#2165 exists to change.
    $other = categoryFromAnotherSite(siteHandle(anyCategory()));

    $withoutFlag = newsBlockCategoryConfig();
    unset($withoutFlag['select_across_sites']);

    expect(augmentCategory($withoutFlag, entryId($other)))->toBeEmpty();
});

it('still keeps a same-site category', function (): void {
    // AC5 — existing same-site selections must behave as they do today.
    expect(augmentCategory(newsBlockCategoryConfig(), entryId(anyCategory())))->toHaveCount(1);
});

/*
 * The integration point, and the one that decides whether the block actually renders
 * filtered rather than merely storing the id.
 *
 * `App\Tags\UamsNews` resolves the selection with `AugmentedEntryIds::fromValue()` on the
 * AUGMENTED value and passes the resulting ids to the news query. So the augmentation this
 * fieldset controls is what decides whether any category id reaches that query at all —
 * and an empty list is not a narrower filter, it is NO filter.
 */
it('delivers the cross-site category id to the news query', function (): void {
    $other = categoryFromAnotherSite(siteHandle(anyCategory()));

    $field = new Field('category', newsBlockCategoryConfig());
    $field->setValue([entryId($other)]);
    $augmented = mustBe(Field::class, $field->augment(), 'the augmented field');

    expect(AugmentedEntryIds::fromValue($augmented->value()))
        ->toHaveCount(1);
});

it('delivers no id without the flag, which renders the block unfiltered', function (): void {
    $other = categoryFromAnotherSite(siteHandle(anyCategory()));

    $withoutFlag = newsBlockCategoryConfig();
    unset($withoutFlag['select_across_sites']);

    $field = new Field('category', $withoutFlag);
    $field->setValue([entryId($other)]);
    $augmented = mustBe(Field::class, $field->augment(), 'the augmented field');

    expect(AugmentedEntryIds::fromValue($augmented->value()))
        ->toBeEmpty();
});
