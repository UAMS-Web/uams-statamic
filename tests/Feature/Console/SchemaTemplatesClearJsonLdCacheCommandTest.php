<?php

declare(strict_types=1);

use App\Console\Commands\SchemaTemplatesClearJsonLdCache;
use App\Services\SchemaTemplates\SchemaJsonLdOutputCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Assert;
use Tests\TestCase;

use function Pest\Laravel\artisan;

/**
 * Feature tests for the JSON-LD cache clear Artisan command.
 *
 * Mutation: 100% on App\Console\Commands\SchemaTemplatesClearJsonLdCache (2 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Console/SchemaTemplatesClearJsonLdCacheCommandTest.php
 */
uses(TestCase::class);

covers(SchemaTemplatesClearJsonLdCache::class);

/**
 * Run an Artisan command and return its PendingCommand for fluent assertions.
 *
 * Pest\Laravel\artisan() is typed PendingCommand|int; with console-output
 * mocking on (the default) it always returns a PendingCommand. The caller MUST
 * chain the assertion WITHOUT storing the result: a PendingCommand executes the
 * command in its destructor, so storing it in a variable would defer the run
 * past the later cache-counter assertion.
 *
 * @param  array<string, mixed>  $parameters
 */
function schemaTemplatesClearRunCommand(string $command, array $parameters = []): PendingCommand
{
    $pending = artisan($command, $parameters);

    // Narrow the PendingCommand|int union (output mocking guarantees a PendingCommand).
    Assert::assertInstanceOf(PendingCommand::class, $pending);

    return $pending;
}

it('clear command bumps json ld cache', function (): void {
    // Reset the cache store so bust counter starts clean
    Cache::flush();

    // Instantiate the cache helper to read the bust counter
    $cache = new SchemaJsonLdOutputCache;

    // Check the bust counter starts at zero before the command runs
    expect($cache->currentBust())->toBe(0);

    // Run the Artisan clear command (same effect as CP bump). Chain the assertion
    // without storing the PendingCommand so it executes synchronously here, before
    // the bust-counter assertion below.
    schemaTemplatesClearRunCommand('schema-templates:clear-json-ld-cache')->assertExitCode(0);

    // Check the bust counter reflects one increment from the command
    expect($cache->currentBust())->toBe(1);

    unset($cache);
});
