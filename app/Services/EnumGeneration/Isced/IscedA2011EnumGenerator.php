<?php

declare(strict_types=1);

namespace App\Services\EnumGeneration\Isced;

/**
 * Builds the `IscedA2011` enum from the bundled UIS ISCED 2011 Annex III
 * (educational attainment) JSON fixture.
 */
final class IscedA2011EnumGenerator
{
    public const string SOURCE_URL = 'https://uis.unesco.org/sites/default/files/documents/international-standard-classification-of-education-isced-2011-en.pdf';

    public const string FIXTURE_FILENAME = 'isced-a-2011.json';

    public function generate(): string
    {
        $fixturePath = __DIR__.DIRECTORY_SEPARATOR.self::FIXTURE_FILENAME;
        $rows = (new IscedAnnexLoader)->load($fixturePath);
        $records = $this->attachHierarchy($rows);
        $config = new IscedEnumConfig(
            enumClass: 'IscedA2011',
            casePrefix: 'ISCEDA',
            classification: 'International Standard Classification of Education 2011, Educational Attainment',
            classificationShort: 'ISCED-A 2011',
            sourceTitle: 'UNESCO Institute for Statistics — ISCED 2011, Annex III (coding of educational attainment)',
            sourceUrl: self::SOURCE_URL,
            emitNotes: 'Codes use the UIS digit scheme (1–3 digit strings). Top-tier codes are single digits.',
            minTierLength: 1,
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
        $childrenByParent = IscedHierarchy::buildChildrenMap($codes, 1);
        $out = [];
        foreach ($rows as $row) {
            $code = $row['code'];
            $out[] = [
                'code' => $code,
                'label' => $row['label'],
                'parent' => IscedHierarchy::parentCode($code, 1),
                'ancestors' => IscedHierarchy::ancestors($code, 1, $existing),
                'children' => $childrenByParent[$code] ?? [],
                'descendants' => IscedHierarchy::descendants($code, $childrenByParent),
                'tier' => IscedHierarchy::tier($code, 1),
            ];
        }

        return $out;
    }
}
