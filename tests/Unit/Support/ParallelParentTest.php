<?php

declare(strict_types=1);

use Tests\Support\ParallelParent;

/**
 * Guards the parent/worker discrimination behind the UAMS-Web/uams-statamic-archive#1970 seed skip.
 *
 * The pure form is what gets covered here: spawning a real band to observe the
 * parent would cost minutes per case and could only assert the happy path, and
 * the failure this protects against is a *serial* run being misread as a parent
 * and silently losing its Stache seed.
 */
it('treats a non-tokenized parallel invocation as the parent', function (): void {
    expect(ParallelParent::isParentFor(false, ['pest', '--parallel', '--colors=always']))->toBeTrue();
});

it('treats a worker as a worker, even though its band is parallel', function (): void {
    // paratest sets TEST_TOKEN in every worker subprocess. The token alone
    // settles it, whatever the worker's own argv happens to carry.
    expect(ParallelParent::isParentFor('7', ['worker.php', '--parallel']))->toBeFalse();
});

it('never treats a serial run as the parent, which would cost it the Stache seed', function (): void {
    // The regression that matters: a serial run has no token either, so the
    // token check alone would strip its seed and leave every content-dependent
    // test resolving null.
    expect(ParallelParent::isParentFor(false, ['pest', '--group=serial']))->toBeFalse();
});

it('reads an empty token as absent rather than as a worker index', function (): void {
    // `getenv()` yields '' rather than false for a set-but-empty variable, and
    // an empty segment is not a worker identity.
    expect(ParallelParent::isParentFor('', ['pest', '--parallel']))->toBeTrue();
});

it('matches the flag exactly, not as a substring of another argument', function (): void {
    expect(ParallelParent::isParentFor(false, ['pest', '--parallel-workers=4']))->toBeFalse();
});
