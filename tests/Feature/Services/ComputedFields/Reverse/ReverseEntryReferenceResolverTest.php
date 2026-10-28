<?php

declare(strict_types=1);

use App\Services\ComputedFields\Reverse\ReverseEntryReferenceResolver;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/*
|--------------------------------------------------------------------------
| ReverseEntryReferenceResolver (UAMS-Web/uams-statamic-archive#1372/UAMS-Web/uams-statamic-archive#1373/UAMS-Web/uams-statamic-archive#1374 shared reverse engine)
|--------------------------------------------------------------------------
|
| Proves the reverse-query idiom against the REAL Stache with real saved
| entries — not a mock — because the whole risk is storage-shape: the same
| `entries` handle is stored as a scalar string by the CP and as a JSON array
| by the WordPress import, and on the flat-file Stache `whereJsonContains` misses
| the scalar shape while `where()` crashes on the array shape (neither is safe for
| both). The resolver sidesteps that by loading candidates and matching membership
| in PHP, so these assert it matches an id stored as a scalar, a single-element
| array, AND a multi-element array, honors the published filter, spans multiple
| source collections, and memoizes per request. `resolveNested` additionally
| matches an id nested inside a group value (the `creator` group pattern), which a
| shallow `resolve` deliberately does not.
|
| Serial + self-cleaning: writes prefixed entries into two existing FLAT
| collections (reusable_content, tables) — no structured-tree setup, no
| collection create/delete churn — and deletes them by slug prefix afterward.
| Skipped when the content/ submodule (and thus those collections) is absent.
|
| @covers \App\Services\ComputedFields\Reverse\ReverseEntryReferenceResolver
|
| @command php artisan test --compact tests/Feature/Services/ComputedFields/Reverse/ReverseEntryReferenceResolverTest.php
*/

const REV_PREFIX = 'zz-rev-';

const REV_PROBE_FIELD = 'zz_reverse_probe';

const REV_NESTED_PROBE_FIELD = 'zz_reverse_nested_probe';

const REV_SEGMENTS_PROBE_FIELD = 'zz_reverse_segments_probe';

const REV_COLLECTION_A = 'reusable_content';

const REV_COLLECTION_B = 'tables';

function revTearDown(): void
{
    foreach ([REV_COLLECTION_A, REV_COLLECTION_B] as $handle) {
        if (! Collection::find($handle)) {
            continue;
        }

        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        $query->where('collection', $handle);

        /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $entries */
        $entries = $query->get();
        $entries
            ->filter(function (Statamic\Entries\Entry $entry): bool {
                /** @var string|null $slug */
                $slug = $entry->slug();

                return str_starts_with((string) $slug, REV_PREFIX);
            })
            ->each(fn (Statamic\Entries\Entry $entry) => $entry->delete());
    }
}

/**
 * Save an entry into a flat collection carrying the probe field set to the given
 * raw value (scalar, array, or null), published unless told otherwise.
 */
function revMakeEntry(string $collection, string $suffix, mixed $probeValue, bool $published = true): Statamic\Entries\Entry
{
    $data = ['title' => 'Rev '.$suffix];

    if ($probeValue !== null) {
        $data[REV_PROBE_FIELD] = $probeValue;
    }

    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection($collection);
    $entry->locale('default');
    $entry->slug(REV_PREFIX.$suffix);
    $entry->published($published);
    $entry->data($data);
    $entry->save();

    return $entry;
}

/**
 * Save an entry into a flat collection carrying the NESTED probe field set to the
 * given (associative / multi-level) value — modelling a `creator`-style group whose
 * per-role sub-fields each hold entry ids.
 */
function revMakeNestedEntry(string $collection, string $suffix, mixed $nestedValue, bool $published = true): Statamic\Entries\Entry
{
    $data = ['title' => 'Rev nested '.$suffix];

    if ($nestedValue !== null) {
        $data[REV_NESTED_PROBE_FIELD] = $nestedValue;
    }

    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection($collection);
    $entry->locale('default');
    $entry->slug(REV_PREFIX.$suffix);
    $entry->published($published);
    $entry->data($data);
    $entry->save();

    return $entry;
}

/**
 * The sorted slugs of a resolved reverse set.
 *
 * @param  iterable<Statamic\Entries\Entry>  $set
 * @return array<int, string>
 */
function revSlugs(iterable $set): array
{
    $slugs = [];
    foreach ($set as $entry) {
        /** @var string $slug */
        $slug = $entry->slug();
        $slugs[] = $slug;
    }
    sort($slugs);

    return $slugs;
}

