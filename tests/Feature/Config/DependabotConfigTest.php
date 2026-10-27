<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

// cspell:ignore gitsubmodule

/**
 * Pins `.github/dependabot.yml` (UAMS-Web/uams-statamic-archive#2852), which turns on weekly version updates.
 *
 * One entry per package ecosystem the repository has, each targeting `main`
 * weekly with its minor and patch updates grouped, and no `gitsubmodule` entry,
 * because every submodule here is content or assets rather than a dependency.
 * Dependabot reads the file only on GitHub, so a typo in it fails silently; this
 * is the only place it is checked before a week goes by with no pull requests.
 *
 * @command  php artisan test --compact tests/Feature/Config/DependabotConfigTest.php
 */

/**
 * The parsed `updates` list.
 *
 * @return list<array<mixed>> One mapping per configured ecosystem.
 */
function dependabotUpdates(): array
{
    $config = Yaml::parseFile(dirname(__DIR__, 3).'/.github/dependabot.yml');

    // Fail with the reason rather than on a later offset read
    if (! is_array($config) || ($config['version'] ?? null) !== 2 || ! is_array($config['updates'] ?? null)) {
        throw new RuntimeException('dependabot.yml is not a version 2 file with an updates list');
    }

    return array_values(array_filter($config['updates'], is_array(...)));
}

it('configures exactly the composer, npm and github-actions ecosystems, at the root', function (): void {
    $ecosystems = array_map(fn (array $update): mixed => $update['package-ecosystem'] ?? null, dependabotUpdates());

    expect($ecosystems)->toBe(['composer', 'npm', 'github-actions']);

    foreach (dependabotUpdates() as $update) {
        expect($update['directory'] ?? null)->toBe('/');
    }
});

it('gives the submodules no entry', function (): void {
    $ecosystems = array_map(fn (array $update): mixed => $update['package-ecosystem'] ?? null, dependabotUpdates());

    expect($ecosystems)->not->toContain('gitsubmodule');
});

it('targets main weekly and groups only minor and patch updates', function (): void {
    foreach (dependabotUpdates() as $update) {
        $ecosystem = is_string($update['package-ecosystem'] ?? null) ? $update['package-ecosystem'] : '';
        $schedule = is_array($update['schedule'] ?? null) ? $update['schedule'] : [];

        expect($update['target-branch'] ?? null)->toBe('main')
            ->and($schedule['interval'] ?? null)->toBe('weekly');

        // One group per entry, so a major update is left ungrouped in a pull request of its own
        $groups = $update['groups'] ?? null;

        expect($groups)->toBeArray()->toHaveCount(1);

        /** @var array<string, array<mixed>> $groups */
        $group = reset($groups);

        expect($group['update-types'] ?? null)->toBe(['minor', 'patch'], 'the '.$ecosystem.' group');
    }
});

it('names no registry, relying on the organization-level grant for the private importer', function (): void {
    $config = Yaml::parseFile(dirname(__DIR__, 3).'/.github/dependabot.yml');

    expect($config)->toBeArray()->not->toHaveKey('registries');
});
