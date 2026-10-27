<?php

declare(strict_types=1);

use App\Listeners\InvalidateSchemaJsonLdCache;
use App\Services\SchemaTemplates\SchemaJsonLdOutputCache;
use Illuminate\Support\Facades\Cache;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Events\EntrySaved;
use Statamic\Events\GlobalSetSaved;
use Statamic\Events\TermSaved;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @return EntryModel&MockInterface
 */
function invalidateSchemaMakeEntrySavedPayload(string $collectionHandle)
{
    $root = Mockery::mock(EntryModel::class);
    $root->shouldReceive('id')->andReturn('root-id');

    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('root')->andReturn($root);
    $entry->shouldReceive('collectionHandle')
        ->once()
        ->andReturn($collectionHandle);

    return $entry;
}

/**
 * @covers \App\Listeners\InvalidateSchemaJsonLdCache
 *
 * Mutation: 100% on App\Listeners\InvalidateSchemaJsonLdCache (46 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Listeners/InvalidateSchemaJsonLdCacheTest.php
 */
it('bumps the cache when a watched entry collection is saved', function () {
    Cache::flush();

    config([
        'schema_templates.json_ld_cache.invalidate_entry_collections' => [
            'posts',
        ],
    ]);

    $cache = new SchemaJsonLdOutputCache;
    app()->instance(SchemaJsonLdOutputCache::class, $cache);

    $entry = invalidateSchemaMakeEntrySavedPayload('posts');

    (new InvalidateSchemaJsonLdCache)->handle(new EntrySaved($entry));

    expect($cache->currentBust())->toBe(1);
});

it('skips when the entry collection is not watched', function () {
    Cache::flush();

    config([
        'schema_templates.json_ld_cache.invalidate_entry_collections' => [
            'posts',
        ],
    ]);

    $cache = new SchemaJsonLdOutputCache;
    app()->instance(SchemaJsonLdOutputCache::class, $cache);

    $entry = invalidateSchemaMakeEntrySavedPayload('pages');

    (new InvalidateSchemaJsonLdCache)->handle(new EntrySaved($entry));

    expect($cache->currentBust())->toBe(0);
});

it('bumps for a watched global set', function () {
    Cache::flush();

    config([
        'schema_templates.json_ld_cache.invalidate_global_set_handles' => [
            'settings',
        ],
    ]);

    $cache = new SchemaJsonLdOutputCache;
    app()->instance(SchemaJsonLdOutputCache::class, $cache);

    $globals = new class
    {
        public function handle(): string
        {
            return 'settings';
        }
    };

    (new InvalidateSchemaJsonLdCache)->handle(new GlobalSetSaved($globals));

    expect($cache->currentBust())->toBe(1);
});

it('bumps for a watched taxonomy', function () {
    Cache::flush();

    config([
        'schema_templates.json_ld_cache.invalidate_taxonomies' => [
            'tags',
        ],
    ]);

    $cache = new SchemaJsonLdOutputCache;
    app()->instance(SchemaJsonLdOutputCache::class, $cache);

    $term = new class
    {
        public function taxonomyHandle(): string
        {
            return 'tags';
        }
    };

    (new InvalidateSchemaJsonLdCache)->handle(new TermSaved($term));

    expect($cache->currentBust())->toBe(1);
});
