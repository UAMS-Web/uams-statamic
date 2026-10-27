<?php

declare(strict_types=1);

use App\Services\LinkHealth\ProbeOutcome;

covers(ProbeOutcome::class);

/**
 * @covers \App\Services\LinkHealth\ProbeOutcome
 *
 * @command  php artisan test --compact tests/Unit/Services/LinkHealth/ProbeOutcomeTest.php
 */
it('marks only Broken and Unreachable as broken for editors', function (): void {
    expect(ProbeOutcome::Broken->isBroken())->toBeTrue()
        ->and(ProbeOutcome::Unreachable->isBroken())->toBeTrue()
        ->and(ProbeOutcome::Healthy->isBroken())->toBeFalse()
        ->and(ProbeOutcome::Refused->isBroken())->toBeFalse()
        ->and(ProbeOutcome::Unverifiable->isBroken())->toBeFalse();
});

it('explains every case in one sentence', function (): void {
    foreach (ProbeOutcome::cases() as $case) {
        expect($case->explanation())->not->toBe('');
    }
});
