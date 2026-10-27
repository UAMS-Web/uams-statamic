<?php

declare(strict_types=1);

use App\Jobs\ScanLinkHealth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The scheduled link-health scan job: it runs the scan command and logs a non-zero exit
 * instead of failing the job (#2834 coverage batch 12).
 *
 * @command  php artisan test --compact tests/Feature/Jobs/ScanLinkHealthJobTest.php
 */
it('runs the link-health scan and logs nothing when it succeeds', function (): void {
    Artisan::shouldReceive('call')->once()->with('link-health:scan')->andReturn(0);
    Log::shouldReceive('warning')->never();

    (new ScanLinkHealth)->handle();
});

it('logs a warning with the exit code when the scan exits non-zero, and does not throw', function (int $exitCode): void {
    Artisan::shouldReceive('call')->once()->with('link-health:scan')->andReturn($exitCode);
    Log::shouldReceive('warning')->once()->with('Scheduled link-health scan exited non-zero.', ['exit_code' => $exitCode]);

    (new ScanLinkHealth)->handle();
})->with([
    'a failure' => [1],
    'another code' => [3],
]);
