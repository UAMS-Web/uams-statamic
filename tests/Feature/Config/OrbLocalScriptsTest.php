<?php

declare(strict_types=1);

/**
 * Placement guard for the orb setup split (#1258).
 *
 * The shared `.agents/setup` and `.agents/resume` that the `orb` sync profile delivers from
 * UAMS-Web/uams-claude-skills (`shared/.claude/skills/orb-setup/templates/`, read at `6aeb381`)
 * run `.agents/setup.pre.local` before the dependency installs, and `.agents/setup.local` and
 * `.agents/resume.local` last. When the sync replaces this repository's own two scripts, any step
 * that is in neither the shared script nor a `.local` file is lost. The tables below place every
 * step of the current scripts; the tests fail when a current step is placed nowhere, or is placed
 * in a `.local` file that does not carry it.
 *
 * Once #641 replaces the current scripts with the shared ones, the step enumeration below finds
 * no `step` calls and the count assertion fails: that is #641's signal to retire this test.
 *
 * A pure file-read check; it does not boot the framework.
 *
 * @command  php artisan test --compact tests/Feature/Config/OrbLocalScriptsTest.php
 */

/**
 * Where each `step '<label>'` of the current `.agents/setup` goes. `shared` names the lines of
 * the shared setup template that cover the step (or the shared part of it); `files` lists the
 * `.local` files that run a step with the same label.
 *
 * @var array<string, array{shared: ?string, files: list<string>}>
 */
const ORB_SETUP_STEP_PLACEMENT = [
    'PHP 8.4 and extensions' => ['shared' => 'setup:28-56 (PHP 8.4 and every extension but bcmath and imagick)', 'files' => ['setup.pre.local']],
    'Node 22 and npm' => ['shared' => 'setup:70-84 (Node 22; its npm is below the engines.npm floor)', 'files' => ['setup.pre.local']],
    'Composer' => ['shared' => 'setup:58-68', 'files' => []],
    'Test content submodules' => ['shared' => null, 'files' => ['setup.pre.local']],
    'Locked PHP dependencies' => ['shared' => 'setup:94-96 (composer install, without the patch-input fingerprint)', 'files' => ['setup.pre.local', 'setup.local']],
    'Local application key' => ['shared' => null, 'files' => ['setup.pre.local']],
    'Publish application assets' => ['shared' => 'setup:94-96 (composer install runs post-autoload-dump)', 'files' => []],
    'Install Git hooks' => ['shared' => 'setup:94-96 (composer install runs post-install-cmd)', 'files' => []],
    'Local SQLite migrations' => ['shared' => null, 'files' => ['setup.local']],
    'Locked JavaScript dependencies' => ['shared' => 'setup:97-99', 'files' => []],
    'Browser test runtime' => ['shared' => null, 'files' => ['setup.local']],
    'News release PDF browser' => ['shared' => null, 'files' => ['setup.local']],
    'Front-end build' => ['shared' => null, 'files' => ['setup.local']],
    'Control Panel build' => ['shared' => null, 'files' => ['setup.local']],
    'Test Stache snapshot' => ['shared' => null, 'files' => ['setup.local']],
];

/**
 * Commands the current `.agents/setup` runs outside a `step` call, and the `.local` file and step
 * label that carry each.
 *
 * @var array<string, array{file: string, label: string}>
 */
const ORB_SETUP_BARE_COMMAND_PLACEMENT = [
    'cp .env.example .env' => ['file' => 'setup.pre.local', 'label' => 'Environment file'],
    'touch database/database.sqlite' => ['file' => 'setup.local', 'label' => 'SQLite database file'],
];

/**
 * Each check of the current `.agents/resume`, and where it goes: the shared resume template's
 * lines, or `resume.local`.
 *
 * @var array<string, string>
 */
const ORB_RESUME_CHECK_PLACEMENT = [
    "php -r 'exit(PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 4 ? 0 : 1);'" => 'resume.local',
    'test -f vendor/autoload.php' => 'resume:12-15',
    'test -d node_modules' => 'resume:16-19',
];

/**
 * The `.local` setup files, in the order the shared setup runs them.
 *
 * @var list<string>
 */
const ORB_LOCAL_SETUP_FILES = ['setup.pre.local', 'setup.local'];

/**
 * Read a file under `.agents/`.
 */
function orbAgentsFile(string $name): string
{
    $contents = file_get_contents(dirname(__DIR__, 3).'/.agents/'.$name);

    expect($contents)->toBeString();

    return is_string($contents) ? $contents : '';
}

/**
 * The labels of every `step '<label>'` call in a setup script, in order.
 *
 * @return list<string>
 */
function orbStepLabels(string $script): array
{
    preg_match_all("/^\\s*step '([^']+)'/m", $script, $matches);

    return $matches[1];
}

/**
 * The check commands of a resume script: every line that runs `php -r` or `test`.
 *
 * @return list<string>
 */
function orbResumeChecks(string $script): array
{
    preg_match_all('/^(?:php -r|test ).*$/m', $script, $matches);

    return array_map(trim(...), $matches[0]);
}

it('places every step of the current setup script', function (): void {
    $labels = orbStepLabels(orbAgentsFile('setup'));

    // The current script runs 15 steps; fewer means the enumeration is reading something else.
    expect($labels)->toHaveCount(15);

    foreach ($labels as $label) {
        expect(ORB_SETUP_STEP_PLACEMENT)->toHaveKey($label);
    }
});

it('runs every step placed in a local file under the same label there', function (): void {
    foreach (ORB_SETUP_STEP_PLACEMENT as $label => $placement) {
        foreach ($placement['files'] as $file) {
            expect(orbStepLabels(orbAgentsFile($file)))->toContain($label);
        }

        if ($placement['files'] === []) {
            expect($placement['shared'])->not->toBeEmpty();
        }
    }
});

it('places the commands the current setup script runs outside a step', function (): void {
    $setup = orbAgentsFile('setup');

    foreach (ORB_SETUP_BARE_COMMAND_PLACEMENT as $command => $placement) {
        $local = orbAgentsFile($placement['file']);

        expect($setup)->toContain($command)
            ->and($local)->toContain($command)
            ->and(orbStepLabels($local))->toContain($placement['label']);
    }
});

it('runs no step in a local file that the tables do not place there', function (): void {
    foreach (ORB_LOCAL_SETUP_FILES as $file) {
        $placed = [];

        foreach (ORB_SETUP_STEP_PLACEMENT as $label => $placement) {
            if (in_array($file, $placement['files'], true)) {
                $placed[] = $label;
            }
        }

        foreach (ORB_SETUP_BARE_COMMAND_PLACEMENT as $placement) {
            if ($placement['file'] === $file) {
                $placed[] = $placement['label'];
            }
        }

        foreach (orbStepLabels(orbAgentsFile($file)) as $label) {
            expect($placed)->toContain($label);
        }
    }
});

it('places every check of the current resume script', function (): void {
    $checks = orbResumeChecks(orbAgentsFile('resume'));
    $local = orbAgentsFile('resume.local');

    expect($checks)->toHaveCount(3);

    foreach ($checks as $check) {
        expect(ORB_RESUME_CHECK_PLACEMENT)->toHaveKey($check);

        if (ORB_RESUME_CHECK_PLACEMENT[$check] === 'resume.local') {
            expect($local)->toContain($check);
        }
    }
});
