<?php

declare(strict_types=1);

use App\Listeners\ValidateClinicalExpertiseTree;
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
| ValidateClinicalExpertiseTree (UAMS-Web/uams-statamic-archive#1056, D4)
|--------------------------------------------------------------------------
|
| Enforces the AoE parent-blueprint hierarchy on tree save. The subsite is
| UNBOUNDED (D1): there are no absolute-depth rules — only the parent-blueprint
| relationship at each node. WordPress import runs bypass validation.
|
| @covers \App\Listeners\ValidateClinicalExpertiseTree
|
| Accepted equivalents, annotated in the listener: the strict flags on the two
| blueprint `in_array()` calls, whose needles are a blueprint handle or a null
| parent and whose lists hold only non-numeric handles. The blueprint lists are
| constants, which the mutation runner skips as uncovered; each item removed by
| hand fails a test here, and the annotation says so.
|
| @command  php artisan test --compact tests/Feature/Listeners/ValidateClinicalExpertiseTreeTest.php
*/

/**
 * Build a CollectionTreeSaving event for the given tree array + handle.
 *
 * @param  array<int, array<string, mixed>>  $tree
 */
function expertiseTreeEvent(array $tree, string $handle = 'clinical_expertise'): CollectionTreeSaving
{
    $mock = Mockery::mock(CollectionTree::class);
    $mock->shouldReceive('handle')->andReturn($handle);
    $mock->shouldReceive('tree')->andReturn($tree);

    return new CollectionTreeSaving($mock);
}

/** Register an entry (by id) with EntryFacade::find, exposing the given blueprint + title. */
function expertiseTreeEntry(string $id, string $blueprint, mixed $title = 'X'): void
{
    $entry = Mockery::mock(Entry::class);

    $bp = Mockery::mock(Blueprint::class);
    $bp->shouldReceive('handle')->andReturn($blueprint);
    $entry->shouldReceive('blueprint')->andReturn($bp);
    $entry->shouldReceive('get')->with('title')->andReturn($title);

    EntryFacade::shouldReceive('find')->with($id)->andReturn($entry);
}

it('skips when the tree handle is not clinical_expertise', function () {
    $tree = Mockery::mock(CollectionTree::class);
    $tree->shouldReceive('handle')->once()->andReturn('pages');
    $tree->shouldReceive('tree')->never();

    expect(throwableFrom(fn () => (new ValidateClinicalExpertiseTree)->handle(new CollectionTreeSaving($tree))))->toBeNull();
});

it('accepts a deeply nested Area of Expertise chain regardless of depth', function () {
    expertiseTreeEntry('e1', 'clinical_expertise');
    expertiseTreeEntry('e2', 'clinical_expertise');
    expertiseTreeEntry('e3', 'clinical_expertise');
    expertiseTreeEntry('e4', 'clinical_expertise');

    $tree = [
        ['entry' => 'e1', 'children' => [
            ['entry' => 'e2', 'children' => [
                ['entry' => 'e3', 'children' => [
                    ['entry' => 'e4'],
                ]],
            ]],
        ]],
    ];

    expect(throwableFrom(fn () => (new ValidateClinicalExpertiseTree)->handle(expertiseTreeEvent($tree))))->toBeNull();
});

it('accepts a content page nested under an Area of Expertise', function () {
    expertiseTreeEntry('e1', 'clinical_expertise');
    expertiseTreeEntry('cp1', 'basic_page');

    $tree = [['entry' => 'e1', 'children' => [['entry' => 'cp1']]]];

    expect(throwableFrom(fn () => (new ValidateClinicalExpertiseTree)->handle(expertiseTreeEvent($tree))))->toBeNull();
});

it('accepts a content page nested under another content page', function () {
    expertiseTreeEntry('e1', 'clinical_expertise');
    expertiseTreeEntry('cp1', 'landing_page');
    expertiseTreeEntry('cp2', 'basic_page');

    $tree = [['entry' => 'e1', 'children' => [['entry' => 'cp1', 'children' => [['entry' => 'cp2']]]]]];

    expect(throwableFrom(fn () => (new ValidateClinicalExpertiseTree)->handle(expertiseTreeEvent($tree))))->toBeNull();
});

it('rejects a content page at the subsite root', function () {
    expertiseTreeEntry('cp1', 'basic_page', 'About Cardiology');

    $tree = [['entry' => 'cp1']];

    expect(fn () => (new ValidateClinicalExpertiseTree)->handle(expertiseTreeEvent($tree)))
        ->toThrow(Exception::class, 'cannot be top-level');
});

it('re-validates every tree save (no cross-save dedupe leak in a long-lived process)', function () {
    expertiseTreeEntry('e1', 'clinical_expertise');
    expertiseTreeEntry('cp1', 'landing_page');

    $listener = new ValidateClinicalExpertiseTree;

    // Save 1 is valid — the content page is nested under an Area of Expertise.
    $listener->handle(expertiseTreeEvent([['entry' => 'e1', 'children' => [['entry' => 'cp1']]]]));

    // Save 2, in the SAME process, re-parents the content page to the root. It must
    // still be rejected — a stale "already validated" set (the removed static dedupe)
    // would have skipped both entries and let the invalid tree through.
    expect(fn () => $listener->handle(expertiseTreeEvent([['entry' => 'cp1']])))
        ->toThrow(Exception::class, 'cannot be top-level');
});

it('rejects an Area of Expertise nested under a content page', function () {
    expertiseTreeEntry('e0', 'clinical_expertise');
    expertiseTreeEntry('cp1', 'landing_page');
    expertiseTreeEntry('e1', 'clinical_expertise', 'Cardiology');

    // e0 (expertise, root) → cp1 (content page, valid) → e1 (expertise, INVALID under content page)
    $tree = [['entry' => 'e0', 'children' => [['entry' => 'cp1', 'children' => [['entry' => 'e1']]]]]];

    expect(fn () => (new ValidateClinicalExpertiseTree)->handle(expertiseTreeEvent($tree)))
        ->toThrow(Exception::class, 'child of another Area of Expertise');
});

it('bypasses validation during a WordPress import', function () {
    $original = $_SERVER['argv'] ?? null;
    $_SERVER['argv'] = ['artisan', 'import:wordpress', 'export.xml'];

    try {
        // A content page at the root would normally be rejected; the import bypass lets it
        // through without reading a single entry.
        EntryFacade::shouldReceive('find')->never();
        $tree = [['entry' => 'cp1']];

        expect(throwableFrom(fn () => (new ValidateClinicalExpertiseTree)->handle(expertiseTreeEvent($tree))))->toBeNull();
    } finally {
        if ($original === null) {
            unset($_SERVER['argv']);
        } else {
            $_SERVER['argv'] = $original;
        }
    }
});

it('logs and skips a node whose blueprint is unknown', function () {
    expertiseTreeEntry('x1', 'some_other_blueprint');

    Log::shouldReceive('warning')
        ->once()
        ->with('ValidateClinicalExpertiseTree::validateTree: Unknown blueprint', ['blueprint' => 'some_other_blueprint']);

    $tree = [['entry' => 'x1']];

    expect(throwableFrom(fn () => (new ValidateClinicalExpertiseTree)->handle(expertiseTreeEvent($tree))))->toBeNull();
});

it('logs and skips a tree node missing an entry id', function () {
    Log::shouldReceive('warning')
        ->once()
        ->with('ValidateClinicalExpertiseTree::validateTree: Missing entry ID in tree node', Mockery::type('array'));

    $tree = [['children' => []]];

    expect(throwableFrom(fn () => (new ValidateClinicalExpertiseTree)->handle(expertiseTreeEvent($tree))))->toBeNull();
});

it('logs and skips when a tree node references a missing entry', function () {
    EntryFacade::shouldReceive('find')->with('ghost')->andReturn(null);

    Log::shouldReceive('warning')
        ->once()
        ->with('ValidateClinicalExpertiseTree::validateTree: Entry not found', ['entry_id' => 'ghost']);

    $tree = [['entry' => 'ghost']];

    expect(throwableFrom(fn () => (new ValidateClinicalExpertiseTree)->handle(expertiseTreeEvent($tree))))->toBeNull();
});

/** Run `$callback` with `$_SERVER['argv']` set to `$argv`, restoring it after. */
function expertiseTreeWithArgv(mixed $argv, callable $callback): mixed
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

it('rejects a landing page at the subsite root', function () {
    expertiseTreeEntry('lp1', 'landing_page', 'Cardiology Landing');

    expect(fn () => (new ValidateClinicalExpertiseTree)->handle(expertiseTreeEvent([['entry' => 'lp1']])))
        ->toThrow(Exception::class, "Content page entry 'Cardiology Landing' must be the child");
});

it('accepts a content page nested under a basic page', function () {
    expertiseTreeEntry('e1', 'clinical_expertise');
    expertiseTreeEntry('cp1', 'basic_page');
    expertiseTreeEntry('cp2', 'landing_page');

    $tree = [['entry' => 'e1', 'children' => [['entry' => 'cp1', 'children' => [['entry' => 'cp2']]]]]];

    expect(throwableFrom(fn () => (new ValidateClinicalExpertiseTree)->handle(expertiseTreeEvent($tree))))->toBeNull();
});

it('names an entry by its id when its title is missing or not text', function (mixed $title) {
    expertiseTreeEntry('cp1', 'basic_page', $title);

    expect(fn () => (new ValidateClinicalExpertiseTree)->handle(expertiseTreeEvent([['entry' => 'cp1']])))
        ->toThrow(Exception::class, "Content page entry 'cp1' must be the child");
})->with(['null' => [null], 'array' => [['not', 'text']]]);

it('reads a numeric title as text', function () {
    expertiseTreeEntry('cp1', 'basic_page', 2024);

    expect(fn () => (new ValidateClinicalExpertiseTree)->handle(expertiseTreeEvent([['entry' => 'cp1']])))
        ->toThrow(Exception::class, "Content page entry '2024' must be the child");
});

it('logs the node it skips for a missing entry id', function () {
    /** @var LegacyMockInterface $log */
    $log = Log::spy();

    (new ValidateClinicalExpertiseTree)->handle(expertiseTreeEvent([['children' => [['entry' => 'cp1']]]]));

    $log->shouldHaveReceived('warning')->once()->with(
        'ValidateClinicalExpertiseTree::validateTree: Missing entry ID in tree node',
        ['node' => ['children' => [['entry' => 'cp1']]]]
    );
});

it('reads a numeric node id as text, and a non-scalar one as empty', function () {
    Log::spy();
    // Matched by identity, so an id passed through as an integer would not match.
    EntryFacade::shouldReceive('find')->withArgs(fn (mixed $id): bool => $id === '9917')->once()->andReturn(null);
    EntryFacade::shouldReceive('find')->withArgs(fn (mixed $id): bool => $id === '')->once()->andReturn(null);

    (new ValidateClinicalExpertiseTree)->handle(expertiseTreeEvent([['entry' => 9917], ['entry' => ['not', 'an', 'id']]]));
});

it('goes on to the next sibling after a node it skips', function (string $skippedNode) {
    // A skipped node is passed over, not the end of its level: the invalid content page
    // after it is still reached.
    expertiseTreeEntry('x1', 'some_other_blueprint');
    expertiseTreeEntry('cp1', 'basic_page', 'Sibling Page');
    EntryFacade::shouldReceive('find')->with('ghost')->andReturn(null);
    Log::spy();

    $skipped = match ($skippedNode) {
        'no id' => ['children' => []],
        'not found' => ['entry' => 'ghost'],
        'unknown blueprint' => ['entry' => 'x1'],
        default => throw new InvalidArgumentException("No such skipped node: {$skippedNode}"),
    };

    expect(fn () => (new ValidateClinicalExpertiseTree)->handle(expertiseTreeEvent([$skipped, ['entry' => 'cp1']])))
        ->toThrow(Exception::class, "Content page entry 'Sibling Page' must be the child");
})->with([
    'a node with no entry id' => ['no id'],
    'an entry it cannot find' => ['not found'],
    'an entry of an unknown blueprint' => ['unknown blueprint'],
]);

it('does not descend into empty or non-list children', function (mixed $children) {
    expertiseTreeEntry('e1', 'clinical_expertise');

    expect(throwableFrom(fn () => (new ValidateClinicalExpertiseTree)->handle(expertiseTreeEvent([['entry' => 'e1', 'children' => $children]]))))
        ->toBeNull();
})->with(['empty list' => [[]], 'not a list' => ['children']]);

it('validates a console run that is not the WordPress import', function (mixed $argv) {
    expertiseTreeEntry('cp1', 'basic_page', 'Console Page');

    expertiseTreeWithArgv($argv, function (): void {
        expect(fn () => (new ValidateClinicalExpertiseTree)->handle(expertiseTreeEvent([['entry' => 'cp1']])))
            ->toThrow(Exception::class, "Content page entry 'Console Page' must be the child");
    });
})->with([
    'another command' => [['artisan', 'statamic:stache:warm']],
    'a non-text argument' => [['artisan', 42]],
    'no argument list' => ['not a list'],
]);

it('validates outside the console even when argv names the import', function () {
    expertiseTreeEntry('cp1', 'basic_page', 'Web Page');

    // A web request carries no import; only a console argv can bypass validation.
    $running = new ReflectionProperty(app(), 'isRunningInConsole');
    $running->setValue(app(), false);

    try {
        expertiseTreeWithArgv(['artisan', 'import:wordpress'], function (): void {
            expect(fn () => (new ValidateClinicalExpertiseTree)->handle(expertiseTreeEvent([['entry' => 'cp1']])))
                ->toThrow(Exception::class, "Content page entry 'Web Page' must be the child");
        });
    } finally {
        $running->setValue(app(), null);
    }
});
