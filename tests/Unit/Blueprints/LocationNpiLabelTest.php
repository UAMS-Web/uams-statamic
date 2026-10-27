<?php

declare(strict_types=1);

/**
 * Pins the clinical location `npi` row label as ORGANIZATIONAL, and pins the
 * neighboring uses of the same imported field that must NOT move with it.
 *
 * NPPES issues type 1 for an individual and type 2 for an organization. A clinical
 * location is the provider, so its NPI is type 2 — the row previously read
 * "Individual", which names the other type (UAMS-Web/uams-statamic-archive#2154).
 *
 * The second and third cases carry the weight: the wrong fix is a repo-wide
 * substitution of the label, which would rewrite fields that are already correct.
 * The shared fieldset is deliberately neutral so every other consumer inherits a
 * label that suits it.
 *
 * Reads the YAML directly rather than through `Blueprint`, so the assertions are
 * about the committed source and need no booted application.
 */
function npiResourcePath(string $relative): string
{
    return dirname(__DIR__, 3).'/resources/'.$relative;
}

it('labels the clinical location npi row as organizational, not individual', function (): void {
    $yaml = file_get_contents(
        npiResourcePath('blueprints/collections/clinical_locations/location.yaml')
    );

    expect($yaml)->toBeString()
        ->toContain("display: 'Organizational National Provider Identifier (NPI)'")
        ->not->toContain('Individual National Provider Identifier');
});

it('leaves the shared fieldset label neutral, so other consumers are unaffected', function (): void {
    $yaml = file_get_contents(npiResourcePath('fieldsets/uamsweb_common_identifiers.yaml'));

    expect($yaml)->toBeString()
        ->toContain("display: 'National Provider Identifier (NPI)'")
        ->not->toContain('Organizational National Provider Identifier');
});

it('applies the organizational label to exactly one blueprint', function (): void {
    $matches = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(dirname(__DIR__, 3).'/resources', FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        // The iterator is typed `mixed`, so narrow before touching SplFileInfo's API.
        if (! $file instanceof SplFileInfo) {
            continue;
        }

        if (! $file->isFile() || $file->getExtension() !== 'yaml') {
            continue;
        }

        $path = $file->getPathname();
        $contents = file_get_contents($path);

        if ($contents !== false && str_contains($contents, 'Organizational National Provider Identifier')) {
            $matches[] = basename($path);
        }
    }

    expect($matches)->toBe(['location.yaml']);
});
