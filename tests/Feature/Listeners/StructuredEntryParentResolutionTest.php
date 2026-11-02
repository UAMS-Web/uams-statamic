<?php

declare(strict_types=1);

use App\Listeners\ValidateClinicalExpertiseEntry;
use App\Listeners\ValidateClinicalLocationsEntry;
use App\Listeners\ValidateKnowledgeBaseEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Structure as StructureFacade;
use Statamic\Structures\Page;
use Statamic\Structures\Structure;
use Statamic\Structures\Tree;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * The parent lookup the three structured-collection validators share.
 *
 * `ValidateKnowledgeBaseEntry`, `ValidateClinicalExpertiseEntry` and
 * `ValidateClinicalLocationsEntry` each carry the same protected `getParent()`, differing only
 * in the structure handle and the log prefix. Each listener's own test file covers the
 * top-level, root-parent, resolved-parent, new-entry `_parent` and missing-structure cases;
 * this file covers the rest once, against all three: where a new entry's parent id comes from,
 * and the three lookups that can miss on an existing entry.
 *
 * @covers \App\Listeners\ValidateKnowledgeBaseEntry
 * @covers \App\Listeners\ValidateClinicalExpertiseEntry
 * @covers \App\Listeners\ValidateClinicalLocationsEntry
 *
 * @command  php artisan test --compact tests/Feature/Listeners/StructuredEntryParentResolutionTest.php
 */
dataset('structured entry validators', [
    'knowledge bases' => [ValidateKnowledgeBaseEntry::class, 'knowledge_bases'],
    'clinical expertise' => [ValidateClinicalExpertiseEntry::class, 'clinical_expertise'],
    'clinical locations' => [ValidateClinicalLocationsEntry::class, 'clinical_locations'],
]);

/**
 * Call a listener's protected `getParent()`.
 *
 * @param  string  $listenerClass  One of the three validators.
 */
function structuredParentOf(string $listenerClass, Entry $entry, ?Request $request = null): mixed
{
    if (! class_exists($listenerClass)) {
        throw new LogicException("No listener class [{$listenerClass}].");
    }

    $listener = new $listenerClass($request ?? Request::create('/'));

    return (new ReflectionMethod($listener, 'getParent'))->invoke($listener, $entry);
}

/**
 * An entry the repository does not know yet, carrying the given `parent` field value.
 */
function structuredNewEntry(mixed $parentField): Entry&MockInterface
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn('new-entry');
    $entry->shouldReceive('get')->with('parent')->andReturn($parentField);

    EntryFacade::shouldReceive('find')->with('new-entry')->andReturn(null);

    return $entry;
}

/**
 * An existing entry in the `default` site, and the structure that holds it.
 */
function structuredExistingEntry(): Entry&MockInterface
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn('existing-entry');
    $entry->shouldReceive('locale')->andReturn('default');

    EntryFacade::shouldReceive('find')->with('existing-entry')->andReturn($entry);

    return $entry;
}

/**
 * Register the structure for a collection and return its `default` tree.
 */
function structuredTreeFor(string $collection): Tree&MockInterface
{
    $tree = Mockery::mock(Tree::class);

    $structure = Mockery::mock(Structure::class);
    $structure->shouldReceive('in')->with('default')->andReturn($tree);

    StructureFacade::shouldReceive('findByHandle')->with('collection::'.$collection)->andReturn($structure);

    return $tree;
}

/**
 * The class name a listener prefixes its log messages with.
 *
 * @param  string  $listenerClass  One of the three validators.
 */
function structuredLogPrefix(string $listenerClass): string
{
    return class_basename($listenerClass).'::getParent: ';
}

it('reads a new entry\'s parent from its own parent field before the request', function (string $listenerClass): void {
    $parent = Mockery::mock(Entry::class);
    EntryFacade::shouldReceive('find')->with('field-parent')->once()->andReturn($parent);

    $request = Request::create('/', 'POST', ['_parent' => 'request-parent', 'parent' => 'p', 'parent_id' => 'pid']);

    expect(structuredParentOf($listenerClass, structuredNewEntry('field-parent'), $request))->toBe($parent);
})->with('structured entry validators');

it('reads a new entry\'s parent from each request input in turn', function (string $listenerClass, string $collection, array $input, string $expectedId): void {
    $parent = Mockery::mock(Entry::class);
    EntryFacade::shouldReceive('find')->with($expectedId)->once()->andReturn($parent);

    expect(structuredParentOf($listenerClass, structuredNewEntry(null), Request::create('/', 'POST', $input)))->toBe($parent);
})->with('structured entry validators')->with([
    '_parent first' => [['_parent' => 'from-underscore', 'parent' => 'from-parent', 'parent_id' => 'from-id'], 'from-underscore'],
    'then parent' => [['parent' => 'from-parent', 'parent_id' => 'from-id'], 'from-parent'],
    'then parent_id' => [['parent_id' => 'from-id'], 'from-id'],
]);

it('gives a new entry no parent when its parent id is empty', function (string $listenerClass): void {
    expect(structuredParentOf($listenerClass, structuredNewEntry(''), Request::create('/')))->toBeNull();
})->with('structured entry validators');

it('logs and gives no parent when the site has no tree', function (string $listenerClass, string $collection): void {
    $entry = structuredExistingEntry();

    $structure = Mockery::mock(Structure::class);
    $structure->shouldReceive('in')->with('default')->andReturn(null);
    StructureFacade::shouldReceive('findByHandle')->with('collection::'.$collection)->andReturn($structure);

    Log::shouldReceive('warning')->once()->with(structuredLogPrefix($listenerClass).'No tree found for site', ['site' => 'default']);

    expect(structuredParentOf($listenerClass, $entry))->toBeNull();
})->with('structured entry validators');

it('logs and gives no parent when the entry is not in the tree', function (string $listenerClass, string $collection): void {
    $entry = structuredExistingEntry();
    structuredTreeFor($collection)->shouldReceive('find')->with('existing-entry')->andReturn(null);

    Log::shouldReceive('warning')->once()->with(structuredLogPrefix($listenerClass).'Entry not found in tree', ['entry_id' => 'existing-entry']);

    expect(structuredParentOf($listenerClass, $entry))->toBeNull();
})->with('structured entry validators');

it('logs and gives no parent when the parent page has no entry', function (string $listenerClass, string $collection): void {
    $entry = structuredExistingEntry();

    $parentPage = Mockery::mock(Page::class);
    $parentPage->shouldReceive('isRoot')->andReturn(false);
    $parentPage->shouldReceive('id')->andReturn('vanished-parent');

    $page = Mockery::mock(Page::class);
    $page->shouldReceive('parent')->andReturn($parentPage);
    structuredTreeFor($collection)->shouldReceive('find')->with('existing-entry')->andReturn($page);

    EntryFacade::shouldReceive('find')->with('vanished-parent')->andReturn(null);

    Log::shouldReceive('warning')->once()->with(structuredLogPrefix($listenerClass).'Parent entry not found', ['parent_id' => 'vanished-parent']);

    expect(structuredParentOf($listenerClass, $entry))->toBeNull();
})->with('structured entry validators');
