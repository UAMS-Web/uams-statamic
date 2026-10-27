<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The sibling of PublicFormControlCoverageGuardTest, for the NON-form Alpine
 * surfaces (UAMS-Web/uams-statamic-archive#2064). That guard is scoped to the public-form view tree by
 * construction; this one covers everything else and the repo as a whole.
 *
 * Why a guard at all: UAMS-Web/uams-statamic-archive#2056 and UAMS-Web/uams-statamic-archive#2060 both shipped a control whose markup was
 * present and whose handler never fired, and in both cases the only assertions
 * were on the markup string. A presence assertion cannot fail when a control
 * stops working, so the coverage that matters is behavioral and something has
 * to insist it exists.
 *
 * @command  php artisan test --compact tests/Feature/NonFormAlpineControlCoverageGuardTest.php
 */

/** The browser suite that must exercise each non-form control's behavior. */
const NON_FORM_CONTROL_SUITE = 'tests/Browser/NonFormAlpineControlsTest.php';

/**
 * The Alpine components rendered outside the public-form view tree, mapped to
 * EVERY view that declares each one. Adding a component here without exercising
 * it in the browser suite fails the coverage check below.
 *
 * The value is a list rather than a single path because a component is not
 * declared once (UAMS-Web/uams-statamic-archive#2281). `fadFilter` has five declarations across three views —
 * three of them in one file, with three different argument shapes — and a
 * `component => view` map could express none of that, so four of the five sat
 * outside this guard while it reported them covered.
 *
 * @return array<string, list<string>>
 */
function nonFormAlpineSurfaces(): array
{
    return [
        'galleryLightbox' => ['resources/views/sets/_gallery.antlers.html'],
        'liteVideo' => ['resources/views/sets/_video.antlers.html'],
        'fadFilter' => [
            'resources/views/clinical_expertise/section.antlers.html',
            'resources/views/page_builder/_uamsweb_block_fad_clinical_resources.antlers.html',
            'resources/views/page_builder/_uamsweb_block_fad_providers.antlers.html',
        ],
        'calculator' => ['resources/views/sets/_calculator.antlers.html'],
        'siteSearchTypeahead' => ['resources/views/search/index.antlers.html'],
    ];
}

/**
 * Whether `$source` declares `$component`, ignoring any Antlers comment.
 *
 * A declaration quoted in prose is not a declaration.
 * `page_builder/components/_block_fad_filter.antlers.html` describes the block it
 * belongs to inside a `{{# @desc … #}}` header and names `x-data="fadFilter"`
 * doing so; the survey that first counted these read it as a fourth declaring
 * view (UAMS-Web/uams-statamic-archive#2281). A scan that counts prose reports a view rendering no component.
 */
function alpineComponentIsDeclaredIn(string $source, string $component): bool
{
    $withoutComments = preg_replace('/\{\{#.*?#\}\}/s', '', $source) ?? $source;

    return str_contains($withoutComments, 'x-data="'.$component);
}

/**
 * Every view under `resources/views` that declares `$component`, sorted.
 *
 * @return list<string>
 */
function viewsDeclaringAlpineComponent(string $component): array
{
    $found = [];

    foreach (File::allFiles(base_path('resources/views')) as $file) {
        /** @var SplFileInfo $file */
        if (! alpineComponentIsDeclaredIn($file->getContents(), $component)) {
            continue;
        }

        // Normalized to forward slashes: `allFiles()` yields mixed separators on
        // Windows, so a platform-shaped literal would pass on one OS only.
        $found[] = str_replace(
            DIRECTORY_SEPARATOR,
            '/',
            str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname())
        );
    }

    sort($found);

    return $found;
}

it('has surfaces to guard, and each declares its component where this guard says it does', function (): void {
    // A guard over an empty or drifted set is not a guard. If a view moves, this
    // fails first and names it, rather than the checks below passing vacuously.
    expect(nonFormAlpineSurfaces())->not->toBeEmpty();

    foreach (nonFormAlpineSurfaces() as $component => $views) {
        expect($views)->not->toBeEmpty();

        foreach ($views as $view) {
            $path = base_path($view);

            // A message argument is deliberately absent: Pest reads a second argument to
            // `toContain()` as another NEEDLE, so passing prose there asserts the prose is
            // in the file and fails for the wrong reason.
            expect(File::exists($path))->toBeTrue();
            expect(File::get($path))->toContain('x-data="'.$component);
        }
    }
});

