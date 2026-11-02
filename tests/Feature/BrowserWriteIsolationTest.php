<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Stache;
use Statamic\Facades\User;
use Statamic\Stache\Stores\Store;
use Tests\Support\BrowserWriteIsolation;
use Tests\Support\ThrowawayRoot;
use Tests\TestCase;

/**
 * The `tests/Browser` smokes persist a collection, a blueprint and a Control
 * Panel super user in `beforeEach` and remove them in `afterEach`. That
 * `afterEach` does not run when the process dies first, and UAMS-Web/uams-statamic-archive#1727 put the suite
 * in local-CI's default `all` set as its last and longest job — the one an
 * interrupt lands on. Each stranded artifact then fails the NEXT run rather than
 * this one: a blueprint left in `resources/blueprints/` trips UAMS-Web/uams-statamic-archive#1889's drift gate,
 * a collection left in `content/` trips UAMS-Web/uams-statamic-archive#1873's litter gate (UAMS-Web/uams-statamic-archive#1920).
 *
 * `Tests\Support\BrowserWriteIsolation` closes that by relocating all three into
 * `storage/framework/testing/`, which git already ignores wholesale — so an
 * interrupted run leaves nothing either gate can see, and no ignore rule has to
 * be added over a tracked source tree.
 *
 * This file covers the mechanism WITHOUT a browser, which is what makes the
 * coverage meaningful: it lives in `tests/Feature` so the default gate runs it,
 * where `tests/Browser` is excluded from `php artisan test` by design and would
 * only be exercised in the `browser` job the isolation exists to protect.
 *
 * The assertions deliberately run while isolation is still ACTIVE — no `end()` in
 * between. That is exactly the state a killed process leaves behind, so a suite
 * that never reaches teardown is the case being asserted rather than a case being
 * assumed.
 *
 * `begin()` re-roots process-wide Stache stores and the blueprint repository, which
 * only a test in the same process could see, and a process runs one test at a time.
 *
 * @command  php artisan test --compact tests/Feature/BrowserWriteIsolationTest.php
 */
uses(TestCase::class);

const BROWSER_ISOLATION_HANDLE = 'zz_isolation_probe_smoke';

/**
 * Persist the three artifacts a CP fieldtype smoke's `beforeEach` creates.
 *
 * Mirrors `CpFieldtypeSmokeTest` rather than paraphrasing it: the point of the
 * test is that THOSE writes are relocated, so drifting from their shape would
 * quietly stop covering them.
 *
 * @return string The email of the seeded super user.
 */
function persistBrowserSmokeArtifacts(): string
{
    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::make(BROWSER_ISOLATION_HANDLE);
    $collection->title('Isolation Probe');
    $collection->save();

    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::makeFromFields([
        'probe_field' => ['type' => 'text', 'display' => 'Probe Field'],
    ]);
    $blueprint->setHandle(BROWSER_ISOLATION_HANDLE);
    $blueprint->setNamespace('collections.'.BROWSER_ISOLATION_HANDLE);
    $blueprint->save();

    $email = 'zz-isolation-probe-'.uniqid().'@example.test';

    /** @var Statamic\Auth\User $user */
    $user = User::make();
    $user->email($email);
    $user->makeSuper();
    $user->password('zz-isolation-probe-password');
    $user->save();

    return $email;
}

/**
 * The throwaway root `BrowserWriteIsolation` writes under.
 *
 * Resolved through {@see ThrowawayRoot} — the same helper the class itself calls
 * — rather than hard-coded to a serial run's segment. A hard-coded one would make
 * "this run is serial" a silent precondition, and since UAMS-Web/uams-statamic-archive#1957 keys the serial
 * fallback on the process id there is no fixed string left to hard-code anyway.
 */
function browserIsolationRoot(): string
{
    return storage_path('framework/testing/browser-writes/'.ThrowawayRoot::segment());
}

/**
 * The directory a Stache store currently reads and writes.
 *
 * Narrowed by control flow rather than annotation: `StoreCollection::get()` is
 * typed nullable, and a missing store here would mean the assertions below were
 * comparing two nulls and passing on it.
 */
