<?php

declare(strict_types=1);

use App\Listeners\ValidateClinicalLocationsTree;
use Illuminate\Support\Facades\Log;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\LegacyMockInterface;
use Statamic\Entries\Entry;
use Statamic\Events\CollectionTreeSaving;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Statamic\Structures\CollectionTree;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/*
|--------------------------------------------------------------------------
| ValidateClinicalLocationsTree (UAMS-Web/uams-statamic-archive#1068, D7)
|--------------------------------------------------------------------------
|
| Enforces the clinical_locations hierarchy on tree save: a `location` is valid
| only at depth 1 (root) or depth 2 (one child location level) — never deeper —
| while content pages (`basic_page`/`landing_page`) are unbounded beneath a
| location and never top-level. WordPress import runs bypass validation.
|
| @covers \App\Listeners\ValidateClinicalLocationsTree
|
| Accepted equivalents, annotated in the listener: the strict flags on the two
| blueprint `in_array()` calls, whose needles are a blueprint handle or a null
| parent and whose lists hold only non-numeric handles. The depth cap and the
| blueprint lists are constants, which the mutation runner skips as uncovered;
| each was changed by hand and fails a test here, and the annotation says so.
|
| @command  php artisan test --compact tests/Feature/Listeners/ValidateClinicalLocationsTreeTest.php
*/

/**
 * Build a CollectionTreeSaving event for the given tree array + handle.
 *
 * @param  array<int, array<string, mixed>>  $tree
 */
function locationsTreeEvent(array $tree, string $handle = 'clinical_locations'): CollectionTreeSaving
{
    $mock = Mockery::mock(CollectionTree::class);
    $mock->shouldReceive('handle')->andReturn($handle);
    $mock->shouldReceive('tree')->andReturn($tree);

    return new CollectionTreeSaving($mock);
}

/** Register an entry (by id) with EntryFacade::find, exposing the given blueprint + title. */
function locationsTreeEntry(string $id, string $blueprint, mixed $title = 'X'): void
{
    $entry = Mockery::mock(Entry::class);

    $bp = Mockery::mock(Blueprint::class);
    $bp->shouldReceive('handle')->andReturn($blueprint);
    $entry->shouldReceive('blueprint')->andReturn($bp);
    $entry->shouldReceive('get')->with('title')->andReturn($title);

    EntryFacade::shouldReceive('find')->with($id)->andReturn($entry);
}

it('skips when the tree handle is not clinical_locations', function () {
    $tree = Mockery::mock(CollectionTree::class);
    $tree->shouldReceive('handle')->once()->andReturn('pages');
    $tree->shouldReceive('tree')->never();

    expect(throwableFrom(fn () => (new ValidateClinicalLocationsTree)->handle(new CollectionTreeSaving($tree))))->toBeNull();
});

it('accepts a root location and one child location level (depth 2)', function () {
    locationsTreeEntry('l1', 'location');
    locationsTreeEntry('l2', 'location');

    $tree = [['entry' => 'l1', 'children' => [['entry' => 'l2']]]];

    expect(throwableFrom(fn () => (new ValidateClinicalLocationsTree)->handle(locationsTreeEvent($tree))))->toBeNull();
});

it('rejects a location nested three levels deep (D7 depth-2 cap)', function () {
    locationsTreeEntry('l1', 'location');
    locationsTreeEntry('l2', 'location');
    locationsTreeEntry('l3', 'location', 'Too Deep');

    // l1 (root, depth 1) → l2 (child, depth 2) → l3 (depth 3, INVALID)
    $tree = [['entry' => 'l1', 'children' => [['entry' => 'l2', 'children' => [['entry' => 'l3']]]]]];

    expect(fn () => (new ValidateClinicalLocationsTree)->handle(locationsTreeEvent($tree)))
        ->toThrow(Exception::class, 'at most one level deep');
});

