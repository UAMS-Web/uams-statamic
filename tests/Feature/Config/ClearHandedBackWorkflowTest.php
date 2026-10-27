<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/**
 * Pins the shape of `.github/workflows/clear-handed-back.yml` (UAMS-Web/uams-statamic-archive#2667).
 *
 * The workflow removes the gate's `handed-back` label when a pull request
 * carrying it is pushed to. Three of its properties are the whole contract,
 * and each fails silently when broken:
 *
 * - It fires on `pull_request` `synchronize` and `edited` only. Any wider
 *   trigger lets opening a pull request, applying the label or a comment
 *   clear it.
 * - Its job runs only when the label is present, so a push to an unlabeled
 *   pull request is skipped rather than spending a runner. An `edited` event
 *   clears it only when the body changed and the sender is not a bot, so a
 *   title-only edit, or a bot rewriting the body, leaves it in place.
 * - It is not gated on `RUN_REMOTE_CI`, unlike every job in `ci.yml`. That
 *   switch is off for months at a time, and gating on it would bring back the
 *   missed removal for exactly that long.
 *
 * The workflow is a byte copy of the synced `handed-back-label` skill's
 * template, which is shared with `uamswp-migration-api` and
 * `wordpress-importer`. A pure file read, so it boots no framework.
 *
 * @command  php artisan test --compact tests/Feature/Config/ClearHandedBackWorkflowTest.php
 */

/**
 * The parsed workflow.
 *
 * @return array<mixed> The workflow's top-level keys.
 */
function clearHandedBackWorkflow(): array
{
    $workflow = Yaml::parseFile(dirname(__DIR__, 3).'/.github/workflows/clear-handed-back.yml');

    // Fail with the reason rather than on a later offset read
    if (! is_array($workflow)) {
        throw new RuntimeException('clear-handed-back.yml did not parse to a mapping');
    }

    return $workflow;
}

/**
 * The workflow's single job.
 *
 * @return array<mixed> The job's keys.
 */
function clearHandedBackJob(): array
{
    $jobs = clearHandedBackWorkflow()['jobs'] ?? null;

    // Require exactly one job, so a second, unguarded one cannot slip in
    if (! is_array($jobs) || count($jobs) !== 1) {
        throw new RuntimeException('clear-handed-back.yml must declare exactly one job');
    }

    $job = reset($jobs);

    // Narrow the job to a mapping for the assertions that read it
    if (! is_array($job)) {
        throw new RuntimeException('clear-handed-back.yml job is not a mapping');
    }

    return $job;
}

it('triggers on a pull request push or edit and on nothing else', function (): void {
    expect(clearHandedBackWorkflow()['on'] ?? null)->toBe(['pull_request' => ['types' => ['synchronize', 'edited']]]);
});

it('runs its job only when the pull request carries handed-back', function (): void {
    expect(clearHandedBackJob()['if'] ?? null)->toStartWith("contains(github.event.pull_request.labels.*.name, 'handed-back') && ");
});

it('clears on an edit only when a non-bot sender changed the body', function (): void {
    expect(clearHandedBackJob()['if'] ?? null)->toBe(
        "contains(github.event.pull_request.labels.*.name, 'handed-back') "
        ."&& (github.event.action == 'synchronize' "
        ."|| (github.event.action == 'edited' "
        .'&& github.event.changes.body != null '
        ."&& github.event.sender.type != 'Bot'))",
    );
});

it('is not gated on RUN_REMOTE_CI', function (): void {
    $source = file_get_contents(dirname(__DIR__, 3).'/.github/workflows/clear-handed-back.yml');

    // Search only the YAML itself, since the header comment names the variable to explain its absence
    $code = implode("\n", array_filter(
        explode("\n", (string) $source),
        fn (string $line): bool => ! str_starts_with(ltrim($line), '#'),
    ));

    expect($code)->not->toContain('RUN_REMOTE_CI');
});

it('grants no permission beyond editing pull request labels', function (): void {
    expect(clearHandedBackWorkflow()['permissions'] ?? null)->toBe([])
        ->and(clearHandedBackJob()['permissions'] ?? null)->toBe(['pull-requests' => 'write']);
});

it('removes the one label by name', function (): void {
    $steps = clearHandedBackJob()['steps'] ?? [];
    $step = is_array($steps) ? ($steps[0] ?? null) : null;
    $run = is_array($step) && is_string($step['run'] ?? null) ? $step['run'] : '';

    expect($run)->toContain('-X DELETE "repos/$REPO/issues/$NUMBER/labels/handed-back"');
});
