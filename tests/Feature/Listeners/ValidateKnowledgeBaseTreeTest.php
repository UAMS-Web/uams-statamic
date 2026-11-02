<?php

declare(strict_types=1);

use App\Listeners\ValidateKnowledgeBaseTree;
use Illuminate\Support\Facades\Log;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\LegacyMockInterface;
use Statamic\Entries\Entry;
use Statamic\Events\CollectionTreeSaving;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Listeners\ValidateKnowledgeBaseTree
 *
 * Mutation: 66 of 67 on App\Listeners\ValidateKnowledgeBaseTree, measured on macOS on 2026-10-08, serial, --covered-only, with every test file that names the class.
 * Five equivalent mutants are annotated in the class with the reason each cannot be killed; the
 * one survivor, a BooleanAndToBooleanOr on the children check, is equivalent and explained there
 * without a marker, because a marker would also suppress the killed one beside it.
 *
 * @command  php artisan test --compact tests/Feature/Listeners/ValidateKnowledgeBaseTreeTest.php
 */
it('skips when the tree handle is not knowledge_bases', function () {
    $tree = Mockery::mock();
    $tree->shouldReceive('handle')
        ->once()
        ->andReturn('pages');
    $tree->shouldReceive('tree')->never();

    expect(throwableFrom(fn () => (new ValidateKnowledgeBaseTree)->handle(new CollectionTreeSaving($tree))))->toBeNull();
});

// ---------------------------------------------------------------------------
// The depth and parent rules
//
// The listener remembers every `<tree>:<entry id>` it has validated in a static map for the
// life of the process, and skips one it has seen. Each test therefore names its entries with a
// prefix no other test uses.
// ---------------------------------------------------------------------------

/**
 * Resolve each id to a mock entry with the given blueprint and title.
 *
 * @param  array<string, array{0: string, 1?: mixed}>  $entries  Entry id => [blueprint, title]
 */
function kbTreeEntries(array $entries): void
{
    EntryFacade::shouldReceive('find')->andReturnUsing(function (string $id) use ($entries): ?Entry {
        if (! isset($entries[$id])) {
            return null;
        }

        $blueprint = Mockery::mock(Blueprint::class);
        $blueprint->shouldReceive('handle')->andReturn($entries[$id][0]);

        $entry = Mockery::mock(Entry::class);
        $entry->shouldReceive('blueprint')->andReturn($blueprint);
        $entry->shouldReceive('get')->with('title')->andReturn(array_key_exists(1, $entries[$id]) ? $entries[$id][1] : 'Title of '.$id);

        return $entry;
    });
}

/**
 * Fire the listener for a knowledge_bases tree with the given structure.
 *
 * @param  array<int, array<array-key, mixed>>  $structure
 */
function kbTreeSave(array $structure, string $handle = 'knowledge_bases'): void
{
    $tree = Mockery::mock();
    $tree->shouldReceive('handle')->andReturn($handle);
    $tree->shouldReceive('tree')->andReturn($structure);

    (new ValidateKnowledgeBaseTree)->handle(new CollectionTreeSaving($tree));
}

it('accepts a Knowledge Base holding Topics, nested Topics and Articles', function (): void {
    $p = uniqid('kbt-valid-');
    kbTreeEntries([
        "$p-kb" => ['knowledge_base'],
        "$p-topic" => ['topic'],
        "$p-subtopic" => ['topic'],
        "$p-article" => ['article'],
        "$p-deep-article" => ['article'],
    ]);

    kbTreeSave([
        ['entry' => "$p-kb", 'children' => [
            ['entry' => "$p-topic", 'children' => [
                ['entry' => "$p-article"],
                ['entry' => "$p-subtopic", 'children' => [
                    ['entry' => "$p-deep-article"],
                ]],
            ]],
        ]],
    ]);
})->throwsNoExceptions();