beforeEach(function (): void {
    if (! Collection::find(REV_COLLECTION_A) || ! Collection::find(REV_COLLECTION_B)) {
        ContentSubmodule::reportMissing('reusable_content + tables collections');
    }

    Blink::flush();
    revTearDown();
});

afterEach(fn () => revTearDown());

it('matches a reference stored as a scalar, a single-element array, and a multi-element array', function (): void {
    $targetId = 'zz-target-facility-id';

    // Three pointing entries, each storing the SAME target id in a different YAML shape.
    revMakeEntry(REV_COLLECTION_A, 'scalar', $targetId);                      // probe: <id>
    revMakeEntry(REV_COLLECTION_A, 'single-array', [$targetId]);             // probe:\n  - <id>
    revMakeEntry(REV_COLLECTION_A, 'multi-array', ['other-id', $targetId]);  // probe:\n  - other\n  - <id>

    // A non-matching entry (different id) must be excluded.
    revMakeEntry(REV_COLLECTION_A, 'unrelated', ['some-other-id']);

    $resolved = app(ReverseEntryReferenceResolver::class)
        ->resolve(REV_COLLECTION_A, REV_PROBE_FIELD, $targetId);

    expect(revSlugs($resolved))->toBe([
        REV_PREFIX.'multi-array',
        REV_PREFIX.'scalar',
        REV_PREFIX.'single-array',
    ]);
})->group('serial');

it('spans multiple source collections in one reverse', function (): void {
    $targetId = 'zz-shared-target';

    revMakeEntry(REV_COLLECTION_A, 'a', $targetId);
    revMakeEntry(REV_COLLECTION_B, 'b', [$targetId]);

    $resolved = app(ReverseEntryReferenceResolver::class)
        ->resolve([REV_COLLECTION_A, REV_COLLECTION_B], REV_PROBE_FIELD, $targetId);

    expect(revSlugs($resolved))->toBe([REV_PREFIX.'a', REV_PREFIX.'b']);
})->group('serial');

it('restricts to published pointing entries by default and includes drafts when asked', function (): void {
    $targetId = 'zz-pub-target';

    revMakeEntry(REV_COLLECTION_A, 'published', $targetId, published: true);
    revMakeEntry(REV_COLLECTION_A, 'draft', $targetId, published: false);

    $resolver = app(ReverseEntryReferenceResolver::class);

    // Default: published only.
    expect(revSlugs($resolver->resolve(REV_COLLECTION_A, REV_PROBE_FIELD, $targetId)))
        ->toBe([REV_PREFIX.'published']);

    Blink::flush();

    // Explicitly include drafts.
    expect(revSlugs($resolver->resolve(REV_COLLECTION_A, REV_PROBE_FIELD, $targetId, publishedOnly: false)))
        ->toBe([REV_PREFIX.'draft', REV_PREFIX.'published']);
})->group('serial');

it('ignores a pointing entry in a collection that was not asked for', function (): void {
    $targetId = 'zz-scoped-target';

    // Both entries point at the target through the same field; only one sits in a
    // collection the resolve names.
    revMakeEntry(REV_COLLECTION_A, 'in-scope', $targetId);
    revMakeEntry(REV_COLLECTION_B, 'out-of-scope', $targetId);

    $resolved = app(ReverseEntryReferenceResolver::class)
        ->resolve(REV_COLLECTION_A, REV_PROBE_FIELD, $targetId);

    expect(revSlugs($resolved))->toBe([REV_PREFIX.'in-scope']);
})->group('serial');

it('keeps the published-only and all-statuses reverses distinct within one request', function (): void {
    $targetId = 'zz-status-distinct';

    revMakeEntry(REV_COLLECTION_A, 'live', $targetId, published: true);
    revMakeEntry(REV_COLLECTION_A, 'unpublished', $targetId, published: false);

    $resolver = app(ReverseEntryReferenceResolver::class);

    // No Blink flush between the reads: each status filter must memoize under its
    // own key, in either order.
    expect(revSlugs($resolver->resolve(REV_COLLECTION_A, REV_PROBE_FIELD, $targetId)))
        ->toBe([REV_PREFIX.'live'])
        ->and(revSlugs($resolver->resolve(REV_COLLECTION_A, REV_PROBE_FIELD, $targetId, publishedOnly: false)))
        ->toBe([REV_PREFIX.'live', REV_PREFIX.'unpublished']);

    Blink::flush();

    expect(revSlugs($resolver->resolve(REV_COLLECTION_A, REV_PROBE_FIELD, $targetId, publishedOnly: false)))
        ->toBe([REV_PREFIX.'live', REV_PREFIX.'unpublished'])
        ->and(revSlugs($resolver->resolve(REV_COLLECTION_A, REV_PROBE_FIELD, $targetId)))
        ->toBe([REV_PREFIX.'live']);
})->group('serial');

