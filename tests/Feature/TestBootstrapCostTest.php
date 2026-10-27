<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * Guards the once-per-process test-bootstrap optimization (UAMS-Web/uams-statamic-archive#1121, UAMS-Web/uams-statamic-archive#1581).
 *
 * Populating the Statamic stache is the dominant test-bootstrap cost and is
 * gated by CreatesApplication to happen once per PHP process — either a snapshot
 * seed (when `test:seed-stache` has run, the UAMS-Web/uams-statamic-archive#1581 default) or a full
 * `statamic:stache:warm` fallback. This guards that gate against the specific
 * regression it exists to catch — a per-*method* population (e.g. an
 * instance-scoped gate flag rather than a static one). Two assertions bound it
 * from both sides: the process-wide population count (warm + seed) must be
 * exactly 1 (a per-method regression makes it climb once more than one method
 * has run), and booting a *fresh* test-case instance — which stands in for the
 * next test method — must not re-populate. Booting `$this` alone would miss a
 * per-instance regression (this instance is already populated), so the
 * fresh-instance probe is what makes the guard deterministic across orderings.
 * The per-method framework boot (~0.8s) is intentionally not reused — see the
 * CreatesApplication docblock for why.
 */
it('populates the Statamic stache once per process, not once per test method', function (): void {
    // Process-wide invariant: exactly one population (seed or warm), no matter
    // how many methods have already run in this process. A per-method regression
    // makes this climb above 1 in every ordering except when this test happens
    // to run first — the probe below covers that residual case.
    expect(TestCase::stacheWarmCount() + TestCase::stacheSeedCount())->toBe(1);

    // A fresh test-case instance stands in for the next test method: its
    // bootstrap must still see the process-wide gate and NOT re-populate. Probing
    // a NEW instance (whose per-instance flag would start false) is what catches
    // a per-method regression that booting the already-populated $this cannot.
    $populatedBefore = TestCase::stacheWarmCount() + TestCase::stacheSeedCount();
    $probe = new (get_class($this))('stache_gate_probe');
    $probe->createApplication();

    expect(TestCase::stacheWarmCount() + TestCase::stacheSeedCount())->toBe($populatedBefore);

    // When a usable snapshot was present at boot (the UAMS-Web/uams-statamic-archive#1581 CI/local-ci default),
    // the worker must have seeded from it and performed zero in-process warms —
    // the win this guards. Keys off the boot-time record, not the live snapshot
    // dir: a sibling test may delete the dir, and its emptiness semantics must
    // match the seed logic. On a subset run with no snapshot the fallback warm is
    // expected, so this half only asserts when a snapshot was actually consumed.
    if (TestCase::stacheSnapshotWasPresentAtBoot()) {
        if (TestCase::stacheSnapshotCopyFailures() === 0) {
            // The healthy path, and the UAMS-Web/uams-statamic-archive#1581 win this guards: copied cleanly,
            // zero in-process warms.
            expect(TestCase::stacheSeedCount())->toBe(1);
            expect(TestCase::stacheWarmCount())->toBe(0);
        } elseif (TestCase::stacheSeedCount() === 1) {
            // A retry recovered it. The copy is flaky under load, but the worker
            // is correctly seeded — so the failures must be BELOW the cap, since
            // exhausting the cap is what forces the warm.
            expect(TestCase::stacheSnapshotCopyFailures())
                ->toBeLessThan(TestCase::stacheSnapshotCopyAttempts());
            expect(TestCase::stacheWarmCount())->toBe(0);
        } else {
            // Every attempt failed, so the snapshot could not be trusted and the
            // process warmed instead (UAMS-Web/uams-statamic-archive#1740) — running on the partial cache is
            // what used to starve the worker. Pin the counter to the cap: asserting
            // only "seeded or warmed" would restate the invariant checked above and
            // could never fail, leaving the counter effectively unasserted.
            expect(TestCase::stacheSnapshotCopyFailures())
                ->toBe(TestCase::stacheSnapshotCopyAttempts());
            expect(TestCase::stacheWarmCount())->toBe(1);
        }
    }
});

/**
 * Guards the second once-per-process bootstrap lever (UAMS-Web/uams-statamic-archive#1582): the ~280ms
 * re-hydration of the 255-site `resources/sites.yaml`, cached per process by
 * CachedSites. It mirrors the stache-warm guard exactly — the hydration count is
 * pinned at 1 process-wide, and booting a *fresh* test-case instance (standing in
 * for the next test method) must not re-hydrate. A per-method regression (e.g. a
 * missing rebind, or an instance-scoped cache flag instead of the process static)
 * makes the count climb; the fresh-instance probe is what makes the guard
 * deterministic across orderings, since booting the already-hydrated $this alone
 * would miss it.
 */
it('hydrates the multisite sites config once per process, not once per test method', function (): void {
    // Process-wide invariant: exactly one hydration, however many methods have
    // already run in this process.
    expect(TestCase::sitesHydrationCount())->toBe(1);

    // A fresh test-case instance stands in for the next test method: its
    // bootstrap must see the process-wide cache and NOT re-hydrate.
    $hydrationsBefore = TestCase::sitesHydrationCount();
    $probe = new (get_class($this))('sites_hydration_gate_probe');
    $probe->createApplication();

    expect(TestCase::sitesHydrationCount())->toBe($hydrationsBefore);
});
