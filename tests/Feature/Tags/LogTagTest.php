<?php

declare(strict_types=1);

use App\Tags\Log;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Statamic\Fields\LabeledValue;
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

/**
 * Run the tag with the given parameters and return the one entry it logged.
 *
 * @param  array<string, mixed>  $parameters
 * @return array{level: string, message: string}
 */
function logTagEntry(array $parameters): array
{
    Event::fake([
        MessageLogged::class,
    ]);

    $tag = new Log;
    $tag->setContext([]);
    $tag->setParameters($parameters);

    expect($tag->index())->toBe('');

    $events = Event::dispatched(MessageLogged::class);

    expect($events)->toHaveCount(1);

    $event = mustBe(MessageLogged::class, $events->first()[0] ?? null, 'the logged event');

    return ['level' => $event->level, 'message' => $event->message];
}

it('dispatches each supported level to its own method', function (string $level) {
    expect(logTagEntry(['message' => 'note', 'level' => $level]))
        ->toBe(['level' => $level, 'message' => 'note']);
})->with([
    'emergency',
    'alert',
    'critical',
    'error',
    'warning',
    'notice',
    'info',
    'debug',
]);

it('reads the level case-insensitively', function () {
    expect(logTagEntry(['message' => 'note', 'level' => 'WARNING'])['level'])->toBe('warning');
});

it('logs an empty message when none is given', function () {
    expect(logTagEntry(['level' => 'info']))->toBe(['level' => 'info', 'message' => '']);
});

it('logs a scalar or string-like message as its string, and anything else as empty', function (mixed $message, string $logged) {
    expect(logTagEntry(['message' => $message, 'level' => 'info'])['message'])->toBe($logged);
})->with([
    // LabeledValue is Arrayable as well as string-like, so the logger would dump its
    // array form if the tag passed it through uncast
    'a labeled option' => [new LabeledValue('Front desk', 'Front Desk'), 'Front desk'],
    'an integer' => [42, '42'],
    'a string-like object' => [new class implements Stringable
    {
        public function __toString(): string
        {
            return 'from an object';
        }
    }, 'from an object'],
    'an array' => [['not', 'a', 'message'], ''],
]);

it('reads a string-like level, and falls back to debug for anything else', function (mixed $level, string $logged) {
    expect(logTagEntry(['message' => 'note', 'level' => $level])['level'])->toBe($logged);
})->with([
    'a labeled option' => [new LabeledValue('notice', 'Notice'), 'notice'],
    'an array' => [['info'], 'debug'],
    'an integer' => [5, 'debug'],
]);
