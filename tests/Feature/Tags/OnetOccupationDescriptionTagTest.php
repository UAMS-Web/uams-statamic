<?php

declare(strict_types=1);

use App\Services\OnetWebServices\OnetOccupationOverviewService;
use App\Tags\OnetOccupationDescription;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(OnetOccupationDescription::class);

/**
 * Feature tests for the OnetOccupationDescription Antlers tag.
 *
 * @command  php artisan test --compact tests/Feature/Tags/OnetOccupationDescriptionTagTest.php
 */

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

it('returns empty when code is missing, blank, or non-string', function (mixed $code): void {
    $svc = Mockery::mock(OnetOccupationOverviewService::class);
    $svc->shouldReceive('overview')->never();
    app()->instance(OnetOccupationOverviewService::class, $svc);

    $tag = new OnetOccupationDescription;
    $tag->setContext([]);
    $tag->setParameters($code === null ? [] : ['code' => $code]);

    expect($tag->index())->toBe('');
})->with([
    'absent' => [null],
    'empty' => [''],
    'whitespace' => ['   '],
    'non-string' => [17],
]);

it('returns empty when the overview cannot be loaded', function (): void {
    /** @var OnetOccupationOverviewService&MockInterface $svc */
    $svc = Mockery::mock(OnetOccupationOverviewService::class);
    $svc->shouldReceive('overview')
        ->once()
        ->with('17-2051.00')
        ->andReturnNull();
    app()->instance(OnetOccupationOverviewService::class, $svc);

    $tag = new OnetOccupationDescription;
    $tag->setContext([]);
    $tag->setParameters(['code' => '17-2051.00']);

    expect($tag->index())->toBe('');
});

it('returns empty when the overview has no description key', function (): void {
    // BooleanOrToBooleanAnd / RemoveEarlyReturn on the isset guard must not
    // surface an undefined-index notice or a non-empty string.
    /** @var OnetOccupationOverviewService&MockInterface $svc */
    $svc = Mockery::mock(OnetOccupationOverviewService::class);
    $svc->shouldReceive('overview')
        ->once()
        ->with('17-2051.00')
        ->andReturn(['title' => 'Civil Engineers']);
    app()->instance(OnetOccupationOverviewService::class, $svc);

    $tag = new OnetOccupationDescription;
    $tag->setContext([]);
    $tag->setParameters(['code' => '17-2051.00']);

    expect($tag->index())->toBe('');
});

it('returns the description when present', function (): void {
    /** @var OnetOccupationOverviewService&MockInterface $svc */
    $svc = Mockery::mock(OnetOccupationOverviewService::class);
    $svc->shouldReceive('overview')
        ->once()
        ->with('17-2051.00')
        ->andReturn([
            'title' => 'Civil Engineers',
            'description' => 'Design projects.',
        ]);
    app()->instance(OnetOccupationOverviewService::class, $svc);

    $tag = new OnetOccupationDescription;
    $tag->setContext([]);
    $tag->setParameters(['code' => ' 17-2051.00 ']);

    expect($tag->index())->toBe('Design projects.');
});