it('detects a real declaration and ignores one quoted inside an Antlers comment', function (): void {
    // ---
    // The PERFORMED control for the check below, which reports which views declare
    // a component. A short or empty result there is indistinguishable from a
    // scanner that cannot see, so the scanner is exercised on both arms first,
    // against source it is known to be right about.
    //
    // UAMS-Web/uams-statamic-archive#2064's own coverage check called its negative control "structural rather
    // than performed". That is the shape that let UAMS-Web/uams-statamic-archive#2281's defects through, so this
    // one is performed.
    // ---
    expect(alpineComponentIsDeclaredIn('<div x-data="fadFilter">', 'fadFilter'))->toBeTrue();
    expect(alpineComponentIsDeclaredIn('<div x-data="fadFilter([\'type\'])">', 'fadFilter'))->toBeTrue();

    expect(alpineComponentIsDeclaredIn('{{# wrapped in x-data="fadFilter" #}}', 'fadFilter'))->toBeFalse();
    expect(alpineComponentIsDeclaredIn('<div x-data="somethingElse">', 'fadFilter'))->toBeFalse();
});

it('lists every view declaring a guarded component, and no view that does not', function (): void {
    // ---
    // Defect 1 of UAMS-Web/uams-statamic-archive#2281. The map held ONE view per component, so four of
    // `fadFilter`'s five declarations sat outside this guard and adding a fifth
    // would have failed nothing. This compares the mapped set against the tree.
    //
    // It fails in both directions deliberately — a declaration in an unlisted
    // view, and a listed view that stopped declaring it — because a map that
    // drifts silently is precisely what is being fixed.
    // ---
    foreach (nonFormAlpineSurfaces() as $component => $views) {
        $expected = $views;
        sort($expected);

        expect(viewsDeclaringAlpineComponent($component))->toBe($expected);
    }
});

it('exercises every non-form Alpine component in the browser suite', function (): void {
    $suite = base_path(NON_FORM_CONTROL_SUITE);

    expect(File::exists($suite))->toBeTrue();

    $source = File::get($suite);

    // ---
    // The negative control is structural rather than performed: the expectation
    // reads the surface map, so dropping a component from the browser suite makes
    // this fail by construction. Verified by deleting the `liteVideo` arm locally,
    // which reds this test naming that component, and restoring it.
    // ---
    foreach (array_keys(nonFormAlpineSurfaces()) as $component) {
        expect($source)->toContain($component);
    }
});

it('declares no inline x-data object literal anywhere in the view tree', function (): void {
    // ---
    // The UAMS-Web/uams-statamic-archive#2060 defect, asserted repo-wide rather than for the form tree alone.
    // `x-data='{ items: [], add() {...} }'` is a CSP PARSE ERROR: the component
    // never exists, so the field rendered no inputs at all and submitted nothing.
    // The form guard covers its own directory; this covers everywhere else, so a
    // new surface cannot reintroduce it outside that tree.
    // ---
    $offenders = [];

    foreach (File::allFiles(base_path('resources/views')) as $file) {
        /** @var SplFileInfo $file */
        if (preg_match('/x-data\s*=\s*([\'"])\s*\{/', $file->getContents()) === 1) {
            // Normalized to forward slashes: `allFiles()` yields mixed separators on
            // Windows, so a platform-shaped literal below would pass on one OS only.
            $offenders[] = str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname())
            );
        }
    }

    // ---
    // INVERTED from UAMS-Web/uams-statamic-archive#2064's characterization, which pinned
    // `_calculator.antlers.html` as the one remaining offender. UAMS-Web/uams-statamic-archive#2247 converted
    // it to the registered `calculator` component, so the expected list is now
    // empty and this guards the whole view tree rather than pinning a known gap.
    //
    // Why UAMS-Web/uams-statamic-archive#2060's sweep reported zero while this file existed: it predates that
    // sweep by nearly three months (added 2026-05-27, swept 2026-08-19), so the
    // file was there to be found. UAMS-Web/uams-statamic-archive#2060's own offender was single-quoted --
    // `x-data='{` on the Lists fieldtype -- and the calculator is double-quoted,
    // so a pattern written from the defect in hand would not have matched it.
    // The regex above is quote-agnostic for exactly that reason.
    // ---
    expect($offenders)->toBe([]);
});
