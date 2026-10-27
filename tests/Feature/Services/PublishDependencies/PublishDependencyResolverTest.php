<?php

declare(strict_types=1);

use App\Services\PublishDependencies\PublishDependencyResolver;
use App\Services\PublishDependencies\PublishDependencyResult;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Query\Builder;
use Statamic\Sites\Site;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Services\PublishDependencies\PublishDependencyResolver
 *
 * UAMS-Web/uams-statamic-archive#1593: collection-agnostic, opt-in detection of a dependent entry whose declared
 * `publish_depends_on` source entries have gone dark. `resolve()` reads the raw
 * tether id list, marks each source down when unpublished or deleted, and applies
 * the per-field policy (AND — all down — by default; OR — any down — under
 * `suppression_policy: any`). When suppressed it targets the collection mount
 * root, else the site root, and never mutates any stored status. Pure Mockery
 * doubles, so no content/ submodule is required.
 *
 * Accepted equivalent / structurally-unreachable mutants (the core policy, AND/OR
 * boundary, down-detection, and redirect-target logic are all killed): the
 * defensive `instanceof` / `is_string` guards on Statamic's untyped getters
 * (`collection()`, `mount()`, `site()`, `locale()`, and the blueprint field/value
 * accessors) — a real entry never yields the non-object/non-string they guard
 * against; the Blink memoization internals — the per-request key concatenation
 * (any ordering stays unique per locale+id), the `instanceof … ? : null`
 * narrowing (both branches yield null for a null result), and removing the
 * `Blink::once` wrapper (memoization is an optimization; the uncached result is
 * identical); and the `suppressedEntryIds()` query internals (`where('collection',
 * …)`, `array_unique` / `array_values`, and the per-collection `isEnabledFor`
 * guard), which the facade-mocked `EntryFacade::query()` returns fixed entries
 * past regardless of the filter — those are covered behaviorally by the
 * unit-tested `resolve()` plus the `ExcludeSuppressedDependents` scope, and a
 * real-entry integration test is deliberately omitted (this repo's parallel
 * real-entry tests have a documented shared-state flake history).
 *
 * @command  php artisan test --compact tests/Feature/Services/PublishDependencies/PublishDependencyResolverTest.php
 */
beforeEach(fn () => Blink::flush());

/** The tether field double, reporting the given suppression policy config value. */
function tetherField(?string $policy = null): Field
{
    $field = Mockery::mock(Field::class);
    $field->shouldReceive('get')->with('suppression_policy')->andReturn($policy);

    return $field;
}

/** A collection double the resolver can resolve a mount + handle from. */
function dependentCollection(string $handle = 'test_dependents', ?string $mountUrl = null, string $mountStatus = 'published'): Collection
{
    $collection = Mockery::mock(Collection::class);
    $collection->shouldReceive('handle')->andReturn($handle);

    if ($mountUrl !== null) {
        $mount = Mockery::mock(Entry::class);
        $mount->shouldReceive('url')->andReturn($mountUrl);
        $mount->shouldReceive('status')->andReturn($mountStatus);
        $collection->shouldReceive('mount')->andReturn($mount);
    } else {
        $collection->shouldReceive('mount')->andReturnNull();
    }

    return $collection;
}

/**
 * A dependent entry with a declared tether.
 *
 * @param  list<array{id: string, status: string|null, title?: string|null}>  $sources  Each source's id and status; a null status is a deleted (unresolvable) source.
 * @param  Field|null  $field  The tether field, or null to model a blueprint that did not opt in.
 * @param  string|list<string>|null  $rawValue  Override the raw tether value (defaults to the source ids); use to model the single-string (max_items:1) storage shape.
 */
