<?php

declare(strict_types=1);

use App\Tags\Log;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for the `log` Antlers tag.
 *
 * Mutation: 100% on App\Tags\Log (16 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Tags/LogTagTest.php
 */
it('dispatches emergency with message', function () {
    Event::fake([
        MessageLogged::class,
    ]);

    // Build tag with emergency level and message
    $tag = new Log;
    $tag->setContext([]);
    $tag->setParameters([
        'message' => 'The system is down!',
        'level' => 'emergency',
    ]);

    // Invoke the tag
    $output = $tag->index();

    // Assert no template output
    expect($output)->toBe('');

    // Assert the log event was dispatched with expected payload
    Event::assertDispatched(
        MessageLogged::class,
        function (MessageLogged $event): bool {
            return $event->level === 'emergency'
                && $event->message === 'The system is down!';
        }
    );
});

it('defaults level to debug', function () {
    Event::fake([
        MessageLogged::class,
    ]);

    // Build tag with message only (no level)
    $tag = new Log;
    $tag->setContext([]);
    $tag->setParameters([
        'message' => 'hello',
    ]);

    // Invoke the tag
    $tag->index();

    // Assert debug level was used
    Event::assertDispatched(
        MessageLogged::class,
        function (MessageLogged $event): bool {
            return $event->level === 'debug'
                && $event->message === 'hello';
        }
    );
});

it('falls back to debug for unknown level', function () {
    Event::fake([
        MessageLogged::class,
    ]);

    // Build tag with an invalid level
    $tag = new Log;
    $tag->setContext([]);
    $tag->setParameters([
        'message' => 'note',
        'level' => 'not-a-real-level',
    ]);

    // Invoke the tag
    $tag->index();

    // Assert fallback to debug
    Event::assertDispatched(
        MessageLogged::class,
        function (MessageLogged $event): bool {
            return $event->level === 'debug'
                && $event->message === 'note';
        }
    );
});
