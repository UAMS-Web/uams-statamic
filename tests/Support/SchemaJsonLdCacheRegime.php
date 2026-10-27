<?php

declare(strict_types=1);

use App\Listeners\InvalidateSchemaJsonLdCache;
use App\Services\SchemaTemplates\SchemaJsonLdOutputCache;

/**
 * Shared JSON-LD cache regime for the `SchemaJsonLd*` suites (UAMS-Web/uams-statamic-archive#1842).
 *
 * Every one of those suites needs the **page output cache** off and, until this
 * helper existed, reached for `json_ld_cache.enabled` to get it. That flag is the
 * master switch, so it took the cross-request **fragment** cache down with it —
 * silently, because nothing in a suite mentions fragments. The result was that
 * 19 of the 20 suites profiled a regime production does not run, and UAMS-Web/uams-statamic-archive#1797,
 * UAMS-Web/uams-statamic-archive#1800 and UAMS-Web/uams-statamic-archive#1801 each measured the schema compose path with the single largest
 * production optimization switched off. Measured on one `clinical_locations`
 * node: fragments off, a second build in the same process costs 12.5s; fragments
 * on, 1.6s — with byte-identical JSON-LD either way.
 *
 * The two switches are set independently here, so disabling page output can never
 * again disable fragments by accident.
 *
 * These are top-level functions shared across suites, so they live here rather
 * than being duplicated — two Pest files declaring the same top-level function is
 * a fatal redeclare, not a shadow. Loaded via `require_once` from `tests/Pest.php`,
 * matching `SchemaLocationFixtures.php` and the other shared helper files.
 */

/**
 * Put the current test on the production JSON-LD fragment-cache regime: page
 * output cache off, fragment cache on, and a fragment namespace this test alone
 * can reach.
 *
 * Call from a suite's `beforeEach`.
 *
 * **Why page output must stay off.** These suites build nodes for in-memory
 * fixture entries that carry no persisted id, and
 * `SchemaJsonLdOutputCache::cacheKey()` falls back to `spl_object_hash()` for an
 * entity whose `id()` is empty. PHP reuses an object hash once the original is
 * freed, so two unrelated fixtures in one process can collide on a single output
 * key and one test can be served another's payload. The fragment key has no such
 * fallback — `SchemaJsonLdFragmentCache::keyFor()` returns null unless the target
 * has a real string id — so it does not carry that hazard.
 *
 * **Why the bust rotation is here and not left to the suites.** Fragment keys are
 * `(bust generation, site, template id, target)`. Nothing in that tuple changes
 * when a test mutates the content a fragment was built from, so without rotation
 * a fragment written by one test is served to the next. Saved mutations already
 * rotate the generation — {@see InvalidateSchemaJsonLdCache} bumps
 * on every watched entry, global-set and term save — but **in-memory** mutations
 * do not, and the suites do exactly that: `SchemaJsonLdLocationIdentifiersContactTest`
 * mutates `uams_fad_settings` through `inDefaultSite()` and restores it without
 * ever saving, so a fragment built inside that window would outlive the mutation
 * with nothing to invalidate it. Rotating per test closes that by construction
 * rather than by auditing which test mutates what, which is the standard UAMS-Web/uams-statamic-archive#1842
 * asks for. It is deliberately not `Cache::flush()`: the Stache index shares this
 * cache store, so a flush would drop the per-worker snapshot the suite was seeded
 * with and force a re-warm.
 *
 * **Cross-test reuse is unsafe, not merely forfeited.** Letting a whole class share
 * one generation looked like a suite-time lever for UAMS-Web/uams-statamic-archive#1537, so it was measured
 * rather than assumed. Reusing a fragment built during an *earlier compose* shifts
 * template-instance `@id` suffix allocation in the graph that reuses it: on the
 * fixture person page, a cold compose emits `hasOccupation` as a full `Occupation`
 * node at `…-1` (41 full nodes), while a compose reusing the earlier test's
 * fragment emits a compact reference to `…-0` (37 full nodes). Both graphs are
 * internally consistent — every compact reference still resolves to a full node,
 * with zero dangling either way, which is the invariant
 * `SchemaJsonLdPersonEntriesTest` pins — so this is not a production defect. But it
 * makes the emitted shape depend on **test order**, which is a flake waiting to
 * happen and would silently break any assertion about which node carries the body.
 * Rotating per test removes the order dependence along with the staleness.
 */
function useProductionJsonLdFragmentCacheRegime(): void
{
    // Master switch on, matching the shipped production default.
    config()->set('schema_templates.json_ld_cache.enabled', true);

    // Page output cache off — the shared-key hazard these suites actually guard.
    config()->set('schema_templates.json_ld_cache.output_enabled', false);

    // Fragment cache on: production's default, now stated rather than inherited.
    config()->set('schema_templates.json_ld_cache.fragments_enabled', true);

    // Give this test its own fragment generation so no earlier test's fragment
    // is reachable, whatever that test mutated.
    app(SchemaJsonLdOutputCache::class)->bump();
}
