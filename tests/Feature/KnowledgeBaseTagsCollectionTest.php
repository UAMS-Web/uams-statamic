<?php

declare(strict_types=1);

use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Fields\Blueprint;
use Statamic\Query\Builder;
use Statamic\Stache\Query\EntryQueryBuilder;
use Symfony\Component\Yaml\Yaml;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\TestCase;

uses(TestCase::class, RequiresContentSubmodule::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#2534: `knowledge_base_tags` was a flat taxonomy enabled only on
 * `default`, and a flat term exists on every site its taxonomy is enabled for, so
 * the importer titled each term `"{site name} — {term name}"` and prefixed its slug
 * with the site. `UAMS-Web/wordpress-importer#1215` settled that tags import as
 * entries in a site-scoped collection, following UAMS-Web/uams-statamic-archive#1863, UAMS-Web/uams-statamic-archive#2532 and UAMS-Web/uams-statamic-archive#2533.
 *
 * Every one of the 193 terms belonged to the Department of Radiology, the only site
 * whose articles use them, so each became an entry on `medicine_radiology` with the
 * site prefix removed from its slug and title. The 23 tagged articles there now
 * store entry ids. These assert the shipped configuration and data against the
 * disk-loaded content: nothing here saves an entry.
 *
 * @command  php artisan test --compact tests/Feature/KnowledgeBaseTagsCollectionTest.php
 */

/** The one site that owns knowledge base tags. */
const KNOWLEDGE_BASE_TAGS_SITE = 'medicine_radiology';

/**
 * Locate a field's config by handle anywhere in a blueprint tree.
 *
 * @param  array<array-key, mixed>  $node  The parsed YAML node to search.
 * @param  string  $handle  The field handle wanted.
 * @return ?array<string, mixed> The field's `field` config, or null when absent.
 */
function knowledgeBaseTagsFieldConfig(array $node, string $handle): ?array
{
    // Return this node's field config when it is the wanted handle
    if (($node['handle'] ?? null) === $handle && is_array($node['field'] ?? null)) {
        /** @var array<string, mixed> $field */
        $field = $node['field'];

        return $field;
    }

    // Walk nested tabs, sections and field lists
    foreach ($node as $value) {
        if (! is_array($value)) {
            continue;
        }

        $found = knowledgeBaseTagsFieldConfig($value, $handle);

        if ($found !== null) {
            return $found;
        }
    }

    return null;
}

/**
 * Every knowledge base tag entry, on any site.
 *
 * @return list<StatamicEntry>
 */
function knowledgeBaseTagEntries(): array
{
    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::findByHandle('knowledge_base_tags');

    $results = mustBe(Builder::class, $collection->queryEntries(), 'the knowledge_base_tags entries query')->get();

    $entries = [];

    foreach (is_iterable($results) ? $results : [] as $entry) {
        if ($entry instanceof StatamicEntry) {
            $entries[] = $entry;
        }
    }

    return $entries;
}

it('is a structured, site-scoped collection with its blueprint, and the taxonomy is retired', function (): void {
    $this->requireContentCollection('knowledge_base_tags');

    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::findByHandle('knowledge_base_tags');

    expect($collection->hasStructure())->toBeTrue()
        // Site-scoped: an entry is authored on one site and never copied to the others.
        ->and($collection->propagate())->toBeFalse()
        // The taxonomy's one site, plus the site whose articles use the tags.
        ->and(mustBe(Illuminate\Support\Collection::class, $collection->sites(), 'knowledge_base_tags sites')->all())
        ->toBe(['default', KNOWLEDGE_BASE_TAGS_SITE])
        // Tags have no public page, as the terms had none.
        ->and($collection->route(KNOWLEDGE_BASE_TAGS_SITE))->toBeNull()
        // The committed blueprint, by its title: with no file, Statamic would build
        // a fallback under the same handle, so a non-null check alone proves nothing.
        ->and(mustBe(Blueprint::class, $collection->entryBlueprint('knowledge_base_tag'), 'the knowledge_base_tag blueprint')->title())->toBe('Knowledge Base, Tag');

    // The flat taxonomy is gone, so nothing can resolve the old source.
    expect(Taxonomy::findByHandle('knowledge_base_tags'))->toBeNull();
});

it('carries every term over as a radiology entry with its plain slug and title', function (): void {
    $this->requireContentCollection('knowledge_base_tags');

    $entries = knowledgeBaseTagEntries();

    expect($entries)->toHaveCount(193);

    foreach ($entries as $entry) {
        $slug = mustBeString($entry->slug(), 'entry slug');
        $title = mustBeString($entry->get('title'), 'entry title');

        // Each lands on the site it belongs to, with the importer's site prefix
        // stripped from both the slug and the title.
        expect($entry->locale())->toBe(KNOWLEDGE_BASE_TAGS_SITE)
            ->and($slug)->not->toStartWith('medicine-radiology_')
            ->and($title)->not->toContain('Department of Radiology')
            ->and($title)->not->toContain(' — ');
    }

    // One known term, end to end: `medicine-radiology_blood` titled
    // "Department of Radiology (College of Medicine) — blood".
    $blood = collect($entries)->first(fn (StatamicEntry $entry): bool => $entry->slug() === 'blood');

    expect($blood)->not->toBeNull()
        ->and($blood?->get('title'))->toBe('blood');
});

it('keeps each tag on its own site, absent from the others', function (): void {
    $this->requireContentCollection('knowledge_base_tags');

    $count = function (string $site): int {
        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        $query->where('collection', 'knowledge_base_tags');
        $query->where('site', $site);
        $query->where('slug', 'blood');

        $count = $query->count();

        // A non-integer count reads as -1, which no expectation below accepts.
        return is_int($count) ? $count : -1;
    };

    $tag = mustBe(StatamicEntry::class, collect(knowledgeBaseTagEntries())->first(fn (StatamicEntry $entry): bool => $entry->slug() === 'blood'), 'the blood tag');

    /** @var EntryQueryBuilder $localizations */
    $localizations = Entry::query();
    $localizations->where('collection', 'knowledge_base_tags');
    $localizations->where('origin', $tag->id());

    // Present on the site it belongs to, absent from `default` even though the
    // collection is enabled there, and never localized: the collection does not
    // propagate.
    expect($count(KNOWLEDGE_BASE_TAGS_SITE))->toBe(1)
        ->and($count('default'))->toBe(0)
        ->and($localizations->count())->toBe(0);
});

it('points the article tags field at the collection', function (): void {
    /** @var array<string, mixed> $parsed */
    $parsed = Yaml::parseFile(resource_path('blueprints/collections/knowledge_bases/article.yaml'));
    $field = knowledgeBaseTagsFieldConfig($parsed, 'tags');

    expect($field)->not->toBeNull();

    if ($field !== null) {
        expect($field['type'] ?? null)->toBe('entries')
            ->and($field['collections'] ?? [])->toBe(['knowledge_base_tags'])
            ->and($field['mode'] ?? null)->toBe('select')
            // The retired taxonomy binding must be gone, not merely supplemented.
            ->and($field)->not->toHaveKey('taxonomies');
    }
});

it('resolves every tag an article stored to a tag entry on the article\'s own site', function (): void {
    $this->requireContentCollection('knowledge_bases');
    $this->requireContentCollection('knowledge_base_tags');

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'knowledge_bases');
    $query->whereNotNull('tags');

    /** @var Illuminate\Support\Collection<int, StatamicEntry> $articles */
    $articles = $query->get();

    $stored = 0;
    $resolved = 0;

    foreach ($articles as $article) {
        $ids = $article->get('tags');

        if (! is_array($ids) || $ids === []) {
            continue;
        }

        $stored += count($ids);

        foreach ($ids as $id) {
            // Every stored value is an existing tag entry on the article's site, so
            // the site-scoped picker shows it as selected. An id that resolves to
            // nothing throws here.
            $tag = mustBe(StatamicEntry::class, Entry::find(mustBeString($id, 'stored tag id')), 'a stored tag id');

            expect($tag->collectionHandle())->toBe('knowledge_base_tags')
                ->and($tag->locale())->toBe($article->locale());

            $resolved++;
        }
    }

    // 220 references across 22 articles, plus 15 in the article that named the
    // taxonomy by a handle that never existed (`knowledge_base_tag::…`).
    expect($articles->count())->toBe(23)
        ->and($stored)->toBe(235)
        ->and($resolved)->toBe($stored);
});

