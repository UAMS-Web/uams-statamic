<?php

declare(strict_types=1);

use App\Support\Imaging\SvgHazardScanner;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * At-rest scan over every stored SVG (UAMS-Web/uams-statamic-archive#1739).
 *
 * Statamic sanitizes SVG on upload, but the sanitizer only sees files arriving
 * *through* Statamic. `public/assets` is a separate submodule repository where a
 * file can be committed directly — which is how the 64 SVGs in the `logos`
 * container got there. This test covers that path.
 *
 * ## The coverage gap, named
 *
 * CI does **not** check out the `public/assets` submodule — `.github/workflows/ci.yml`
 * initializes only `content/` and the 2 KB `users/` (UAMS-Web/uams-statamic-archive#2477), deliberately, to keep
 * checkout cheap. So this test **skips** on CI and runs author-side and in any
 * checkout with the submodule populated. It is a real check that a gate does not
 * currently run.
 *
 * That is why the scanner's own logic is covered separately and unconditionally
 * in tests/Unit/Support/Imaging/SvgHazardScannerTest.php: the part that can
 * regress silently (detection) is gated everywhere, and only the corpus sweep
 * depends on the submodule. Checking `public/assets` out in CI would close the
 * gap at the cost of a binary-heavy clone on every run — see UAMS-Web/uams-statamic-archive#1739.
 *
 * @command  php artisan test --compact tests/Feature/Assets/StoredSvgHazardScanTest.php
 */
uses(TestCase::class);

it('stores no svg carrying script, a handler, or embedded foreign content', function (): void {
    // Resolve the web-served asset root
    $root = public_path('assets');

    // Skip when the public/assets submodule is not checked out (CI, fresh worktrees)
    if (! File::isDirectory($root)) {
        $this->markTestSkipped('The public/assets submodule is not checked out.');
    }

    // Collect every stored SVG beneath the asset root
    $svgs = collect(File::allFiles($root))
        ->filter(fn ($file): bool => strtolower($file->getExtension()) === 'svg')
        ->values();

    // Skip when the submodule is present but empty rather than passing vacuously
    if ($svgs->isEmpty()) {
        $this->markTestSkipped('The public/assets submodule contains no SVG files.');
    }

    // Scan each stored SVG and keep only the files that produced findings
    $scanner = new SvgHazardScanner;

    $hazardous = $svgs
        ->map(fn ($file): array => [
            'path' => str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname()),
            'findings' => $scanner->scan((string) File::get($file->getPathname())),
        ])
        ->filter(fn (array $result): bool => $result['findings'] !== [])
        ->map(fn (array $result): string => sprintf(
            '%s — %s',
            $result['path'],
            implode(' ', array_column($result['findings'], 'detail'))
        ))
        ->values()
        ->all();

    expect($hazardous)->toBe([], 'these stored SVGs carry script-bearing constructs');
});
