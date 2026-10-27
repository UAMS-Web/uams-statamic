<?php

declare(strict_types=1);

use App\Jobs\ScanEntryLinks;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

covers(ScanEntryLinks::class);

uses(TestCase::class);

/*
 * UAMS-Web/uams-statamic-archive#1772 — the job the on-publish listener dispatches.
 *
 * The Artisan facade is mocked rather than letting the scan run: a real run issues outbound
 * requests to every host the entry links to, which is the whole reason this work is off the
 * save path in the first place.
 *
 * Mutation: tracked under App\Jobs\ScanEntryLinks (--covered-only).
 *
 * @command  php artisan test --compact tests/Feature/LinkHealth/ScanEntryLinksTest.php
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

it('stores constructor arguments on public properties', function (): void {
    $job = new ScanEntryLinks(
        'entry-9',
        'news',
        'posts',
    );

    expect($job->entryId)->toBe('entry-9')
        ->and($job->site)->toBe('news')
        ->and($job->collection)->toBe('posts');
});

it('scopes the scan to one entry, not to its whole collection', function (): void {
    // AC2. Scoped to site and collection alone, publishing one entry re-probes every other
    // entry in that collection and rewrites findings this save has nothing to do with.
    Artisan::shouldReceive('call')
        ->once()
        ->withArgs(function (string $command, array $parameters): bool {
            return $command === 'link-health:scan'
                && $parameters['--entry'] === 'entry-1'
                && $parameters['--site'] === 'news'
                && $parameters['--collection'] === 'posts';
        })
        ->andReturn(0);

    Log::shouldReceive('warning')->never();

    (new ScanEntryLinks('entry-1', 'news', 'posts'))->handle();
});

it('logs and swallows a failure rather than failing the job', function (): void {
    // AC4. A job left in the failed queue is one somebody has to clear before the next
    // publish scan runs, so a probe error must not put it there.
    Artisan::shouldReceive('call')->once()->andThrow(new RuntimeException('probe exploded'));
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'On-publish link scan failed.'
                && ($context['entry'] ?? null) === 'entry-2'
                && ($context['site'] ?? null) === 'default'
                && ($context['collection'] ?? null) === 'pages'
                && str_contains(
                    mustBeString(
                        $context['exception'] ?? null,
                        'the logged exception message',
                    ),
                    'probe exploded',
                );
        });

    $job = new ScanEntryLinks('entry-2', 'default', 'pages');

    // The claim is that handle() RETURNS rather than propagating. Asserted as a value the
    // test can see, because a test whose only evidence is the absence of an exception is
    // reported as risky and is one refactor away from asserting nothing at all.
    $threw = false;

    try {
        $job->handle();
    } catch (Throwable) {
        $threw = true;
    }

    expect($threw)->toBeFalse();
});
