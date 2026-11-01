<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tests\Support\BrowserSubmodules;
use Tests\Support\ThrowawayRoot;
use Tests\TestCase;

/**
 * The missing-submodule guard for browser runs (#378).
 *
 * Driven against a throwaway checkout root rather than the real tree, so the
 * failing arms can be watched without de-initializing this checkout's own
 * submodules. Each arm is two-sided: a root with everything present must pass,
 * and each absent submodule must be named in the failure.
 *
 * @command  php artisan test --compact tests/Feature/BrowserSubmodulesTest.php
 */
uses(TestCase::class);

$root = '';

beforeEach(function () use (&$root): void {
    $root = storage_path('framework/testing/browser-submodules-'.ThrowawayRoot::segment());
    File::deleteDirectory($root);

    // Populate every required mount point, as an initialized checkout has them
    foreach (BrowserSubmodules::REQUIRED as $path) {
        File::ensureDirectoryExists($root.'/'.$path);
        File::put($root.'/'.$path.'/.git', "gitdir: elsewhere\n");
    }
});

afterEach(function () use (&$root): void {
    if ($root !== '' && File::isDirectory($root)) {
        File::deleteDirectory($root);
    }
});

it('passes when every required submodule is populated', function () use (&$root): void {
    /** @var string $root */
    expect(BrowserSubmodules::missing($root))->toBe([])
        ->and(throwableFrom(fn () => BrowserSubmodules::assertPresent($root)))->toBeNull();
});

it('names every absent submodule and the command, whether the mount point is empty or missing', function () use (&$root): void {
    /** @var string $root */
    // An uninitialized submodule leaves an empty directory; a checkout that never
    // created the mount point leaves none. Both are absent.
    File::deleteDirectory($root.'/content');
    File::ensureDirectoryExists($root.'/content');
    File::deleteDirectory($root.'/users');
    File::deleteDirectory($root.'/resources/forms');

    $thrown = throwableFrom(fn () => BrowserSubmodules::assertPresent($root));

    expect($thrown)->toBeInstanceOf(RuntimeException::class)
        ->and($thrown?->getMessage())->toBe(
            'The browser suite needs the content, users, resources/forms submodules, which are not initialized in this checkout. '
            .'Every Control Panel smoke boots the application from them and would fail on whatever '
            .'it reaches first (a missing collection, asset container or login user) rather than on this. '
            .'Run: git submodule update --init content users resources/forms'
        );
});

it('names only the submodule that is absent', function () use (&$root): void {
    /** @var string $root */
    File::deleteDirectory($root.'/users');

    $thrown = throwableFrom(fn () => BrowserSubmodules::assertPresent($root));

    expect(BrowserSubmodules::missing($root))->toBe(['users'])
        ->and($thrown?->getMessage())->toContain('needs the users submodule, which is not initialized')
        ->and($thrown?->getMessage())->toEndWith('Run: git submodule update --init users');
});

it('names a content mount point holding only the files statamic:install scaffolds', function () use (&$root): void {
    /** @var string $root */
    // Running `composer install` before the submodule init leaves this tree, and
    // the init then refuses the non-empty directory, so `content` is never cloned.
    File::deleteDirectory($root.'/content');

    foreach (['assets', 'collections', 'globals', 'navigation', 'taxonomies'] as $directory) {
        File::ensureDirectoryExists($root.'/content/'.$directory);
        File::put($root.'/content/'.$directory.'/.gitkeep', '');
    }

    $thrown = throwableFrom(fn () => BrowserSubmodules::assertPresent($root));

    expect(BrowserSubmodules::missing($root))->toBe(['content'])
        ->and($thrown?->getMessage())->toContain('needs the content submodule, which is not initialized')
        ->and($thrown?->getMessage())->toEndWith('Run: git submodule update --init content');
});

it('names a mount point holding only output an earlier run stranded', function () use (&$root): void {
    /** @var string $root */
    // Real files rather than placeholders, so this is what a test for `.gitkeep`
    // alone would pass, and what `scripts/local-ci.mjs` clears as app output.
    File::delete($root.'/users/.git');
    File::put($root.'/users/stranded.yaml', "email: stranded@example.com\n");

    expect(BrowserSubmodules::missing($root))->toBe(['users']);
});

it('accepts a submodule whose git directory is a directory rather than a gitlink file', function () use (&$root): void {
    /** @var string $root */
    File::delete($root.'/users/.git');
    File::ensureDirectoryExists($root.'/users/.git');

    expect(BrowserSubmodules::missing($root))->toBe([]);
});
