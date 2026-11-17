<?php

declare(strict_types=1);

use App\Services\LinkHealth\ContentItemType;
use App\Services\LinkHealth\OccurrenceContext;
use App\Services\LinkHealth\ProbeAttempt;
use App\Services\LinkHealth\ProbeOutcome;

covers(OccurrenceContext::class, ProbeAttempt::class);

/**
 * Two value objects of the link-health scan (UAMS-Web/uams-statamic#309): where the
 * walk is (`OccurrenceContext`) and what one guarded request found
 * (`ProbeAttempt`). They carry no logic; what is pinned is that each keeps what it
 * was given, and that a probe defaults to no reason and to not answered, which
 * `ExternalProber` relies on to tell "unreachable" from "answered, then failed".
 *
 * @command  php artisan test --compact tests/Unit/Services/LinkHealth/LinkHealthValueObjectsTest.php
 */
it('keeps the content item the walk is inside', function (): void {
    $context = new OccurrenceContext(ContentItemType::Term, 'topics', 'term-1', 'health');

    expect($context->itemType)->toBe(ContentItemType::Term)
        ->and($context->container)->toBe('topics')
        ->and($context->id)->toBe('term-1')
        ->and($context->site)->toBe('health');
});

it('defaults a probe attempt to no reason and not answered', function (): void {
    $attempt = new ProbeAttempt(ProbeOutcome::Unreachable, null);

    expect($attempt->outcome)->toBe(ProbeOutcome::Unreachable)
        ->and($attempt->status)->toBeNull()
        ->and($attempt->reason)->toBeNull()
        ->and($attempt->answered)->toBeFalse();
});

it('keeps every field a probe attempt is given', function (): void {
    $attempt = new ProbeAttempt(ProbeOutcome::Broken, 404, 'not found after a redirect', true);

    expect($attempt->outcome)->toBe(ProbeOutcome::Broken)
        ->and($attempt->status)->toBe(404)
        ->and($attempt->reason)->toBe('not found after a redirect')
        ->and($attempt->answered)->toBeTrue();
});
