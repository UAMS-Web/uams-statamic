<?php

declare(strict_types=1);

use App\Entries\KnowledgeBaseEntry;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\SkippedWithMessageException;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Entries\Entry;
use Statamic\Entries\Entry as BaseEntry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Integration coverage for per-knowledge-base mounting (UAMS-Web/uams-statamic-archive#826).
 *
 * Exercises the two pieces the mounted URL relies on against the real Stache:
 *   - `App\Stache\CollectionEntriesStore` hydrates every `knowledge_bases` entry
 *     as `KnowledgeBaseEntry` (so the `uri` index and query builder use the
 *     custom URI resolution), while other collections keep the stock class.
 *   - `KnowledgeBaseEntry::routeData()` exposes a live `mount_prefix` token equal
 *     to the mount-parent page's URI for a mounted root, and nothing for topics,
 *     articles, and un-mounted roots.
 *
 * The mount reference is set in memory (never saved) so the test does not mutate
 * the content submodule.
 *
 * @covers \App\Stache\CollectionEntriesStore
 * @covers \App\Entries\KnowledgeBaseEntry
 *
 * Mutation: content-dependent on App\Entries\KnowledgeBaseEntry +
 * App\Stache\CollectionEntriesStore — the five content-dependent tests self-skip
 * without the content/ submodule (the discriminator case below does not, since
 * it drives the guard directly), so Pest reports no covered mutants locally;
 * coverage runs in CI (--covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Entries/KnowledgeBaseMountResolutionTest.php
 */

/**
 * Assert a real committed entry resolved, skipping the test only when the
 * `content/` submodule is genuinely absent.
 *
 * Guards from a top-level function because Pest's `it()` closures call these
 * resolvers before `$this` is in scope, so the trait's instance methods are not
 * reachable — but the *discriminator* is shared with
 * `Tests\Concerns\RequiresContentSubmodule` rather than copied, so a `null`
 * lookup in a populated checkout fails here too (UAMS-Web/uams-statamic-archive#1738). These three tests
 * self-skipping in a fully initialized checkout is what surfaced that bug.
 *
 * @param  mixed  $entry  The resolved entry, or `null`
 * @param  string  $description  What the missing entry provides
 * @return EntryContract The non-null entry
 */
function requireKnowledgeBaseContentEntry(mixed $entry, string $description): EntryContract
{
    // Narrow before delegating; anything that is not an entry is a failed lookup.
    return ContentSubmodule::requireEntry(
        $entry instanceof EntryContract ? $entry : null,
        $description
    );
}

/**
 * Resolve a `knowledge_base` root entry, skipping when content is absent.
 *
 * @return KnowledgeBaseEntry The resolved root entry
 */
function resolveKnowledgeBaseRoot(): KnowledgeBaseEntry
{
    /** @var EntryQueryBuilder $query */
    $query = EntryFacade::query();
    $collectionQuery = $query->where('collection', 'knowledge_bases');

    /** @var EntryQueryBuilder $collectionQuery */
    $blueprintQuery = $collectionQuery->where('blueprint', KnowledgeBaseEntry::ROOT_BLUEPRINT);

    /** @var EntryQueryBuilder $blueprintQuery */
    /** @var KnowledgeBaseEntry $root */
    $root = requireKnowledgeBaseContentEntry(
        $blueprintQuery->first(),
        'a knowledge_bases root entry'
    );

    return $root;
}

/**
 * Resolve any routable pages entry to use as a mount parent, skipping when
 * content is absent.
 *
 * @return BaseEntry The resolved page entry
 */
function resolveRoutablePage(): BaseEntry
{
    /** @var EntryQueryBuilder $query */
    $query = EntryFacade::query();
    $pagesQuery = $query->where('collection', 'pages');

    // Find the first pages entry that resolves to a non-root URI.
    /** @var EntryQueryBuilder $pagesQuery */
    $pages = $pagesQuery->get();

    /** @var EntryCollection $pages */
    $page = $pages->first(function (mixed $entry): bool {
        /** @var BaseEntry $entry */
        return $entry->uri() && $entry->uri() !== '/';
    });

    // Skip the test when no routable page is available.
    /** @var BaseEntry $page */
    $page = requireKnowledgeBaseContentEntry($page, 'a routable pages entry');

    return $page;
}

/**
 * The local guard delegates to the shared discriminator rather than skipping on
 * any `null` (UAMS-Web/uams-statamic-archive#1738). Environment-agnostic on purpose: which branch is correct
 * depends on whether this checkout carries `content/`, and hard-coding either
 * one would red a bare worktree or reinstate the false green. In CI, where
 * `content/` is always initialized, this asserts the failing branch.
 *
 * Unlike the five tests below it, this one never self-skips — it drives the
 * guard directly, so it cannot go quiet the way they did.
 */
it('routes the local guard through the submodule discriminator', function (): void {
    $expected = ContentSubmodule::isInitialized()
        ? AssertionFailedError::class
        : SkippedWithMessageException::class;

    try {
        requireKnowledgeBaseContentEntry(null, 'a probe entry');
    } catch (Throwable $thrown) {
        // Exact class: SkippedWithMessageException extends AssertionFailedError,
        // so instanceof would pass for either branch and prove nothing.
        expect($thrown::class)->toBe($expected);

        return;
    }

    $this->fail('requireKnowledgeBaseContentEntry() returned on a null lookup.');
});

/**
 * Knowledge base entries hydrate as the custom KnowledgeBaseEntry class on the
 * query path (not just via Entry::find()), so the URI index sees the mounted
 * URI resolution.
 */
it('store hydrates knowledge_bases as the custom entry class', function () {
    /** @var EntryQueryBuilder $query */
    $query = EntryFacade::query();
    $collectionQuery = $query->where('collection', 'knowledge_bases');

    // Resolve any knowledge base entry from the query builder path.
    /** @var EntryQueryBuilder $collectionQuery */
    $entry = requireKnowledgeBaseContentEntry(
        $collectionQuery->first(),
        'a knowledge_bases entry'
    );

    // The custom store must re-wrap it as a KnowledgeBaseEntry.
    expect($entry)->toBeInstanceOf(KnowledgeBaseEntry::class);
});

/**
 * Entries outside the knowledge_bases collection keep the stock Entry class.
 */
it('store leaves other collections on the base entry class', function () {
    /** @var EntryQueryBuilder $query */
    $query = EntryFacade::query();
    $pagesQuery = $query->where('collection', 'pages');

    // Resolve any pages entry from the query builder path.
    /** @var EntryQueryBuilder $pagesQuery */
    $entry = requireKnowledgeBaseContentEntry(
        $pagesQuery->first(),
        'a pages entry'
    );

    // Pages must not be re-wrapped as KnowledgeBaseEntry.
    expect($entry)->toBeInstanceOf(BaseEntry::class);
    expect($entry)->not->toBeInstanceOf(KnowledgeBaseEntry::class);
});

/**
 * A mounted knowledge base root exposes a `mount_prefix` equal to its
 * mount-parent page's live URI, so the route resolves at
 * `{mount_parent_uri}/{slug}`.
 */
it('route data mount prefix is the mount parent uri', function () {
    // Resolve a knowledge base root and a routable page to mount it under.
    $root = resolveKnowledgeBaseRoot();
    $page = resolveRoutablePage();

    // Declare the mount in memory only (no save, so content is untouched).
    $root->set('mount_parent', [$page->id()]);

    // The mount prefix must equal the mount-parent page's current URI.
    /** @var array<string, mixed> $routeData */
    $routeData = $root->routeData();
    expect($routeData['mount_prefix'])->toBe($page->uri());
});

/**
 * Topics carry no mount prefix; they inherit the mounted path through the
 * route's `{parent_uri}`, which chains from the mounted root.
 */
it('route data mount prefix is empty for topics', function () {
    /** @var EntryQueryBuilder $query */
    $query = EntryFacade::query();
    $collectionQuery = $query->where('collection', 'knowledge_bases');

    /** @var EntryQueryBuilder $collectionQuery */
    $blueprintQuery = $collectionQuery->where('blueprint', 'topic');

    // Resolve a topic entry within the knowledge base.
    /** @var EntryQueryBuilder $blueprintQuery */
    $topic = requireKnowledgeBaseContentEntry(
        $blueprintQuery->first(),
        'a knowledge_bases topic entry'
    );

    // Topics never prepend a mount prefix.
    /** @var Entry $topic */
    /** @var array<string, mixed> $routeData */
    $routeData = $topic->routeData();
    expect($routeData['mount_prefix'])->toBe('');
});

/**
 * A root without a mount reference keeps its original collection-relative URL:
 * the mount prefix is empty.
 */
it('route data mount prefix is empty without a mount', function () {
    // Resolve a knowledge base root and clear any mount reference.
    $root = resolveKnowledgeBaseRoot();
    $root->set('mount_parent', null);

    // Un-mounted roots carry no mount prefix.
    /** @var array<string, mixed> $routeData */
    $routeData = $root->routeData();
    expect($routeData['mount_prefix'])->toBe('');
});
