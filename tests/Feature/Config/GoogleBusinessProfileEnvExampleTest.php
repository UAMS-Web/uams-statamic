<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#2783 — `.env.example` lists the three Google Business Profile credential
 * names (empty values) so a new checkout records that they exist. Values are
 * never committed; UAMS-Web/uams-statamic-archive#2617 will read them once Google approves API access.
 *
 * @coversNothing
 *
 * @command php artisan test --compact tests/Feature/Config/GoogleBusinessProfileEnvExampleTest.php
 */

/**
 * Parse `KEY=value` lines from `.env.example` into a map. Comment and blank
 * lines are skipped. Empty values stay empty strings.
 *
 * @return array<string, string>
 */
function googleBusinessProfileEnvExampleMap(): array
{
    $path = base_path('.env.example');
    expect($path)->toBeFile();

    $map = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (! str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $map[$key] = $value;
    }

    return $map;
}

it('lists the three Google Business Profile credential names with empty values', function (): void {
    $map = googleBusinessProfileEnvExampleMap();

    expect($map)
        ->toHaveKey('GOOGLE_BUSINESS_PROFILE_CLIENT_ID')
        ->toHaveKey('GOOGLE_BUSINESS_PROFILE_CLIENT_SECRET')
        ->toHaveKey('GOOGLE_BUSINESS_PROFILE_REFRESH_TOKEN');

    expect($map['GOOGLE_BUSINESS_PROFILE_CLIENT_ID'])->toBe('');
    expect($map['GOOGLE_BUSINESS_PROFILE_CLIENT_SECRET'])->toBe('');
    expect($map['GOOGLE_BUSINESS_PROFILE_REFRESH_TOKEN'])->toBe('');
});

it('documents the Business Information API purpose above the three names', function (): void {
    $contents = file_get_contents(base_path('.env.example'));
    expect($contents)->not->toBeFalse();

    expect($contents)
        ->toContain('categories.list')
        ->toContain('business.manage')
        ->toContain('never committed');
});
