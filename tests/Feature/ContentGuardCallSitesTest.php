<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\SkippedWithMessageException;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Symfony\Component\Finder\Finder;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class, RequiresContentSubmodule::class);

/**
 * Call-site guard for UAMS-Web/uams-statamic-archive#1738. `tests/Unit/ContentSubmoduleGuardTest.php` pins
 * the discriminator itself; this pins that the trait test authors actually reach
 * genuinely delegates to it. Without this, restoring `RequiresContentSubmodule`
 * to its pre-#1738 body (an unconditional `markTestSkipped()` on any `null`)
 * re-introduces the whole defect with a green suite: the helper's own tests keep
 * passing because nothing calls it. `KnowledgeBaseMountResolutionTest`'s
 * top-level function is covered by a sibling case in that file, since a Pest
 * file cannot be `require_once`d from here without re-registering its tests.
 *
 * The expectation is environment-agnostic on purpose. Which branch is correct
 * depends on whether this checkout has `content/`, so each case asserts the
 * branch the environment selects — hard-coding `FAIL` would red every bare
 * worktree, and hard-coding `SKIP` would be exactly the false green being
 * removed. In CI, where `content/` is always initialized, these assert the
 * failing branch.
 *
 * @command  php artisan test --compact tests/Feature/ContentGuardCallSitesTest.php
 */

/**
 * The exception class the guards must throw in this environment.
 *
 * @return class-string<Throwable> `SkippedWithMessageException` without `content/`, `AssertionFailedError` with it
 */
function expectedContentGuardThrowable(): string
{
    return ContentSubmodule::isInitialized()
        ? AssertionFailedError::class
        : SkippedWithMessageException::class;
}

it('routes the trait entry guard through the submodule discriminator', function (): void {
    try {
        $this->requireContentEntry(null, 'a probe entry');
    } catch (Throwable $thrown) {
        // Exact class: SkippedWithMessageException extends AssertionFailedError,
        // so instanceof would pass for either branch and prove nothing.
        expect($thrown::class)->toBe(expectedContentGuardThrowable());

        return;
    }

    $this->fail('requireContentEntry() returned on a null lookup.');
});

it('routes the trait collection guard through the submodule discriminator', function (): void {
    try {
        // A handle no collection will ever carry, so the lookup is null in every
        // environment and only the discriminator decides the outcome.
        $this->requireContentCollection('a_collection_that_does_not_exist');
    } catch (Throwable $thrown) {
        expect($thrown::class)->toBe(expectedContentGuardThrowable());

        return;
    }

    $this->fail('requireContentCollection() returned on a null lookup.');
});

it('keeps the qualifying-entry guard skipping regardless of the submodule', function (): void {
    // The counterpart guard must NOT harden: a content set that satisfies no
    // editorial precondition is a fact, not an infrastructure failure.
    try {
        $this->skipWithoutQualifyingContentEntry(null, 'no entry qualifies');
    } catch (Throwable $thrown) {
        expect($thrown::class)->toBe(SkippedWithMessageException::class);
        expect($thrown->getMessage())->toContain('No committed content qualifies');

        return;
    }

    $this->fail('skipWithoutQualifyingContentEntry() returned on a null lookup.');
});

it('returns a resolved entry rather than throwing', function (): void {
    $entry = Mockery::mock(EntryContract::class);

    // assertSame() rather than expect()->toBe(): the trait's methods are
    // invisible to PHPStan — see the method.notFound pin in phpstan.neon.dist —
    // so their return reads as mixed and expect()'s TValue cannot be resolved.
    $this->assertSame($entry, $this->requireContentEntry($entry, 'a probe entry'));
    $this->assertSame($entry, $this->skipWithoutQualifyingContentEntry($entry, 'a probe entry'));
});

/**
 * The shape UAMS-Web/uams-statamic-archive#1738 removed and UAMS-Web/uams-statamic-archive#1828 swept: a bare skip naming the `content/`
 * submodule. It reports *any* cause of an unresolvable lookup — a starved
 * Stache worker, a renamed handle, a fixture the content set never carried —
 * as a benign SKIP that the band still totals as PASS.
 *
 * Deliberately NOT matched, because they name a different condition and their
 * skip is correct: `Imagick is required to rasterize SVG.`, `Requires a
 * writable content path…`, the Formidable/Gravity Forms fixture-directory
 * guards, and the bare `<taxonomy>::<term> term missing` guards. That last
 * group includes the `subject_matter::cellulitis` pair, which UAMS-Web/uams-statamic-archive#1828 scoped out
 * explicitly: resolving them is a fork that moves the UAMS-Web/uams-statamic-archive#1744 parallel baseline
 * off 2.
 */
const CONTENT_SUBMODULE_SKIP_IDIOM = '/markTestSkipped\s*\(.*content\/?\s+submodule/i';

it('leaves no bare content-submodule skip guard in the suite', function (): void {
    $offenders = [];

    foreach (Finder::create()->files()->in(base_path('tests'))->name('*.php') as $file) {
        $path = 'tests/'.str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname());

        // The discriminator is where the idiom belongs; this file quotes it.
        if (in_array($path, ['tests/Support/ContentSubmodule.php', 'tests/Feature/ContentGuardCallSitesTest.php'], true)) {
            continue;
        }

        foreach (explode("\n", (string) file_get_contents($file->getPathname())) as $number => $line) {
            if (preg_match(CONTENT_SUBMODULE_SKIP_IDIOM, $line) === 1) {
                $offenders[] = $path.':'.($number + 1);
            }
        }
    }

    expect($offenders)->toBe([], sprintf(
        "Route these through Tests\Support\ContentSubmodule rather than skipping outright:\n  %s\n\n"
        ."reportMissing() skips only when content/ is genuinely absent and FAILS when it is present;\n"
        .'reportNoQualifyingContent() is the always-skip counterpart for an editorial precondition.',
        implode("\n  ", $offenders),
    ));
});
