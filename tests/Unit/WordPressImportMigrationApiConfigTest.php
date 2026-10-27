<?php

declare(strict_types=1);

/**
 * Guard for the migration API config block (UAMS-Web/uams-statamic-archive#2078, inverted by UAMS-Web/uams-statamic-archive#2358).
 *
 * ORIGINALLY this asserted the block carried `base_url`, `secret` and `per_page`:
 * `MigrationApi\Client::fromConfig()` read all three, the importer's README
 * specified them as a required host-app step, and they had been documented and
 * never added — so `--source=api` could not run and nothing here noticed.
 *
 * `UAMS-Web/wordpress-importer#982` removed that seam. The address and the key are
 * now supplied per run and stored nowhere, because the estate is 26 separate
 * multisite networks each issuing its own key. `Client::fromConfig()` no longer
 * exists; `Client::forRun($baseUrl, $key)` replaced it.
 *
 * SO THE ASSERTION IS INVERTED RATHER THAN DELETED. `per_page` is still read and is
 * still pinned. The other two must now be ABSENT, and that is the half worth having
 * a test for: a key read by nothing is indistinguishable from one read by something,
 * so re-adding either would look like configuring the importer and would put a live
 * credential on disk for a value that belongs in the operator's clipboard for the
 * length of a run.
 *
 * The file is required by path rather than read through `config()`, so this stays a
 * true unit test with no booted container, and it asserts the file rather than a
 * merged config value. No secret is asserted on, in either direction.
 */

/**
 * The `wordpress-import` config file, loaded without a booted container.
 *
 * @return array<string, mixed> The config array, or empty if the file returned
 *                              something else
 */
function wordPressImportConfig(): array
{
    $loaded = require dirname(__DIR__, 2).'/config/wordpress-import.php';

    /** @var array<string, mixed> $config */
    $config = \is_array($loaded) ? $loaded : [];

    return $config;
}

/**
 * The `migration_api` block, narrowed to an array for offset access.
 *
 * @return array<string, mixed> The block, or empty when it is absent
 */
function migrationApiBlock(): array
{
    $found = wordPressImportConfig()['migration_api'] ?? null;

    /** @var array<string, mixed> $block */
    $block = \is_array($found) ? $found : [];

    return $block;
}

it('still defines the migration API block, for the paging the importer reads', function (): void {
    expect(wordPressImportConfig())->toHaveKey('migration_api')
        ->and(migrationApiBlock())->toHaveKey('per_page');
});

it('does not configure the migration API address or key', function (): void {
    // Inverted by UAMS-Web/uams-statamic-archive#2358. These were required until UAMS-Web/wordpress-importer#982
    // stopped reading them; re-adding either would present as configuration that
    // does nothing, and would put a credential on disk for a value that is supplied
    // per run and stored nowhere.
    expect(migrationApiBlock())->not->toHaveKey('base_url')
        ->and(migrationApiBlock())->not->toHaveKey('secret');
});

it('casts per_page to an int, because env() returns strings', function (): void {
    // A string "150" would be accepted by the client only to be reported as a
    // configuration error; a value that was never cast is the same defect as a
    // missing one.
    expect(migrationApiBlock()['per_page'])->toBeInt();
});

it('defaults per_page rather than leaving it null when the environment is unset', function (): void {
    // The client falls back to 100 itself, but a null here would mean the host is
    // not supplying the key at all — which is what this block exists to fix.
    expect(migrationApiBlock()['per_page'])->toBeGreaterThan(0);
});