it('rejects a Knowledge Base below the top level', function (): void {
    $p = uniqid('kbt-nested-kb-');
    kbTreeEntries(["$p-kb" => ['knowledge_base'], "$p-inner" => ['knowledge_base', 'Inner KB']]);

    expect(fn () => kbTreeSave([['entry' => "$p-kb", 'children' => [['entry' => "$p-inner"]]]]))
        ->toThrow(Exception::class, "Knowledge Base entry 'Inner KB' must be a top-level entry.");
});

it('rejects a Topic at the top level', function (): void {
    $p = uniqid('kbt-topic-top-');
    kbTreeEntries(["$p-topic" => ['topic', 'Stray Topic']]);

    expect(fn () => kbTreeSave([['entry' => "$p-topic"]]))
        ->toThrow(Exception::class, "Topic entry 'Stray Topic' must be the child of a Knowledge Base entry or a Topic entry.");
});

it('rejects a Topic under an Article', function (): void {
    $p = uniqid('kbt-topic-under-article-');
    kbTreeEntries([
        "$p-kb" => ['knowledge_base'],
        "$p-t" => ['topic'],
        "$p-a" => ['article'],
        "$p-topic" => ['topic', 'Stray Topic'],
    ]);

    expect(fn () => kbTreeSave([['entry' => "$p-kb", 'children' => [
        ['entry' => "$p-t", 'children' => [['entry' => "$p-a", 'children' => [['entry' => "$p-topic"]]]]],
    ]]]))->toThrow(Exception::class, "Topic entry 'Stray Topic' must be the child of a Knowledge Base entry or a Topic entry.");
});

it('rejects an Article whose parent is not a Topic', function (): void {
    $p = uniqid('kbt-article-parent-');
    kbTreeEntries(["$p-kb" => ['knowledge_base'], "$p-article" => ['article', 'Loose Article']]);

    expect(fn () => kbTreeSave([['entry' => "$p-kb", 'children' => [['entry' => "$p-article"]]]]))
        ->toThrow(Exception::class, "Article entry 'Loose Article' must be the child of a Topic entry.");
});

it('rejects an Article at the top level', function (): void {
    $p = uniqid('kbt-article-top-');
    kbTreeEntries(["$p-article" => ['article', 'Top Article']]);

    expect(fn () => kbTreeSave([['entry' => "$p-article"]]))
        ->toThrow(Exception::class, "Article entry 'Top Article' must be the child of a Topic entry.");
});

it('enforces the minimum depth a parent rule alone would allow', function (): void {
    // The parent rules already imply the depths, so the depth checks are reached only through
    // validateTree() called with a parent blueprint at too shallow a depth.
    $p = uniqid('kbt-depth-');
    kbTreeEntries(["$p-topic" => ['topic', 'Shallow Topic'], "$p-article" => ['article', 'Shallow Article']]);

    $validate = new ReflectionMethod(ValidateKnowledgeBaseTree::class, 'validateTree');
    $listener = new ValidateKnowledgeBaseTree;

    expect(fn () => $validate->invoke($listener, [['entry' => "$p-topic"]], 1, 'knowledge_bases', 'knowledge_base'))
        ->toThrow(Exception::class, "Topic entry 'Shallow Topic' must be at depth 2 or greater.")
        ->and(fn () => $validate->invoke($listener, [['entry' => "$p-article"]], 2, 'knowledge_bases', 'topic'))
        ->toThrow(Exception::class, "Article entry 'Shallow Article' must be at depth 3 or greater.");
});

it('accepts each minimum depth exactly', function (): void {
    $p = uniqid('kbt-depth-ok-');
    kbTreeEntries(["$p-topic" => ['topic'], "$p-article" => ['article']]);

    $validate = new ReflectionMethod(ValidateKnowledgeBaseTree::class, 'validateTree');
    $listener = new ValidateKnowledgeBaseTree;

    $validate->invoke($listener, [['entry' => "$p-topic"]], 2, 'knowledge_bases', 'knowledge_base');
    $validate->invoke($listener, [['entry' => "$p-article"]], 3, 'knowledge_bases', 'topic');
})->throwsNoExceptions();

