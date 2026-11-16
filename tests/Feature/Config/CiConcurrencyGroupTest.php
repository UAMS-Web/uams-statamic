<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/**
 * Drift guard for #530: every push run of the `CI` workflow gets its own concurrency group.
 *
 * With one group per ref, every push to `main` shared `CI-refs/heads/main`. GitHub keeps one
 * running and one pending run per group and cancels the pending one when another is queued,
 * whatever `cancel-in-progress` says, so a merge landing during a long run cancelled the previous
 * merge's run before it started a job. Nineteen commits on `main` carried only CodeQL's three
 * settled green check runs, and the chain never ran on them.
 *
 * The decision (recorded on #530, made in the shared template in
 * UAMS-Web/uams-claude-skills#473) keys a pull request's group on its ref, so a new push still
 * cancels that pull request's stale run, and every other run's group on its commit. Nothing else
 * objects if the group is edited back: the symptom is a short, green check-run population on a
 * commit, read as a pass. This names the line instead.
 *
 * A pure file-read check; it does not boot the framework.
 *
 * @command  php artisan test --compact tests/Feature/Config/CiConcurrencyGroupTest.php
 */
$concurrency = function (): array {
    $workflow = Yaml::parseFile(dirname(__DIR__, 3).'/.github/workflows/ci.yml');

    expect($workflow)->toBeArray()->toHaveKey('concurrency');

    $block = is_array($workflow) ? $workflow['concurrency'] : null;

    expect($block)->toBeArray();

    return is_array($block) ? $block : [];
};

it('keys a push run on its commit, so a later push cannot cancel it while pending', function () use ($concurrency) {
    // The shared template's line; `github.sha` is what makes each push run's group unique
    expect($concurrency()['group'] ?? null)
        ->toBe("\${{ github.workflow }}-\${{ github.event_name == 'pull_request' && github.ref || github.sha }}");
});

it('still cancels a pull request run that a newer push to it made stale', function () use ($concurrency) {
    expect($concurrency()['cancel-in-progress'] ?? null)
        ->toBe("\${{ github.event_name == 'pull_request' }}");
});
