<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Feature tests for Composer patch lock integrity.
 *
 * Ensures each SHA-256 in patches.lock.json matches the corresponding patch file
 * on disk so cweagans/composer-patches does not reject installs after a drift
 * between committed patches and lock metadata.
 *
 * No mutable PHP surface: this test reads patches.lock.json and the patch files
 * directly from disk; there is no backing App\ class (no Mutation target).
 *
 * @command  php artisan test --compact tests/Feature/PatchesLockChecksumTest.php
 */
it('asserts every locklisted patch file exists and matches its sha256', function (): void {
    // Build path to the lock file
    $lockPath = base_path('patches.lock.json');

    // Assert the lock file exists
    expect($lockPath)->toBeFile();

    // Read the lock file contents from disk
    $lockContents = file_get_contents($lockPath);

    // Narrow the read result to a string for json_decode
    $lockContents = is_string($lockContents) ? $lockContents : '';

    // Load the lock file contents
    $lock = json_decode($lockContents, true);

    // Assert the lock file contents is an array
    expect($lock)->toBeArray();

    // Assert the lock file contents has a 'patches' key
    expect($lock)->toHaveKey('patches');

    /** @var array<string, mixed> $lock */
    $patches = $lock['patches'];

    // Narrow the patches map to an iterable of package patch groups
    /** @var array<string, array<int, array<string, mixed>>> $patches */

    // Loop through the patches
    foreach ($patches as $packagePatches) {
        // Loop through the package patches
        foreach ($packagePatches as $patchMeta) {
            // Extract the relative path to the patch file
            $relativePath = $patchMeta['url'];

            // Narrow the relative path to a string
            $relativePath = is_string($relativePath) ? $relativePath : '';

            // Build the absolute path to the patch file
            $patchPath = base_path($relativePath);

            // Assert the patch file exists on disk
            Assert::assertFileExists(
                $patchPath,
                "Patch file missing for lock entry: {$relativePath}"
            );

            // Assert the patch file SHA-256 matches the lock file entry value
            Assert::assertSame(
                $patchMeta['sha256'],
                hash_file('sha256', $patchPath),
                "Run `composer patches-relock` after changing {$relativePath}."
            );
        }
    }
});
