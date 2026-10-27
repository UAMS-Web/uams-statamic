<?php

declare(strict_types=1);

use Tests\Support\StacheIsolatedTestCase;
use Tests\Support\ThrowawayRoot;

uses(StacheIsolatedTestCase::class);

// Regression guard for UAMS-Web/uams-statamic-archive#1136 and UAMS-Web/uams-statamic-archive#1957: the throwaway Stache + blueprint roots
// must be scoped to a segment no other run shares — the per-worker TEST_TOKEN
// under --parallel, a process-unique `seq-<pid>` in a serial run — so neither
// ~12 paratest workers nor two concurrent serial runs share one testing tree and
// wipe each other's fixtures mid-test. Asserts the current segment appears in
// both paths; reverting the tokenization drops it and turns this red. Kept
// segment-relative (no hardcoded testing path) so it does not itself trip the
// UAMS-Web/uams-statamic-archive#1149 isolation guard.
//
// The segment comes from {@see ThrowawayRoot} rather than being re-derived here.
// A local copy of the derivation is what made this guard misleading: it read
// `Env::get('TEST_TOKEN', 'seq')`, so it passed under --parallel — where the
// token is set and both sides agree — and reddened only in a serial run, which
// is the case the CI band never exercises.
it('roots the throwaway Stache and blueprint dirs under the per-run segment', function () {
    $token = ThrowawayRoot::segment();

    /** @var string $fakeStacheDirectory */
    $fakeStacheDirectory = $this->fakeStacheDirectory;
    /** @var string $fakeBlueprintDirectory */
    $fakeBlueprintDirectory = $this->fakeBlueprintDirectory;

    $stache = str_replace('\\', '/', $fakeStacheDirectory);
    $blueprints = str_replace('\\', '/', $fakeBlueprintDirectory);

    // `toEndWith` on the blueprint half, not `toContain`: the blueprint path has
    // no trailing segment, so a `toContain` of the OLD literal `seq` passed as a
    // prefix of `seq-<pid>` and would stay green through a future de-tokenization.
    expect($stache)->toContain("/stache/{$token}/dev-null")
        ->and($blueprints)->toEndWith("/blueprints/{$token}");
});
