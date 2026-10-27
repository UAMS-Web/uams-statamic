<?php

declare(strict_types=1);

use Composer\Semver\Intervals;
use Composer\Semver\VersionParser;
use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#1618 — Hold Dart Sass below 3.0 until #30 migrates `resources/scss/` off `@import`.
 *
 * Dart Sass 3.0 removes the `@import` rule that `resources/scss/` still relies on
 * (deprecation-warned but still compiling on the 1.x line), so a `sass` bump to
 * `>=3.0` would turn `npm run build` from a warning into a hard failure. This guard
 * fails the moment `package.json` — or the resolved lockfile — would let `sass`
 * reach 3.0, so the recurring dependency-update chore cannot silently accept the
 * major.
 *
 * Retire this test AND the Step 5 "Held back — `sass` below 3.0" note in
 * `.claude/skills/update-dependencies-ticket/SKILL.md` when #30 lands and the
 * `@import` usage is gone — the two artifacts must be removed together.
 *
 * No mutable App\ PHP surface under test: this asserts package-manifest constraints
 * only, so there is no class to target for mutation testing.
 */

// Both guards judge "does this reach Dart Sass 3.0?" with one mechanism — interval
// intersection with `>=3.0.0` — so they agree on every version, prereleases included
// (`3.0.0-rc.1` intersects `>=3.0.0`; `version_compare` would wrongly rank it below).
it('never lets the package.json sass range reach Dart Sass 3.0 (which removes @import; see #30)', function (): void {
    /** @var array{devDependencies?: array<string, string>, dependencies?: array<string, string>} $manifest */
    $manifest = json_decode((string) file_get_contents(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);

    // `sass` normally lives under devDependencies; fall back to dependencies in case it ever moves.
    $range = $manifest['devDependencies']['sass'] ?? $manifest['dependencies']['sass'] ?? '';
    expect($range)->not->toBe(''); // `sass` must remain a declared dependency

    $parser = new VersionParser;

    try {
        $declared = $parser->parseConstraints($range);
    } catch (UnexpectedValueException $e) {
        // An unparseable range can't be proven safe — surface it for a human rather than passing silently.
        throw new RuntimeException(
            sprintf('Could not parse the package.json `sass` range "%s" — review it by hand; it must stay below Dart Sass 3.0 until #30 migrates off `@import`.', $range),
            previous: $e,
        );
    }

    // `haveIntersections` is exact where fixed-version probes fail: it catches narrow
    // windows (`~3.1.0`, an exact `3.2.5`) and compound ranges (`^2 || ^3`, `*`) too.
    expect(Intervals::haveIntersections($declared, $parser->parseConstraints('>=3.0.0')))->toBeFalse(
        sprintf('package.json pins `sass` to "%s", which permits Dart Sass 3.0+. 3.0 removes the `@import` rule still used across `resources/scss/`, so `npm run build` would hard-fail. Hold `sass` below 3.0 until #30 migrates to `@use`/`@forward`.', $range),
    );
});

it('never lets the resolved package-lock sass version reach Dart Sass 3.0', function (): void {
    /** @var array{packages?: array<string, array{version?: string}>} $lock */
    $lock = json_decode((string) file_get_contents(base_path('package-lock.json')), true, flags: JSON_THROW_ON_ERROR);

    $resolved = $lock['packages']['node_modules/sass']['version'] ?? '';
    expect($resolved)->not->toBe(''); // `sass` must remain resolved in the lockfile

    // Same interval test as the range guard so a 3.0 prerelease (`3.0.0-rc.1`), which
    // already removes `@import`, is rejected rather than ranked below `3.0.0`.
    $parser = new VersionParser;

    expect(Intervals::haveIntersections($parser->parseConstraints($resolved), $parser->parseConstraints('>=3.0.0')))->toBeFalse(
        sprintf('package-lock.json resolves `sass` to %s, at or above Dart Sass 3.0, which removes the `@import` rule still used across `resources/scss/`. Hold `sass` below 3.0 until #30.', $resolved),
    );
});
