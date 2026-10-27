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