it('keeps each article pointing at the tags it had, in order', function (string $slug, array $expected): void {
    $this->requireContentCollection('knowledge_bases');

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'knowledge_bases');
    $query->where('site', KNOWLEDGE_BASE_TAGS_SITE);
    $query->where('slug', $slug);

    $article = mustBe(StatamicEntry::class, $query->first(), "the {$slug} article");
    $ids = mustBeArray($article->get('tags'), "{$slug} tags");

    $titles = [];

    foreach (array_slice($ids, 0, count($expected) + 1) as $id) {
        $tag = mustBe(StatamicEntry::class, Entry::find(mustBeString($id, 'stored tag id')), 'a stored tag id');
        $titles[] = $tag->get('title');
    }

    // The ids name the same tags, in the same order, as the term slugs they
    // replaced: a permuted slug-to-id mapping would pass every count above.
    expect(array_slice($titles, 1, count($expected)))->toBe($expected);
})->with([
    // Stored `medicine-radiology_blood`, `_bowel`, `_currant`, `_donut` before.
    'plain term slugs' => ['intussusception', ['bowel', 'currant', 'donut']],
    // Stored `'knowledge_base_tag::medicine-radiology_abuse'` and so on: a handle
    // that never existed, mapped to the same entries.
    'the prefixed-handle form' => ['musculoskeletal-overview', ['abuse', 'buckle']],
]);

it('defines the tag blueprint in the collections namespace', function (): void {
    // The taxonomy never had a blueprint file of its own; its terms named one
    // Statamic generated. The collection's is committed.
    expect(file_exists(resource_path('blueprints/collections/knowledge_base_tags/knowledge_base_tag.yaml')))->toBeTrue();
});
