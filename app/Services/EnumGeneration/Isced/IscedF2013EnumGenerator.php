<?php

declare(strict_types=1);

namespace App\Services\EnumGeneration\Isced;

/**
 * Builds the `IscedF2013` enum from the bundled `isced-f-2013.json` fixture
 * (Eurostat ISCEDF13-derived canonical codes and labels).
 *
 * Codes use the 2/3/4-digit UIS scheme (`01`, `011`, `0114`, …). The frozen
 * JSON was produced from the Eurostat SDMX TSV by stripping the leading `F`,
 * excluding aggregate rows (codes containing `_` or `-`), and excluding meta
 * rows without an `F` prefix (`TOTAL`, `OTH`, …). Labels are preserved
 * verbatim (including upstream typos).
 */
final class IscedF2013EnumGenerator
{
    public const string SOURCE_URL = 'https://ec.europa.eu/eurostat/api/dissemination/sdmx/2.1/codelist/ESTAT/ISCEDF13/latest?format=TSV&lang=en';

    public const string FIXTURE_FILENAME = 'isced-f-2013.json';

    /**
     * Default path for the bundled fixture under `app/Services/EnumGeneration/Isced/`.
     */
    public static function defaultFixturePath(): string
    {
        return __DIR__.DIRECTORY_SEPARATOR.self::FIXTURE_FILENAME;
    }

    public static function fixtureIsReadable(): bool
    {
        return is_readable(self::defaultFixturePath());
    }

    /**
     * Generate `app/Enums/IscedF2013.php`.
     *
     * @param  ?string  $jsonPath  Optional path to a fixture JSON file; when null the bundled file is used
     */
    public function generate(?string $jsonPath = null): string
    {
        $path = $jsonPath ?? self::defaultFixturePath();
        $rows = (new IscedFieldsLoader)->load($path);
        $records = $this->attachHierarchy($rows);
        $config = new IscedEnumConfig(
            enumClass: 'IscedF2013',
            casePrefix: 'ISCEDF',
            classification: 'International Standard Classification of Education: Fields of Education and Training 2013',
            classificationShort: 'ISCED-F 2013',
            sourceTitle: 'Eurostat SDMX codelist ISCEDF13 (English labels), frozen as JSON',
            sourceUrl: self::SOURCE_URL,
            emitNotes: 'Codes use the canonical 2/3/4-digit UIS scheme. Refresh `isced-f-2013.json` from the Eurostat ISCEDF13 TSV when you need updated labels.',
            minTierLength: 2,
        );

        return (new IscedEnumWriter)->write($records, $config);
    }

    /**
     * @param  array<int, array{code: string, label: string}>  $rows
     * @return array<int, array{code: string, label: string, parent: ?string, ancestors: array<int, string>, children: array<int, string>, descendants: array<int, string>, tier: int}>
     */
    private function attachHierarchy(array $rows): array
    {
        $codes = array_map(static fn (array $r): string => $r['code'], $rows);
        $existing = array_fill_keys($codes, true);
        $childrenByParent = IscedHierarchy::buildChildrenMap($codes, 2);
        $out = [];
        foreach ($rows as $row) {
            $code = $row['code'];
            $out[] = [
                'code' => $code,
                'label' => $row['label'],
                'parent' => IscedHierarchy::parentCode($code, 2),
                'ancestors' => IscedHierarchy::ancestors($code, 2, $existing),
                'children' => $childrenByParent[$code] ?? [],
                'descendants' => IscedHierarchy::descendants($code, $childrenByParent),
                'tier' => IscedHierarchy::tier($code, 2),
            ];
        }

        return $out;
    }
}
