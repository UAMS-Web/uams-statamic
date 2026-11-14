<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Statamic\Facades\Antlers;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\View\Antlers\Language\Runtime\GlobalRuntimeState;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\Support\EntriesAndTermsIsolatedTestCase;
use Tests\Support\IsolatesCollectionTrees;
use Tests\TestCase;

uses(EntriesAndTermsIsolatedTestCase::class, RequiresContentSubmodule::class, IsolatesCollectionTrees::class);

const ANTLERS_ISOLATION_PREFIX = 'zz-antlers-isolation-';

/**
 * Regression coverage for the process-global Antlers runtime bleed (UAMS-Web/uams-statamic-archive#1567).
 *
 * Statamic's Antlers runtime keeps `public static` state on
 * `Statamic\View\Antlers\Language\Runtime\GlobalRuntimeState` that Laravel's
 * per-test application refresh does not clear. A completed front-end HTTP render
 * leaks `GlobalRuntimeState::$activeTracerCount` (the assignment-tracer depth
 * counter never returns to zero), and that leak persists into the next test in
 * the same process. `StateManager::resetState()` — production's per-response
 * reset — clears the traced assignments and the `traceTagAssignments` flag but
 * NOT `activeTracerCount`, so a leaked count keeps the assignment tracer "on":
 * a `{{ $x = param }}` assignment in one `Antlers::parse()` then bleeds into a
 * later omitted-parameter parse. `Tests\TestCase::setUp()` now runs that same
 * `resetState()` and additionally zeroes `activeTracerCount`, so every test
 * starts with a cold runtime.
 *
 * The two tests below run in one process in definition order, as a band worker
 * runs a file's cases,
 * so the first genuinely warms the runtime and the second proves the reset kept
 * that warmth from bleeding across the test boundary. Mutation-verify by running
 * this whole file: the second test's guard only goes red once the first has
 * warmed the tracer in the same process, so `--filter`ing the second test alone
 * would pass vacuously even with the reset removed.
 *
 * @command  php artisan test --compact tests/Feature/AntlersRuntimeStateIsolationTest.php
 */
it('warms the process-global Antlers runtime with a front-end HTTP render', function (): void {
    $this->requireContentCollection('pages');

    // An end-to-end HTTP render through the full layout leaks the tracer: the main
    // navigation's `*recursive children*` raises `activeTracerCount` and never
    // lowers it. This used to render a `post_tags` term page, but UAMS-Web/uams-statamic-archive#2532 made
    // `post_tags` a structured collection, and no taxonomy has a routed term page
    // any more. The home page needs no fixture and still renders that navigation.
    $this->withoutVite();

    /** @var TestCase $test */
    $test = $this;
    $test->get('/')->assertOk();

    // Guard the regression's own premise: if this render ever stops leaking
    // the tracer, the isolation assertion in the next test becomes vacuous.
    expect(GlobalRuntimeState::$activeTracerCount)->toBeGreaterThan(0);
});

it('does not inherit the prior test\'s warm runtime, so an omitted-parameter render is unfiltered', function (): void {
    // The fix contract: setUp() cleared the counter the previous test leaked.
    // Without the reset this is the value the warming render left (non-zero), and
    // that leaked depth is exactly what re-filters the "unfiltered" render below.
    expect(GlobalRuntimeState::$activeTracerCount)->toBe(0);

    $this->requireContentCollection('posts');
    $this->requireContentCollection('post_categories');

    Site::setCurrent('default');

    // Saving a `post_categories` entry writes its structure tree, so move the trees onto a
    // per-process root for this test only (#557). Not on the base: the first test's premise
    // needs the committed trees, and under tree isolation its tracer count stays 0.
    $this->isolateCollectionTrees();

    try {
        /** @var Statamic\Entries\Entry $cardiology */
        $cardiology = Entry::make();
        /** @var Statamic\Entries\Entry $cardiology */
        $cardiology = $cardiology->collection('post_categories');
        /** @var Statamic\Entries\Entry $cardiology */
        $cardiology = $cardiology->locale('default');
        /** @var Statamic\Entries\Entry $cardiology */
        $cardiology = $cardiology->slug(ANTLERS_ISOLATION_PREFIX.'cardiology');
        /** @var Statamic\Entries\Entry $cardiology */
        $cardiology = $cardiology->data(['title' => 'Cardiology']);
        $cardiology->saveQuietly();

        /** @var Statamic\Entries\Entry $inCategory */
        $inCategory = Entry::make();
        /** @var Statamic\Entries\Entry $inCategory */
        $inCategory = $inCategory->collection('posts');
        /** @var Statamic\Entries\Entry $inCategory */
        $inCategory = $inCategory->locale('default');
        /** @var Statamic\Entries\Entry $inCategory */
        $inCategory = $inCategory->published(true);
        /** @var Statamic\Entries\Entry $inCategory */
        $inCategory = $inCategory->date('2026-02-01');
        /** @var Statamic\Entries\Entry $inCategory */
        $inCategory = $inCategory->slug(ANTLERS_ISOLATION_PREFIX.'in-cat');
        /** @var Statamic\Entries\Entry $inCategory */
        $inCategory = $inCategory->data(['title' => 'Isolation Heart Health', 'post_categories' => [$cardiology->id()]]);
        $inCategory->saveQuietly();

        /** @var Statamic\Entries\Entry $uncategorized */
        $uncategorized = Entry::make();
        /** @var Statamic\Entries\Entry $uncategorized */
        $uncategorized = $uncategorized->collection('posts');
        /** @var Statamic\Entries\Entry $uncategorized */
        $uncategorized = $uncategorized->locale('default');
        /** @var Statamic\Entries\Entry $uncategorized */
        $uncategorized = $uncategorized->published(true);
        /** @var Statamic\Entries\Entry $uncategorized */
        $uncategorized = $uncategorized->date('2026-02-02');
        /** @var Statamic\Entries\Entry $uncategorized */
        $uncategorized = $uncategorized->slug(ANTLERS_ISOLATION_PREFIX.'other');
        /** @var Statamic\Entries\Entry $uncategorized */
        $uncategorized = $uncategorized->data(['title' => 'Isolation Unrelated Announcement']);
        $uncategorized->saveQuietly();

        // First a filtered render (which performs the leaking `{{ $filter_category
        // = ... }}` assignment), then the omitted-parameter render. The second must
        // list every post — the uncategorized one included.
        $filtered = (string) Antlers::parse(
            '{{ partial:posts/listing filter_category="'.ANTLERS_ISOLATION_PREFIX.'cardiology" }}',
            []
        );
        expect($filtered)->toContain('Isolation Heart Health');
        expect($filtered)->not->toContain('Isolation Unrelated Announcement');

        $all = (string) Antlers::parse('{{ partial:posts/listing }}', []);
        expect($all)
            ->toContain('Isolation Heart Health')
            ->toContain('Isolation Unrelated Announcement');
    } finally {
        foreach (['posts', 'post_categories'] as $collection) {
            /** @var EntryQueryBuilder $query */
            $query = Entry::query();
            $query->where('collection', $collection);

            /** @var Collection<int, Statamic\Entries\Entry> $entries */
            $entries = $query->get();

            $entries
                ->filter(function (Statamic\Entries\Entry $e): bool {
                    /** @var string|null $slug */
                    $slug = $e->slug();

                    return str_starts_with((string) $slug, ANTLERS_ISOLATION_PREFIX);
                })
                ->each(function (Statamic\Entries\Entry $e): void {
                    $e->deleteQuietly();
                });
        }

        // Put the shared trees back; the tree this test wrote goes with the throwaway root
        $this->restoreCollectionTrees();
    }
});
