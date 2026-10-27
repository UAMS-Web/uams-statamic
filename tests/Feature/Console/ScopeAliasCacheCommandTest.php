<?php

declare(strict_types=1);

use App\Enums\SchemaType;
use App\Scopes\EntriesBySchemaType;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The `scope-alias-cache` command: clear, warm or refresh the EntriesBySchemaType alias list
 * (#255 coverage batch 12).
 *
 * Every test switches to the array store first, so clearing or warming the list never
 * touches the file cache that other test processes share.
 *
 * @command  php artisan test --compact tests/Feature/Console/ScopeAliasCacheCommandTest.php
 */
beforeEach(function (): void {
    Cache::setDefaultDriver('array');
});

/**
 * The alias list the cache holds now, or null when it holds none.
 *
 * @return list<string>|null
 */
function scopeAliasCacheCommandAliases(): ?array
{
    /** @var list<string>|null $aliases */
    $aliases = Cache::get(EntriesBySchemaType::CACHE_KEY_ALIASES);

    return $aliases;
}

it('refuses an action it does not know, and leaves the cache alone', function (): void {
    Cache::put(EntriesBySchemaType::CACHE_KEY_ALIASES, ['kept']);

    expect(Artisan::call('scope-alias-cache', ['action' => 'flush']))->toBe(1)
        ->and(Artisan::output())->toContain('Invalid action. Must be: clear, warm, or refresh.')
        ->and(scopeAliasCacheCommandAliases())->toBe(['kept']);
});

it('clears the alias list', function (): void {
    Cache::put(EntriesBySchemaType::CACHE_KEY_ALIASES, ['stale']);

    expect(Artisan::call('scope-alias-cache', ['action' => 'clear']))->toBe(0);

    $output = Artisan::output();

    expect($output)->toContain('Scope alias cache cleared successfully.')
        ->and($output)->not->toContain('warmed')
        ->and(scopeAliasCacheCommandAliases())->toBeNull();
});

it('warms the alias list from every SchemaType case, and refreshes by clearing first', function (string $action, bool $clears): void {
    Cache::put(EntriesBySchemaType::CACHE_KEY_ALIASES, ['stale']);
    $count = count(SchemaType::cases());

    expect(Artisan::call('scope-alias-cache', ['action' => $action]))->toBe(0);

    $output = Artisan::output();
    $aliases = scopeAliasCacheCommandAliases();

    expect($output)->toContain(sprintf('Scope alias cache warmed successfully (%d types).', $count))
        ->and(str_contains($output, 'cleared successfully'))->toBe($clears)
        ->and($aliases)->toHaveCount($count)
        ->and($aliases)->toContain('entries_by_schema_type_thing', 'entries_by_schema_type_creative_work')
        ->and($aliases)->not->toContain('stale');
})->with([
    'warm' => ['warm', false],
    'refresh' => ['refresh', true],
]);
