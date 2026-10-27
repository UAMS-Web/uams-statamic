<?php

declare(strict_types=1);

use Statamic\StaticCaching\Middleware\Cache as StatamicStaticCacheMiddleware;
use Tests\TestCase;

use function Pest\Laravel\get;
use function Pest\Laravel\withoutMiddleware;
use function Pest\Laravel\withoutVite;

uses(TestCase::class);

/*
 * No mutable PHP surface: this is a route/template smoke test for `/` with no
 * backing App class under test, so there is no Mutation target.
 */

it('returns a successful response for the application root', function () {
    // Avoid blocking on the static-cache lock when `.env` enables a strategy
    withoutMiddleware(StatamicStaticCacheMiddleware::class);

    // The home view resolves Vite assets through the project's manifest at
    // `public/build/manifest.json`. That directory is gitignored and only
    // populated by `npm run build`, which CI runs in a separate parallel
    // job from the PHPUnit job. Without `withoutVite()`, asset lookups
    // throw inside the view and the response is 500 instead of 200.
    withoutVite();

    $response = get('/');

    $response->assertStatus(200);
});

// Formerly `serial`, ungated by UAMS-Web/uams-statamic-archive#1700. This `GET /` smoke intermittently
// returned 503 under `pest --parallel`. UAMS-Web/uams-statamic-archive#1689 pinned the cause — the STACHE
// warming lock, a flock over the single, non-tokenized
// `storage/statamic/stache-locks` held for the duration of a warm, not the
// static-cache lock this file was right to rule out — and disabled that lock
// for the suite.
//
// Removing the gate needed its own evidence, since a gate is retired by showing
// the test is stable in the parallel pass, not by arguing the cause is gone.
// UAMS-Web/uams-statamic-archive#1700 cleared two consecutive parallel runs on a checkout with no Stache
// snapshot (the state where a worker falls back to a full warm, which is when
// the race fired); UAMS-Web/uams-statamic-archive#1715 carries the remaining eight of the ten-run bar. Until
// it closes, this is ungated on partial evidence — if the 503 recurs, restore
// the gate with the observed reason rather than retrying until green.
//
// It is also exercised by the CP/browser smoke suite. See the
// parallel-fragility convention in tests/README.md.
