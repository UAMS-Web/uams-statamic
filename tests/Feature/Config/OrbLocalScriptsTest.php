<?php

declare(strict_types=1);

/**
 * Placement guard for the orb setup split (#1258).
 *
 * The shared `.agents/setup` and `.agents/resume` that the `orb` sync profile delivers from
 * UAMS-Web/uams-claude-skills (`shared/.claude/skills/orb-setup/templates/`, read at `948ee12`)
 * run `.agents/setup.local` and `.agents/resume.local` last. When the sync replaces this
 * repository's own two scripts, any step that is in neither the shared script nor a `.local`
 * file is lost. The table below places every step of the current scripts; the tests fail when a
 * current step is placed nowhere, or is placed in a `.local` file that does not carry it.
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
 * the shared setup template that cover the step (or the shared part of it); `local` is true when
 * `.agents/setup.local` runs a step with the same label.
 *
 * @var array<string, array{shared: ?string, local: bool}>
 */
const ORB_SETUP_STEP_PLACEMENT = [
    'PHP 8.4 and extensions' => ['shared' => 'setup:27-55 (PHP 8.4 and every extension but bcmath and imagick)', 'local' => true],
    'Node 22 and npm' => ['shared' => 'setup:69-83 (Node 22; its npm is below the engines.npm floor)', 'local' => true],
    'Composer' => ['shared' => 'setup:57-67', 'local' => false],
    'Test content submodules' => ['shared' => null, 'local' => true],
    'Locked PHP dependencies' => ['shared' => 'setup:86-88 (composer install, without the patch-input fingerprint)', 'local' => true],
    'Local application key' => ['shared' => null, 'local' => true],
    'Publish application assets' => ['shared' => null, 'local' => true],
    'Install Git hooks' => ['shared' => 'setup:86-88 (composer install runs post-install-cmd)', 'local' => false],
    'Local SQLite migrations' => ['shared' => null, 'local' => true],
    'Locked JavaScript dependencies' => ['shared' => 'setup:89-91', 'local' => false],
    'Browser test runtime' => ['shared' => null, 'local' => true],
    'News release PDF browser' => ['shared' => null, 'local' => true],
    'Front-end build' => ['shared' => null, 'local' => true],
    'Control Panel build' => ['shared' => null, 'local' => true],
    'Test Stache snapshot' => ['shared' => null, 'local' => true],
];

/**
 * Commands the current `.agents/setup` runs outside a `step` call, and the label of the
 * `.agents/setup.local` step that carries each.
 *
 * @var array<string, string>
 */
const ORB_SETUP_BARE_COMMAND_PLACEMENT = [
    'cp .env.example .env' => 'Environment file',
    'touch database/database.sqlite' => 'SQLite database file',
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

it('runs every step placed in setup.local under the same label there', function (): void {
    $localLabels = orbStepLabels(orbAgentsFile('setup.local'));

    foreach (ORB_SETUP_STEP_PLACEMENT as $label => $placement) {
        if ($placement['local']) {
            expect($localLabels)->toContain($label);
        } else {
            expect($placement['shared'])->not->toBeEmpty();
        }
    }
});

it('places the commands the current setup script runs outside a step', function (): void {
    $setup = orbAgentsFile('setup');
    $local = orbAgentsFile('setup.local');
    $localLabels = orbStepLabels($local);

    foreach (ORB_SETUP_BARE_COMMAND_PLACEMENT as $command => $label) {
        expect($setup)->toContain($command)
            ->and($local)->toContain($command)
            ->and($localLabels)->toContain($label);
    }
});

it('runs no step in setup.local that the table does not place', function (): void {
    $placed = [
        ...array_keys(array_filter(ORB_SETUP_STEP_PLACEMENT, static fn (array $placement): bool => $placement['local'])),
        ...array_values(ORB_SETUP_BARE_COMMAND_PLACEMENT),
    ];

    foreach (orbStepLabels(orbAgentsFile('setup.local')) as $label) {
        expect($placed)->toContain($label);
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