it('accepts content pages nested unbounded beneath a location', function () {
    locationsTreeEntry('l1', 'location');
    locationsTreeEntry('cp1', 'basic_page');
    locationsTreeEntry('cp2', 'landing_page');
    locationsTreeEntry('cp3', 'basic_page');

    // location → content page → content page → content page (depth 4, allowed)
    $tree = [['entry' => 'l1', 'children' => [['entry' => 'cp1', 'children' => [['entry' => 'cp2', 'children' => [['entry' => 'cp3']]]]]]]];

    expect(throwableFrom(fn () => (new ValidateClinicalLocationsTree)->handle(locationsTreeEvent($tree))))->toBeNull();
});

it('accepts a content page directly under a depth-2 child location', function () {
    locationsTreeEntry('l1', 'location');
    locationsTreeEntry('l2', 'location');
    locationsTreeEntry('cp1', 'basic_page');

    // root location → child location (depth 2) → content page (depth 3, allowed — content pages unbounded)
    $tree = [['entry' => 'l1', 'children' => [['entry' => 'l2', 'children' => [['entry' => 'cp1']]]]]];

    expect(throwableFrom(fn () => (new ValidateClinicalLocationsTree)->handle(locationsTreeEvent($tree))))->toBeNull();
});

it('rejects a content page at the subsite root', function () {
    locationsTreeEntry('cp1', 'basic_page', 'Orphan Page');

    $tree = [['entry' => 'cp1']];

    expect(fn () => (new ValidateClinicalLocationsTree)->handle(locationsTreeEvent($tree)))
        ->toThrow(Exception::class, 'cannot be top-level');
});

it('rejects a location nested under a content page', function () {
    locationsTreeEntry('l0', 'location');
    locationsTreeEntry('cp1', 'landing_page');
    locationsTreeEntry('l1', 'location', 'Nested Wrong');

    // l0 (root) → cp1 (content page) → l1 (location, INVALID under a content page)
    $tree = [['entry' => 'l0', 'children' => [['entry' => 'cp1', 'children' => [['entry' => 'l1']]]]]];

    expect(fn () => (new ValidateClinicalLocationsTree)->handle(locationsTreeEvent($tree)))
        ->toThrow(Exception::class, 'child of another location');
});

it('names the offending entry in the rejection message', function () {
    locationsTreeEntry('cp1', 'basic_page', 'My Orphan Page');

    // The title (not the id) must appear in the error surfaced to the editor.
    expect(fn () => (new ValidateClinicalLocationsTree)->handle(locationsTreeEvent([['entry' => 'cp1']])))
        ->toThrow(Exception::class, 'My Orphan Page');
});

it('re-validates every tree save (no cross-save dedupe leak)', function () {
    locationsTreeEntry('l1', 'location');
    locationsTreeEntry('cp1', 'landing_page');

    $listener = new ValidateClinicalLocationsTree;

    // Save 1 is valid — the content page is nested under a location.
    $listener->handle(locationsTreeEvent([['entry' => 'l1', 'children' => [['entry' => 'cp1']]]]));

    // Save 2, same process, re-parents the content page to the root — still rejected.
    expect(fn () => $listener->handle(locationsTreeEvent([['entry' => 'cp1']])))
        ->toThrow(Exception::class, 'cannot be top-level');
});

it('bypasses validation during a WordPress import', function () {
    $original = $_SERVER['argv'] ?? null;
    $_SERVER['argv'] = ['artisan', 'import:wordpress', 'export.xml'];

    try {
        // A location three levels deep would normally be rejected; the import bypass lets it through.
        locationsTreeEntry('l1', 'location');
        locationsTreeEntry('l2', 'location');
        locationsTreeEntry('l3', 'location');
        $tree = [['entry' => 'l1', 'children' => [['entry' => 'l2', 'children' => [['entry' => 'l3']]]]]];

        expect(throwableFrom(fn () => (new ValidateClinicalLocationsTree)->handle(locationsTreeEvent($tree))))->toBeNull();
    } finally {
        if ($original === null) {
            unset($_SERVER['argv']);
        } else {
            $_SERVER['argv'] = $original;
        }
    }
});