function dependentEntry(
    array $sources,
    ?Field $field,
    ?Collection $collection = null,
    string $siteUrl = 'https://site.test',
    string $id = 'dependent',
    string|array|null $rawValue = null,
): Entry {
    $site = Mockery::mock(Site::class);
    $site->shouldReceive('url')->andReturn($siteUrl);

    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('field')->with('publish_depends_on')->andReturn($field);

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('locale')->andReturn('default');
    $entry->shouldReceive('site')->andReturn($site);
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('collection')->andReturn($collection ?? dependentCollection());

    $ids = array_map(fn (array $s): string => $s['id'], $sources);
    $entry->shouldReceive('value')->with('publish_depends_on')->andReturn($rawValue ?? $ids);

    foreach ($sources as $source) {
        if ($source['status'] === null) {
            EntryFacade::shouldReceive('find')->with($source['id'])->andReturnNull();

            continue;
        }

        $sourceEntry = Mockery::mock(Entry::class);
        $sourceEntry->shouldReceive('status')->andReturn($source['status']);
        $sourceEntry->shouldReceive('value')->with('title')->andReturn($source['title'] ?? null);
        EntryFacade::shouldReceive('find')->with($source['id'])->andReturn($sourceEntry);
    }

    return $entry;
}

/** Resolve an entry that must be suppressed, asserting and returning the non-null result. */
function suppressed(Entry $entry): PublishDependencyResult
{
    $result = (new PublishDependencyResolver)->resolve($entry);

    // Narrowing by control flow rather than `assert()`: the analyser verifies the
    // declared return type instead of taking an assertion's word for it, and the
    // failure names the entry rather than reporting a bare assertion (UAMS-Web/uams-statamic-archive#1733).
    if (! $result instanceof PublishDependencyResult) {
        $id = $entry->id();

        throw new RuntimeException(sprintf(
            'Expected entry "%s" to be suppressed, but the resolver returned no result.',
            is_scalar($id) ? (string) $id : '(no id)',
        ));
    }

    return $result;
}

// --- AND policy (default) --------------------------------------------------

it('suppresses when every source is down under the default AND policy', function (): void {
    $entry = dependentEntry(
        sources: [
            ['id' => 'p1', 'status' => 'draft', 'title' => 'Dr. Alice'],
            ['id' => 'p2', 'status' => 'draft', 'title' => 'Dr. Bob'],
        ],
        field: tetherField(),
        collection: dependentCollection(mountUrl: 'https://site.test/resources'),
    );

    $result = suppressed($entry);

    expect($result->redirectUrl)->toBe('https://site.test/resources');
    expect($result->downSourceTitles)->toBe(['Dr. Alice', 'Dr. Bob']);
    expect($result->requireAll)->toBeTrue();
});

it('does not suppress a mixed live/down set under the AND policy', function (): void {
    $entry = dependentEntry(
        sources: [
            ['id' => 'p1', 'status' => 'published', 'title' => 'Dr. Alice'],
            ['id' => 'p2', 'status' => 'draft', 'title' => 'Dr. Bob'],
        ],
        field: tetherField(),
    );

    expect((new PublishDependencyResolver)->resolve($entry))->toBeNull();
});

it('does not suppress when every source is live', function (): void {
    $entry = dependentEntry(
        sources: [['id' => 'p1', 'status' => 'published']],
        field: tetherField(),
    );

    expect((new PublishDependencyResolver)->resolve($entry))->toBeNull();
});

// --- OR policy -------------------------------------------------------------

it('suppresses when any source is down under the OR policy', function (): void {
    $entry = dependentEntry(
        sources: [
            ['id' => 'p1', 'status' => 'published', 'title' => 'Dr. Alice'],
            ['id' => 'p2', 'status' => 'draft', 'title' => 'Dr. Bob'],
        ],
        field: tetherField('any'),
    );

    $result = suppressed($entry);

    expect($result->downSourceTitles)->toBe(['Dr. Bob']);
    expect($result->requireAll)->toBeFalse();
});

it('does not suppress under the OR policy when every source is live', function (): void {
    $entry = dependentEntry(
        sources: [
            ['id' => 'p1', 'status' => 'published'],
            ['id' => 'p2', 'status' => 'published'],
        ],
        field: tetherField('any'),
    );

    expect((new PublishDependencyResolver)->resolve($entry))->toBeNull();
});

