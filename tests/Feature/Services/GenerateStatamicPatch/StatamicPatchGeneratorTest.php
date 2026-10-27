<?php

declare(strict_types=1);

use App\Services\GenerateStatamicPatch\StatamicPatchGenerator;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Exercises the patch generator end-to-end against a hermetic fixture tree.
 *
 * The service's only coupling to the framework is `base_path()`, which it uses to
 * locate `patches/src/<basename>.{original,patched}.php`, the vendored target under
 * `vendor/statamic/cms/`, and the `patches/<basename>.patch` output. Each test
 * scaffolds those files under a unique temp base and points `base_path()` there for
 * the duration of the call only (see {@see spgRun}), so the real `git diff` code
 * path runs without touching the repository's actual `patches/` or `vendor/` trees.
 *
 * @covers \App\Services\GenerateStatamicPatch\StatamicPatchGenerator
 *
 * @command  php artisan test --compact tests/Feature/Services/GenerateStatamicPatch/StatamicPatchGeneratorTest.php
 */
beforeEach(function (): void {
    // A fresh, uniquely-named fixture base per test keeps runs parallel-safe and
    // lets afterEach delete exactly this test's tree (no cross-worker glob race).
    $this->spgBase = sys_get_temp_dir().'/statamic-patch-gen-test-'.bin2hex(random_bytes(8));
});

afterEach(function (): void {
    $base = $this->spgBase ?? null;
    if (is_string($base) && is_dir($base)) {
        spgRemoveDir($base);
    }
});

/**
 * Render a minimal single-method PHP class source with the given method body.
 */
function spgClassSource(
    string $className,
    string $methodBody,
    string $methodName = 'createLabel',
    ?string $namespace = null,
    bool $isStatic = false
): string {
    $namespaceLine = $namespace !== null ? "namespace {$namespace};\n\n" : '';
    $staticKeyword = $isStatic ? 'static ' : '';

    return "<?php\n\n{$namespaceLine}class {$className}\n{\n"
        ."    public {$staticKeyword}function {$methodName}(string \$label): string\n    {\n"
        ."{$methodBody}\n    }\n}\n";
}

/**
 * Materialize the two canonical snippets + the vendored target file under the
 * fixture base, and return the [basename, targetDiffPath] pair the generator needs.
 *
 * @return array{0: string, 1: string}
 */
function spgScaffold(
    string $base,
    string $originalBody,
    string $patchedBody,
    string $vendorBody,
    string $targetDiffPath = 'src/CreateLabelHost.php',
    string $methodName = 'createLabel',
    bool $isStatic = false
): array {
    $basename = 'test-create-label-patch';

    mkdir($base.'/patches/src', 0777, true);
    mkdir($base.'/vendor/statamic/cms/'.dirname($targetDiffPath), 0777, true);

    file_put_contents(
        $base.'/patches/src/'.$basename.'.original.php',
        spgClassSource('OriginalSnippet', $originalBody, $methodName, null, $isStatic)
    );
    file_put_contents(
        $base.'/patches/src/'.$basename.'.patched.php',
        spgClassSource('PatchedSnippet', $patchedBody, $methodName, null, $isStatic)
    );
    file_put_contents(
        $base.'/vendor/statamic/cms/'.$targetDiffPath,
        spgClassSource('CreateLabelHost', $vendorBody, $methodName, 'Statamic\\Vendor', $isStatic)
    );

    return [$basename, $targetDiffPath];
}

/**
 * Run a closure with `base_path()` pointed at the fixture tree, restoring the real
 * base path afterward even if the closure throws.
 *
 * @template TReturn
 *
 * @param  Closure(): TReturn  $fn
 * @return TReturn
 */
function spgRun(string $base, Closure $fn): mixed
{
    $originalBasePath = base_path();
    app()->setBasePath($base);

    try {
        return $fn();
    } finally {
        app()->setBasePath($originalBasePath);
    }
}

/**
 * Recursively delete a directory of plain files (no `.git` locks to contend with).
 */
function spgRemoveDir(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($entries as $entry) {
        /** @var SplFileInfo $entry */
        if ($entry->isDir()) {
            @rmdir($entry->getPathname());
        } else {
            @unlink($entry->getPathname());
        }
    }

    @rmdir($path);
}

it('generates a patch git can apply from an unpatched vendor file', function (): void {
    /** @var string $base */
    $base = $this->spgBase;

    // Vendor matches the original snippet (patches not yet applied): the generator
    // diffs the original method body against the patched one.
    [$basename, $target] = spgScaffold(
        $base,
        originalBody: '        return $label;',
        patchedBody: '        return strtoupper($label);',
        vendorBody: '        return $label;',
    );

    $patchPath = spgRun(
        $base,
        fn (): string => (new StatamicPatchGenerator)->generatePatchFromValues($basename, $target)
    );

    expect($patchPath)->toBe($base.'/patches/'.$basename.'.patch')
        ->and(is_file($patchPath))->toBeTrue();

    $patch = (string) file_get_contents($patchPath);

    // A unified git diff turning the original body into the patched one.
    expect($patch)->toContain('a/'.$target)
        ->and($patch)->toContain('b/'.$target)
        ->and($patch)->toContain('@@')
        ->and($patch)->toContain('-        return $label;')
        ->and($patch)->toContain('+        return strtoupper($label);')
        ->and($patch)->toEndWith("\n");
});