it('keeps nested resolves over different fields distinct within one request', function (): void {
    $targetId = 'zz-field-distinct';

    // The target is named only under the shallow probe field.
    revMakeEntry(REV_COLLECTION_A, 'probe-field', $targetId);

    $resolver = app(ReverseEntryReferenceResolver::class);

    // The nested probe field names nothing, so this memoizes an empty set.
    expect(revSlugs($resolver->resolveNested(REV_COLLECTION_A, REV_NESTED_PROBE_FIELD, $targetId)))
        ->toBe([]);

    // Same request, a different field list: it must not reuse the empty set above.
    expect(revSlugs($resolver->resolveNested(REV_COLLECTION_A, REV_PROBE_FIELD, $targetId)))
        ->toBe([REV_PREFIX.'probe-field']);
})->group('serial');

it('keeps resolves distinct when handles differ only in where an underscore splits them', function (): void {
    $targetId = 'zz-boundary-target';
    revMakeEntry(REV_COLLECTION_A, 'boundary', $targetId);

    $resolver = app(ReverseEntryReferenceResolver::class);

    // `reusable` is no collection, so this memoizes an empty set. Joined with `_`,
    // its collection and field read the same as the real pair below.
    expect(revSlugs($resolver->resolve('reusable', 'content_'.REV_PROBE_FIELD, $targetId)))
        ->toBe([]);

    // Same request: the real pair must not reuse the empty set above.
    expect(revSlugs($resolver->resolve(REV_COLLECTION_A, REV_PROBE_FIELD, $targetId)))
        ->toBe([REV_PREFIX.'boundary']);
})->group('serial');

it('returns an empty set for a target that nothing references', function (): void {
    revMakeEntry(REV_COLLECTION_A, 'unrelated', ['some-other-id']);

    $resolved = app(ReverseEntryReferenceResolver::class)
        ->resolve(REV_COLLECTION_A, REV_PROBE_FIELD, 'zz-nobody-points-here');

    expect(revSlugs($resolved))->toBe([]);
})->group('serial');

it('skips a source collection that does not exist but still queries the real ones (#1458)', function (): void {
    // A configured source collection can be absent from the current Stache (e.g. a
    // minimal/isolated test root that provisions only some collections). Querying
    // it made `whereStatus('published')` apply per-collection status logic over a
    // handle with no live collection and fatal ("Call to a member function handle()
    // on null"). The resolver must skip the missing handle — a collection that does
    // not exist holds no reverse references — and still query the real ones.
    $targetId = 'zz-mixed-target';
    revMakeEntry(REV_COLLECTION_A, 'real-match', $targetId);

    $resolver = app(ReverseEntryReferenceResolver::class);

    // A missing handle alone: empty set, no fatal.
    expect(revSlugs($resolver->resolve('zz_no_such_collection', REV_PROBE_FIELD, $targetId)))
        ->toBe([]);

    Blink::flush();

    // A missing handle mixed with a real one: the real match still resolves (the
    // missing collection is dropped, not allowed to crash or void the whole query).
    expect(revSlugs($resolver->resolve([REV_COLLECTION_A, 'zz_no_such_collection'], REV_PROBE_FIELD, $targetId)))
        ->toBe([REV_PREFIX.'real-match']);
})->group('serial');

it('returns an empty set (and no query) for a null or empty target id', function (): void {
    // A not-yet-saved target has no id to be referenced by; must short-circuit.
    // An entry storing an empty id must not make '' a match either.
    revMakeEntry(REV_COLLECTION_A, 'empty-id', '');
    revMakeNestedEntry(REV_COLLECTION_A, 'empty-nested-id', ['writer' => ['']]);

    $resolver = app(ReverseEntryReferenceResolver::class);

    expect($resolver->resolve(REV_COLLECTION_A, REV_PROBE_FIELD, null)->all())->toBe([])
        ->and($resolver->resolve(REV_COLLECTION_A, REV_PROBE_FIELD, '')->all())->toBe([])
        ->and($resolver->resolveNested(REV_COLLECTION_A, REV_NESTED_PROBE_FIELD, '')->all())->toBe([]);
})->group('serial');

