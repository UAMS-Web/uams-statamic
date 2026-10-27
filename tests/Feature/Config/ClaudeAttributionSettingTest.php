<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#3086: the pull-request attribution footer is switched off in `.claude/settings.json`.
 *
 * No `App\` PHP surface is under test: this pins a checked-in configuration file. An empty
 * `attribution.pr` removes the attribution line Claude Code writes into a pull-request
 * description, and `attribution.sessionUrl` set to false omits the session link. Commit
 * trailers (`attribution.commit`) are deliberately left to the default.
 */

/**
 * Decode the project's Claude Code settings file.
 *
 * @return array<string, mixed>
 */
function claudeProjectSettings(): array
{
    $settings = json_decode((string) file_get_contents(base_path('.claude/settings.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($settings)->toBeArray();

    /** @var array<string, mixed> $settings */
    return $settings;
}

it('switches off the pull-request attribution line and the session link', function (): void {
    expect(claudeProjectSettings()['attribution'] ?? null)->toBe([
        'pr' => '',
        'sessionUrl' => false,
    ]);
});

it('leaves the commit attribution at its default', function (): void {
    $attribution = claudeProjectSettings()['attribution'] ?? [];

    expect($attribution)->toBeArray()->not->toHaveKey('commit');
});
