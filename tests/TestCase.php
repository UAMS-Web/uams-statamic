<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Asset;
use App\Services\SchemaTemplates\SchemaTemplateEmitContextRuntimeStack;
use App\Support\ContentMirror;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Statamic\View\Antlers\Language\Runtime\GlobalRuntimeState;
use Statamic\View\State\StateManager;
use Tests\Support\BrowserWriteIsolation;
use Throwable;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetAntlersRuntimeState();
        $this->prepareAssetMetadataDatabaseSchema();
        $this->suppressAssetMetadataYamlSync();
        $this->stubViteAssetResolution();
    }

    /**
     * Release the browser suite's write isolation (UAMS-Web/uams-statamic-archive#1920).
     *
     * A no-op — one static comparison — for every test that never armed it, which
     * is why a browser-only concern can sit on the shared base class without
     * costing the rest of the suite anything.
     *
     * It lives HERE rather than beside its `begin()` in `tests/Pest.php` because
     * Pest chains a `uses()`-level hook AHEAD of the file-level one in BOTH
     * directions — `ChainableClosure::bound()` calls its first argument first, and
     * `Testable::tearDown()` passes the `uses()` closure as that argument. A
     * `uses(...)->afterEach()` teardown would therefore restore the real
     * blueprint/collection/user directories and delete the throwaway root BEFORE
     * each smoke's own `afterEach` ran, aiming every one of those fixture deletes
     * at tracked source. Harmless only while no smoke handle collides with a real
     * one, which is not a property worth resting on.
     *
     * `Testable::tearDown()` calls `parent::tearDown()` inside the `finally` that
     * follows the whole afterEach chain, so this override is the one hook that
     * genuinely runs last. `Tests\Browser\BrowserWriteIsolationOrderingTest` pins
     * that ordering behaviorally rather than trusting this comment.
     */
    protected function tearDown(): void
    {
        BrowserWriteIsolation::end();

        parent::tearDown();
    }

    /**
     * Report a torn content mirror as the cause when a test fails on a YAML parse error (UAMS-Web/uams-statamic-archive#2762).
     *
     * A mirror file rewritten mid-read surfaces as a Statamic `ParseException` in whatever
     * suite booted it, naming only the YAML file, so it reads as a defect in the branch. This
     * re-throws with {@see ContentMirror::parseFailureDiagnosis()}'s explanation, keeping the
     * original as the previous exception. Any other failure passes through unchanged.
     */
    protected function onNotSuccessfulTest(Throwable $t): never
    {
        $diagnosis = ContentMirror::parseFailureDiagnosis($t);

        if ($diagnosis !== null) {
            parent::onNotSuccessfulTest(new RuntimeException($diagnosis, 0, $t));
        }

        parent::onNotSuccessfulTest($t);
    }

    /**
     * Resolve Vite assets to stubs for the duration of a test, so a missing build
     * manifest cannot stand in for the exception a route actually raised (UAMS-Web/uams-statamic-archive#2160).
     *
     * `public/build/manifest.json` is gitignored and written only by `npm run build`,
     * which CI runs in the `build` job — the `pest` job never has it. Any test that
     * renders a view resolving assets therefore throws `ViteManifestNotFoundException`
     * in CI while passing on a developer machine that has built once.
     *
     * The masking is the part worth fixing rather than the 500 itself. When a route
     * raises its own HTTP exception, `RendersHttpExceptions` renders an error page,
     * that page resolves assets, and the manifest exception is thrown DURING the
     * render — so the manifest exception is the only one the failure output shows and
     * the original is lost. UAMS-Web/uams-statamic-archive#2160 was filed on two `PostsFeedRouteTest` cases for
     * exactly that reason; once unmasked they reported a 404, a route cache compiled
     * before the Stache seed and so missing every feed route (UAMS-Web/uams-statamic-archive#3106).
     *
     * The seam already existed and the default was the bug, which is the same shape
     * as {@see self::suppressAssetMetadataYamlSync()} directly below. Sixteen test
     * files had each remembered `withoutVite()` for themselves; the ones that did not
     * got a masked exception rather than a missing assertion. Arming it here inverts
     * that: a test cannot now be told the wrong cause because its author did not know
     * to opt in.
     *
     * A test that genuinely exercises asset resolution opts back out for its own
     * duration by swapping the container binding, as
     * `NewsReleasePdfGeneratorTest` already does. Nothing in the suite asserts a
     * non-empty Vite URL.
     *
     * `tests/Browser` is EXEMPT, and an earlier revision of this docblock claimed
     * the opposite — that the browser suite runs "against a served app, where this
     * binding has no reach." It has reach. `tests/Pest.php` binds `Tests\TestCase`
     * into that suite with `uses(TestCase::class)->in('Browser')`, so this `setUp()`
     * runs there, and the browser plugin serves the app IN-PROCESS rather than out
     * of a separate server. Stubbing the resolution therefore reaches the page under
     * test: the Control Panel injects its Vue bundle through `@vite`, so the bundle
     * never loads, `Statamic` is undefined, and every CP smoke times out waiting for
     * an interface that will never render. Measured back to back in one worktree on
     * identical assets: `CpSmokeTest` passes in 3.83s without the arming and fails at
     * 33.58s with it.
     *
     * The exemption is read from the test's own class rather than from a flag,
     * because `setUp()` runs BEFORE Pest's `beforeEach` chain and nothing a
     * `uses()`-level hook sets can be read here in time. Restoring afterwards with
     * `withVite()` would be the alternative and is worse: `withoutVite()` calls
     * `ViteFacade::clearResolvedInstance()` and `withVite()` does not, so the facade
     * can keep serving the stub it cached. Never arming is the only form with
     * nothing to undo.
     */
    private function stubViteAssetResolution(): void
    {
        if ($this->runsInBrowserSuite()) {
            return;
        }

        $this->withoutVite();
    }

    /**
     * Whether the running test belongs to `tests/Browser`.
     *
     * Read from the generated class NAME, not from its file. Pest builds each test
     * class with `eval()`, so Reflection reports the factory rather than the test —
     * measured, because the obvious path check silently never matches:
     *
     *   class      P\Tests\Browser\CpSmokeTest
     *   getFileName()  '…/pest/src/Factories/TestCaseFactory.php(170) : eval()\'d code'
     *
     * The namespace does carry the suite, and it is the more portable of the two in
     * any case: a namespace separator is a backslash on every platform, where a path
     * test would have had to reason about `DIRECTORY_SEPARATOR`. The `P\` prefix is
     * Pest's own, so the segment rather than the whole prefix is what is matched.
     *
     * This is needed because `tests/Browser` is deliberately not a registered
     * testsuite (`tests/Pest.php`), so there is no suite name to ask for.
     */
    private function runsInBrowserSuite(): bool
    {
        return \str_contains(static::class, 'Tests\\Browser\\');
    }

    /**
     * Suppress `App\Models\Asset`'s YAML dual-write for the duration of a test,
     * so a probe asset cannot overwrite a real one's committed metadata (UAMS-Web/uams-statamic-archive#1919).
     *
     * The model writes every save to `content/models/assets/{id}.yml` by design,
     * for version control. Under test the metadata database is `:memory:`
     * (phpunit.xml `ASSET_METADATA_DATABASE`), so its autoincrement restarts at
     * `1` in every process and the FIRST asset any test creates lands on the real
     * `1.yml` — replacing a stored image's alt text, caption and photographer
     * credit with a fixture's. Anyone who commits the `content/` submodule after
     * a test run ships that.
     *
     * The seam already existed; the default was the bug. `$skipYamlSync` was
     * opt-out, so suppression depended on every asset-creating test remembering
     * a toggle, and the ones that forgot destroyed content silently. Arming it
     * here inverts the failure direction: a test that forgets now writes
     * nothing, which is a missing assertion rather than a corrupted file.
     *
     * A test that genuinely exercises the dual-write opts back in for its own
     * duration — set `Asset::$skipYamlSync = false` and repoint `base_path()` at
     * a temp tree, as `AssetMetadataYamlSyncIsolationTest` does. This runs in
     * `setUp()` rather than once per process precisely so that opt-in is
     * self-healing: whatever a test leaves the flag set to, the next test is
     * re-armed.
     */
    private function suppressAssetMetadataYamlSync(): void
    {
        Asset::$skipYamlSync = true;
    }

    /**
     * Reset Statamic's process-global Antlers runtime state between tests.
     *
     * `Statamic\View\Antlers\Language\Runtime\GlobalRuntimeState` is entirely
     * `public static`, and Laravel's per-test application refresh rebuilds the
     * container but does not touch statics — so a runtime left "warm" by one
     * test's HTTP page render bleeds into the next test's `Antlers::parse()`.
     * Production resets it per response via `StateManager::resetState()` (the
     * `ClearState` listener on `RequestHandled`/`ResponseCreated`); this app's
     * `TestCase` extends Laravel's base `TestCase` directly, so it inherits no
     * such reset. Calling the same `resetState()` here gives every test the
     * production per-response reset.
     *
     * `resetState()` clears the render-node cache, the traced tag assignments,
     * the `traceTagAssignments` flag, and the `ResetsState` tag counters (e.g.
     * `{{ rotate }}` / `{{ increment }}`) — but NOT `$activeTracerCount`, the
     * assignment-tracer depth counter. A completed front-end HTTP render leaks
     * that counter (it never returns to zero), and a non-zero count keeps the
     * assignment tracer "on" across parses, so a `{{ $filter_category = param }}`
     * assignment in one parse bleeds into a later omitted-parameter parse (the
     * UAMS-Web/uams-statamic-archive#1567 leak that survives `resetState()`). Zeroing it is the piece
     * `resetState()` misses.
     *
     * The emit context stack is the app's own static of the same kind. Production
     * pops it in a `finally`, so only a test can leave a context behind, and the
     * schema tags prefer that context to the one in their own Antlers scope
     * (UAMS-Web/uams-statamic#442).
     */
    private function resetAntlersRuntimeState(): void
    {
        StateManager::resetState();
        GlobalRuntimeState::$activeTracerCount = 0;

        while (SchemaTemplateEmitContextRuntimeStack::peek() !== null) {
            SchemaTemplateEmitContextRuntimeStack::pop();
        }
    }

    /**
     * Ensure `assets_meta` exists on the `asset_metadata` connection so HTTP
     * tests that render pages augmenting Eloquent assets do not fail on SQLite
     * `:memory:` (see phpunit.xml `ASSET_METADATA_DATABASE`).
     *
     * Schema mirrors
     * `database/migrations/2026_03_18_100000_create_asset_metadata_table.php`;
     * inline DDL avoids running the migrator during PHPUnit (faster, no locks).
     *
     * Do not cache a "already migrated" flag across tests: Laravel may refresh
     * the in-memory database between test classes while the PHP process stays
     * alive, which would leave a fresh DB without this table.
     */
    private function prepareAssetMetadataDatabaseSchema(): void
    {
        $schema = Schema::connection('asset_metadata');

        if ($schema->hasTable('assets_meta')) {
            return;
        }

        $schema->create(
            'assets_meta',
            function (Blueprint $table) {
                $table->id();
                $table->string('container')->index();
                $table->string('folder')->index();
                $table->string('basename')->index();
                $table->string('filename')->index();
                $table->char('extension', 10)->index();
                $table->string('path')->index();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->unique(
                    [
                        'container',
                        'folder',
                        'basename',
                    ]
                );
            }
        );
    }
}
