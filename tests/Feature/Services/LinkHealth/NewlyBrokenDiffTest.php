<?php

declare(strict_types=1);

use App\Services\LinkHealth\NewlyBrokenDiff;
use App\Services\LinkHealth\ProbeOutcome;
use App\Services\LinkHealth\ResolutionState;
use Tests\TestCase;

/**
 * Coverage for {@see NewlyBrokenDiff} — what broke since the last run (UAMS-Web/uams-statamic-archive#1766).
 *
 * @command  php artisan test --compact tests/Feature/Services/LinkHealth/NewlyBrokenDiffTest.php
 */
uses(TestCase::class);

covers(NewlyBrokenDiff::class);

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

it('reports an occurrence that went from healthy to broken', function (): void {
    expect(NewlyBrokenDiff::between(
        ['entry:a:body.0' => ProbeOutcome::Healthy],
        ['entry:a:body.0' => ProbeOutcome::Broken],
    ))->toBe(['entry:a:body.0']);
});

it('does not report an occurrence that was already broken', function (): void {
    // It has not changed. The standing list covers it; this list is for what changed.
    expect(NewlyBrokenDiff::between(
        ['entry:a:body.0' => ProbeOutcome::Broken],
        ['entry:a:body.0' => ProbeOutcome::Broken],
    ))->toBe([]);
});

it('does not report an occurrence that recovered', function (): void {
    expect(NewlyBrokenDiff::between(
        ['entry:a:body.0' => ProbeOutcome::Broken],
        ['entry:a:body.0' => ProbeOutcome::Healthy],
    ))->toBe([]);
});

// AC6, and the case the acceptance criterion singles out. A URL broken in one entry, then added
// to a second, must NOT appear as newly broken — nothing broke, an editor merely linked it again.
it('does not report an already-broken URL appearing in a new entry', function (): void {
    $previous = ['entry:a:body.0' => ProbeOutcome::Broken];
    $current = [
        'entry:a:body.0' => ProbeOutcome::Broken,
        // Same URL, different entry, no previous record of THIS occurrence.
        'entry:b:body.0' => ProbeOutcome::Broken,
    ];

    expect(NewlyBrokenDiff::between($previous, $current))->toBe([]);
});

// The converse, so the rule above is not achieved by reporting nothing.
it('still reports a genuine transition in the same run as a newly seen occurrence', function (): void {
    // Newly-seen must come FIRST so ContinueToBreak cannot abort before the
    // later healthy→broken transition is recorded.
    $previous = ['entry:a:body.0' => ProbeOutcome::Healthy];
    $current = [
        'entry:b:body.0' => ProbeOutcome::Broken,
        'entry:a:body.0' => ProbeOutcome::Broken,
    ];

    expect(NewlyBrokenDiff::between($previous, $current))->toBe(['entry:a:body.0']);
});

it('treats an occurrence with no previous record as newly seen, not newly broken', function (): void {
    expect(NewlyBrokenDiff::between([], ['entry:a:body.0' => ProbeOutcome::Broken]))->toBe([]);
});

it('reports an unreachable target as broken, since the editor sees a dead link either way', function (): void {
    expect(NewlyBrokenDiff::between(
        ['entry:a:body.0' => ProbeOutcome::Healthy],
        ['entry:a:body.0' => ProbeOutcome::Unreachable],
    ))->toBe(['entry:a:body.0']);
});

// A guard refusal is not a transition to broken. This install cannot reach the host — that is not
// something that broke on Tuesday, and putting it in the "what changed" list spends attention on
// a link nobody can act on.
it('does not report a link that became guard-refused', function (): void {
    expect(NewlyBrokenDiff::between(
        ['entry:a:body.0' => ProbeOutcome::Healthy],
        ['entry:a:body.0' => ProbeOutcome::Refused],
    ))->toBe([]);
});

// --- internal outcomes travel through the same diff -------------------------

it('reports an internal target that went missing', function (ResolutionState $now, array $expected): void {
    // InstanceOfToTrue on ``$outcome instanceof ProbeOutcome`` would call
    // isBroken() on a ResolutionState and fail this suite.
    expect(NewlyBrokenDiff::between(
        ['entry:a:body.0' => ResolutionState::Ok],
        ['entry:a:body.0' => $now],
    ))->toBe($expected);
})->with([
    'deleted' => [ResolutionState::Missing, ['entry:a:body.0']],
    'unpublished' => [ResolutionState::Unpublished, ['entry:a:body.0']],
    'file gone' => [ResolutionState::MissingFile, ['entry:a:body.0']],
    // A path served by a redirect resolves. Nothing broke.
    'redirected' => [ResolutionState::Redirected, []],
    // Not examined is not a verdict, so it cannot be a transition to one.
    'not checked' => [ResolutionState::NotChecked, []],
]);

it('preserves the order of the current run', function (): void {
    $previous = [
        'entry:a:body.0' => ProbeOutcome::Healthy,
        'entry:b:body.0' => ProbeOutcome::Healthy,
    ];
    $current = [
        'entry:b:body.0' => ProbeOutcome::Broken,
        'entry:a:body.0' => ProbeOutcome::Broken,
    ];

    expect(NewlyBrokenDiff::between($previous, $current))->toBe(['entry:b:body.0', 'entry:a:body.0']);
});

it('does not report a link that became unverifiable as newly broken', function (): void {
    // UAMS-Web/uams-statamic-archive#1768. An intranet host the scan could not reach from where it ran has not broken: the same
    // link scanned on-network answers. Reporting it would page an editor about the scan's network.
    expect(NewlyBrokenDiff::between(
        ['entry:a:body.0' => ProbeOutcome::Healthy],
        ['entry:a:body.0' => ProbeOutcome::Unverifiable],
    ))->toBe([]);
});

it('reports an unverifiable link that turns out broken once it is reached', function (): void {
    // Unverifiable counts as healthy for a transition, so the day the host answers with a 404 is
    // the day it is news.
    expect(NewlyBrokenDiff::between(
        ['entry:a:body.0' => ProbeOutcome::Unverifiable],
        ['entry:a:body.0' => ProbeOutcome::Broken],
    ))->toBe(['entry:a:body.0']);
});

it('does not report a still-healthy ResolutionState pair', function (): void {
    expect(NewlyBrokenDiff::between(
        ['entry:a:body.0' => ResolutionState::Redirected],
        ['entry:a:body.0' => ResolutionState::Ok],
    ))->toBe([]);
});