it('resolveNested matches an id nested anywhere inside a group value, spanning roles', function (): void {
    $targetId = 'zz-nested-person';

    // A `creator`-style group: the target id appears under one role sub-field.
    revMakeNestedEntry(REV_COLLECTION_A, 'as-writer', ['writer' => [$targetId], 'editor' => ['someone-else']]);
    // Same target under a different role, stored as a scalar rather than an array.
    revMakeNestedEntry(REV_COLLECTION_A, 'as-host', ['host' => $targetId, 'guest' => null]);
    // A group that never names the target must be excluded.
    revMakeNestedEntry(REV_COLLECTION_A, 'no-match', ['writer' => ['other-1'], 'editor' => ['other-2']]);

    $resolved = app(ReverseEntryReferenceResolver::class)
        ->resolveNested(REV_COLLECTION_A, REV_NESTED_PROBE_FIELD, $targetId);

    expect(revSlugs($resolved))->toBe([REV_PREFIX.'as-host', REV_PREFIX.'as-writer']);
})->group('serial');

it('keeps shallow and nested matching distinct within one request (no cache-key collision)', function (): void {
    $targetId = 'zz-mode-distinct';

    // The id is buried inside a group value — only the nested matcher should find it.
    revMakeNestedEntry(REV_COLLECTION_A, 'nested-only', ['writer' => [$targetId]]);

    $resolver = app(ReverseEntryReferenceResolver::class);

    // Shallow resolve over the same field sees an array-of-arrays and matches nothing,
    // memoizing the empty set under its key.
    expect(revSlugs($resolver->resolve(REV_COLLECTION_A, REV_NESTED_PROBE_FIELD, $targetId)))
        ->toBe([]);

    // In the SAME request (no Blink flush): the nested resolve must still deep-collect
    // and match. This only holds because resolveNested namespaces its memo key ('nested:'
    // discriminator) distinctly from the shallow resolve — without that namespace the
    // nested call would reuse the shallow empty set cached above and wrongly return [].
    expect(revSlugs($resolver->resolveNested(REV_COLLECTION_A, REV_NESTED_PROBE_FIELD, $targetId)))
        ->toBe([REV_PREFIX.'nested-only']);
})->group('serial');

it('unions multiple nested fields, matching an id nested under a replicator field only', function (): void {
    $targetId = 'zz-segment-person';

    // Model a video post: NO top-level `creator` group, the credit is nested inside a
    // `segments`-style replicator (each set carries its own creator group). Only a scan
    // that unions the segments field reaches it.
    /** @var Statamic\Entries\Entry $segmentEntry */
    $segmentEntry = Entry::make();
    $segmentEntry->collection(REV_COLLECTION_A);
    $segmentEntry->locale('default');
    $segmentEntry->slug(REV_PREFIX.'segment-credit');
    $segmentEntry->published(true);
    $segmentEntry->data([
        'title' => 'Rev segment',
        REV_SEGMENTS_PROBE_FIELD => [
            ['type' => 'box', 'url' => 'https://app.box.com/x', 'creator' => ['videographer' => [$targetId]]],
            ['type' => 'youtube', 'url' => 'https://youtu.be/x', 'creator' => ['host' => ['someone-else']]],
        ],
    ]);
    $segmentEntry->save();

    // A segments entry that never credits the target must be excluded.
    revMakeNestedEntry(REV_COLLECTION_A, 'no-credit', ['writer' => ['other']]);

    $resolved = app(ReverseEntryReferenceResolver::class)
        ->resolveNested(REV_COLLECTION_A, [REV_NESTED_PROBE_FIELD, REV_SEGMENTS_PROBE_FIELD], $targetId);

    expect(revSlugs($resolved))->toBe([REV_PREFIX.'segment-credit']);
})->group('serial');

it('memoizes the reverse set per request (one Stache query for repeated reads)', function (): void {
    $targetId = 'zz-memo-target';
    revMakeEntry(REV_COLLECTION_A, 'memo', $targetId);

    $resolver = app(ReverseEntryReferenceResolver::class);

    $first = $resolver->resolve(REV_COLLECTION_A, REV_PROBE_FIELD, $targetId);

    // Delete the underlying entry; a re-query would now return empty, but the
    // memoized set for this request must still hold the original result.
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', REV_COLLECTION_A);

    /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $entries */
    $entries = $query->get();
    $entries
        ->filter(fn (Statamic\Entries\Entry $entry): bool => $entry->slug() === REV_PREFIX.'memo')
        ->each(fn (Statamic\Entries\Entry $entry) => $entry->delete());

    $second = $resolver->resolve(REV_COLLECTION_A, REV_PROBE_FIELD, $targetId);

    expect(revSlugs($first))->toBe([REV_PREFIX.'memo'])
        ->and(revSlugs($second))->toBe([REV_PREFIX.'memo']);
})->group('serial');