it('treats the explicit `all` policy shipped in the fieldset as the AND default', function (): void {
    // The production fieldset ships `suppression_policy: all`; feed that literal
    // value through to prove it is the AND policy — a mixed set is not suppressed.
    $entry = dependentEntry(
        sources: [
            ['id' => 'p1', 'status' => 'published'],
            ['id' => 'p2', 'status' => 'draft'],
        ],
        field: tetherField('all'),
    );

    expect((new PublishDependencyResolver)->resolve($entry))->toBeNull();
});

it('normalizes a mixed-case, padded OR policy value', function (): void {
    // policyRequiresAll() lowercases + trims, so ` ANY ` selects the OR policy.
    $entry = dependentEntry(
        sources: [
            ['id' => 'p1', 'status' => 'published'],
            ['id' => 'p2', 'status' => 'draft', 'title' => 'Dr. Bob'],
        ],
        field: tetherField(' ANY '),
    );

    expect(suppressed($entry)->downSourceTitles)->toBe(['Dr. Bob']);
});

it('restores a previously-down dependent automatically when its source is re-published', function (): void {
    // Exercise the actual down -> up transition: the same dependent resolves as
    // suppressed while its source is down, then (source re-published, per-request
    // memo flushed) resolves to null — proving the restore is stateless (the read
    // path re-resolves the source's live status each request), with no stored-
    // status flip to reverse.
    $site = Mockery::mock(Site::class);
    $site->shouldReceive('url')->andReturn('https://site.test');

    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('field')->with('publish_depends_on')->andReturn(tetherField());

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn('dependent');
    $entry->shouldReceive('locale')->andReturn('default');
    $entry->shouldReceive('site')->andReturn($site);
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('collection')->andReturn(dependentCollection(mountUrl: 'https://site.test/resources'));
    $entry->shouldReceive('value')->with('publish_depends_on')->andReturn(['p1']);

    // The source is down (draft) on the first resolve, re-published on the second.
    $source = Mockery::mock(Entry::class);
    $source->shouldReceive('status')->andReturn('draft', 'published');
    $source->shouldReceive('value')->with('title')->andReturn('Dr. Returning');
    EntryFacade::shouldReceive('find')->with('p1')->andReturn($source);

    $resolver = new PublishDependencyResolver;

    // Down -> suppressed.
    expect($resolver->resolve($entry))->toBeInstanceOf(PublishDependencyResult::class);

    // Re-published (memo flushed for the new request) -> restored, no result.
    Blink::flush();
    expect($resolver->resolve($entry))->toBeNull();
});

it('never mutates the dependent or its sources while resolving', function (): void {
    // The doubles stub only read methods; the resolver calling set()/save()/
    // published() on either the dependent or a source would raise an unexpected-
    // method error. Reaching the assertion is the never-touch-the-source guarantee.
    $entry = dependentEntry(
        sources: [['id' => 'p1', 'status' => 'draft', 'title' => 'Dr. Gone']],
        field: tetherField(),
        collection: dependentCollection(mountUrl: 'https://site.test/resources'),
    );

    expect((new PublishDependencyResolver)->resolve($entry))
        ->toBeInstanceOf(PublishDependencyResult::class);
});

// --- Delete == unpublish ---------------------------------------------------

it('treats a deleted source as down and names it by id', function (): void {
    $entry = dependentEntry(
        sources: [['id' => 'deleted-provider', 'status' => null]],
        field: tetherField(),
        collection: dependentCollection(mountUrl: 'https://site.test/resources'),
    );

    expect(suppressed($entry)->downSourceTitles)->toBe(['deleted-provider']);
});

// --- Opt-in gating ---------------------------------------------------------

it('returns null when the entry declares no sources', function (): void {
    $entry = dependentEntry(sources: [], field: tetherField());

    expect((new PublishDependencyResolver)->resolve($entry))->toBeNull();
});

it('returns null when the blueprint did not opt in (no tether field)', function (): void {
    $entry = dependentEntry(sources: [['id' => 'p1', 'status' => 'draft']], field: null);

    expect((new PublishDependencyResolver)->resolve($entry))->toBeNull();
});

