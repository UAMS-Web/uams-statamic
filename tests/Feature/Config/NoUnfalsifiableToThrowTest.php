<?php

declare(strict_types=1);

/**
 * Guards against the two `toThrow()` forms that cannot fail as they read (UAMS-Web/uams-statamic-archive#2964, UAMS-Web/uams-statamic-archive#1759).
 *
 * `expect($fn)->not->toThrow(X::class)` passes whenever `$fn` throws something that is not an
 * `X`, so it misses a `TypeError` or a call on null; and `toThrow()` treats an interface such as
 * `Throwable` as a message substring, so `->not->toThrow(Throwable::class)` passes for any
 * exception whose message lacks the word. `throwableFrom()` in tests/Support/Throwables.php is the
 * form that can fail: `expect(throwableFrom(fn () => ...))->toBeNull()`.
 *
 * The scan reads code lines only. Comment and docblock lines are skipped, because the files that
 * explain the trap quote the idiom.
 *
 * @command  php artisan test --compact tests/Feature/Config/NoUnfalsifiableToThrowTest.php
 */

/**
 * The code lines in `$source` that use a `toThrow()` form that cannot fail as it reads.
 *
 * @return list<string>
 */
function unfalsifiableToThrowLines(string $source): array
{
    $found = [];

    foreach (preg_split('/\R/', $source) ?: [] as $line) {
        $trimmed = ltrim($line);

        // Skip comment and docblock lines, which may quote the idiom to explain it.
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*')) {
            continue;
        }

        if (str_contains($line, '->not->toThrow(') || preg_match('/toThrow\(\s*\\\\?Throwable::class/', $line) === 1) {
            $found[] = trim($line);
        }
    }

    return $found;
}

it('finds each unfalsifiable form, and nothing in comments or the safe form', function (): void {
    $source = <<<'PHP'
        expect(fn () => $a())->not->toThrow(Exception::class);
        expect(fn () => $b())
            ->not->toThrow(TypeError::class);
        expect(fn () => $c())->toThrow(Throwable::class);
        expect(fn () => $d())->toThrow(\Throwable::class, 'boom');
        // `->not->toThrow(…)` passes on the wrong throwable.
         * expect(fn () => $e())->not->toThrow(Exception::class);
        expect(throwableFrom(fn () => $f()))->toBeNull();
        expect(fn () => $g())->toThrow(RuntimeException::class, 'boom');
        PHP;

    expect(unfalsifiableToThrowLines($source))->toBe([
        'expect(fn () => $a())->not->toThrow(Exception::class);',
        '->not->toThrow(TypeError::class);',
        'expect(fn () => $c())->toThrow(Throwable::class);',
        "expect(fn () => \$d())->toThrow(\\Throwable::class, 'boom');",
    ]);
});

it('finds no unfalsifiable toThrow() form in the test suite', function (): void {
    $root = dirname(__DIR__, 3).'/tests';
    $offenders = [];
    $scanned = 0;

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php' || $file->getRealPath() === __FILE__) {
            continue;
        }

        $scanned++;
        $source = file_get_contents($file->getPathname());

        foreach (unfalsifiableToThrowLines(is_string($source) ? $source : '') as $line) {
            $offenders[] = substr($file->getPathname(), strlen($root) + 1).': '.$line;
        }
    }

    // A scan over nothing would pass; the suite holds well over a thousand PHP files.
    expect($scanned)->toBeGreaterThan(1000)
        ->and($offenders)->toBe([]);
});
