<?php

declare(strict_types=1);

use App\Support\Navigation\SubsectionResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SubsectionResolver::class);

/**
 * A blueprint mock reporting the given handle, for the blueprint-rule branch.
 */
function mockBlueprintHandle(string $handle): Blueprint
{
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('handle')->andReturn($handle);

    return $blueprint;
}

/**
 * Coverage for the subsection-root resolution (UAMS-Web/uams-statamic-archive#52).
 *
 * The self/ancestor walk runs against the real `pages` structure in the
 * `content/` submodule (self-skips without it). `about` is flagged as a
 * subsection root; its descendants resolve to it, and pages outside any
 * subsection resolve to null. The not-found branch is covered with a mock so it
 * needs no content.
 *
 * @command  php artisan test --compact tests/Feature/Support/Navigation/SubsectionResolverTest.php
 * @command  vendor/bin/pest tests/Feature/Support/Navigation/SubsectionResolverTest.php --mutate --path=app/Support/Navigation --class="App\Support\Navigation\SubsectionResolver"
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

/**
 * The `about` page entry id — flagged "starts a new subsection".
 */
const SUBSECTION_ABOUT_ID = 'f5f1b2f2-d543-4d6b-a837-12f272931c01';

/**
 * Skip unless the `pages` collection is present (content submodule).
 */
function subsectionRequirePages(): void
{
    if (! Collection::find('pages')) {
        ContentSubmodule::reportMissing('pages collection');
    }
}

it('resolves a page that itself starts a subsection to itself', function (): void {
    subsectionRequirePages();

    $root = (new SubsectionResolver)->resolveRoot(SUBSECTION_ABOUT_ID);

    expect($root?->id())->toBe(SUBSECTION_ABOUT_ID);
});

it('resolves a direct child to its subsection-root ancestor', function (): void {
    subsectionRequirePages();

    // d40fd687 is a direct child of `about` and is not itself a subsection.
    $root = (new SubsectionResolver)->resolveRoot('d40fd687-233e-436e-a1b1-c3713a1228fd');

    expect($root?->id())->toBe(SUBSECTION_ABOUT_ID);
});

it('resolves a deep descendant to the nearest subsection-root ancestor', function (): void {
    subsectionRequirePages();

    // 964c919e sits several levels below `about` with no nearer subsection.
    $root = (new SubsectionResolver)->resolveRoot('964c919e-6581-4be4-a95f-0fcde59ece16');

    expect($root?->id())->toBe(SUBSECTION_ABOUT_ID);
});

it('resolves a page outside any subsection to null', function (): void {
    subsectionRequirePages();

    expect((new SubsectionResolver)->resolveRoot('home'))->toBeNull();
});

it('returns null when the entry cannot be found', function (): void {
    EntryFacade::shouldReceive('find')->with('missing')->andReturnNull();

    expect((new SubsectionResolver)->resolveRoot('missing'))->toBeNull();
});

it('skips a stale ancestor id that no longer resolves to an entry', function (): void {
    // current (not a subsection) → ancestor 'stale' (deleted) → ancestor 'root' (subsection).
    // Both are ordinary pages (non-AoE blueprint), so the marker decides.
    $current = Mockery::mock(Entry::class);
    $current->shouldReceive('blueprint')->andReturn(mockBlueprintHandle('page'));
    $current->shouldReceive('value')->with('subsection')->andReturn(false);

    $root = Mockery::mock(Entry::class);
    $root->shouldReceive('blueprint')->andReturn(mockBlueprintHandle('page'));
    $root->shouldReceive('value')->with('subsection')->andReturn(true);

    EntryFacade::shouldReceive('find')->with('current')->andReturn($current);
    EntryFacade::shouldReceive('find')->with('stale')->andReturnNull();
    EntryFacade::shouldReceive('find')->with('root')->andReturn($root);

    // Inject an ancestor chain whose first id no longer resolves.
    $resolver = new class extends SubsectionResolver
    {
        protected function ancestorIds(int|string $entryId): array
        {
            return ['stale', 'root'];
        }
    };

    expect($resolver->resolveRoot('current'))->toBe($root);
});

it('treats an Area-of-Expertise entry as its own subsection root without a marker (#1057)', function (): void {
    // An AoE entry starts a subsection by blueprint — no `subsection` marker set.
    $aoe = Mockery::mock(Entry::class);
    $aoe->shouldReceive('blueprint')->andReturn(mockBlueprintHandle('clinical_expertise'));

    EntryFacade::shouldReceive('find')->with('cardiology')->andReturn($aoe);

    expect((new SubsectionResolver)->resolveRoot('cardiology'))->toBe($aoe);
});

