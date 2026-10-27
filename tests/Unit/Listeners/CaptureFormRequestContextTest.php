<?php

declare(strict_types=1);

use App\Listeners\CaptureFormRequestContext;
use App\Support\Forms\FormRequestContext;
use Illuminate\Http\Request;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Forms\Submission;
use Statamic\Events\FormSubmitted;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(CaptureFormRequestContext::class);

/**
 * Clear any stashed contexts left by a prior example.
 */
afterEach(function (): void {
    FormRequestContext::pull('sub-123');
    FormRequestContext::pull('');
});

/**
 * @covers \App\Listeners\CaptureFormRequestContext
 *
 * @command  php artisan test --compact tests/Unit/Listeners/CaptureFormRequestContextTest.php
 */
it('stashes captured request context under the submission id', function (): void {
    // Bind a live request so capture() reads known submitter values
    app()->instance(
        'request',
        Request::create(
            '/forms/contact',
            'POST',
            [],
            [],
            [],
            [
                'REMOTE_ADDR' => '203.0.113.10',
                'HTTP_REFERER' => 'https://example.test/referrer',
            ],
        ),
    );

    $submission = Mockery::mock(Submission::class);
    $submission->shouldReceive('id')->once()->andReturn('sub-123');

    (new CaptureFormRequestContext)->handle(new FormSubmitted($submission));

    $stashed = FormRequestContext::pull('sub-123');

    expect($stashed)->toBeInstanceOf(FormRequestContext::class)
        ->and($stashed?->ip)->toBe('203.0.113.10')
        ->and($stashed?->referrer)->toBe('https://example.test/referrer');
});

it('casts a scalar non-string submission id before stashing', function (): void {
    app()->instance(
        'request',
        Request::create('/forms/contact', 'POST'),
    );

    $submission = Mockery::mock(Submission::class);
    // Integer id requires ``(string)`` before stash()'s string parameter
    $submission->shouldReceive('id')->once()->andReturn(42);

    (new CaptureFormRequestContext)->handle(new FormSubmitted($submission));

    expect(FormRequestContext::pull('42'))->toBeInstanceOf(FormRequestContext::class);
});

it('stashes under an empty key when the submission id is not scalar', function (): void {
    app()->instance(
        'request',
        Request::create('/forms/contact', 'POST'),
    );

    $submission = Mockery::mock(Submission::class);
    // Non-scalar id forces the empty-string stash key
    $submission->shouldReceive('id')->once()->andReturn(['not', 'scalar']);

    (new CaptureFormRequestContext)->handle(new FormSubmitted($submission));

    expect(FormRequestContext::pull(''))->toBeInstanceOf(FormRequestContext::class)
        ->and(FormRequestContext::pull('sub-123'))->toBeNull();
});
