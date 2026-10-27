<?php

declare(strict_types=1);

use App\Support\ContentDescendantIds;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Contracts\Taxonomies\TermRepository;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Structures\Page;
use Tests\Support\Doubles\TermDescendantTestStub;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for `ContentDescendantIds` entry and term descendant walks.
 *
 * @covers \App\Support\ContentDescendantIds
 *
 * The term walk builds its parent → children reverse map from the RAW stored
 * `parent` value (`get('parent')`) rather than the augmented `parent` property, so
 * it no longer runs a Stache Term sub-query per term (UAMS-Web/uams-statamic-archive#1367). Behavior is unchanged;
 * the real-term round-trip is pinned in ContentDescendantIdsTermTreeTest.
 *
 * @command  php artisan test --compact tests/Feature/Support/ContentDescendantIdsTest.php
 */
it('returns the flattened structure descendants for an entry', function () {
    $childPage = Mockery::mock();
    $childPage->shouldReceive('id')->andReturn('child');

    $grandChildPage = Mockery::mock();
    $grandChildPage->shouldReceive('id')->andReturn('grandchild');

    $page = Mockery::mock(Page::class);
    $page->shouldReceive('flattenedPages')->once()->andReturn(collect([$childPage, $grandChildPage]));

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('page')->once()->andReturn($page);

    EntryFacade::shouldReceive('find')->once()->with('root')->andReturn($entry);

    expect(ContentDescendantIds::forEntry('root'))->toBe(['child', 'grandchild']);
});

it('returns an empty list when the entry cannot be loaded', function () {
    EntryFacade::shouldReceive('find')->once()->with('missing')->andReturn(null);

    expect(ContentDescendantIds::forEntry('missing'))->toBe([]);
});

it('returns an empty list when the entry is not in a structured collection', function () {
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('page')->once()->andReturn(null);

    EntryFacade::shouldReceive('find')->once()->with('flat')->andReturn($entry);

    expect(ContentDescendantIds::forEntry('flat'))->toBe([]);
});

it('skips structure descendant pages that expose no usable id', function () {
    $nullIdPage = Mockery::mock();
    $nullIdPage->shouldReceive('id')->andReturn(null);

    $emptyIdPage = Mockery::mock();
    $emptyIdPage->shouldReceive('id')->andReturn('');

    $realPage = Mockery::mock();
    $realPage->shouldReceive('id')->andReturn('real');

    $page = Mockery::mock(Page::class);
    $page->shouldReceive('flattenedPages')->once()->andReturn(collect([$nullIdPage, $emptyIdPage, $realPage]));

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('page')->once()->andReturn($page);

    EntryFacade::shouldReceive('find')->once()->with('root')->andReturn($entry);

    expect(ContentDescendantIds::forEntry('root'))->toBe(['real']);
});

it('returns breadth-first descendants for a term tree', function () {
    // Parents are bare slugs (single-taxonomy storage), qualified with the `tax` handle.
    $root = new TermDescendantTestStub('tax::root', null);
    $childA = new TermDescendantTestStub('tax::a', 'root');
    $childB = new TermDescendantTestStub('tax::b', 'root');
    $grandchildA = new TermDescendantTestStub('tax::a1', 'a');
    $grandchildB = new TermDescendantTestStub('tax::b1', 'b');

    $termRepo = bindTermRepository();
    $termRepo->shouldReceive('find')->once()->with('tax::root')->andReturn($root);
    $termRepo->shouldReceive('whereTaxonomy')->once()->with('tax')
        ->andReturn(collect([$root, $childA, $childB, $grandchildA, $grandchildB]));

    // Breadth-first: both children precede either grandchild. A depth-first walk
    // of this symmetric tree would instead yield tax::a, tax::b, tax::b1, tax::a1.
    expect(ContentDescendantIds::forTerm('tax::root'))->toBe(['tax::a', 'tax::b', 'tax::a1', 'tax::b1']);
});

it('qualifies an already-namespaced parent reference without double-prefixing', function () {
    // A parent stored as a full `handle::slug` reference is used verbatim.
    $root = new TermDescendantTestStub('tax::root', null);
    $child = new TermDescendantTestStub('tax::a', 'tax::root');

    $termRepo = bindTermRepository();
    $termRepo->shouldReceive('find')->once()->with('tax::root')->andReturn($root);
    $termRepo->shouldReceive('whereTaxonomy')->once()->with('tax')->andReturn(collect([$root, $child]));

    expect(ContentDescendantIds::forTerm('tax::root'))->toBe(['tax::a']);
});

it('unwraps a single-element array parent value', function () {
    // A max_items:1 terms field may persist the slug wrapped in a one-element array.
    $root = new TermDescendantTestStub('tax::root', null);
    $child = new TermDescendantTestStub('tax::a', ['root']);

    $termRepo = bindTermRepository();
    $termRepo->shouldReceive('find')->once()->with('tax::root')->andReturn($root);
    $termRepo->shouldReceive('whereTaxonomy')->once()->with('tax')->andReturn(collect([$root, $child]));

    expect(ContentDescendantIds::forTerm('tax::root'))->toBe(['tax::a']);
});

it('returns an empty list when the starting term cannot be loaded', function () {
    $termRepo = bindTermRepository();
    $termRepo->shouldReceive('find')->once()->with('tax::missing')->andReturn(null);
    $termRepo->shouldReceive('whereTaxonomy')->never();

    expect(ContentDescendantIds::forTerm('tax::missing'))->toBe([]);
});

it('returns an empty list when the reference carries no taxonomy handle', function () {
    $orphan = new TermDescendantTestStub('::slug', null);

    $termRepo = bindTermRepository();
    $termRepo->shouldReceive('find')->once()->with('::slug')->andReturn($orphan);
    $termRepo->shouldReceive('whereTaxonomy')->never();

    expect(ContentDescendantIds::forTerm('::slug'))->toBe([]);
});

it('skips terms whose parent cannot be resolved to a reference', function () {
    $root = new TermDescendantTestStub('tax::root', null);
    $childWithRootParent = new TermDescendantTestStub('tax::a', 'root');
    // Non-string raw parent -> unresolvable parent reference.
    $childWithUnresolvableParent = new TermDescendantTestStub('tax::orphan', 123);
    // Empty-string raw parent -> unresolvable parent reference.
    $childWithEmptyParent = new TermDescendantTestStub('tax::empty', '');

    $termRepo = bindTermRepository();
    $termRepo->shouldReceive('find')->once()->with('tax::root')->andReturn($root);
    // The recordable child comes LAST so a skip must `continue`, not `break`, to
    // reach it (a `break` mutant would drop tax::a and return []).
    $termRepo->shouldReceive('whereTaxonomy')->once()->with('tax')->andReturn(
        collect([$root, $childWithUnresolvableParent, $childWithEmptyParent, $childWithRootParent])
    );

    expect(ContentDescendantIds::forTerm('tax::root'))->toBe(['tax::a']);
});

it('skips a child whose own reference is empty even when its parent resolves', function () {
    $root = new TermDescendantTestStub('tax::root', null);
    // A child of the start term whose own id() is empty must not leak '' into the
    // result (it would otherwise be filed under the start bucket and emitted).
    $childWithEmptyOwnRef = new TermDescendantTestStub('', 'root');
    $realChild = new TermDescendantTestStub('tax::a', 'root');

    $termRepo = bindTermRepository();
    $termRepo->shouldReceive('find')->once()->with('tax::root')->andReturn($root);
    $termRepo->shouldReceive('whereTaxonomy')->once()->with('tax')->andReturn(
        collect([$root, $childWithEmptyOwnRef, $realChild])
    );

    expect(ContentDescendantIds::forTerm('tax::root'))->toBe(['tax::a']);
});

it('terminates and dedupes on a pre-existing cycle in the term parent data', function () {
    // a's parent is b, b's parent is a (a 2-cycle), and c is another child of b.
    $termA = new TermDescendantTestStub('tax::a', 'b');
    $termB = new TermDescendantTestStub('tax::b', 'a');
    $termC = new TermDescendantTestStub('tax::c', 'b');

    $termRepo = bindTermRepository();
    $termRepo->shouldReceive('find')->once()->with('tax::a')->andReturn($termA);
    $termRepo->shouldReceive('whereTaxonomy')->once()->with('tax')->andReturn(collect([$termA, $termB, $termC]));

    // From a: b is reached, then b's children [a (already seen), c]. The walk must
    // `continue` past the seen a to still emit c (a `break` mutant would drop c),
    // and must not loop back into the a/b cycle.
    expect(ContentDescendantIds::forTerm('tax::a'))->toBe(['tax::b', 'tax::c']);
});

/**
 * Bind a fresh TermRepository mock into the container and return it.
 */
function bindTermRepository(): MockInterface
{
    $termRepo = Mockery::mock(TermRepository::class);
    app()->instance(TermRepository::class, $termRepo);

    return $termRepo;
}
