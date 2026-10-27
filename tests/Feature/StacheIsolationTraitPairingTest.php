<?php

declare(strict_types=1);

use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Tests\Concerns\IsolatesStacheCache;
use Tests\Support\SchemaFixtureStacheTestCase;
use Tests\Support\StacheIsolatedTestCase;
use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#1876 — the two Stache-isolation traits must never be separated.
 *
 * {@see PreventsSavingStacheItemsToDisk} re-roots each store's **directory** at a
 * throwaway tree; {@see IsolatesStacheCache} re-roots the **cache store** those
 * stores index into. Applied alone, the first one is the UAMS-Web/uams-statamic-archive#1747 starvation: an
 * isolated test builds the index for a real collection handle out of its fixture
 * directory and persists it to the worker's shared Stache cache, where the next
 * test reads a one-entry `knowledge_bases` as authoritative.
 *
 * UAMS-Web/uams-statamic-archive#1747 fixed that by pairing them on both base cases, and UAMS-Web/uams-statamic-archive#1786 soaked it over
 * ten clean bands. UAMS-Web/uams-statamic-archive#1876 then asked, after a recurrence of the signature, whether
 * anything added since had re-opened the path — because **the leak needs only one
 * such class**. Auditing that by hand once answers it once; asserting it here
 * answers it for every future base case and every direct trait user, and turns
 * what would otherwise surface weeks later as an unattributable parallel flake
 * into an immediate, named failure.
 *
 * @command  php artisan test --compact tests/Feature/StacheIsolationTraitPairingTest.php
 */

/**
 * Every class declared under `tests/` — Pest test *files* declare none, so this is
 * the base cases, support classes, and concerns.
 *
 * The name is read out of the source and only then resolved. Deriving it from the
 * PSR-4 path and calling `class_exists()` looks equivalent and is not: that runs
 * the autoloader, and *including* a Pest test file registers its tests a second
 * time, which aborts the run with `A test with the description … already exists`.
 *
 * @return list<class-string> Class and trait names declared under `tests/`
 */
function declaredTestSupportClasses(): array
{
    $root = str_replace(DIRECTORY_SEPARATOR, '/', base_path('tests'));

    /** @var list<class-string> $classes */
    $classes = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        if (! is_string($contents)) {
            continue;
        }

        if (preg_match('/^namespace\s+([^;]+);/m', $contents, $namespaceMatch) !== 1) {
            continue;
        }

        // `preg_match_all`, not `preg_match`: a single match resolves only the FIRST
        // declaration per file, so a second class in the same file was never checked —
        // and `readonly` / `enum` had to be listed or a file leading with one dropped
        // out entirely, silently passing the whole check.
        if (preg_match_all('/^(?:abstract\s+|final\s+|readonly\s+)*(?:class|trait|enum)\s+(\w+)/m', $contents, $nameMatches) < 1) {
            continue;
        }

        foreach ($nameMatches[1] as $name) {
            $candidate = trim($namespaceMatch[1]).'\\'.$name;

            if (class_exists($candidate) || trait_exists($candidate) || enum_exists($candidate)) {
                /** @var class-string $candidate */
                $classes[] = $candidate;
            }
        }
    }

    sort($classes);

    return $classes;
}

it('pairs the directory isolation trait with the cache isolation trait everywhere', function (): void {
    $unpaired = [];
    $paired = [];

    foreach (declaredTestSupportClasses() as $class) {
        $traits = class_uses_recursive($class);

        if (! in_array(PreventsSavingStacheItemsToDisk::class, $traits, true)) {
            continue;
        }

        if (in_array(IsolatesStacheCache::class, $traits, true)) {
            $paired[] = $class;

            continue;
        }

        $unpaired[] = $class;
    }

    // Guard against a vacuous pass: a scan that resolved no classes, or a rename
    // that made the trait check silently match nothing, would satisfy the
    // assertion below for the wrong reason. Both known base cases must be seen.
    expect($paired)->toContain(StacheIsolatedTestCase::class)
        ->and($paired)->toContain(SchemaFixtureStacheTestCase::class);

    expect($unpaired)->toBe([], implode("\n", [
        'A test class re-roots the Stache store directories without re-rooting the Stache CACHE store.',
        'That is the #1747 starvation path: the fixture index for a real collection handle is written',
        'to the worker\'s shared cache, and the next test reads it as the real collection.',
        'Add `use Tests\Concerns\IsolatesStacheCache;` and call `isolateStacheCache()` before',
        '`preventSavingStacheItemsToDisk()`.',
    ]));
});

it('keeps the directory isolation trait inside the shared base cases', function (): void {
    $root = str_replace(DIRECTORY_SEPARATOR, '/', base_path('tests'));

    // The pairing assertion above reaches classes. A Pest test file declares none,
    // so a test calling the trait's method inline would slip past it — this closes
    // that door by requiring the call site itself to live in a shared base case.
    $callers = [];

    // Split so the literal never appears in this file, which the scan also reads —
    // otherwise the guard matches its own source and can never pass.
    $callSite = '->preventSavingStacheItemsToDisk'.'(';

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        if (! is_string($contents) || ! str_contains($contents, $callSite)) {
            continue;
        }

        $path = str_replace(DIRECTORY_SEPARATOR, '/', $file->getPathname());
        $callers[] = substr($path, strlen($root) + 1);
    }

    sort($callers);

    expect($callers)->toBe([
        'Support/SchemaFixtureStacheTestCase.php',
        'Support/StacheIsolatedTestCase.php',
    ], 'Re-rooting the Stache store directories belongs to a shared base case that also isolates the cache store (#1747, #1876).');
});