it('reconstructs the upstream baseline when the vendor file is already patched', function (): void {
    /** @var string $base */
    $base = $this->spgBase;

    // Vendor already carries the patched body; the generator must rebuild the
    // unpatched baseline from the original snippet before diffing (the other branch).
    [$basename, $target] = spgScaffold(
        $base,
        originalBody: '        return $label;',
        patchedBody: '        return strtoupper($label);',
        vendorBody: '        return strtoupper($label);',
    );

    $patchPath = spgRun(
        $base,
        fn (): string => (new StatamicPatchGenerator)->generatePatchFromValues($basename, $target)
    );

    $patch = (string) file_get_contents($patchPath);

    // The reconstructed diff is still original -> patched.
    expect($patch)->toContain('-        return $label;')
        ->and($patch)->toContain('+        return strtoupper($label);');
});

it('throws when the vendor method matches neither snippet variant', function (): void {
    /** @var string $base */
    $base = $this->spgBase;

    [$basename, $target] = spgScaffold(
        $base,
        originalBody: '        return $label;',
        patchedBody: '        return strtoupper($label);',
        vendorBody: '        return trim($label);',
    );

    expect(fn (): string => spgRun(
        $base,
        fn (): string => (new StatamicPatchGenerator)->generatePatchFromValues($basename, $target)
    ))->toThrow(RuntimeException::class, 'does not match either the original or patched snippet');
});

it('throws when required generator values are empty', function (): void {
    // Validation runs before any file I/O, so no fixture tree is needed.
    expect(fn (): string => (new StatamicPatchGenerator)->generatePatchFromValues('', 'src/Anything.php', 'createLabel'))
        ->toThrow(RuntimeException::class, 'Missing required patch-generation values');
});

it('throws when the patched snippet is identical to the original', function (): void {
    /** @var string $base */
    $base = $this->spgBase;

    // original === patched === vendor: substitution yields no change to diff.
    [$basename, $target] = spgScaffold(
        $base,
        originalBody: '        return $label;',
        patchedBody: '        return $label;',
        vendorBody: '        return $label;',
    );

    expect(fn (): string => spgRun(
        $base,
        fn (): string => (new StatamicPatchGenerator)->generatePatchFromValues($basename, $target)
    ))->toThrow(RuntimeException::class, 'Patched method is identical to the original snippet');
});

it('throws when the snippet is missing the requested method', function (): void {
    /** @var string $base */
    $base = $this->spgBase;

    [$basename, $target] = spgScaffold(
        $base,
        originalBody: '        return $label;',
        patchedBody: '        return strtoupper($label);',
        vendorBody: '        return $label;',
    );

    // Overwrite the original snippet with a class that lacks createLabel().
    file_put_contents(
        $base.'/patches/src/'.$basename.'.original.php',
        spgClassSource('OriginalSnippet', '        return $label;', 'renamedLabel')
    );

    expect(fn (): string => spgRun(
        $base,
        fn (): string => (new StatamicPatchGenerator)->generatePatchFromValues($basename, $target)
    ))->toThrow(RuntimeException::class, 'Unable to find createLabel() in the given PHP source');
});

it('throws when the target method text appears more than once in the vendor file', function (): void {
    /** @var string $base */
    $base = $this->spgBase;

    [$basename, $target] = spgScaffold(
        $base,
        originalBody: '        return $label;',
        patchedBody: '        return strtoupper($label);',
        vendorBody: '        return strtoupper($label);',
    );

    // Two identical (patched) methods: rebuilding the baseline can't uniquely locate
    // the method text to replace, so replaceUniqueMethodSubstring() bails.
    $duplicated = "<?php\n\nnamespace Statamic\\Vendor;\n\nclass CreateLabelHost\n{\n"
        ."    public function createLabel(string \$label): string\n    {\n        return strtoupper(\$label);\n    }\n\n"
        ."    public function createLabel(string \$label): string\n    {\n        return strtoupper(\$label);\n    }\n}\n";

    file_put_contents($base.'/vendor/statamic/cms/'.$target, $duplicated);

    expect(fn (): string => spgRun(
        $base,
        fn (): string => (new StatamicPatchGenerator)->generatePatchFromValues($basename, $target)
    ))->toThrow(RuntimeException::class, 'Ambiguous method match');
});

it('extracts a static method declaration, not only an instance one', function (): void {
    /** @var string $base */
    $base = $this->spgBase;

    // Statamic declares `ModifierManager::evaluate()` static (UAMS-Web/uams-statamic-archive#1673). Before the
    // pattern admitted an optional `static`, the declaration scan found nothing and
    // the generator aborted with "Unable to find …() in the given PHP source" — a
    // message that reads as a missing method rather than an unsupported modifier.
    [$basename, $target] = spgScaffold(
        $base,
        originalBody: '        return $label;',
        patchedBody: '        return strtoupper($label);',
        vendorBody: '        return $label;',
        isStatic: true,
    );

    $patchPath = spgRun(
        $base,
        fn (): string => (new StatamicPatchGenerator)->generatePatchFromValues($basename, $target)
    );

    expect($patchPath)->toBeFile();

    $patch = file_get_contents($patchPath);
    $patch = is_string($patch) ? $patch : '';

    // The diff carries the change, and its context line proves the static
    // declaration is what was located rather than some other method.
    expect($patch)->toContain('-        return $label;');
    expect($patch)->toContain('+        return strtoupper($label);');
    expect($patch)->toContain('public static function createLabel');
});