it('names an entry by its id when its title is missing or not text', function (mixed $title): void {
    $p = uniqid('kbt-untitled-');
    kbTreeEntries(["$p-kb" => ['knowledge_base'], "$p-inner" => ['knowledge_base', $title]]);

    expect(fn () => kbTreeSave([['entry' => "$p-kb", 'children' => [['entry' => "$p-inner"]]]]))
        ->toThrow(Exception::class, "Knowledge Base entry '$p-inner' must be a top-level entry.");
})->with(['null' => [null], 'array' => [['not', 'text']]]);

it('reads a numeric title as text', function (): void {
    $p = uniqid('kbt-numeric-');
    kbTreeEntries(["$p-kb" => ['knowledge_base'], "$p-inner" => ['knowledge_base', 2024]]);

    expect(fn () => kbTreeSave([['entry' => "$p-kb", 'children' => [['entry' => "$p-inner"]]]]))
        ->toThrow(Exception::class, "Knowledge Base entry '2024' must be a top-level entry.");
});

it('logs and skips a node with no entry id', function (): void {
    $p = uniqid('kbt-no-id-');
    kbTreeEntries(["$p-article" => ['article', 'Top Article']]);
    /** @var LegacyMockInterface $log */
    $log = Log::spy();

    // The id-less node is skipped, so its invalid child is never reached.
    kbTreeSave([['children' => [['entry' => "$p-article"]]]]);

    $log->shouldHaveReceived('warning')->once()->with(
        'ValidateKnowledgeBaseTree::validateTree: Missing entry ID in tree node',
        ['node' => ['children' => [['entry' => "$p-article"]]]]
    );
});

it('logs and skips an entry it cannot find, with its children', function (): void {
    $p = uniqid('kbt-missing-');
    kbTreeEntries(["$p-article" => ['article', 'Orphan Article']]);
    /** @var LegacyMockInterface $log */
    $log = Log::spy();

    kbTreeSave([['entry' => "$p-gone", 'children' => [['entry' => "$p-article"]]]]);

    $log->shouldHaveReceived('warning')->once()->with(
        'ValidateKnowledgeBaseTree::validateTree: Entry not found',
        ['entry_id' => "$p-gone"]
    );
});

it('reads a numeric node id as text, and a non-scalar one as empty', function (): void {
    /** @var LegacyMockInterface $log */
    $log = Log::spy();
    // Matched by identity, so an id passed through as an integer would not match.
    EntryFacade::shouldReceive('find')->withArgs(fn (mixed $id): bool => $id === '9917')->once()->andReturn(null);
    EntryFacade::shouldReceive('find')->withArgs(fn (mixed $id): bool => $id === '')->once()->andReturn(null);

    kbTreeSave([['entry' => 9917], ['entry' => ['not', 'an', 'id']]]);

    $log->shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $context === ['entry_id' => '9917'])->once();
    $log->shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $context === ['entry_id' => ''])->once();
});

it('goes on to the next sibling after a node it skips', function (array $skipped): void {
    // A skipped node is passed over, not the end of its level: the invalid Article after it
    // is still reached.
    $p = uniqid('kbt-sibling-');
    kbTreeEntries(["$p-faq" => ['faq'], "$p-article" => ['article', 'Sibling Article']]);
    Log::spy();

    $skipped = array_map(fn (mixed $value): mixed => \is_string($value) ? str_replace('PREFIX', $p, $value) : $value, $skipped);

    expect(fn () => kbTreeSave([$skipped, ['entry' => "$p-article"]]))
        ->toThrow(Exception::class, "Article entry 'Sibling Article' must be the child of a Topic entry.");
})->with([
    'a node with no entry id' => [['children' => []]],
    'an entry it cannot find' => [['entry' => 'PREFIX-gone']],
    'an entry of an unknown blueprint' => [['entry' => 'PREFIX-faq']],
]);

