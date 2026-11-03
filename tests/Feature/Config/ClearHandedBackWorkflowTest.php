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
 * - It is gated on `RUN_REMOTE_CI` only in the shared form, `!= 'false'`, which
 *   still runs while the variable is unset (UAMS-Web/uams-claude-skills#416). Any
 *   form that stops it while the switch is unset would bring back the missed
 *   removal for as long as the switch stays that way.
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

/**
 * The job's condition with the shared `RUN_REMOTE_CI` gate removed from its front.
 *
 * The synced template puts `vars.RUN_REMOTE_CI != 'false' && ` before the job's own
 * condition (UAMS-Web/uams-claude-skills#416), and the copy before that sync has no gate,
 * so both read the same here.
 */
function clearHandedBackJobCondition(): mixed
{
    $condition = clearHandedBackJob()['if'] ?? null;
    $gate = "vars.RUN_REMOTE_CI != 'false' && ";

    return is_string($condition) && str_starts_with($condition, $gate) ? substr($condition, strlen($gate)) : $condition;
}

it('triggers on a pull request push or edit and on nothing else', function (): void {
    expect(clearHandedBackWorkflow()['on'] ?? null)->toBe(['pull_request' => ['types' => ['synchronize', 'edited']]]);
});

it('runs its job only when the pull request carries handed-back', function (): void {
    expect(clearHandedBackJobCondition())->toStartWith("contains(github.event.pull_request.labels.*.name, 'handed-back') && ");
});

it('clears on an edit only when a non-bot sender changed the body', function (): void {
    expect(clearHandedBackJobCondition())->toBe(
        "contains(github.event.pull_request.labels.*.name, 'handed-back') "
        ."&& (github.event.action == 'synchronize' "
        ."|| (github.event.action == 'edited' "
        .'&& github.event.changes.body != null '
        ."&& github.event.sender.type != 'Bot'))",
    );
});

it('is ungated, or gated on RUN_REMOTE_CI only in the shared form that runs while it is unset', function (): void {
    $source = file_get_contents(dirname(__DIR__, 3).'/.github/workflows/clear-handed-back.yml');
    $gate = "vars.RUN_REMOTE_CI != 'false'";

    // Search only the YAML itself, since the header comment names the variable
    $code = implode("\n", array_filter(
        explode("\n", (string) $source),
        fn (string $line): bool => ! str_starts_with(ltrim($line), '#'),
    ));

    // Any other use of the variable, such as `== 'true'`, would stop the job while it is unset
    expect(str_replace($gate, '', $code))->not->toContain('RUN_REMOTE_CI')
        ->and(substr_count($code, $gate))->toBeLessThanOrEqual(1);

    // Where the gate is present, it leads the job's condition rather than one branch of it
    $condition = clearHandedBackJob()['if'] ?? null;
    expect(! str_contains($code, $gate) || (is_string($condition) && str_starts_with($condition, $gate.' && ')))->toBeTrue();
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
