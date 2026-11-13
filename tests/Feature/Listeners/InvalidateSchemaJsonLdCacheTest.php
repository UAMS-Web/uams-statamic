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
 * Mutation-clean on App\Listeners\InvalidateSchemaJsonLdCache (UAMS-Web/uams-statamic#315),
 * with no accepted equivalents.
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

/**
 * Fire the listener with the three watch lists set as given, and return how many
 * times it bumped the cache.
 *
 * @param  EntrySaved|GlobalSetSaved|TermSaved  $event  The save event to handle
 * @param  mixed  $watched  The value of all three watch lists
 */
function invalidateSchemaBumpsFor(EntrySaved|GlobalSetSaved|TermSaved $event, mixed $watched): int
{
    Cache::flush();

    config([
        'schema_templates.json_ld_cache.invalidate_entry_collections' => $watched,
        'schema_templates.json_ld_cache.invalidate_global_set_handles' => $watched,
        'schema_templates.json_ld_cache.invalidate_taxonomies' => $watched,
    ]);

    $cache = new SchemaJsonLdOutputCache;
    app()->instance(SchemaJsonLdOutputCache::class, $cache);

    (new InvalidateSchemaJsonLdCache)->handle($event);

    return $cache->currentBust();
}

/** A global set or term double whose handle method returns `$handle`. */
function invalidateSchemaHandled(string $method, mixed $handle): object
{
    if ($method === 'handle') {
        return new class($handle)
        {
            public function __construct(private readonly mixed $value) {}

            public function handle(): mixed
            {
                return $this->value;
            }
        };
    }

    return new class($handle)
    {
        public function __construct(private readonly mixed $value) {}

        public function taxonomyHandle(): mixed
        {
            return $this->value;
        }
    };
}

it('ignores a payload that is not a Statamic entry, global set or term', function (string $kind) {
    $event = match ($kind) {
        // The event reads the payload's root to track who started the save, so the
        // stand-in has one but is not an entry.
        'entry' => new EntrySaved(new class
        {
            public function root(): object
            {
                return new class
                {
                    public function id(): string
                    {
                        return 'root-id';
                    }
                };
            }
        }),
        'global set' => new GlobalSetSaved(new stdClass),
        'term' => new TermSaved(new stdClass),
        default => throw new InvalidArgumentException("No such kind: {$kind}"),
    };

    expect(invalidateSchemaBumpsFor($event, ['posts']))->toBe(0);
})->with(['entry', 'global set', 'term']);

it('ignores a blank global set or taxonomy handle, even when a blank is watched', function (string $method) {
    $payload = invalidateSchemaHandled($method, '');
    $event = $method === 'handle' ? new GlobalSetSaved($payload) : new TermSaved($payload);

    expect(invalidateSchemaBumpsFor($event, ['']))->toBe(0);
})->with(['global set' => ['handle'], 'taxonomy' => ['taxonomyHandle']]);

it('bumps nothing when a watch list is not a list', function (string $kind) {
    $event = match ($kind) {
        'entry' => new EntrySaved(invalidateSchemaMakeEntrySavedPayload('posts')),
        'global set' => new GlobalSetSaved(invalidateSchemaHandled('handle', 'posts')),
        'term' => new TermSaved(invalidateSchemaHandled('taxonomyHandle', 'posts')),
        default => throw new InvalidArgumentException("No such kind: {$kind}"),
    };

    expect(invalidateSchemaBumpsFor($event, 'posts'))->toBe(0);
})->with(['entry', 'global set', 'term']);

it('matches a handle against the watch list strictly', function (string $kind) {
    // `true` loosely equals any non-empty string, so only a strict match leaves it
    // matching nothing.
    $event = match ($kind) {
        'entry' => new EntrySaved(invalidateSchemaMakeEntrySavedPayload('posts')),
        'global set' => new GlobalSetSaved(invalidateSchemaHandled('handle', 'posts')),
        'term' => new TermSaved(invalidateSchemaHandled('taxonomyHandle', 'posts')),
        default => throw new InvalidArgumentException("No such kind: {$kind}"),
    };

    expect(invalidateSchemaBumpsFor($event, [true]))->toBe(0);
})->with(['entry', 'global set', 'term']);