it('leaves an ordinary page without the marker outside any subsection (site-wide guard)', function (): void {
    // Making AoE entries auto-roots must NOT turn other collections' pages into
    // subsections: a non-AoE page with no marker and no subsection ancestor stays null.
    $page = Mockery::mock(Entry::class);
    $page->shouldReceive('blueprint')->andReturn(mockBlueprintHandle('page'));
    $page->shouldReceive('value')->with('subsection')->andReturn(false);

    EntryFacade::shouldReceive('find')->with('a-plain-page')->andReturn($page);

    $resolver = new class extends SubsectionResolver
    {
        protected function ancestorIds(int|string $entryId): array
        {
            return [];
        }
    };

    expect($resolver->resolveRoot('a-plain-page'))->toBeNull();
});

/**
 * A page entry mock with the given blueprint handle and marker value.
 */
function subsectionPageMock(string $blueprint, mixed $marker): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('blueprint')->andReturn(mockBlueprintHandle($blueprint));
    $entry->shouldReceive('value')->with('subsection')->andReturn($marker);

    return $entry;
}

/**
 * A resolver whose ancestor walk returns the given ids, immediate parent first.
 *
 * @param  list<int|string>  $ids
 */
function subsectionResolverWithAncestors(array $ids): SubsectionResolver
{
    return new class($ids) extends SubsectionResolver
    {
        /**
         * @param  list<int|string>  $ids
         */
        public function __construct(private readonly array $ids) {}

        protected function ancestorIds(int|string $entryId): array
        {
            return $this->ids;
        }
    };
}

it('prefers the page itself over a subsection-root ancestor', function (): void {
    $self = subsectionPageMock('page', true);
    $parent = subsectionPageMock('page', true);

    EntryFacade::shouldReceive('find')->with('self')->andReturn($self);
    EntryFacade::shouldReceive('find')->with('parent')->andReturn($parent);

    expect(subsectionResolverWithAncestors(['parent'])->resolveRoot('self'))->toBe($self);
});

it('returns the nearest subsection-root ancestor when several ancestors start one', function (): void {
    $self = subsectionPageMock('page', false);
    $parent = subsectionPageMock('page', false);
    $nearRoot = subsectionPageMock('page', true);
    $farRoot = subsectionPageMock('page', true);

    EntryFacade::shouldReceive('find')->with('self')->andReturn($self);
    EntryFacade::shouldReceive('find')->with('parent')->andReturn($parent);
    EntryFacade::shouldReceive('find')->with('near')->andReturn($nearRoot);
    EntryFacade::shouldReceive('find')->with('far')->andReturn($farRoot);

    expect(subsectionResolverWithAncestors(['parent', 'near', 'far'])->resolveRoot('self'))->toBe($nearRoot);
});

it('returns null when no ancestor starts a subsection', function (): void {
    EntryFacade::shouldReceive('find')->with('self')->andReturn(subsectionPageMock('page', false));
    EntryFacade::shouldReceive('find')->with('parent')->andReturn(subsectionPageMock('page', null));
    EntryFacade::shouldReceive('find')->with('stale')->andReturnNull();

    expect(subsectionResolverWithAncestors(['parent', 'stale'])->resolveRoot('self'))->toBeNull();
});

it('starts a subsection only for a marker that is exactly true', function (mixed $marker): void {
    EntryFacade::shouldReceive('find')->with('self')->andReturn(subsectionPageMock('page', $marker));

    expect(subsectionResolverWithAncestors([])->resolveRoot('self'))->toBeNull();
})->with([
    'string true' => ['true'],
    'integer one' => [1],
    'string one' => ['1'],
    'non-empty array' => [[true]],
]);

it('treats an Area-of-Expertise ancestor as the subsection root of its descendants', function (): void {
    $aoe = subsectionPageMock('clinical_expertise', null);

    EntryFacade::shouldReceive('find')->with('child')->andReturn(subsectionPageMock('page', false));
    EntryFacade::shouldReceive('find')->with('aoe')->andReturn($aoe);

    expect(subsectionResolverWithAncestors(['aoe'])->resolveRoot('child'))->toBe($aoe);
});

it('does not auto-start a subsection for a blueprint handle that only resembles the Area-of-Expertise one', function (string $handle): void {
    EntryFacade::shouldReceive('find')->with('self')->andReturn(subsectionPageMock($handle, false));

    expect(subsectionResolverWithAncestors([])->resolveRoot('self'))->toBeNull();
})->with([
    'prefix' => ['clinical'],
    'upper case' => ['Clinical_Expertise'],
    'suffixed' => ['clinical_expertise_page'],
]);