function browserIsolationStoreDirectory(string $key): string
{
    $store = Stache::stores()->get($key);

    if (! $store instanceof Store) {
        throw new RuntimeException(sprintf('The Stache "%s" store is not registered.', $key));
    }

    return (string) $store->directory();
}

/**
 * The tracked paths each artifact would land in without isolation.
 *
 * @return array<string, string> Description keyed to absolute path.
 */
function trackedBrowserSmokePaths(string $email): array
{
    return [
        'blueprint' => base_path('resources/blueprints/collections/'.BROWSER_ISOLATION_HANDLE),
        'collection' => base_path('content/collections/'.BROWSER_ISOLATION_HANDLE.'.yaml'),
        'user' => base_path('users/'.$email.'.yaml'),
    ];
}

it('keeps a smoke\'s collection, blueprint and user out of every tracked tree', function (): void {
    BrowserWriteIsolation::begin();

    try {
        $email = persistBrowserSmokeArtifacts();

        // Nothing in a tracked tree — asserted before any teardown, which is the
        // state a band killed at its bound leaves the worktree in.
        foreach (trackedBrowserSmokePaths($email) as $description => $path) {
            expect(File::exists($path))->toBeFalse("the {$description} was written into a tracked tree at {$path}");
        }

        // And the writes really happened, somewhere ignored. Without this the test
        // would pass just as well against a save that silently failed.
        $throwaway = browserIsolationRoot();

        expect(File::exists($throwaway.'/blueprints/collections/'.BROWSER_ISOLATION_HANDLE))->toBeTrue()
            ->and(File::exists($throwaway.'/collections/'.BROWSER_ISOLATION_HANDLE.'.yaml'))->toBeTrue()
            ->and(File::exists($throwaway.'/users/'.$email.'.yaml'))->toBeTrue();
    } finally {
        BrowserWriteIsolation::end();
    }
});

it('still resolves real collections and blueprints while isolated', function (): void {
    // The counterweight. Re-rooting to EMPTY directories would satisfy the test
    // above completely while putting a materially different application under the
    // browser: an empty CP collection listing, and front-end pages that 404. The
    // seeded copies are what make the isolation invisible to the smokes.
    $realCollections = Collection::handles()->all();
    $realBlueprintCount = \count(File::allFiles(base_path('resources/blueprints')));

    expect($realCollections)->not->toBeEmpty()
        ->and($realBlueprintCount)->toBeGreaterThan(0);

    BrowserWriteIsolation::begin();

    try {
        expect(Collection::handles()->all())->toEqualCanonicalizing($realCollections)
            ->and(\count(File::allFiles(Blueprint::directory())))->toBe($realBlueprintCount);
    } finally {
        BrowserWriteIsolation::end();
    }
});

it('restores the real directories and removes the throwaway root on teardown', function (): void {
    $realBlueprintDirectory = Blueprint::directory();
    $realCollectionsDirectory = browserIsolationStoreDirectory('collections');
    $realUsersDirectory = browserIsolationStoreDirectory('users');

    BrowserWriteIsolation::begin();

    expect(Blueprint::directory())->not->toBe($realBlueprintDirectory);

    BrowserWriteIsolation::end();

    // A leaked re-root is worse than no isolation: every later test in the process
    // would read and write a directory that has just been deleted.
    expect(Blueprint::directory())->toBe($realBlueprintDirectory)
        ->and(browserIsolationStoreDirectory('collections'))->toBe($realCollectionsDirectory)
        ->and(browserIsolationStoreDirectory('users'))->toBe($realUsersDirectory)
        ->and(File::exists(browserIsolationRoot()))->toBeFalse();
});

it('is safe to end without having begun', function (): void {
    // A smoke that fails inside `setUp` never reaches `begin()`, and the
    // directory-level `afterEach` runs regardless. Throwing here would replace the
    // real failure with a teardown error and hide it.
    expect(throwableFrom(fn () => BrowserWriteIsolation::end()))->toBeNull();
});
