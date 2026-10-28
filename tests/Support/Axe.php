<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Accessibility (axe-core) smoke helpers (issue UAMS-Web/uams-statamic-archive#1046)
|--------------------------------------------------------------------------
|
| pestphp/pest-plugin-browser bundles axe-core and injects `window.axe` into
| every page it loads, so no npm dependency is needed. These helpers run a scan
| scoped to the automatable WCAG 2.0/2.1 A & AA rule sets, surface the FULL
| violation report (every impact level) in the job output for visibility, and
| fail only on `serious`/`critical` — the automatable failures the §504 gate
| must stop from merging. `moderate`/`minor` are reported, not failed, so the
| gate can be ratcheted later (#54).
|
| Run via the opt-in browser suite:  vendor/bin/pest tests/Browser
|
*/

use Pest\Browser\Api\ArrayablePendingAwaitablePage;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Api\Webpage;

/**
 * Replace the ENTIRE served document with a controlled HTML5 document whose body
 * is the given markup, so axe scans only that markup. Replacing the whole
 * `documentElement` (not just the body) discards the served page's residual
 * `<head>` and any live Control-Panel SPA mount, which would otherwise make the
 * scan order-dependent (e.g. a prior test's login redirects /cp to the dashboard
 * SPA). `window.axe` lives on `window`, so it survives the replacement.
 *
 * @param  Webpage|PendingAwaitablePage|ArrayablePendingAwaitablePage  $page  The page returned from visit()/navigate().
 * @param  string  $bodyHtml  Inner markup for the document <body>.
 */
function loadAccessibilityFixture($page, string $bodyHtml): void
{
    $page->script(
        '() => { document.documentElement.lang = "en";'
        .' document.documentElement.innerHTML = '
        .json_encode('<head><meta charset="utf-8"><title>Accessibility fixture</title></head><body>')
        .' + '.json_encode($bodyHtml).' + '.json_encode('</body>').'; }'
    );
}

/**
 * Run axe-core against the current page DOM, scoped to the WCAG 2.0/2.1 A & AA
 * tag sets, and return the raw violations array. Each violation has at least
 * `id`, `impact` (critical|serious|moderate|minor), `help`, and `nodes`.
 *
 * @param  Webpage|PendingAwaitablePage|ArrayablePendingAwaitablePage  $page  The page returned from visit()/navigate().
 * @return array<int, array<string, mixed>>
 */
function runAxe($page): array
{
    $violations = $page->script(
        'async () => (await window.axe.run(document, {'
        .'runOnly: {type: "tag", values: ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa"]}'
        .'})).violations'
    );

    if (! is_array($violations)) {
        return [];
    }

    /** @var array<int, array<string, mixed>> $violations */
    return $violations;
}

/**
 * Assert the current page has no `serious` or `critical` axe violations, after
 * writing a full report of every violation (including the non-failing
 * `moderate`/`minor` ones) to STDERR so they surface in the CI job output and
 * can be ratcheted into the gate later.
 *
 * @param  Webpage|PendingAwaitablePage|ArrayablePendingAwaitablePage  $page  The page returned from visit()/navigate().
 * @param  string  $context  A label for the scanned surface (shown in output).
 */
function assertNoSeriousAccessibilityViolations($page, string $context): void
{
    $violations = runAxe($page);

    // Surface the full report — every impact level — so it is visible even when
    // the gate passes (AC: report moderate/minor without failing).
    $lines = ['[axe] '.$context.' — '.count($violations).' violation(s):'];
    foreach (['critical', 'serious', 'moderate', 'minor'] as $impact) {
        foreach ($violations as $violation) {
            if (($violation['impact'] ?? null) !== $impact) {
                continue;
            }

            /** @var string $id */
            $id = $violation['id'] ?? '?';
            /** @var array<int, mixed> $nodes */
            $nodes = $violation['nodes'] ?? [];
            /** @var string $help */
            $help = $violation['help'] ?? '';

            $lines[] = sprintf(
                '  [%s] %s (%d node(s)): %s',
                $impact,
                $id,
                count($nodes),
                $help
            );
        }
    }
    fwrite(STDERR, implode("\n", $lines)."\n");

    $blocking = array_values(array_filter(
        $violations,
        fn (array $violation): bool => in_array($violation['impact'] ?? null, ['critical', 'serious'], true)
    ));

    $report = implode("\n", array_map(
        function (array $violation): string {
            /** @var string $id */
            $id = $violation['id'] ?? '?';
            /** @var string $help */
            $help = $violation['help'] ?? '';

            // The array_filter above kept only impacts in [critical, serious], so
            // `impact` is guaranteed present here.
            return sprintf('  - [%s] %s: %s', $violation['impact'], $id, $help);
        },
        $blocking
    ));

    expect($blocking)->toBeEmpty(
        sprintf("Found %d serious/critical accessibility violation(s) on [%s]:\n%s", count($blocking), $context, $report)
    );
}
