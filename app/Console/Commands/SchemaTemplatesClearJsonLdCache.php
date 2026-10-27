<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SchemaTemplates\SchemaJsonLdOutputCache;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Artisan command that bumps the schema JSON-LD output cache bust counter.
 *
 * Matches the Statamic control panel **Clear** action so operators can invalidate
 * cached JSON-LD script payloads from CI or a shell without opening the CP.
 */
#[Description('Bump the schema JSON-LD output cache bust counter (same as CP Clear)')]
#[Signature('schema-templates:clear-json-ld-cache')]
class SchemaTemplatesClearJsonLdCache extends Command
{
    /**
     * Increment the bust counter so new JSON-LD cache keys are used site-wide.
     *
     * @return int Laravel `Command::SUCCESS` after the bump completes
     */
    public function handle(): int
    {
        // Resolve the cache helper from the container
        $cache = app(SchemaJsonLdOutputCache::class);

        // Bump the bust generation and persist last bump metadata
        $cache->bump();

        // Report success for operators and automation logs
        $this->info('Schema JSON-LD cache bust counter incremented.');

        // Unset local variables to free up memory
        unset(
            $cache
        );

        // Return success
        return self::SUCCESS;
    }
}
