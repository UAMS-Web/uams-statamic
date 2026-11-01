<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaJsonLdFragmentAtId;
use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplateEmitContext;
use App\Services\SchemaTemplates\SchemaTemplateEmitContextRuntimeStack;
use App\Services\SchemaTemplates\SchemaTemplateInstanceWatermarkBag;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * `Tests\TestCase` starts every test with an empty emit context stack
 * (UAMS-Web/uams-statamic#442).
 *
 * The stack is static, so it outlives the test that pushed to it. The schema tags
 * read it ahead of their own Antlers scope, so a context left there by one test
 * silently replaces the context the next test passes in.
 *
 * @command  php artisan test --compact tests/Feature/EmitContextStackResetTest.php
 */

/**
 * Build an emit context, with `$atId` recorded as already emitted when one is given.
 *
 * @param  string|null  $atId  The `@id` to mark as emitted.
 * @return SchemaTemplateEmitContext A fresh context.
 */
function emitContextStackResetContext(?string $atId = null): SchemaTemplateEmitContext
{
    $entryIds = [];
    $nodeIds = $atId === null ? [] : [$atId => true];
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
 * Run the reset `Tests\TestCase::setUp()` runs.
 *
 * @param  TestCase  $testCase  The running test.
 */
function runEmitContextStackReset(TestCase $testCase): void
{
    Closure::bind(fn () => $this->resetAntlersRuntimeState(), $testCase, TestCase::class)();
}

afterEach(function (): void {
    runEmitContextStackReset($this);
});

it('empties every context a test left on the stack', function (): void {
    SchemaTemplateEmitContextRuntimeStack::push(emitContextStackResetContext());
    SchemaTemplateEmitContextRuntimeStack::push(emitContextStackResetContext());

    runEmitContextStackReset($this);

    expect(SchemaTemplateEmitContextRuntimeStack::peek())->toBeNull();
});

it('lets a tag read the context in its own scope once a leftover context is gone', function (): void {
    $atId = SchemaJsonLdFragmentAtId::atIdFromRootRelativePath('/country/us');

    $parse = function () use ($atId): mixed {
        $output = Antlers::parse(
            '{{ schema_country code="US" }}',
            [SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_EMIT_CONTEXT => emitContextStackResetContext($atId)],
            true
        );

        return json_decode(trim((string) $output), true);
    };

    // The leftover context has emitted nothing, so while it stays the tag prints the
    // full node whatever its own scope says.
    SchemaTemplateEmitContextRuntimeStack::push(emitContextStackResetContext());
    expect($parse())->toHaveKey('@type', 'Country');

    runEmitContextStackReset($this);

    expect($parse())->toBe(['@id' => $atId]);
});
