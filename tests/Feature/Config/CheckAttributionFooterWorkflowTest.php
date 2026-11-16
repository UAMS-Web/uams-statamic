<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/**
 * Pins the shape of the attribution-footer check in `.github/workflows/pr-body-checks.yml` (#752).
 *
 * The check fails a pull request whose body carries the harness attribution
 * footer that UAMS-Web/uams-statamic-archive#2321 forbids. It began as its own
 * workflow (UAMS-Web/uams-statamic-archive#2701); the shared skills now deliver
 * it inside `pr-body-checks.yml`, which calls the reusable workflow of the same
 * name in UAMS-Web/uams-ci-workflows and reports its result as the required
 * `Attribution footer` context. Each property below fails silently when broken:
 *
 * - It fires on every event that can change the body, not only on a push. The
 *   footer most often arrives in the opening body, which a `synchronize`-only
 *   trigger never sees.
 * - Every job is gated on `RUN_REMOTE_CI` only in the shared form, `!= 'false'`,
 *   which still runs while the variable is unset (UAMS-Web/uams-claude-skills#416).
 * - The call names a release tag, never a branch, so a change to the shared
 *   check reaches this repository only when the tag here moves.
 * - The `Attribution footer` job exists under that name, which a ruleset
 *   requires, and passes only when the called check succeeded.
 * - The called job runs the check the `writing-pull-requests` skill ships from
 *   this tree, so that script has to exist here.
 *
 * A pure file read, so it boots no framework.
 *
 * @command  php artisan test --compact tests/Feature/Config/CheckAttributionFooterWorkflowTest.php
 */

/**
 * The workflow's path in this tree.
 */
function prBodyChecksWorkflowPath(): string
{
    return dirname(__DIR__, 3).'/.github/workflows/pr-body-checks.yml';
}

/**
 * The parsed workflow.
 *
 * @return array<mixed> The workflow's top-level keys.
 */
function prBodyChecksWorkflow(): array
{
    $workflow = Yaml::parseFile(prBodyChecksWorkflowPath());

    // Fail with the reason rather than on a later offset read
    if (! is_array($workflow)) {
        throw new RuntimeException('pr-body-checks.yml did not parse to a mapping');
    }

    return $workflow;
}

/**
 * The workflow's jobs, keyed by job id.
 *
 * @return array<string, array<mixed>> Each job's keys.
 */
function prBodyChecksJobs(): array
{
    $jobs = prBodyChecksWorkflow()['jobs'] ?? null;

    // Narrow the jobs to a mapping of mappings for the assertions that read them
    if (! is_array($jobs)) {
        throw new RuntimeException('pr-body-checks.yml declares no jobs');
    }

    $mapped = [];

    foreach ($jobs as $id => $job) {
        if (! is_array($job)) {
            throw new RuntimeException("pr-body-checks.yml job {$id} is not a mapping");
        }

        $mapped[(string) $id] = $job;
    }

    return $mapped;
}

/**
 * The one job named `Attribution footer`.
 *
 * @return array<mixed> The job's keys.
 */
function attributionFooterJob(): array
{
    $named = array_filter(prBodyChecksJobs(), fn (array $job): bool => ($job['name'] ?? null) === 'Attribution footer');

    // Require exactly one, so a rename or a duplicate fails here
    if (count($named) !== 1) {
        throw new RuntimeException('pr-body-checks.yml must declare exactly one job named "Attribution footer"');
    }

    return reset($named);
}

it('declares the call and the two reporting jobs, and nothing else', function (): void {
    expect(array_keys(prBodyChecksJobs()))->toBe(['checks', 'attribution-footer', 'closing-keywords']);
});

it('triggers on every pull request event that can change the body', function (): void {
    expect(prBodyChecksWorkflow()['on'] ?? null)->toBe([
        'pull_request' => ['types' => ['opened', 'edited', 'reopened', 'ready_for_review', 'synchronize']],
    ]);
});

it('gates every job on RUN_REMOTE_CI only in the shared form that runs while it is unset', function (): void {
    $source = (string) file_get_contents(prBodyChecksWorkflowPath());
    $gate = "vars.RUN_REMOTE_CI != 'false'";

    // Search only the YAML itself, since the header comment names the variable
    $code = implode("\n", array_filter(
        explode("\n", $source),
        fn (string $line): bool => ! str_starts_with(ltrim($line), '#'),
    ));

    // Any other use of the variable, such as `== 'true'`, would stop a job while it is unset
    expect(str_replace($gate, '', $code))->not->toContain('RUN_REMOTE_CI');

    // Each job's condition is the gate itself, or a conjunction ending in it
    foreach (prBodyChecksJobs() as $id => $job) {
        $condition = $job['if'] ?? null;

        expect($condition === $gate || (is_string($condition) && str_ends_with($condition, ' && '.$gate)))
            ->toBeTrue("job {$id} is not gated on {$gate}");
    }
});

it('calls the shared reusable workflow at a release tag with read-only access', function (): void {
    $checks = prBodyChecksJobs()['checks'];

    expect(prBodyChecksWorkflow()['permissions'] ?? null)->toBe([])
        ->and($checks['uses'] ?? null)->toMatch('#^UAMS-Web/uams-ci-workflows/\.github/workflows/pr-body-checks\.yml@v\d+\.\d+\.\d+$#')
        ->and($checks['permissions'] ?? null)->toBe(['contents' => 'read', 'pull-requests' => 'read', 'issues' => 'read']);
});

it('reports the Attribution footer context from the called check, failing unless it succeeded', function (): void {
    $job = attributionFooterJob();
    $env = '';
    $run = '';

    // Collect every step's env and run block, so the assertion does not depend on step order
    foreach (is_array($job['steps'] ?? null) ? $job['steps'] : [] as $step) {
        if (is_array($step)) {
            $env .= implode("\n", array_filter(is_array($step['env'] ?? null) ? $step['env'] : [], is_string(...)));
            $run .= is_string($step['run'] ?? null) ? $step['run'] : '';
        }
    }

    expect($job['needs'] ?? null)->toBe(['checks'])
        ->and($job['if'] ?? null)->toStartWith('always() && ')
        ->and($env)->toContain('needs.checks.outputs.footer')
        ->and($run)->toContain('[ "$RESULT" = "success" ]');
});

it('carries the skill check the called job runs', function (): void {
    expect(dirname(__DIR__, 3).'/.claude/skills/writing-pull-requests/scripts/check-attribution-footer.mjs')->toBeFile();
});
