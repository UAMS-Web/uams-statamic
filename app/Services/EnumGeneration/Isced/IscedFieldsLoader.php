<?php

declare(strict_types=1);

namespace App\Services\EnumGeneration\Isced;

use ValueError;

/**
 * Loads code/label rows from the bundled ISCED-F 2013 JSON fixture.
 *
 * Canonical codes are 2–4 digit strings (Eurostat leading `F` already removed).
 */
final class IscedFieldsLoader
{
    /**
     * @return array<int, array{code: string, label: string}>
     */
    public function load(string $jsonPath): array
    {
        if (! is_readable($jsonPath)) {
            throw new ValueError('ISCED-F fixture not readable: '.$jsonPath);
        }

        $raw = file_get_contents($jsonPath);

        // Check whether the read failed. is_readable() above already passed, and a
        // directory reads as an empty string rather than false, so no test can reach it.
        // @pest-mutate-ignore: FalseToTrue,ConcatRemoveLeft,ConcatRemoveRight,ConcatSwitchSides
        if ($raw === false) {
            throw new ValueError('Failed to read ISCED-F fixture: '.$jsonPath);
        }

        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded) || ! isset($decoded['rows']) || ! is_array($decoded['rows'])) {
            throw new ValueError("ISCED-F fixture missing 'rows' array: ".$jsonPath);
        }

        $out = [];
        foreach ($decoded['rows'] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $code = isset($row['code']) && is_scalar($row['code']) ? trim((string) $row['code']) : '';
            $label = isset($row['label']) && is_scalar($row['label']) ? trim((string) $row['label']) : '';
            if ($code === '') {
                continue;
            }

            if ($label === '') {
                continue;
            }

            if (! preg_match('/^\d{2,4}$/', $code)) {
                throw new ValueError(sprintf('Invalid ISCED-F code in %s: %s', $jsonPath, $code));
            }

            $out[$code] = ['code' => $code, 'label' => $label];
        }

        if ($out === []) {
            throw new ValueError('ISCED-F fixture produced no valid rows: '.$jsonPath);
        }

        // Sort by code; usort() also reindexes the code-keyed rows into a list
        usort($out, static fn (array $a, array $b): int => IscedHierarchy::compareCodes($a['code'], $b['code']));

        return $out;
    }
}
