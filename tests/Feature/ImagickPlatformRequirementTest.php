<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/**
 * Regression guard for UAMS-Web/uams-statamic-archive#1830. `ext-imagick` is a declared platform requirement, so
 * **every** `composer install` platform-checks it — not only the jobs that go on to
 * boot the framework. A CI job added without the extension therefore dies at its
 * install step.
 *
 * That failure is loud, but nothing currently surfaces it: remote CI is disabled
 * org-wide via `RUN_REMOTE_CI`, and `scripts/local-ci.mjs` runs on a developer
 * machine that installed the extension by hand. So the gap would stay invisible
 * until Actions is re-enabled — which is how the original defect survived, and why
 * the invariant is asserted here rather than left to the first red run.
 *
 * A pure file-read check; like the sibling `RectorProfileSplitTest` and
 * `SkipBaselineGuardTest` guards it deliberately avoids booting the framework.
 *
 * @command  php artisan test --compact tests/Feature/ImagickPlatformRequirementTest.php
 */
$root = dirname(__DIR__, 2);

/**
 * Read a repository file, failing with the path when it cannot be read.
 *
 * @param  string  $path  Absolute path to read.
 * @return string The file contents.
 */
$readFile = function (string $path): string {
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException(sprintf('Unable to read %s.', $path));
    }

    return $contents;
};

it('declares ext-imagick so composer install fails fast rather than the app dying at boot', function () use ($root, $readFile): void {
    $manifest = json_decode($readFile($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    $require = is_array($manifest) && is_array($manifest['require'] ?? null) ? $manifest['require'] : [];

    expect($require)->toHaveKey('ext-imagick');
});

it('gives imagick to every CI job that runs composer install', function () use ($root): void {
    $workflow = Yaml::parseFile($root.'/.github/workflows/ci.yml');

    $jobs = is_array($workflow) && is_array($workflow['jobs'] ?? null) ? $workflow['jobs'] : [];

    expect($jobs)->not->toBeEmpty();

    // Only the jobs that install are constrained — `js` and `spell` never touch
    // Composer, so requiring the extension of them would be noise.
    /** @var array<string, string> $installing */
    $installing = [];

    foreach ($jobs as $name => $job) {
        $steps = is_array($job) && is_array($job['steps'] ?? null) ? $job['steps'] : [];

        if (! str_contains(json_encode($steps, JSON_THROW_ON_ERROR), 'composer install')) {
            continue;
        }

        $extensions = '';

        foreach ($steps as $step) {
            if (! is_array($step) || ! str_contains(is_string($step['uses'] ?? null) ? $step['uses'] : '', 'setup-php')) {
                continue;
            }

            $with = is_array($step['with'] ?? null) ? $step['with'] : [];
            $extensions = is_string($with['extensions'] ?? null) ? $with['extensions'] : '';

            break;
        }

        $installing[(string) $name] = $extensions;
    }

    // Guard the guard: an empty list would make the assertion below vacuous.
    expect($installing)->not->toBeEmpty();

    // Collected rather than asserted per job so a failure names every offending job
    // at once — `toContain()` is variadic in Pest, so it cannot carry a message.
    $missing = array_keys(array_filter(
        $installing,
        fn (string $extensions): bool => ! str_contains($extensions, 'imagick'),
    ));

    expect($missing)->toBe([]);
});

it('does not build the extension on jobs that never run composer install', function () use ($root): void {
    // The converse, so the requirement does not creep onto every job. Building
    // `imagick` costs setup-php time, and `test-isolation-guard` is the FIRST
    // link in the cost-ordered chain — its own comment says it runs "only PHP —
    // no composer install, no content submodule, and no framework boot". An
    // earlier pass added it to that job and to `composer-validate` because a
    // raw text scan matched the phrase inside their comments (UAMS-Web/uams-statamic-archive#1830).
    $workflow = Yaml::parseFile($root.'/.github/workflows/ci.yml');

    $jobs = is_array($workflow) && is_array($workflow['jobs'] ?? null) ? $workflow['jobs'] : [];

    /** @var array<int, string> $wasteful */
    $wasteful = [];

    foreach ($jobs as $name => $job) {
        $steps = is_array($job) && is_array($job['steps'] ?? null) ? $job['steps'] : [];

        $installs = false;
        $extensions = '';

        foreach ($steps as $step) {
            if (! is_array($step)) {
                continue;
            }

            // Only a real `run:` step counts — a mention inside a comment does not
            // survive YAML parsing, which is precisely why this test parses rather
            // than greps.
            if (str_contains(is_string($step['run'] ?? null) ? $step['run'] : '', 'composer install')) {
                $installs = true;
            }

            if (str_contains(is_string($step['uses'] ?? null) ? $step['uses'] : '', 'setup-php')) {
                $with = is_array($step['with'] ?? null) ? $step['with'] : [];
                $extensions = is_string($with['extensions'] ?? null) ? $with['extensions'] : '';
            }
        }

        if (! $installs && str_contains($extensions, 'imagick')) {
            $wasteful[] = (string) $name;
        }
    }

    expect($wasteful)->toBe([]);
});
