<?php

declare(strict_types=1);

use App\Listeners\ValidateClinicalExpertiseTree;
use Illuminate\Support\Facades\Log;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
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
function expertiseTreeEntry(string $id, string $blueprint, string $title = 'X'): void
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
        // A content page at the root would normally be rejected; the import bypass lets it through.
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
