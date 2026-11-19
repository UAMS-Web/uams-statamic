<?php

declare(strict_types=1);

use App\Support\Multisite\SiteTreeCache;
use App\Support\Multisite\SiteTreeSnapshot;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Events\GlobalSetDeleted;
use Statamic\Events\GlobalSetSaved;
use Statamic\Events\GlobalVariablesDeleted;
use Statamic\Events\GlobalVariablesSaved;
use Statamic\Events\StacheCleared;
use Statamic\Events\StacheWarmed;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Facades\Stache;
use Statamic\Globals\Variables;
use Statamic\Sites\Site as SiteObject;
use Statamic\Stache\Stores\Store;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * The multisite tree search reads, built from `uams_multisite_settings` and cached (#480).
 *
 * The hermetic cases mock the global set and point the two Stache stores the fingerprint reads at
 * a throwaway directory, so they run everywhere. The cases that state the real tree's figures
 * need the `content/` submodule and self-skip without it.
 *
 * @command  php artisan test --compact tests/Feature/Support/Multisite/SiteTreeCacheTest.php
 */
/**
 * A Stache store, as the store its key names.
 *
 * @param  string  $key  The store's key.
 */
function siteTreeStore(string $key): Store
{
    return mustBe(Store::class, Stache::store($key), 'the '.$key.' store');
}

$stores = [];

beforeEach(function () use (&$stores): void {
    Cache::forget(SiteTreeCache::KEY);
    $stores = [
        'globals' => siteTreeStore('globals')->directory(),
        'global-variables' => siteTreeStore('global-variables')->directory(),
    ];
});

afterEach(function () use (&$stores): void {
    foreach ($stores as $store => $directory) {
        siteTreeStore($store)->directory($directory);
    }

    Cache::forget(SiteTreeCache::KEY);
});

/**
 * Mock the global set: each site's origin and stored `subject`, as `value()` reads it.
 *
 * @param  array<string, array{0: string|null|false, 1: mixed}>  $sites  Handle => [origin locale,
 *                                                                       null for a root, false for no localization; the `subject` value()`].
 * @param  int|null  $finds  How many times the set may be looked up, or null for any number.
 */
function siteTreeMockGlobals(array $sites, ?int $finds = null): void
{
    $set = Mockery::mock(Statamic\Contracts\Globals\GlobalSet::class);

    // A stray site the contract allows but no handle can come from, ahead of the real ones
    $siteObjects = [new stdClass];

    foreach ($sites as $handle => [$originLocale, $subject]) {
        $site = Mockery::mock(SiteObject::class);
        $site->shouldReceive('handle')->andReturn($handle);
        $siteObjects[] = $site;

        if ($originLocale === false) {
            $set->shouldReceive('in')->with($handle)->andReturnNull();

            continue;
        }

        $origin = null;
        if (\is_string($originLocale)) {
            $origin = Mockery::mock(Variables::class);
            $origin->shouldReceive('locale')->andReturn($originLocale);
        }

        $variables = Mockery::mock(Variables::class);
        $variables->shouldReceive('origin')->andReturn($origin);
        // A closure answers at read time, so a test can change the subject between requests
        $subject instanceof Closure
            ? $variables->shouldReceive('value')->with('subject')->andReturnUsing($subject)
            : $variables->shouldReceive('value')->with('subject')->andReturn($subject);
        $set->shouldReceive('in')->with($handle)->andReturn($variables);
    }

    Site::shouldReceive('all')->andReturn(collect($siteObjects));
    $find = GlobalSet::shouldReceive('find')->with('uams_multisite_settings')->andReturn($set);

    if ($finds !== null) {
        $find->times($finds);
    }
}

/**
 * Point the two Stache stores at a throwaway directory holding a copy of each file the
 * fingerprint reads, and return the directory.
 */
function siteTreeThrowawayStores(): string
{
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'site-tree-'.uniqid();
    $files = new Filesystem;
    $files->ensureDirectoryExists($root.'/medicine');
    file_put_contents($root.'/uams_multisite_settings.yaml', "sites:\n  default: null\n  medicine: default\n");
    file_put_contents($root.'/medicine/uams_multisite_settings.yaml', "subject: major_entity\n");

    siteTreeStore('globals')->directory($root);
    siteTreeStore('global-variables')->directory($root);

    return $root;
}

/**
 * A fresh cache, as the next request gets one.
 */
function nextRequestTree(): SiteTreeSnapshot
{
    return (new SiteTreeCache)->snapshot();
}

it('reads each site\'s origin and subject through value(), and a missing localization as a root with none', function (): void {
    siteTreeThrowawayStores();
    siteTreeMockGlobals([
        'detached' => [false, null],
        'default' => [null, 'root'],
        'inside' => ['default', 'root'],
        'inside_blog' => ['inside', 'root'],
        'medicine' => ['default', 'major_entity'],
        'odd' => ['default', ['not', 'a', 'string']],
    ]);

    $tree = nextRequestTree();

    expect($tree->parents)->toBe(['detached' => null, 'default' => null, 'inside' => 'default', 'inside_blog' => 'inside', 'medicine' => 'default', 'odd' => 'default'])
        ->and($tree->subjects)->toBe(['detached' => null, 'default' => 'root', 'inside' => 'root', 'inside_blog' => 'root', 'medicine' => 'major_entity', 'odd' => null])
        ->and($tree->nearestTier('inside_blog'))->toBe('inside');
});

it('builds no tree from a missing global set', function (): void {
    siteTreeThrowawayStores();
    $default = Mockery::mock(SiteObject::class);
    $default->shouldReceive('handle')->andReturn('default');
    Site::shouldReceive('all')->andReturn(collect([$default]));
    GlobalSet::shouldReceive('find')->with('uams_multisite_settings')->andReturnNull();

    expect(nextRequestTree()->parents)->toBe(['default' => null]);
});

it('reads the global set once while the files are unchanged, and once per request instance', function (): void {
    siteTreeThrowawayStores();
    siteTreeMockGlobals(['default' => [null, 'root'], 'medicine' => ['default', 'major_entity'], 'dept' => ['medicine', 'sub_entity']], finds: 1);

    $cache = new SiteTreeCache;
    $first = $cache->snapshot();

    expect($cache->snapshot())->toBe($first)
        ->and(nextRequestTree()->covered('medicine'))->toBe(['medicine', 'dept'])
        ->and(Cache::get(SiteTreeCache::KEY))->toHaveKeys(['fingerprint', 'parents', 'subjects']);
});

it('rebuilds when a file the global set is stored in changes, with no event and no manual clear', function (string $file, ?string $contents): void {
    $root = siteTreeThrowawayStores();
    siteTreeMockGlobals(['default' => [null, 'root'], 'medicine' => ['default', 'major_entity']], finds: 2);

    nextRequestTree();
    $contents === null ? unlink($root.'/'.$file) : file_put_contents($root.'/'.$file, $contents);
    nextRequestTree();
    nextRequestTree();

    (new Filesystem)->deleteDirectory($root);
})->with([
    'an origin in the set file' => ['uams_multisite_settings.yaml', "sites:\n  default: null\n  medicine: health\n"],
    'a subject in a variables file' => ['medicine/uams_multisite_settings.yaml', "subject: sub_entity\n"],
    'a variables file emptied' => ['medicine/uams_multisite_settings.yaml', ''],
    'a variables file removed' => ['medicine/uams_multisite_settings.yaml', null],
    'a variables file given the set file\'s contents' => ['medicine/uams_multisite_settings.yaml', "sites:\n  default: null\n  medicine: default\n"],
]);

it('rebuilds over a stored entry that is malformed or from other files', function (mixed $stored): void {
    siteTreeThrowawayStores();
    siteTreeMockGlobals(['default' => [null, 'root'], 'medicine' => ['default', 'major_entity'], 'dept' => ['medicine', 'sub_entity']], finds: 1);
    Cache::forever(SiteTreeCache::KEY, $stored);

    expect(nextRequestTree()->covered('medicine'))->toBe(['medicine', 'dept']);
})->with([
    'another fingerprint' => [['fingerprint' => 'stale', 'parents' => ['medicine' => null], 'subjects' => ['medicine' => null]]],
    'not an array' => ['tree'],
    'no parents' => [['fingerprint' => null, 'subjects' => []]],
]);

it('changes the next request\'s result when a subject or an origin is saved', function (string $event): void {
    siteTreeThrowawayStores();
    $medicine = 'major_entity';
    siteTreeMockGlobals(['default' => [null, 'root'], 'medicine' => ['default', function () use (&$medicine): string {
        return $medicine;
    }], 'dept' => ['medicine', 'sub_entity']]);
    expect(nextRequestTree()->nearestTier('dept'))->toBe('medicine');

    // The same files, so only the event can tell the cache the tree moved
    $medicine = 'sub_entity';
    expect(nextRequestTree()->nearestTier('dept'))->toBe('medicine');

    $set = Mockery::mock(Statamic\Contracts\Globals\GlobalSet::class)->shouldIgnoreMissing();
    $set->shouldReceive('handle')->andReturn('probe');
    $variables = Mockery::mock(Variables::class)->shouldIgnoreMissing();
    $variables->shouldReceive('globalSet')->andReturn($set);
    event(match ($event) {
        'set saved' => new GlobalSetSaved($set),
        'set deleted' => new GlobalSetDeleted($set),
        'variables saved' => new GlobalVariablesSaved($variables),
        'variables deleted' => new GlobalVariablesDeleted($variables),
        'stache cleared' => new StacheCleared,
        'stache warmed' => new StacheWarmed,
        default => throw new RuntimeException('No event named '.$event),
    });

    expect(Cache::has(SiteTreeCache::KEY))->toBeFalse()
        ->and(nextRequestTree()->nearestTier('dept'))->toBeNull();
})->with(['set saved', 'set deleted', 'variables saved', 'variables deleted', 'stache cleared', 'stache warmed']);

/**
 * Skip unless the multisite-settings global set is present (content submodule).
 */
function siteTreeRequireGlobals(): void
{
    if (! GlobalSet::find('uams_multisite_settings')) {
        ContentSubmodule::reportMissing('uams_multisite_settings global');
    }
}

it('covers a tier\'s whole subtree on the real tree', function (string $tier, int $sites): void {
    siteTreeRequireGlobals();

    expect(nextRequestTree()->covered($tier))->toHaveCount($sites);
})->with([
    'medicine' => ['medicine', 38],
    'health' => ['health', 28],
    'inside' => ['inside', 53],
]);

it('resolves the nearest tier on the real tree', function (): void {
    siteTreeRequireGlobals();
    $tree = nextRequestTree();

    // Every site, by its nearest tier, and the 20 tiers the ticket counts
    $tiers = array_values(array_filter(array_keys($tree->parents), $tree->isTier(...)));
    $belowResearch = $tree->descendants('research');
    $insideChildren = array_keys(array_filter($tree->parents, fn (?string $parent): bool => $parent === 'inside'));

    expect($tiers)->toHaveCount(20)
        ->and($tree->parents)->toHaveCount(255)
        ->and($tree->covered('default'))->toHaveCount(255)
        ->and($tree->nearestTier('medicine'))->toBeNull()
        ->and($tree->subjects['inside'])->toBe('root')
        ->and($belowResearch)->not->toBeEmpty()
        ->and($tree->nearestTier($belowResearch[0]))->toBe('research')
        ->and($tree->nearestTier('research'))->toBe('academic_affairs')
        ->and($tree->descendants('academic_affairs'))->toHaveCount(23)
        ->and($insideChildren)->not->toBeEmpty()
        ->and(array_map($tree->nearestTier(...), $insideChildren))->each->toBe('inside')
        ->and($tree->nearestTier('inside_medicine'))->toBe('inside');
});
