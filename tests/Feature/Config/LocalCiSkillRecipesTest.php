<?php

declare(strict_types=1);

use Tests\TestCase;

/**
 * The `local-ci` skill's scoped job recipes name every job whose guard covers the paths
 * each recipe describes (UAMS-Web/uams-statamic-archive#2645).
 *
 * A scoped `--jobs` list fails in one direction only: a job left out cannot fail, so the
 * run is green and the gap leaves no trace. The docs-only recipe once omitted `no-emoji`,
 * the one guard scoped to `.claude/`, `docs/` and `README.md`, and the PHP-only recipe
 * omitted `spell` and `encoding`, both of which read `*.php`.
 *
 * What each guard covers is read from the tool that owns it rather than restated here:
 * `spell` from the globs in `scripts/spell.mjs`, `no-emoji` from the roots in
 * `scripts/check-no-emoji.mjs`, and the job names from `scripts/local-ci.mjs`. A change to
 * any of those that the skill does not follow fails here.
 *
 * @command  php artisan test --compact tests/Feature/Config/LocalCiSkillRecipesTest.php
 */
uses(TestCase::class);

/**
 * Read a repository file.
 *
 * @param  string  $path  Path relative to the repository root.
 */
function localCiRepoFile(string $path): string
{
    $contents = file_get_contents(base_path($path));

    expect($contents)->toBeString();

    /** @var string $contents */
    return $contents;
}

/**
 * The quoted strings in a JavaScript array literal assigned to a named constant.
 *
 * @param  string  $source  The JavaScript source.
 * @param  string  $name  The constant's name.
 * @return list<string>
 */
function localCiJsArray(string $source, string $name): array
{
    if (preg_match('/const '.preg_quote($name, '/').' = \[([^\]]*)\]/', $source, $match) !== 1) {
        throw new RuntimeException(sprintf('No `%s` array found.', $name));
    }

    preg_match_all("/'([^']+)'/", $match[1], $items);

    return $items[1];
}

/**
 * The job list a skill recipe names.
 *
 * @param  string  $label  The recipe's label, as the start of its bullet.
 * @return list<string>
 */
function localCiRecipeJobs(string $label): array
{
    $skill = localCiRepoFile('.claude/skills/local-ci/SKILL.md');

    if (preg_match('/^- '.preg_quote($label, '/').'[^\n]*?`--jobs ([a-z,-]+)`/m', $skill, $match) !== 1) {
        throw new RuntimeException(sprintf('No `%s` recipe found in the skill.', $label));
    }

    return explode(',', $match[1]);
}

/**
 * The file extensions `spell` scans, from `scripts/spell.mjs`.
 *
 * @return list<string>
 */
function localCiSpellExtensions(): array
{
    $globs = localCiJsArray(localCiRepoFile('scripts/spell.mjs'), 'GLOBS');

    $extensions = [];

    foreach ($globs as $glob) {
        if (preg_match('/\{([^}]+)\}/', $glob, $match) === 1) {
            array_push($extensions, ...explode(',', $match[1]));
        }
    }

    return $extensions;
}

/**
 * The roots `no-emoji` scans, from `scripts/check-no-emoji.mjs`.
 *
 * @return list<string>
 */
function localCiNoEmojiRoots(): array
{
    $source = localCiRepoFile('scripts/check-no-emoji.mjs');

    return [...localCiJsArray($source, 'ROOTS'), ...localCiJsArray($source, 'FILES')];
}

it('lists every job the runner accepts, in the order the runner runs them', function (): void {
    $chain = localCiJsArray(localCiRepoFile('scripts/local-ci.mjs'), 'CHAIN');

    $found = preg_match('/`--jobs` accepts any subset of `([a-z -]+)`/', localCiRepoFile('.claude/skills/local-ci/SKILL.md'), $match);

    expect($found)->toBe(1)
        ->and(explode(' ', $match[1] ?? ''))->toBe($chain);
});

it('gives every job a row in the job table', function (): void {
    $skill = localCiRepoFile('.claude/skills/local-ci/SKILL.md');

    foreach (localCiJsArray(localCiRepoFile('scripts/local-ci.mjs'), 'ALL_JOBS') as $job) {
        expect($skill)->toContain('| `'.$job.'` |');
    }
});

it('names every guard covering a docs-only diff in the docs-only recipe', function (): void {
    $required = ['encoding'];

    if (in_array('md', localCiSpellExtensions(), true)) {
        $required[] = 'spell';
    }

    if (array_intersect(['.claude', 'docs'], localCiNoEmojiRoots()) !== []) {
        $required[] = 'no-emoji';
    }

    expect(localCiRecipeJobs('Docs-only'))->toContain(...$required);
});

it('names every guard covering a PHP diff in the PHP-only recipe', function (): void {
    $required = ['pint', 'phpstan', 'encoding'];

    if (in_array('php', localCiSpellExtensions(), true)) {
        $required[] = 'spell';
    }

    expect(localCiRecipeJobs('PHP-only diff'))->toContain(...$required);
});

it('names every guard covering a front-end diff in the front-end recipe, and no-emoji is not one of them', function (): void {
    $required = ['js', 'build', 'encoding'];

    if (array_intersect(['js', 'vue'], localCiSpellExtensions()) !== []) {
        $required[] = 'spell';
    }

    // The recipe is correct without `no-emoji` only while its paths stay outside that guard's roots
    expect(localCiNoEmojiRoots())->not->toContain('resources')
        ->and(localCiRecipeJobs('Pure front-end'))->toContain(...$required);
});
