<?php

declare(strict_types=1);

/**
 * The string-building section of the `php-coding-standards` skill must not call
 * double-quoted interpolation something to avoid (UAMS-Web/uams-statamic-archive#2607). Since UAMS-Web/uams-statamic-archive#2567 its guidance is: `sprintf()` for format
 * specifiers, casts, or several placeholders; double-quoted interpolation for a simple
 * variable; a heredoc for generated source.
 *
 * Until UAMS-Web/uams-statamic-archive#2750 this also held a Cursor style rule to the same guidance. That rule is
 * retired, and the skill is the one copy every agent reads.
 *
 * @command  php artisan test --compact tests/Feature/Config/PhpStringInterpolationGuidanceTest.php
 */
$root = dirname(__DIR__, 3);

$read = static function (string $path) use ($root): string {
    $contents = file_get_contents($root.'/'.$path);

    expect($contents)->toBeString();

    return (string) $contents;
};

/**
 * The skill's string-building section: everything from its heading to the next `## `
 * heading. Found by its title rather than its number, because the shared sync (UAMS-Web/uams-statamic-archive#2833)
 * renumbered the sections and this one moved from 5 to 7 (UAMS-Web/uams-statamic-archive#2846).
 */
$skillStringSection = static function (string $markdown): string {
    foreach (preg_split('/^## /m', $markdown) ?: [] as $candidate) {
        if (preg_match('/^\d+\. Building strings\b/', $candidate) === 1) {
            return $candidate;
        }
    }

    return '';
};

it('does not call interpolation something to avoid in the skill', function () use ($read, $skillStringSection): void {
    $section = $skillStringSection($read('.claude/skills/php-coding-standards/SKILL.md'));

    expect($section)->toContain('Double-quoted interpolation')
        ->and(stripos($section, 'avoid'))->toBeFalse();
});