it('goes on to the next sibling after an entry it has already validated', function (): void {
    $p = uniqid('kbt-seen-sibling-');
    kbTreeEntries(["$p-kb" => ['knowledge_base'], "$p-article" => ['article', 'New Article']]);

    kbTreeSave([['entry' => "$p-kb"]]);

    expect(fn () => kbTreeSave([['entry' => "$p-kb"], ['entry' => "$p-article"]]))
        ->toThrow(Exception::class, "Article entry 'New Article' must be the child of a Topic entry.");
});

it('logs and skips an entry of an unknown blueprint, with its children', function (): void {
    $p = uniqid('kbt-unknown-');
    kbTreeEntries(["$p-faq" => ['faq'], "$p-article" => ['article', 'Hidden Article']]);
    /** @var LegacyMockInterface $log */
    $log = Log::spy();

    kbTreeSave([['entry' => "$p-faq", 'children' => [['entry' => "$p-article"]]]]);

    $log->shouldHaveReceived('warning')->once()->with(
        'ValidateKnowledgeBaseTree::validateTree: Unknown blueprint',
        ['blueprint' => 'faq']
    );
});

it('does not descend into empty or non-list children', function (mixed $children): void {
    $p = uniqid('kbt-children-');
    kbTreeEntries(["$p-kb" => ['knowledge_base']]);

    kbTreeSave([['entry' => "$p-kb", 'children' => $children]]);
})->with(['empty list' => [[]], 'not a list' => ['children']])->throwsNoExceptions();

it('validates an entry once per tree for the life of the process', function (): void {
    // Characterization: a second save naming an entry already validated under the same tree
    // handle skips it, even when it has moved somewhere the rules forbid.
    $p = uniqid('kbt-once-');
    kbTreeEntries(["$p-kb" => ['knowledge_base'], "$p-topic" => ['topic', 'Moved Topic']]);

    kbTreeSave([['entry' => "$p-kb", 'children' => [['entry' => "$p-topic"]]]]);
    kbTreeSave([['entry' => "$p-topic"]]);
})->throwsNoExceptions();

it('skips validation during a WordPress console import', function (): void {
    EntryFacade::shouldReceive('find')->never();
    $original = $_SERVER['argv'] ?? null;

    try {
        $_SERVER['argv'] = ['artisan', 'import:wordpress', '--fresh'];
        kbTreeSave([['entry' => uniqid('kbt-import-')]]);
    } finally {
        if ($original === null) {
            unset($_SERVER['argv']);
        } else {
            $_SERVER['argv'] = $original;
        }
    }
});

it('validates a console run that is not the WordPress import', function (mixed $argv): void {
    $p = uniqid('kbt-console-');
    kbTreeEntries(["$p-article" => ['article', 'Console Article']]);
    $original = $_SERVER['argv'] ?? null;

    try {
        $_SERVER['argv'] = $argv;

        expect(fn () => kbTreeSave([['entry' => "$p-article"]]))
            ->toThrow(Exception::class, "Article entry 'Console Article' must be the child of a Topic entry.");
    } finally {
        if ($original === null) {
            unset($_SERVER['argv']);
        } else {
            $_SERVER['argv'] = $original;
        }
    }
})->with([
    'another command' => [['artisan', 'statamic:stache:warm']],
    'a non-text argument' => [['artisan', 42]],
    'no argument list' => ['not a list'],
]);

it('validates outside the console even when argv names the import', function (): void {
    $p = uniqid('kbt-web-');
    kbTreeEntries(["$p-article" => ['article', 'Web Article']]);
    $original = $_SERVER['argv'] ?? null;

    // A web request carries no import; only a console argv can bypass validation.
    $running = new ReflectionProperty(app(), 'isRunningInConsole');
    $running->setValue(app(), false);

    try {
        $_SERVER['argv'] = ['artisan', 'import:wordpress'];

        expect(fn () => kbTreeSave([['entry' => "$p-article"]]))
            ->toThrow(Exception::class, "Article entry 'Web Article' must be the child of a Topic entry.");
    } finally {
        $running->setValue(app(), null);

        if ($original === null) {
            unset($_SERVER['argv']);
        } else {
            $_SERVER['argv'] = $original;
        }
    }
});
