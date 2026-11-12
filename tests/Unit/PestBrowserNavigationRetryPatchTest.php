<?php

declare(strict_types=1);

use Pest\Browser\Playwright\NavigationRetry;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Guards `patches/pest-plugin-browser-navigation-no-buffer-space-retry.patch`.
 *
 * Remove this file when the patch is removed; see **`pest-plugin-browser`
 * navigation retry on `net::ERR_NO_BUFFER_SPACE`** in `patches/README.md` for
 * the retirement condition.
 *
 * `NavigationRetry::onNoBufferSpace()` takes the navigation and the sleep as
 * callables, so the retry is asserted without a browser and without waiting:
 * the navigation records each attempt and throws what the Playwright client
 * throws for a failed `goto`, the sleep records the backoff it was asked for,
 * and the report records the line a job log would show for each retry. The
 * class exists only in the patched copy of the package.
 *
 * There is no backing App\ PHP class, so there is no mutable PHP surface to
 * mutation-test.
 *
 * @command  php artisan test --compact tests/Unit/PestBrowserNavigationRetryPatchTest.php
 */
it('retries a navigation refused with net::ERR_NO_BUFFER_SPACE and returns once it succeeds', function (): void {
    $attempts = 0;
    $sleeps = [];
    $reports = [];

    NavigationRetry::onNoBufferSpace(
        function () use (&$attempts): void {
            $attempts++;

            // The message the client threw on the Windows runner (CI run 41964133422).
            if ($attempts === 1) {
                throw new ExpectationFailedException('net::ERR_NO_BUFFER_SPACE at http://127.0.0.1:63215/__kb_reactions_fixture.html');
            }
        },
        function (int $microseconds) use (&$sleeps): void {
            $sleeps[] = $microseconds;
        },
        function (string $line) use (&$reports): void {
            $reports[] = $line;
        },
    );

    expect($attempts)->toBe(2)
        ->and($sleeps)->toBe([500_000])
        // One line per retry, naming the error, so a job log shows the retry fired.
        ->and($reports)->toBe([
            'pest-plugin-browser: retry 1 of 2 after net::ERR_NO_BUFFER_SPACE at http://127.0.0.1:63215/__kb_reactions_fixture.html (patch from UAMS-Web/uams-statamic#585)',
        ]);
});

it('throws the error once the retries are spent, with a longer wait before each retry', function (): void {
    $attempts = 0;
    $sleeps = [];
    $reports = [];

    expect(function () use (&$attempts, &$sleeps, &$reports): void {
        NavigationRetry::onNoBufferSpace(
            function () use (&$attempts): void {
                $attempts++;

                throw new ExpectationFailedException("page.goto: net::ERR_NO_BUFFER_SPACE at http://127.0.0.1:63215/attempt-{$attempts}");
            },
            function (int $microseconds) use (&$sleeps): void {
                $sleeps[] = $microseconds;
            },
            function (string $line) use (&$reports): void {
                $reports[] = $line;
            },
        );
    })->toThrow(ExpectationFailedException::class, 'attempt-3');

    // One first attempt plus two retries, so a persistent condition still fails the
    // test, in about 1.5s of waiting rather than never.
    expect($attempts)->toBe(1 + NavigationRetry::RETRIES)
        ->and($sleeps)->toBe([500_000, 1_000_000])
        ->and($reports)->toHaveCount(2);
});

it('throws any other navigation error on its first occurrence', function (string $message): void {
    $attempts = 0;
    $sleeps = [];
    $reports = [];

    expect(function () use (&$attempts, &$sleeps, &$reports, $message): void {
        NavigationRetry::onNoBufferSpace(
            function () use (&$attempts, $message): void {
                $attempts++;

                throw new ExpectationFailedException($message);
            },
            function (int $microseconds) use (&$sleeps): void {
                $sleeps[] = $microseconds;
            },
            function (string $line) use (&$reports): void {
                $reports[] = $line;
            },
        );
    })->toThrow(ExpectationFailedException::class, $message);

    expect($attempts)->toBe(1)
        ->and($sleeps)->toBe([])
        ->and($reports)->toBe([]);
})->with([
    'connection refused' => ['net::ERR_CONNECTION_REFUSED at http://127.0.0.1:63215/'],
    'navigation timeout' => ['page.goto: Timeout 5000ms exceeded.'],
    'a different buffer error' => ['net::ERR_INSUFFICIENT_RESOURCES at http://127.0.0.1:63215/'],
]);

it('does not retry an exception the Playwright client did not raise', function (): void {
    $attempts = 0;

    // The client reports a failed goto as ExpectationFailedException; a
    // RuntimeException carrying the same text comes from somewhere else (the
    // connection dropping, a bug in a test) and is not the runner condition.
    expect(function () use (&$attempts): void {
        NavigationRetry::onNoBufferSpace(
            function () use (&$attempts): void {
                $attempts++;

                throw new RuntimeException('net::ERR_NO_BUFFER_SPACE');
            },
            function (int $microseconds): void {},
            function (string $line): void {},
        );
    })->toThrow(RuntimeException::class);

    expect($attempts)->toBe(1);
});

it('routes Page::goto through the retry', function (): void {
    // A re-rolled patch that kept the new class but lost the Page hunk would leave
    // every test above green while no navigation retried, so read the patched
    // method itself. The class is named by string because the plugin marks Page
    // @internal, and reading its source needs no access to its API.
    $method = new ReflectionMethod('Pest\Browser\Playwright\Page', 'goto');
    $lines = file((string) $method->getFileName());

    if ($lines === false) {
        throw new RuntimeException("Could not read {$method->getFileName()}, the file that declares Page::goto().");
    }

    $body = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

    expect($body)->toContain('NavigationRetry::onNoBufferSpace(');
});
