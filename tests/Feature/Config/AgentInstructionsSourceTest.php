<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#2539 — `AGENTS.md` is the single source of agent instructions. Since UAMS-Web/uams-statamic-archive#2833 there is
 * no `CLAUDE.md` at all: Claude Code reads `AGENTS.md` itself when none exists (UAMS-Web/uams-statamic-archive#2839).
 *
 * No `App\` PHP surface is under test: this pins checked-in repository structure. Each
 * case guards a way the arrangement can quietly come apart:
 *
 * - A `CLAUDE.md` coming back, so Claude Code reads it instead and the two drift as they
 *   did before UAMS-Web/uams-statamic-archive#2539.
 * - Laravel Boost's generated block landing in a new `CLAUDE.md`, which it does whenever
 *   `config/boost.php` stops pointing Claude Code's `guidelines_path` at `AGENTS.md`.
 * - The block's opening tag written in prose inside `AGENTS.md`. Boost finds the block
 *   by a pattern that starts at the tag's first occurrence, so a mention above the block
 *   makes the next regeneration overwrite everything in between. Measured on this
 *   branch: a sentence naming the tag lost the whole preamble on one `boost:update`.
 * - A rule or style file added without being named in `AGENTS.md`'s pointer section,
 *   which is the only route a Cursor agent has to it.
 * - A Cursor-only rule added to `.cursor/rules`, beside the synced twins of the
 *   `.claude/rules` rules (UAMS-Web/uams-statamic-archive#2750).
 *
 * @command  php artisan test --compact tests/Feature/Config/AgentInstructionsSourceTest.php
 */
$root = dirname(__DIR__, 3);

$read = static function (string $path) use ($root): string {
    $contents = file_get_contents($root.'/'.$path);

    expect($contents)->toBeString();

    return (string) $contents;
};

it('has no CLAUDE.md, so Claude Code reads AGENTS.md itself', function () use ($root, $read): void {
    expect(file_exists($root.'/CLAUDE.md'))->toBeFalse()
        ->and($read('AGENTS.md'))->toContain('the repository has no `CLAUDE.md`');
});

it('keeps Laravel Boost writing its block into AGENTS.md', function () use ($read): void {
    expect(config('boost.agents.claude_code.guidelines_path'))->toBe('AGENTS.md')
        ->and($read('AGENTS.md'))->toContain('<laravel-boost-guidelines>');
});

it('names the Boost tag in AGENTS.md only as the block\'s own opening and closing', function () use ($read): void {
    $agents = $read('AGENTS.md');

    expect(substr_count($agents, '<laravel-boost-guidelines>'))->toBe(1)
        ->and(substr_count($agents, '</laravel-boost-guidelines>'))->toBe(1)
        ->and(substr_count($agents, 'laravel-boost-guidelines'))->toBe(2);
});

it('names every standing rule in AGENTS.md', function (string $directory) use ($read, $root): void {
    $agents = $read('AGENTS.md');

    $files = array_map('basename', glob($root.'/'.$directory.'/*.{md,mdc}', GLOB_BRACE) ?: []);

    expect($files)->not->toBe([]);

    foreach ($files as $file) {
        expect($agents)->toContain('('.$directory.'/'.$file.')');
    }
})->with([
    'standing rules' => ['.claude/rules'],
]);

/**
 * UAMS-Web/uams-statamic-archive#2750 retired the Cursor-only style rules. Since UAMS-Web/uams-statamic-archive#2833 the shared sync renders every
 * `.claude/rules` rule into `.cursor/rules` on purpose, and names that directory in
 * `AGENTS.md`'s generated section, so the directory is no longer empty. What still holds
 * is that nothing in it stands alone: each file is the rendered twin of a `.claude/rules`
 * rule, so a hand-written Cursor-only rule cannot come back.
 */
it('keeps .cursor/rules to the synced twins of the .claude rules', function () use ($root): void {
    $files = glob($root.'/.cursor/rules/*') ?: [];

    expect($files)->not->toBe([]);

    foreach ($files as $file) {
        $name = basename($file);

        expect($name)->toEndWith('.mdc')
            ->and(is_file($root.'/.claude/rules/'.basename($name, '.mdc').'.md'))->toBeTrue(
                ".cursor/rules/{$name} has no .claude/rules twin, so it is a Cursor-only rule."
            );
    }
});
