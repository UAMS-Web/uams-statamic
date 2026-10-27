<?php

declare(strict_types=1);

/**
 * Guards the `pest-plugin-mutate` annotation-parsing patch (UAMS-Web/uams-statamic-archive#2327), which fixes three silent
 * `@pest-mutate-ignore` defects. Each assertion below reproduces one of them against the
 * VENDORED source, so the test fails if the patch stops being applied — the failure this
 * closes is that `composer install` does not re-patch an already-installed package, so a
 * reused `vendor/` keeps the unpatched code while the install reports success.
 *
 * Retire this with the patch, per #212.
 *
 * @command  php artisan test --compact tests/Feature/Config/MutateIgnorePatchTest.php
 */
$plugin = dirname(__DIR__, 3).'/vendor/pestphp/pest-plugin-mutate/src/Support';

/**
 * Read a vendored plugin source file, failing loudly when it cannot be read.
 *
 * The narrowing is the point: `file_get_contents()` returns `string|false`, and a runtime
 * expectation is not something the analyzer reads as narrowing, so asserting the type inside
 * the test would leave every downstream `strpos()` call typed against `false`.
 *
 * @param  string  $path  Absolute path to the vendored source file.
 * @return string Contents of the file.
 */
function mutateIgnorePatchSource(string $path): string
{
    $source = file_get_contents($path);

    if (! is_string($source)) {
        throw new RuntimeException("Unable to read the vendored source at [{$path}].");
    }

    return $source;
}

/**
 * Locate a needle in the vendored source, failing loudly when it is absent.
 *
 * @param  string  $source  File contents to search.
 * @param  string  $needle  Substring to locate.
 * @return int Byte offset of the needle's first occurrence.
 */
function mutateIgnorePatchOffset(string $source, string $needle): int
{
    $offset = strpos($source, $needle);

    if (! is_int($offset)) {
        throw new RuntimeException("Expected to find [{$needle}] in the vendored source.");
    }

    return $offset;
}

it('resets the ignore list per line so a bare marker cannot inherit the previous one', function () use ($plugin): void {
    $source = mutateIgnorePatchSource($plugin.'/MutationGenerator.php');

    // Defect 3: without this reset the variable survives the iteration and `?? ['all']`
    // can never fire once any `:`-form has been seen on an earlier line.
    expect($source)->toContain('$mutatorsToIgnore = null;');

    // The reset must precede the conditional assignment, or it clears what that assignment set.
    $reset = mutateIgnorePatchOffset($source, '$mutatorsToIgnore = null;');
    $assign = mutateIgnorePatchOffset($source, "\$mutatorsToIgnore = explode(',', Str::after(");

    expect($reset)->toBeLessThan($assign);
});

it('trims the captured tail before testing it for empty', function () use ($plugin): void {
    $source = mutateIgnorePatchSource($plugin.'/NodeVisitor.php');

    // Defects 1 and 2: `(.*)` captures the rest of the line, so a bare marker followed by a
    // space — or a docblock's own terminator — yields a non-empty tail that matches no
    // mutator and suppresses nothing, inverting the bare form on whitespace alone.
    expect($source)->toContain('$mutatorsToIgnore = trim($matches[1]');

    // The unpatched empty test reads the raw capture; the patched one reads the trimmed value.
    expect($source)->not->toContain("if (\$matches[1] === '') {");
});

it('still keys suppression on the mutator name, so an unknown name suppresses nothing', function () use ($plugin): void {
    $source = mutateIgnorePatchSource($plugin.'/NodeVisitor.php');

    // The negative case the patch must NOT change: a tail naming something that is not a
    // mutator still matches nothing. Without this the patch could "fix" the inert forms by
    // making every non-empty tail blanket-suppress, which is worse than the defect.
    expect($source)->toContain('in_array($this->mutator::name()');
});
