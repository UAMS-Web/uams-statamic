<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaTemplateEmitContext;
use App\Services\SchemaTemplates\SchemaTemplateEmitContextRuntimeStack;
use App\Services\SchemaTemplates\SchemaTemplateInstanceWatermarkBag;

covers(SchemaTemplateEmitContextRuntimeStack::class);

/**
 * Pins the LIFO behavior of SchemaTemplateEmitContextRuntimeStack.
 *
 * The stack is process-wide static state, so each example empties it first.
 * Every call runs with warnings and notices raised as exceptions, because
 * `phpunit.xml` does not fail a test on a warning: without that, a `peek()`
 * that read past an empty stack would return null and pass.
 *
 * @command  php artisan test --compact tests/Unit/Services/SchemaTemplates/SchemaTemplateEmitContextRuntimeStackTest.php
 */

/**
 * Build a distinct emit context; identity is all the stack cares about.
 *
 * @return SchemaTemplateEmitContext A fresh context.
 */
function runtimeStackContext(): SchemaTemplateEmitContext
{
    $entryIds = [];
    $nodeIds = [];
    $materialized = [];

    return new SchemaTemplateEmitContext(
        $entryIds,
        $nodeIds,
        $materialized,
        [],
        null,
        new SchemaTemplateInstanceWatermarkBag,
    );
}

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    (new ReflectionClass(SchemaTemplateEmitContextRuntimeStack::class))
        ->setStaticPropertyValue('stack', []);

    set_error_handler(static function (int $severity, string $message): never {
        throw new ErrorException($message, 0, $severity);
    });

    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

afterEach(function (): void {
    restore_error_handler();
});

it('peeks null on an empty stack without a warning', function (): void {
    expect(SchemaTemplateEmitContextRuntimeStack::peek())->toBeNull();
});

it('peeks the most recently pushed context', function (): void {
    $outer = runtimeStackContext();
    $inner = runtimeStackContext();

    SchemaTemplateEmitContextRuntimeStack::push($outer);
    expect(SchemaTemplateEmitContextRuntimeStack::peek())->toBe($outer);

    SchemaTemplateEmitContextRuntimeStack::push($inner);
    expect(SchemaTemplateEmitContextRuntimeStack::peek())->toBe($inner);
});

it('pops back to the outer context and then to empty', function (): void {
    $outer = runtimeStackContext();
    $inner = runtimeStackContext();

    SchemaTemplateEmitContextRuntimeStack::push($outer);
    SchemaTemplateEmitContextRuntimeStack::push($inner);

    SchemaTemplateEmitContextRuntimeStack::pop();
    expect(SchemaTemplateEmitContextRuntimeStack::peek())->toBe($outer);

    SchemaTemplateEmitContextRuntimeStack::pop();
    expect(SchemaTemplateEmitContextRuntimeStack::peek())->toBeNull();
});

it('treats a pop on an empty stack as a no-op', function (): void {
    SchemaTemplateEmitContextRuntimeStack::pop();
    expect(SchemaTemplateEmitContextRuntimeStack::peek())->toBeNull();

    $context = runtimeStackContext();
    SchemaTemplateEmitContextRuntimeStack::push($context);
    expect(SchemaTemplateEmitContextRuntimeStack::peek())->toBe($context);
});

it('does not pop more than one context per call', function (): void {
    $first = runtimeStackContext();
    $second = runtimeStackContext();
    $third = runtimeStackContext();

    SchemaTemplateEmitContextRuntimeStack::push($first);
    SchemaTemplateEmitContextRuntimeStack::push($second);
    SchemaTemplateEmitContextRuntimeStack::push($third);
    SchemaTemplateEmitContextRuntimeStack::pop();

    expect(SchemaTemplateEmitContextRuntimeStack::peek())->toBe($second);
});
