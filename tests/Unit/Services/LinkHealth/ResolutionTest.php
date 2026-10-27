<?php

declare(strict_types=1);

use App\Services\LinkHealth\Resolution;
use App\Services\LinkHealth\ResolutionState;

covers(Resolution::class);

/**
 * @covers \App\Services\LinkHealth\Resolution
 *
 * @command  php artisan test --compact tests/Unit/Services/LinkHealth/ResolutionTest.php
 */
it('marks Missing, Unpublished, and MissingFile as broken', function (): void {
    expect((new Resolution(ResolutionState::Missing))->isBroken())->toBeTrue()
        ->and((new Resolution(ResolutionState::Unpublished))->isBroken())->toBeTrue()
        ->and((new Resolution(ResolutionState::MissingFile))->isBroken())->toBeTrue();
});

it('does not mark Ok, Redirected, or NotChecked as broken', function (): void {
    expect((new Resolution(ResolutionState::Ok))->isBroken())->toBeFalse()
        ->and((new Resolution(ResolutionState::Redirected))->isBroken())->toBeFalse()
        ->and((new Resolution(ResolutionState::NotChecked))->isBroken())->toBeFalse();
});
