<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * The seed-account record names the `users` submodule's `.gitignore` re-admits (UAMS-Web/uams-statamic-archive#2746).
 *
 * Registrant records are ignored by `*.yaml`, and each seed account is re-admitted by a
 * `!<email>.yaml` line (UAMS-Web/uams-statamic-archive#2454). Tests read the names from that file rather than writing
 * them out, so the addresses live only in the private submodule (UAMS-Web/uams-statamic-archive#2740).
 */
final class SeedAccounts
{
    /**
     * The record file names a `.gitignore` body re-admits, in the order it lists them.
     *
     * Only a negated pattern naming one `.yaml` file counts: a negated glob or a path
     * re-admits something that is not a single seed record.
     *
     * @param  string  $gitignore  The `.gitignore` body.
     * @return list<string> File names such as `someone@example.test.yaml`.
     */
    public static function readmittedRecords(string $gitignore): array
    {
        $records = [];

        foreach (explode("\n", str_replace("\r", '', $gitignore)) as $line) {
            $line = trim($line);

            if (! str_starts_with($line, '!') || ! str_ends_with($line, '.yaml')) {
                continue;
            }

            $name = substr($line, 1);

            // A path or a glob is not one seed record
            if (str_contains($name, '/') || str_contains($name, '*')) {
                continue;
            }

            $records[] = $name;
        }

        return $records;
    }

    /**
     * The record file names the checked-out `users` submodule re-admits.
     *
     * The caller skips first when the submodule is absent, so a missing file here is a
     * setup error rather than a reason to return nothing.
     *
     * @return list<string>
     */
    public static function fromUsersSubmodule(): array
    {
        $gitignore = file_get_contents(base_path('users/.gitignore'));

        if ($gitignore === false) {
            throw new \RuntimeException('The users submodule has no readable .gitignore.');
        }

        return self::readmittedRecords($gitignore);
    }
}
