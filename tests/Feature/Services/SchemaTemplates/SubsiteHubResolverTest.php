<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SubsiteHubResolver;
use App\Support\ContentAncestorIds;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Entry;
use Tests\Support\ContentSubmodule;
use Tests\Support\Doubles\AncestorExposingSubsiteHubResolver;
use Tests\TestCase;

/**
 * UAMS-Web/uams-statamic-archive#1414 — the nearest-subsite-hub structure-tree walk. The ancestor id list is stubbed via the
 * protected seam so the walk logic (skip non-hubs, stop at the first hub, return null when none)
 * is exercised without a persisted structure tree; `Entry::find()` returns the fixtures.
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * A resolver whose ancestor chain is fixed, mirroring the seam the shipped computed services expose.
 *
 * @param  list<string>  $ancestors
 */
function resolverWithAncestors(array $ancestors): SubsiteHubResolver
{
    return new class($ancestors) extends SubsiteHubResolver
    {
        /** @param  list<string>  $fixedAncestors */
        public function __construct(private array $fixedAncestors) {}

        #[Override]
        protected function ancestorIds(int|string $entryId): array
        {
            return $this->fixedAncestors;
        }
    };
}

/** A synthetic entry on the given blueprint that `Entry::find()` will return. */
function findableEntry(string $collection, string $blueprint, string $id): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection($collection);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->blueprint($blueprint);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->locale('health');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($id);

    return $entry;
}

beforeEach(function (): void {
    /** @var Statamic\Entries\Entry $page */
    $page = Entry::make();
    /** @var Statamic\Entries\Entry $page */
    $page = $page->collection('clinical_expertise');
    /** @var Statamic\Entries\Entry $page */
    $page = $page->blueprint('basic_page');
    /** @var Statamic\Entries\Entry $page */
    $page = $page->locale('health');
    /** @var Statamic\Entries\Entry $page */
    $page = $page->id('page-1');
    $this->page = $page;
});

it('returns the nearest clinical_expertise ancestor, skipping non-hub ancestors', function () {
    $mid = findableEntry('clinical_expertise', 'basic_page', 'mid');
    $hub = findableEntry('clinical_expertise', 'clinical_expertise', 'aoe-1');

    $find = Entry::partialMock();
    $find->shouldReceive('find')->with('mid')->andReturn($mid);
    $find->shouldReceive('find')->with('aoe-1')->andReturn($hub);

    $resolver = resolverWithAncestors(['mid', 'aoe-1']);

    /** @var Statamic\Entries\Entry $page */
    $page = $this->page;

    expect($resolver->resolve($page)?->id())->toBe('aoe-1')
        ->and($resolver->resolveIdList($page))->toBe(['aoe-1']);
});

it('recognizes a location entry as a hub', function () {
    $hub = findableEntry('clinical_locations', 'location', 'loc-1');

    $find = Entry::partialMock();
    $find->shouldReceive('find')->with('loc-1')->andReturn($hub);

    $resolver = resolverWithAncestors(['loc-1']);

    /** @var Statamic\Entries\Entry $page */
    $page = $this->page;

    expect($resolver->resolve($page)?->id())->toBe('loc-1');
});

it('stops at the first (closest) hub ancestor', function () {
    $near = findableEntry('clinical_expertise', 'clinical_expertise', 'near');
    $far = findableEntry('clinical_expertise', 'clinical_expertise', 'far');

    $find = Entry::partialMock();
    $find->shouldReceive('find')->with('near')->andReturn($near);
    $find->shouldReceive('find')->with('far')->andReturn($far);

    $resolver = resolverWithAncestors(['near', 'far']);

    /** @var Statamic\Entries\Entry $page */
    $page = $this->page;

    expect($resolver->resolve($page)?->id())->toBe('near');
});

it('returns null when no ancestor is a subsite hub', function () {
    $a = findableEntry('clinical_expertise', 'basic_page', 'a');
    $b = findableEntry('clinical_expertise', 'landing_page', 'b');

    $find = Entry::partialMock();
    $find->shouldReceive('find')->with('a')->andReturn($a);
    $find->shouldReceive('find')->with('b')->andReturn($b);

    $resolver = resolverWithAncestors(['a', 'b']);

    /** @var Statamic\Entries\Entry $page */
    $page = $this->page;

    expect($resolver->resolve($page))->toBeNull()
        ->and($resolver->resolveIdList($page))->toBeNull();
});

it('skips a stale ancestor id whose entry no longer resolves', function () {
    $hub = findableEntry('clinical_expertise', 'clinical_expertise', 'aoe-1');

    $find = Entry::partialMock();
    $find->shouldReceive('find')->with('stale')->andReturnNull();
    $find->shouldReceive('find')->with('aoe-1')->andReturn($hub);

    $resolver = resolverWithAncestors(['stale', 'aoe-1']);

    /** @var Statamic\Entries\Entry $page */
    $page = $this->page;

    expect($resolver->resolve($page)?->id())->toBe('aoe-1');
});

function resolverExposingAncestors(): AncestorExposingSubsiteHubResolver
{
    return new AncestorExposingSubsiteHubResolver;
}

it('returns null for a page that has no id yet', function () {
    /** @var Statamic\Entries\Entry $page */
    $page = Entry::make();
    /** @var Statamic\Entries\Entry $page */
    $page = $page->collection('pages');

    expect((new SubsiteHubResolver)->resolve($page))->toBeNull()
        ->and((new SubsiteHubResolver)->resolveIdList($page))->toBeNull();
});

it('treats a page that is not saved yet as having no ancestors', function () {
    $resolver = resolverExposingAncestors();

    expect($resolver->ancestorsOf('not-saved-yet'))->toBe([])
        ->and($resolver->resolve(findableEntry('pages', 'page', 'not-saved-yet')))->toBeNull();
});

it('walks the saved structure tree the way the shared helper does', function () {
    // Any saved page with at least two ancestors: the walk must match the shared helper.
    if (! ContentSubmodule::isInitialized()) {
        ContentSubmodule::reportMissing('a nested page for the subsite hub walk');
    }

    $nested = null;
    /** @var iterable<Statamic\Entries\Entry> $candidates */
    $candidates = Entry::whereCollection('pages');
    foreach ($candidates as $candidate) {
        $id = $candidate->id();
        if (is_string($id) && count(ContentAncestorIds::forEntry($id)) >= 2) {
            $nested = $id;
            break;
        }
    }

    expect($nested)->not->toBeNull();

    /** @var string $nested */
    $ancestors = resolverExposingAncestors()->ancestorsOf($nested);

    expect($ancestors)->toBe(ContentAncestorIds::forEntry($nested))
        ->and(count($ancestors))->toBeGreaterThanOrEqual(2);
});