it('logs and skips a node whose blueprint is unknown', function () {
    locationsTreeEntry('x1', 'some_other_blueprint');

    Log::shouldReceive('warning')
        ->once()
        ->with('ValidateClinicalLocationsTree::validateTree: Unknown blueprint', ['blueprint' => 'some_other_blueprint']);

    $tree = [['entry' => 'x1']];

    expect(throwableFrom(fn () => (new ValidateClinicalLocationsTree)->handle(locationsTreeEvent($tree))))->toBeNull();
});

it('logs and skips a tree node missing an entry id', function () {
    Log::shouldReceive('warning')
        ->once()
        ->with('ValidateClinicalLocationsTree::validateTree: Missing entry ID in tree node', Mockery::type('array'));

    $tree = [['children' => []]];

    expect(throwableFrom(fn () => (new ValidateClinicalLocationsTree)->handle(locationsTreeEvent($tree))))->toBeNull();
});

it('logs and skips when a tree node references a missing entry', function () {
    EntryFacade::shouldReceive('find')->with('ghost')->andReturn(null);

    Log::shouldReceive('warning')
        ->once()
        ->with('ValidateClinicalLocationsTree::validateTree: Entry not found', ['entry_id' => 'ghost']);

    $tree = [['entry' => 'ghost']];

    expect(throwableFrom(fn () => (new ValidateClinicalLocationsTree)->handle(locationsTreeEvent($tree))))->toBeNull();
});

/** Run `$callback` with `$_SERVER['argv']` set to `$argv`, restoring it after. */
function locationsTreeWithArgv(mixed $argv, callable $callback): mixed
{
    $had = array_key_exists('argv', $_SERVER);
    $original = $_SERVER['argv'] ?? null;
    $_SERVER['argv'] = $argv;

    try {
        return $callback();
    } finally {
        if ($had) {
            $_SERVER['argv'] = $original;
        } else {
            unset($_SERVER['argv']);
        }
    }
}

it('rejects either kind of content page at the subsite root', function (string $blueprint) {
    locationsTreeEntry('cp1', $blueprint, 'Root Page');

    expect(fn () => (new ValidateClinicalLocationsTree)->handle(locationsTreeEvent([['entry' => 'cp1']])))
        ->toThrow(Exception::class, "Content page entry 'Root Page' must be the child");
})->with(['a basic page' => ['basic_page'], 'a landing page' => ['landing_page']]);

it('accepts a content page nested under either kind of content page', function (string $parent) {
    locationsTreeEntry('l1', 'location');
    locationsTreeEntry('cp1', $parent);
    locationsTreeEntry('cp2', 'basic_page');

    $tree = [['entry' => 'l1', 'children' => [['entry' => 'cp1', 'children' => [['entry' => 'cp2']]]]]];

    expect(throwableFrom(fn () => (new ValidateClinicalLocationsTree)->handle(locationsTreeEvent($tree))))->toBeNull();
})->with(['a basic page' => ['basic_page'], 'a landing page' => ['landing_page']]);

it('names an entry by its id when its title is missing or not text', function (mixed $title) {
    locationsTreeEntry('cp1', 'basic_page', $title);

    expect(fn () => (new ValidateClinicalLocationsTree)->handle(locationsTreeEvent([['entry' => 'cp1']])))
        ->toThrow(Exception::class, "Content page entry 'cp1' must be the child");
})->with(['null' => [null], 'array' => [['not', 'text']]]);

it('reads a numeric title as text', function () {
    locationsTreeEntry('cp1', 'basic_page', 2024);

    expect(fn () => (new ValidateClinicalLocationsTree)->handle(locationsTreeEvent([['entry' => 'cp1']])))
        ->toThrow(Exception::class, "Content page entry '2024' must be the child");
});

