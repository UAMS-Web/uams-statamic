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
        // Only the keys are read, through isset(), so the fill value cannot change them
        // @pest-mutate-ignore: TrueToFalse
        $existing = array_fill_keys($codes, true);
        // A shorter top tier only adds parents no code has, which buildChildrenMap() skips
        // @pest-mutate-ignore: DecrementInteger
        $childrenByParent = IscedHierarchy::buildChildrenMap($codes, 2);
        $out = [];
        foreach ($rows as $row) {
            $code = $row['code'];
            $out[] = [
                'code' => $code,
                'label' => $row['label'],
                'parent' => IscedHierarchy::parentCode($code, 2),
                // A shorter top tier only adds ancestors no code has, which ancestors() filters out
                // @pest-mutate-ignore: DecrementInteger
                'ancestors' => IscedHierarchy::ancestors($code, 2, $existing),
                'children' => $childrenByParent[$code] ?? [],
                'descendants' => IscedHierarchy::descendants($code, $childrenByParent),
                'tier' => IscedHierarchy::tier($code, 2),
            ];
        }

        return $out;
    }
}