it('returns null for a collection opted out via config', function (): void {
    config()->set('publish_dependencies.disabled_collections', ['test_dependents']);

    $entry = dependentEntry(
        sources: [['id' => 'p1', 'status' => 'draft']],
        field: tetherField(),
    );

    expect((new PublishDependencyResolver)->resolve($entry))->toBeNull();
});

// --- Redirect target -------------------------------------------------------

it('falls back to the site root when the collection has no published mount', function (): void {
    $entry = dependentEntry(
        sources: [['id' => 'p1', 'status' => 'draft']],
        field: tetherField(),
        collection: dependentCollection(mountUrl: null),
        siteUrl: 'https://uams.test',
    );

    expect(suppressed($entry)->redirectUrl)->toBe('https://uams.test');
});

it('skips an unpublished mount and falls back to the site root', function (): void {
    $entry = dependentEntry(
        sources: [['id' => 'p1', 'status' => 'draft']],
        field: tetherField(),
        collection: dependentCollection(mountUrl: 'https://site.test/resources', mountStatus: 'draft'),
        siteUrl: 'https://uams.test',
    );

    expect(suppressed($entry)->redirectUrl)->toBe('https://uams.test');
});

// --- Storage shape + memoization ------------------------------------------

it('normalizes a single-string tether value (max_items:1) to one source', function (): void {
    $entry = dependentEntry(
        sources: [['id' => 'p1', 'status' => 'draft', 'title' => 'Dr. Solo']],
        field: tetherField(),
        collection: dependentCollection(mountUrl: 'https://site.test/resources'),
        rawValue: 'p1',
    );

    expect(suppressed($entry)->downSourceTitles)->toBe(['Dr. Solo']);
});

it('memoizes resolution per request so the source is resolved once', function (): void {
    $site = Mockery::mock(Site::class);
    $site->shouldReceive('url')->andReturn('https://site.test');

    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('field')->with('publish_depends_on')->andReturn(tetherField());

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn('dependent');
    $entry->shouldReceive('locale')->andReturn('default');
    $entry->shouldReceive('site')->andReturn($site);
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('collection')->andReturn(dependentCollection(mountUrl: 'https://site.test/resources'));
    $entry->shouldReceive('value')->with('publish_depends_on')->andReturn(['p1']);

    // The single find() proves the second resolve() hit the Blink cache.
    $source = Mockery::mock(Entry::class);
    $source->shouldReceive('status')->andReturn('draft');
    $source->shouldReceive('value')->with('title')->andReturn('Dr. Once');
    EntryFacade::shouldReceive('find')->with('p1')->once()->andReturn($source);

    $resolver = new PublishDependencyResolver;
    $first = $resolver->resolve($entry);
    $second = $resolver->resolve($entry);

    expect($first)->toBe($second);
});

// --- suppressedEntryIds (listing exclusion) --------------------------------

it('collects the ids of every suppressed dependent across opted-in collections', function (): void {
    /** @var Collection&MockInterface $collection */
    $collection = dependentCollection(handle: 'test_dependents', mountUrl: 'https://site.test/resources');

    $tetherBlueprint = Mockery::mock(Blueprint::class);
    $tetherBlueprint->shouldReceive('hasField')->with('publish_depends_on')->andReturn(true);
    $collection->shouldReceive('entryBlueprints')->andReturn(collect([$tetherBlueprint]));

    CollectionFacade::shouldReceive('all')->andReturn(collect([$collection]));

    $down = dependentEntry(
        sources: [['id' => 'p1', 'status' => 'draft']],
        field: tetherField(),
        collection: $collection,
        id: 'down-resource',
    );
    $live = dependentEntry(
        sources: [['id' => 'p2', 'status' => 'published']],
        field: tetherField(),
        collection: $collection,
        id: 'live-resource',
    );

    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('where')->with('collection', 'test_dependents')->andReturnSelf();
    // Live entry first so the down entry is only reached if the scan `continue`s
    // past a non-suppressed entry rather than breaking out of the loop.
    $builder->shouldReceive('get')->andReturn(collect([$live, $down]));
    EntryFacade::shouldReceive('query')->andReturn($builder);

    expect((new PublishDependencyResolver)->suppressedEntryIds())->toBe(['down-resource']);
});
