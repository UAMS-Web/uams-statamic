<?php

declare(strict_types=1);

use App\Exceptions\PeopleMergeException;

/**
 * Empty marker exception (UAMS-Web/uams-statamic-archive#838): no executable lines to mutate.
 *
 * @command  php artisan test --compact tests/Unit/Exceptions/PeopleMergeExceptionTest.php
 */
covers(PeopleMergeException::class);

it('is a RuntimeException carrying the refusal message', function (): void {
    $exception = new PeopleMergeException('not enough candidates');

    // Callers catch it as a RuntimeException, so pin that it is caught as one.
    expect(fn () => throw $exception)->toThrow(RuntimeException::class)
        ->and($exception->getMessage())->toBe('not enough candidates');
});