it('logs the node it skips for a missing entry id', function () {
    /** @var LegacyMockInterface $log */
    $log = Log::spy();

    (new ValidateClinicalLocationsTree)->handle(locationsTreeEvent([['children' => [['entry' => 'cp1']]]]));

    $log->shouldHaveReceived('warning')->once()->with(
        'ValidateClinicalLocationsTree::validateTree: Missing entry ID in tree node',
        ['node' => ['children' => [['entry' => 'cp1']]]]
    );
});

it('reads a numeric node id as text, and a non-scalar one as empty', function () {
    Log::spy();
    // Matched by identity, so an id passed through as an integer would not match.
    EntryFacade::shouldReceive('find')->withArgs(fn (mixed $id): bool => $id === '9917')->once()->andReturn(null);
    EntryFacade::shouldReceive('find')->withArgs(fn (mixed $id): bool => $id === '')->once()->andReturn(null);

    (new ValidateClinicalLocationsTree)->handle(locationsTreeEvent([['entry' => 9917], ['entry' => ['not', 'an', 'id']]]));
});

it('goes on to the next sibling after a node it skips', function (string $skippedNode) {
    // A skipped node is passed over, not the end of its level: the invalid content page
    // after it is still reached.
    locationsTreeEntry('x1', 'some_other_blueprint');
    locationsTreeEntry('cp1', 'basic_page', 'Sibling Page');
    EntryFacade::shouldReceive('find')->with('ghost')->andReturn(null);
    Log::spy();

    $skipped = match ($skippedNode) {
        'no id' => ['children' => []],
        'not found' => ['entry' => 'ghost'],
        'unknown blueprint' => ['entry' => 'x1'],
        default => throw new InvalidArgumentException("No such skipped node: {$skippedNode}"),
    };

    expect(fn () => (new ValidateClinicalLocationsTree)->handle(locationsTreeEvent([$skipped, ['entry' => 'cp1']])))
        ->toThrow(Exception::class, "Content page entry 'Sibling Page' must be the child");
})->with([
    'a node with no entry id' => ['no id'],
    'an entry it cannot find' => ['not found'],
    'an entry of an unknown blueprint' => ['unknown blueprint'],
]);

it('does not descend into empty or non-list children', function (mixed $children) {
    locationsTreeEntry('l1', 'location');

    expect(throwableFrom(fn () => (new ValidateClinicalLocationsTree)->handle(locationsTreeEvent([['entry' => 'l1', 'children' => $children]]))))
        ->toBeNull();
})->with(['empty list' => [[]], 'not a list' => ['children']]);

it('validates a console run that is not the WordPress import', function (mixed $argv) {
    locationsTreeEntry('cp1', 'basic_page', 'Console Page');

    locationsTreeWithArgv($argv, function (): void {
        expect(fn () => (new ValidateClinicalLocationsTree)->handle(locationsTreeEvent([['entry' => 'cp1']])))
            ->toThrow(Exception::class, "Content page entry 'Console Page' must be the child");
    });
})->with([
    'another command' => [['artisan', 'statamic:stache:warm']],
    'a non-text argument' => [['artisan', 42]],
    'no argument list' => ['not a list'],
]);

it('validates outside the console even when argv names the import', function () {
    locationsTreeEntry('cp1', 'basic_page', 'Web Page');

    // A web request carries no import; only a console argv can bypass validation.
    $running = new ReflectionProperty(app(), 'isRunningInConsole');
    $running->setValue(app(), false);

    try {
        locationsTreeWithArgv(['artisan', 'import:wordpress'], function (): void {
            expect(fn () => (new ValidateClinicalLocationsTree)->handle(locationsTreeEvent([['entry' => 'cp1']])))
                ->toThrow(Exception::class, "Content page entry 'Web Page' must be the child");
        });
    } finally {
        $running->setValue(app(), null);
    }
});
