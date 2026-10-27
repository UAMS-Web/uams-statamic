<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#2751: the Cursor commands point only at guidance that exists.
 *
 * UAMS-Web/uams-statamic-archive#2750 retired `.cursor/rules/`, and two commands in `.cursor/commands/` were written to apply
 * rule files there. They now apply sections of the `php-coding-standards` skill instead. A command
 * that names a retired rule file, or a `.claude/` path or skill section that no longer exists,
 * would send a Cursor agent to nothing, and nothing else would notice.
 *
 * Ported from `UAMS-Web/wordpress-importer`'s `tests/Unit/CursorCommandsTest.php` (#1280), which
 * solved the same ticket.
 *
 * @command  php artisan test --compact tests/Feature/Config/CursorCommandsTest.php
 */
$root = dirname(__DIR__, 3);

/**
 * Every Cursor command file, keyed by its path relative to the repository root.
 *
 * @param  string  $root  The repository root.
 * @return array<string, string> The commands' contents
 */
function cursorCommandFiles(string $root): array
{
    $commands = [];
    $paths = glob($root.'/.cursor/commands/*.md');

    foreach ($paths === false ? [] : $paths as $path) {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('%s could not be read.', $path));
        }

        $commands[substr($path, strlen($root) + 1)] = $contents;
    }

    return $commands;
}

it('finds the command files it checks', function () use ($root): void {
    // Positive control: an empty glob would make every case below pass vacuously
    expect(cursorCommandFiles($root))->toHaveKeys([
        '.cursor/commands/apply-arrow-functions.md',
        '.cursor/commands/apply-php-type-hints.md',
    ]);
});

it('names no retired .cursor/rules file', function () use ($root): void {
    foreach (cursorCommandFiles($root) as $path => $contents) {
        expect(str_contains($contents, '.cursor/rules/'))->toBeFalse(sprintf('%s names a retired .cursor/rules file.', $path));
    }
});

it('points only at .claude paths that exist', function () use ($root): void {
    $checked = 0;

    foreach (cursorCommandFiles($root) as $path => $contents) {
        preg_match_all('#\.claude/[A-Za-z0-9_./-]+\.md#', $contents, $matches);

        foreach ($matches[0] as $target) {
            expect(is_file($root.'/'.$target))->toBeTrue(sprintf('%s names %s, which does not exist.', $path, $target));
            $checked++;
        }
    }

    // A command set that names no .claude path at all would make the loop above pass vacuously
    expect($checked)->toBeGreaterThan(0);
});

it('names php-coding-standards sections that exist', function () use ($root): void {
    $skill = file_get_contents($root.'/.claude/skills/php-coding-standards/SKILL.md');

    if ($skill === false) {
        throw new RuntimeException('The php-coding-standards skill could not be read.');
    }

    $checked = 0;

    foreach (cursorCommandFiles($root) as $path => $contents) {
        if (! str_contains($contents, 'php-coding-standards')) {
            continue;
        }

        preg_match_all('/"([^"]+)"/', $contents, $matches);

        expect($matches[1])->not->toBeEmpty($path);

        foreach ($matches[1] as $section) {
            expect($skill)->toMatch('/^## \d+\. '.preg_quote($section, '/').'\b/m', sprintf('%s names section "%s".', $path, $section));
            $checked++;
        }
    }

    // As above: with no command naming a section, the loop would assert nothing
    expect($checked)->toBeGreaterThan(0);
});
