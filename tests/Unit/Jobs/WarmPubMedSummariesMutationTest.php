<?php

declare(strict_types=1);

use App\Jobs\WarmPubMedSummaries;
use App\Services\PubMed\PubMedEsummaryService;
use Illuminate\Support\Facades\Log;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(WarmPubMedSummaries::class);

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(12_000_000);
        $padded = true;
    }
});

/**
 * @covers \App\Jobs\WarmPubMedSummaries
 *
 * @command  php artisan test --compact tests/Unit/Jobs/WarmPubMedSummariesMutationTest.php
 */
it('warms every pmid through the esummary service', function (): void {
    $service = Mockery::mock(PubMedEsummaryService::class);
    $service->shouldReceive('rememberSummaryRow')->once()->with('111');
    $service->shouldReceive('rememberSummaryRow')->once()->with('222');

    (new WarmPubMedSummaries(['111', '222']))->handle($service);
});

it('logs a warning and continues when a pmid warm throws', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'WarmPubMedSummaries: failed to warm PMID'
                && $context['pmid'] === 'bad'
                && $context['message'] === 'boom';
        });

    $service = Mockery::mock(PubMedEsummaryService::class);
    $service->shouldReceive('rememberSummaryRow')
        ->once()
        ->with('bad')
        ->andThrow(new RuntimeException('boom'));
    $service->shouldReceive('rememberSummaryRow')->once()->with('good');

    (new WarmPubMedSummaries(['bad', 'good']))->handle($service);
});
