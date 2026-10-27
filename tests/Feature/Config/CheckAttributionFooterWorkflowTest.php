<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/**
 * Pins the shape of `.github/workflows/check-attribution-footer.yml` (UAMS-Web/uams-statamic-archive#2701).
 *
 * The workflow fails a pull request whose body carries the harness attribution
 * footer that UAMS-Web/uams-statamic-archive#2321 forbids. Three of its properties are the contract, and each
 * fails silently when broken:
 *
 * - It fires on every event that can change the body, not only on a push. The
 *   footer most often arrives in the opening body, which a `synchronize`-only
 *   trigger never sees.
 * - It is not gated on `RUN_REMOTE_CI`, which is off for months at a time.
 * - It runs the check the `writing-pull-requests` skill ships, so the markers
 *   it matches live in one tested place rather than in the workflow's shell.
 *   The copy follows the skill's template (UAMS-Web/uams-statamic-archive#3107), and the path it names has to
 *   exist in this tree.
 *
 * A pure file read, so it boots no framework.
 *
 * @command  php artisan test --compact tests/Feature/Config/CheckAttributionFooterWorkflowTest.php
 */

/**
 * The parsed workflow.
 *
 * @return array<mixed> The workflow's top-level keys.
 */
function attributionFooterWorkflow(): array
{
    $workflow = Yaml::parseFile(dirname(__DIR__, 3).'/.github/workflows/check-attribution-footer.yml');

    // Fail with the reason rather than on a later offset read
    if (! is_array($workflow)) {
        throw new RuntimeException('check-attribution-footer.yml did not parse to a mapping');
    }

    return $workflow;
}

/**
 * The workflow's single job.
 *
 * @return array<mixed> The job's keys.
 */
function attributionFooterJob(): array
{
    $jobs = attributionFooterWorkflow()['jobs'] ?? null;

    // Require exactly one job, so a second, unguarded one cannot slip in
    if (! is_array($jobs) || count($jobs) !== 1) {
        throw new RuntimeException('check-attribution-footer.yml must declare exactly one job');
    }

    $job = reset($jobs);

    // Narrow the job to a mapping for the assertions that read it
    if (! is_array($job)) {
        throw new RuntimeException('check-attribution-footer.yml job is not a mapping');
    }

    return $job;
}

it('triggers on every pull request event that can change the body', function (): void {
    expect(attributionFooterWorkflow()['on'] ?? null)->toBe([
        'pull_request' => ['types' => ['opened', 'edited', 'reopened', 'ready_for_review', 'synchronize']],
    ]);
});

it('is not gated on RUN_REMOTE_CI', function (): void {
    $source = file_get_contents(dirname(__DIR__, 3).'/.github/workflows/check-attribution-footer.yml');

    // Search only the YAML itself, since the header comment names the variable to explain its absence
    $code = implode("\n", array_filter(
        explode("\n", (string) $source),
        fn (string $line): bool => ! str_starts_with(ltrim($line), '#'),
    ));

    expect($code)->not->toContain('RUN_REMOTE_CI');
});

it('grants only read access', function (): void {
    expect(attributionFooterWorkflow()['permissions'] ?? null)->toBe([])
        ->and(attributionFooterJob()['permissions'] ?? null)->toBe(['contents' => 'read', 'pull-requests' => 'read']);
});

it('reads the body from the API and runs the skill check on it', function (): void {
    $steps = attributionFooterJob()['steps'] ?? [];
    $run = '';

    // Collect every run block, so the assertion does not depend on step order
    foreach (is_array($steps) ? $steps : [] as $step) {
        if (is_array($step) && is_string($step['run'] ?? null)) {
            $run .= $step['run'];
        }
    }

    $script = '.claude/skills/writing-pull-requests/scripts/check-attribution-footer.mjs';

    expect($run)->toContain('gh api "repos/$REPO/pulls/$NUMBER"')
        ->and($run)->toContain("node {$script} ")
        ->and(dirname(__DIR__, 3).'/'.$script)->toBeFile();
});
