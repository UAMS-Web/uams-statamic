<?php

declare(strict_types=1);

namespace App\Services\EnumGeneration\Isco08;

use App\Imports\SpreadsheetImport;
use App\Services\EnumGeneration\GeneratorUtils;
use Maatwebsite\Excel\Facades\Excel;
use ValueError;

/**
 * Builds a timestamped `Isco08` enum file from official ILO ISCO-08 files.
 */
final class Isco08EnumGenerator
{
    public const string URL_HUB = 'https://isco-ilo.netlify.app/en/isco-08/';

    public const string URL_DEFINITIONS_XLSX = 'https://www.ilo.org/ilostat-files/ISCO/newdocs-08-2021/ISCO-08/ISCO-08%20EN%20Structure%20and%20definitions.xlsx';

    public const string URL_INDEX_XLSX = 'https://www.ilo.org/ilostat-files/ISCO/newdocs-08-2021/ISCO-08/ISCO-08%20-88%20EN%20Index.xlsx';

    /**
     * Default paths for repo-shipped fixtures under `app/Services/EnumGeneration/Isco08/`
     * (used by `php artisan generate:isco-08-enum --local`).
     *
     * @return array{definitions: string, index: string}
     */
    public static function defaultLocalFixturePaths(): array
    {
        $dir = app_path('Services/EnumGeneration/Isco08');

        return [
            'definitions' => $dir.DIRECTORY_SEPARATOR.'ISCO-08 EN Structure and definitions.xlsx',
            'index' => $dir.DIRECTORY_SEPARATOR.'ISCO-08 -88 EN Index.xlsx',
        ];
    }

    /**
     * True when both default local fixture files exist and are readable.
     */
    public static function localFixturesAreReadable(): bool
    {
        $paths = self::defaultLocalFixturePaths();

        return array_all($paths, fn (string $path): bool => is_readable($path));
    }

    /**
     * @param  ?string  $definitionsXlsxPath  Path to Structure and definitions.xlsx, or null to download
     * @param  ?string  $indexXlsxPath  Path to Index .xlsx, or null to download
     * @return string Absolute path to the written generated enum file
     */
    public function generate(
        ?string $definitionsXlsxPath = null,
        ?string $indexXlsxPath = null,
    ): string {
        $definitionsXlsxPath ??= $this->downloadToTemp(
            self::URL_DEFINITIONS_XLSX,
            'isco08_definitions_',
            '.xlsx'
        );
        $indexXlsxPath ??= $this->downloadToTemp(
            self::URL_INDEX_XLSX,
            'isco08_index_',
            '.xlsx'
        );

        $definitionsByCode = $this->parseDefinitionsWorkbook($definitionsXlsxPath);
        $titlesByCode = $this->parseIndexWorkbook($indexXlsxPath);
        $structureRows = $this->buildStructureRowsFromSourceTables($definitionsByCode, $titlesByCode);
        $this->ensureHierarchyUnitsFromPrefixes($structureRows);

        $merged = $this->mergeRecords($structureRows, $definitionsByCode, $titlesByCode);
        $this->attachHierarchy($merged);

        $outputPath = app_path('Enums/Isco08.php');
        $php = $this->buildPhpContent($merged);
        if (file_put_contents($outputPath, $php) === false) {
            throw new ValueError('Failed to write enum file: '.$outputPath);
        }

        return $outputPath;
    }

    private function downloadToTemp(string $url, string $prefix, string $suffix): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.$prefix.uniqid('', true).$suffix;
        $ok = GeneratorUtils::downloadToFile(
            $url,
            $path,
            ['Accept' => '*/*'],
            180,
        );
        if (! $ok) {
            throw new ValueError('Failed to download: '.$url);
        }

        return $path;
    }

    /**
     * Builds one row per distinct code found in the definitions and index workbooks.
     *
     * @param  array<string, array<string, string>>  $definitionsByCode
     * @param  array<string, array<int, string>>  $titlesByCode
     * @return array<string, array{code: string, title: string}>
     */
    private function buildStructureRowsFromSourceTables(
        array $definitionsByCode,
        array $titlesByCode,
    ): array {
        $union = array_unique(
            array_merge(
                array_keys($definitionsByCode),
                array_keys($titlesByCode),
            )
        );
        if ($union === []) {
            throw new ValueError(
                'No ISCO-08 codes found: the definitions and index workbooks produced no valid code rows.',
            );
        }

        $byCode = [];
        foreach ($union as $code) {
            if (! $this->isNumericIscoCode($code)) {
                continue;
            }

            $def = $definitionsByCode[$code] ?? null;
            $title = $def !== null && $def['title_en'] !== ''
                ? $def['title_en']
                : '';
            $byCode[$code] = [
                'code' => $code,
                'title' => $title,
            ];
        }

        if ($byCode === []) {
            throw new ValueError(
                'No valid numeric ISCO-08 codes after filtering the definitions and index workbooks.',
            );
        }

        return $byCode;
    }

    /**
     * Derives `level` and group fields from the 1–4 digit `code` (ISCO-08 structure).
     *
     * @return array{level: int, major_group: string, sub_major_group: ?string, minor_group: ?string, unit_group: ?string}
     */
    private function hierarchyFromCode(string $code): array
    {
        $n = strlen($code);

        return [
            'level' => $n,
            'major_group' => $n >= 1 ? substr($code, 0, 1) : '',
            'sub_major_group' => $n >= 2 ? substr($code, 0, 2) : null,
            'minor_group' => $n >= 3 ? substr($code, 0, 3) : null,
            'unit_group' => $n >= 4 ? substr($code, 0, 4) : null,
        ];
    }

    /**
     * Adds missing intermediate codes (e.g. 1, 11, 111) when the workbooks only list leaf rows.
     *
     * @param  array<string, array{code: string, title: string}>  $byCode
     */
    private function ensureHierarchyUnitsFromPrefixes(array &$byCode): void
    {
        $needed = [];
        foreach (array_keys($byCode) as $u) {
            $needed[$u] = true;
            $walk = $u;
            while (strlen($walk) > 1) {
                $walk = substr($walk, 0, -1);
                $needed[$walk] = true;
            }
        }

        foreach (array_keys($needed) as $code) {
            if (isset($byCode[$code])) {
                continue;
            }

            if (! $this->isNumericIscoCode($code)) {
                continue;
            }

            $byCode[$code] = $this->syntheticStructureRow($code);
        }
    }

    /**
     * @return array{code: string, title: string}
     */
    private function syntheticStructureRow(string $code): array
    {
        return [
            'code' => $code,
            'title' => '',
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function parseDefinitionsWorkbook(string $path): array
    {
        /** @var array<int, array<int, mixed>> $sheets */
        $sheets = Excel::toArray(new SpreadsheetImport, $path);
        $parsed = $this->parseSheetWithHeader(
            $sheets,
            [
                'isco 08 code',
                'isco-08 code',
                'isco-08',
                'isco08 code',
            ],
            ['title en', 'title', 'definition'],
        );
        if ($parsed === null) {
            throw new ValueError('Could not locate definitions sheet in '.$path);
        }

        [$rows, $headerIndex] = $parsed;
        $headerRow = $rows[$headerIndex];
        $col = $this->buildColumnIndex($headerRow);
        $codeKeys = ['isco 08 code', 'isco-08 code', 'isco08 code', 'isco code'];
        $codeCol = null;
        foreach ($codeKeys as $ck) {
            if (isset($col[$ck])) {
                $codeCol = $col[$ck];
                break;
            }
        }

        if ($codeCol === null) {
            throw new ValueError('Definitions workbook missing ISCO code column: '.$path);
        }

        $titleCol = $col['title en'] ?? $col['title'] ?? null;
        $defCol = $col['definition'] ?? null;
        $tasksCol = $col['tasks include'] ?? null;
        $exclCol = $col['excluded occupations'] ?? null;
        $notesCol = $col['notes'] ?? null;

        $out = [];
        for ($r = $headerIndex + 1, $max = count($rows); $r < $max; $r++) {
            $line = $rows[$r];
            $code = $this->normalizeIscoCode($line[$codeCol] ?? '');
            if ($code === '') {
                continue;
            }

            if (! $this->isNumericIscoCode($code)) {
                continue;
            }

            $titleEn = $titleCol !== null ? trim($this->toStringValue($line[$titleCol] ?? '')) : '';
            $definition = $defCol !== null ? $this->normalizeMultiline($this->toStringValue($line[$defCol] ?? '')) : '';
            $tasks = $tasksCol !== null ? $this->normalizeMultiline($this->toStringValue($line[$tasksCol] ?? '')) : '';
            $excluded = $exclCol !== null ? $this->normalizeMultiline($this->toStringValue($line[$exclCol] ?? '')) : '';
            $notes = $notesCol !== null ? $this->normalizeMultiline($this->toStringValue($line[$notesCol] ?? '')) : '';

            $out[$code] = [
                'title_en' => $titleEn,
                'definition' => $definition,
                'tasks_include' => $tasks,
                'excluded_occupations' => $excluded,
                'notes' => $notes,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function parseIndexWorkbook(string $path): array
    {
        /** @var array<int, array<int, mixed>> $sheets */
        $sheets = Excel::toArray(new SpreadsheetImport, $path);
        $parsed = $this->parseSheetWithHeader(
            $sheets,
            [
                'isco-08',
                'isco 08',
                'isco08',
            ],
            ['english title'],
        );
        if ($parsed === null) {
            throw new ValueError('Could not locate index sheet in '.$path);
        }

        [$rows, $headerIndex] = $parsed;
        $headerRow = $rows[$headerIndex];
        $codeCol = $this->findColumnIndexExact($headerRow, ['isco-08', 'isco 08', 'isco08']);
        $titleCol = $this->findColumnIndexExact($headerRow, ['english title']);
        if ($codeCol === null || $titleCol === null) {
            throw new ValueError('Index workbook missing ISCO-08 or English title column: '.$path);
        }

        $byCode = [];
        for ($r = $headerIndex + 1, $max = count($rows); $r < $max; $r++) {
            $line = $rows[$r];
            $code = $this->normalizeIscoCode($line[$codeCol] ?? '');
            if ($code === '') {
                continue;
            }

            if (! $this->isNumericIscoCode($code)) {
                continue;
            }

            $title = trim($this->toStringValue($line[$titleCol] ?? ''));
            if ($title === '') {
                continue;
            }

            if (mb_strlen($title) < 2) {
                continue;
            }

            if (! isset($byCode[$code])) {
                $byCode[$code] = [];
            }

            $byCode[$code][$title] = true;
        }

        foreach ($byCode as $c => $set) {
            $list = array_keys($set);
            natcasesort($list);
            $byCode[$c] = array_values($list);
        }

        return $byCode;
    }

    /**
     * @param  array<string, array{code: string, title: string}>  $structureRows
     * @param  array<string, array<string, string>>  $definitionsByCode
     * @param  array<string, array<int, string>>  $titlesByCode
     * @return array<string, array<string, mixed>>
     */
    private function mergeRecords(
        array $structureRows,
        array $definitionsByCode,
        array $titlesByCode,
    ): array {
        $out = [];
        foreach ($structureRows as $code => $row) {
            $h = $this->hierarchyFromCode($code);
            $def = $definitionsByCode[$code] ?? null;
            $titles = $titlesByCode[$code] ?? [];
            $title = $row['title'];
            if ($def !== null && $def['title_en'] !== '') {
                $title = $def['title_en'];
            } elseif ($title === '' && $titles !== []) {
                $title = (string) $titles[0];
            }

            $definition = $def['definition'] ?? '';
            $tasksInclude = $def['tasks_include'] ?? '';
            $excluded = $def['excluded_occupations'] ?? '';
            $notes = $def['notes'] ?? '';

            $out[$code] = [
                'level' => $h['level'],
                'code' => $code,
                'major_group' => $h['major_group'],
                'sub_major_group' => $h['sub_major_group'],
                'minor_group' => $h['minor_group'],
                'unit_group' => $h['unit_group'],
                'title' => $title,
                'definition' => $definition,
                'tasks_include' => $tasksInclude,
                'excluded_occupations' => $excluded === '' ? null : $excluded,
                'notes' => $notes === '' ? null : $notes,
                'occupational_titles' => $titles,
            ];
        }

        uksort($out, $this->compareIscoCodes(...));

        return $out;
    }

    /**
     * @param  array<string, array<string, mixed>>  $merged
     */
    private function attachHierarchy(array &$merged): void
    {
        $units = array_keys($merged);
        usort($units, $this->compareIscoCodes(...));
        $unitSet = array_fill_keys($units, true);
        $childrenByParent = [];
        foreach ($units as $u) {
            $p = $this->parentCode($u);
            if ($p !== null && isset($unitSet[$p])) {
                if (! isset($childrenByParent[$p])) {
                    $childrenByParent[$p] = [];
                }

                $childrenByParent[$p][] = $u;
            }
        }

        foreach (array_keys($childrenByParent) as $p) {
            usort($childrenByParent[$p], $this->compareIscoCodes(...));
        }

        foreach ($units as $u) {
            $parent = $this->parentCode($u);
            if ($parent !== null && ! isset($unitSet[$parent])) {
                $parent = null;
            }

            $ancestors = $this->ancestorsForUnit($u);
            $ancestors = array_values(array_filter(
                $ancestors,
                static fn (string $a): bool => isset($unitSet[$a])
            ));
            $children = $childrenByParent[$u] ?? [];
            $descendants = $this->collectDescendants($u, $childrenByParent);
            usort($descendants, $this->compareIscoCodes(...));
            $merged[$u]['parent'] = $parent;
            $merged[$u]['ancestors'] = $ancestors;
            $merged[$u]['children'] = $children;
            $merged[$u]['descendants'] = $descendants;
        }
    }

    /**
     * @param  array<string, list<string>>  $childrenByParent
     * @return array<int, string>
     */
    private function collectDescendants(string $unit, array $childrenByParent): array
    {
        $out = [];
        $stack = $childrenByParent[$unit] ?? [];
        $seen = [];
        while ($stack !== []) {
            $c = array_pop($stack);
            if (isset($seen[$c])) {
                continue;
            }

            $seen[$c] = true;
            $out[] = $c;
            foreach ($childrenByParent[$c] ?? [] as $next) {
                $stack[] = $next;
            }
        }

        usort($out, $this->compareIscoCodes(...));

        return $out;
    }

    /**
     * @return array<int, string>
     */
    private function ancestorsForUnit(string $unit): array
    {
        $chain = [];
        $u = $unit;
        while (strlen($u) > 1) {
            $u = substr($u, 0, -1);
            $chain[] = $u;
        }

        return array_reverse($chain);
    }

    private function parentCode(string $code): ?string
    {
        if (strlen($code) <= 1) {
            return null;
        }

        return substr($code, 0, -1);
    }

    private function compareIscoCodes(string $a, string $b): int
    {
        $len = strlen($a) <=> strlen($b);
        if ($len !== 0) {
            return $len;
        }

        return strcmp($a, $b);
    }

    private function normalizeIscoCode(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_float($value) || is_int($value)) {
            return (string) $value;
        }

        $s = trim($this->toStringValue($value));
        if (preg_match('/^(\d+)\.0+$/', $s, $m)) {
            return $m[1];
        }

        return $s;
    }

    /**
     * Whether the code is a valid ISCO-08 unit for this enum (digits only, length 1–4).
     */
    private function isNumericIscoCode(string $code): bool
    {
        return (bool) preg_match('/^\d{1,4}$/', $code);
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @param  array<int, string>  $normalizedNames  Lowercase, trimmed, single-space keys to match
     */
    private function findColumnIndexExact(array $headerRow, array $normalizedNames): ?int
    {
        $want = [];
        foreach ($normalizedNames as $n) {
            $want[$n] = true;
        }

        foreach ($headerRow as $idx => $name) {
            $key = strtolower(trim(preg_replace('/\s+/', ' ', $this->toStringValue($name)) ?? ''));
            if ($key !== '' && isset($want[$key])) {
                return (int) $idx;
            }
        }

        return null;
    }

    private function normalizeMultiline(string $s): string
    {
        $s = str_replace("\r\n", "\n", $s);
        $s = str_replace("\r", "\n", $s);

        return trim($s);
    }

    /**
     * Coerces a mixed spreadsheet cell (or any value) to string, matching PHP's implicit cast.
     */
    private function toStringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Coerces a mixed spreadsheet cell (or any value) to int, matching PHP's implicit cast.
     */
    private function toIntValue(mixed $value): int
    {
        return is_scalar($value) ? (int) $value : 0;
    }

    /**
     * @param  array<int, array<int, mixed>>  $sheets
     * @param  array<int, string>  $codeHeaderCandidates
     * @param  array<int, string>  $extraNeedles
     * @return ?array{0: array<int, array<int, mixed>>, 1: int}
     */
    private function parseSheetWithHeader(
        array $sheets,
        array $codeHeaderCandidates,
        array $extraNeedles,
    ): ?array {
        foreach ($sheets as $sheetRows) {
            /** @var array<int, array<int, mixed>> $sheetRows */
            $headerIndex = $this->findHeaderRowIndex($sheetRows, $codeHeaderCandidates, $extraNeedles);
            if ($headerIndex !== null) {
                return [$sheetRows, $headerIndex];
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, string>  $codeHeaderCandidates
     * @param  array<int, string>  $extraNeedles
     */
    private function findHeaderRowIndex(array $rows, array $codeHeaderCandidates, array $extraNeedles): ?int
    {
        $limit = min(60, count($rows));
        for ($i = 0; $i < $limit; $i++) {
            $joined = '';
            foreach ($rows[$i] as $cell) {
                $joined .= '|'.strtolower(trim($this->toStringValue($cell)));
            }

            $joined .= '|';
            $hasCode = array_any($codeHeaderCandidates, fn (string $cand): bool => str_contains($joined, '|'.$cand.'|')
                || str_contains($joined, '|'.$cand)
                || str_contains($joined, $cand.'|'));

            if (! $hasCode) {
                continue;
            }

            $extraOk = array_any($extraNeedles, fn (string $n): bool => str_contains($joined, $n));

            if ($extraOk) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @return array<string, int>
     */
    private function buildColumnIndex(array $headerRow): array
    {
        $col = [];
        foreach ($headerRow as $idx => $name) {
            $key = strtolower(trim(preg_replace('/\s+/', ' ', $this->toStringValue($name)) ?? ''));
            if ($key !== '' && ! isset($col[$key])) {
                $col[$key] = (int) $idx;
            }
        }

        return $col;
    }

    /**
     * @param  array<string, array<string, mixed>>  $merged
     */
    private function buildPhpContent(array $merged): string
    {
        $generatedAt = date('Y-m-d');
        $count = count($merged);
        $out = "<?php\n\n";
        $out .= "namespace App\\Enums;\n\n";
        $out .= "use App\\Enums\\Attributes\\Isco08Info;\n";
        $out .= "use App\\Services\\EnumGeneration\\EnumDataUtils;\n\n";

        $out .= $this->enumClassDocblock($count, $generatedAt);
        $out .= "enum Isco08: string\n{\n";

        foreach ($merged as $rec) {
            $out .= $this->emitCase($rec);
        }

        $out .= $this->emitIsco08DataMethod();
        $out .= $this->emitGetters();
        $out .= $this->emitHierarchyHelperMethods();

        return $out."}\n";
    }

    private function enumClassDocblock(int $count, string $generatedAt): string
    {
        $hub = self::URL_HUB;
        $def = self::URL_DEFINITIONS_XLSX;
        $idx = self::URL_INDEX_XLSX;

        return <<<DOC
/**
 * International Standard Classification of Occupations (ISCO-08) structure codes.
 *
 * Hub (context and references): {$hub}
 *
 * Inputs when regenerating (see `php artisan generate:isco-08-enum`):
 *   - Structure and definitions (Excel, optional default download): {$def}
 *   - Occupational titles index (Excel, optional default download): {$idx}
 *   - Codes and English titles for structure rows are derived from these two workbooks; no separate structure CSV.
 *
 * Generated: {$generatedAt}
 * Regenerate: php artisan generate:isco-08-enum
 *
 * Case names use prefix ISCO08_ (backing value is the 1–4 digit code).
 * Total cases: {$count}
 */
DOC."\n";
    }

    /**
     * @param  array<string, mixed>  $rec
     */
    private function emitCase(array $rec): string
    {
        $code = $this->toStringValue($rec['code']);
        $caseName = 'ISCO08_'.$code;
        $level = $this->toIntValue($rec['level']);
        $lines = [];
        $lines[] = '    #[Isco08Info(';
        $lines[] = '        level: '.$level.',';
        $lines[] = '        code: '.$this->phpString($this->toStringValue($rec['code'])).',';
        $lines[] = '        major_group: '.$this->phpString($this->toStringValue($rec['major_group'])).',';
        $lines[] = '        sub_major_group: '.$this->phpNullableString($rec['sub_major_group'] ?? null).',';
        $lines[] = '        minor_group: '.$this->phpNullableString($rec['minor_group'] ?? null).',';
        $lines[] = '        unit_group: '.$this->phpNullableString($rec['unit_group'] ?? null).',';
        $lines[] = '        title: '.$this->phpString($this->toStringValue($rec['title'])).',';
        $lines[] = '        definition: '.$this->phpString($this->toStringValue($rec['definition'])).',';
        $lines[] = '        tasks_include: '.$this->phpString($this->toStringValue($rec['tasks_include'])).',';
        $lines[] = '        excluded_occupations: '.$this->phpNullableString($rec['excluded_occupations']).',';
        $lines[] = '        notes: '.$this->phpNullableString($rec['notes']).',';
        $lines[] = '        occupational_titles: '.$this->phpStringArray($rec['occupational_titles']).',';
        $lines[] = '        parent: '.$this->phpNullableString($rec['parent'] ?? null).',';
        $lines[] = '        ancestors: '.$this->phpStringArray($rec['ancestors']).',';
        $lines[] = '        children: '.$this->phpStringArray($rec['children']).',';
        $lines[] = '        descendants: '.$this->phpStringArray($rec['descendants']).',';
        $lines[] = '    )]';
        $lines[] = "    case {$caseName} = '{$code}';\n";

        return implode("\n", $lines)."\n";
    }

    private function phpString(string $s): string
    {
        $encoded = json_encode($s, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded === false) {
            return '""';
        }

        return $encoded;
    }

    private function phpNullableString(mixed $s): string
    {
        if ($s === null || $s === '') {
            return 'null';
        }

        return $this->phpString($this->toStringValue($s));
    }

    private function phpStringArray(mixed $items): string
    {
        if (! is_array($items) || $items === []) {
            return '[]';
        }

        $parts = [];
        foreach ($items as $item) {
            $parts[] = $this->phpString($this->toStringValue($item));
        }

        return '['.implode(', ', $parts).']';
    }

    private function emitIsco08DataMethod(): string
    {
        return <<<'PHP'
    /**
     * Lazily built data map from case value to metadata fields.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function isco08Data(): array
    {
        $dataExtractor = static fn (Isco08Info $info, string $caseValue): array => [
            'level' => $info->level,
            'code' => $info->code,
            'major_group' => $info->major_group,
            'sub_major_group' => $info->sub_major_group ?? '',
            'minor_group' => $info->minor_group ?? '',
            'unit_group' => $info->unit_group ?? '',
            'title' => $info->title,
            'definition' => $info->definition,
            'tasks_include' => $info->tasks_include,
            'excluded_occupations' => $info->excluded_occupations ?? '',
            'notes' => $info->notes ?? '',
            'occupational_titles' => $info->occupational_titles,
            'parent' => $info->parent ?? '',
            'ancestors' => $info->ancestors,
            'children' => $info->children,
            'descendants' => $info->descendants,
        ];
        $result = EnumDataUtils::buildLazyDataMap(
            self::class,
            Isco08Info::class,
            $dataExtractor
        );
        unset($dataExtractor);

        return $result;
    }

PHP;
    }

    private function emitGetters(): string
    {
        return <<<'PHP'
    public function level(): int
    {
        return EnumDataUtils::getIntegerFieldValue(
            self::isco08Data(),
            $this->value,
            'level',
            'Isco08',
        );
    }

    public function code(): string
    {
        return EnumDataUtils::getFieldValue(
            self::isco08Data(),
            $this->value,
            'code',
            'Isco08',
        );
    }

    public function majorGroup(): string
    {
        return EnumDataUtils::getFieldValue(
            self::isco08Data(),
            $this->value,
            'major_group',
            'Isco08',
        );
    }

    public function subMajorGroup(): ?string
    {
        $v = EnumDataUtils::getFieldValue(
            self::isco08Data(),
            $this->value,
            'sub_major_group',
            'Isco08',
        );

        return $v === '' ? null : $v;
    }

    public function minorGroup(): ?string
    {
        $v = EnumDataUtils::getFieldValue(
            self::isco08Data(),
            $this->value,
            'minor_group',
            'Isco08',
        );

        return $v === '' ? null : $v;
    }

    public function unitGroup(): ?string
    {
        $v = EnumDataUtils::getFieldValue(
            self::isco08Data(),
            $this->value,
            'unit_group',
            'Isco08',
        );

        return $v === '' ? null : $v;
    }

    public function title(): string
    {
        return EnumDataUtils::getFieldValue(
            self::isco08Data(),
            $this->value,
            'title',
            'Isco08',
        );
    }

    public function definition(): string
    {
        return EnumDataUtils::getFieldValue(
            self::isco08Data(),
            $this->value,
            'definition',
            'Isco08',
        );
    }

    public function tasksInclude(): string
    {
        return EnumDataUtils::getFieldValue(
            self::isco08Data(),
            $this->value,
            'tasks_include',
            'Isco08',
        );
    }

    public function excludedOccupations(): ?string
    {
        $v = EnumDataUtils::getFieldValue(
            self::isco08Data(),
            $this->value,
            'excluded_occupations',
            'Isco08',
        );

        return $v === '' ? null : $v;
    }

    public function notes(): ?string
    {
        $v = EnumDataUtils::getFieldValue(
            self::isco08Data(),
            $this->value,
            'notes',
            'Isco08',
        );

        return $v === '' ? null : $v;
    }

    /**
     * @return array<int, string>
     */
    public function occupationalTitles(): array
    {
        return EnumDataUtils::getArrayFieldValue(
            self::isco08Data(),
            $this->value,
            'occupational_titles',
            'Isco08',
        );
    }

    public function parent(): ?string
    {
        $v = EnumDataUtils::getFieldValue(
            self::isco08Data(),
            $this->value,
            'parent',
            'Isco08',
        );

        return $v === '' ? null : $v;
    }

    /**
     * @return array<int, string>
     */
    public function ancestors(): array
    {
        return EnumDataUtils::getArrayFieldValue(
            self::isco08Data(),
            $this->value,
            'ancestors',
            'Isco08',
        );
    }

    /**
     * @return array<int, string>
     */
    public function children(): array
    {
        return EnumDataUtils::getArrayFieldValue(
            self::isco08Data(),
            $this->value,
            'children',
            'Isco08',
        );
    }

    /**
     * @return array<int, string>
     */
    public function descendants(): array
    {
        return EnumDataUtils::getArrayFieldValue(
            self::isco08Data(),
            $this->value,
            'descendants',
            'Isco08',
        );
    }

PHP;
    }

    /**
     * ISCO-08 structure helpers (hierarchy option rows, deepest resolved code) for any UI or API use.
     */
    private function emitHierarchyHelperMethods(): string
    {
        return "\n".<<<'PHP'
    /**
     * Major groups (level 1) and direct children grouped by parent code, for cascading selects or APIs.
     * Each row uses `key` = structure code, `value` = "{code} {title}" (single ASCII space), and
     * `description` = the structure definition text from the enum case.
     *
     * @return array{majors: list<array{key: string, value: string, description: string}>, children_by_parent: array<string, list<array{key: string, value: string, description: string}>>}
     */
    public static function hierarchyOptionRows(): array
    {
        $majors = [];
        $childrenByParent = [];
        foreach (self::cases() as $case) {
            $code = $case->value;
            $label = $code.' '.$case->title();
            $definition = $case->definition();
            if ($case->level() === 1) {
                $majors[] = [
                    'key' => $code,
                    'value' => $label,
                    'description' => $definition,
                ];
            }
            $parent = $case->parent();
            if ($parent !== null && $parent !== '') {
                $childrenByParent[$parent][] = [
                    'key' => $code,
                    'value' => $label,
                    'description' => $definition,
                ];
            }
        }
        usort(
            $majors,
            static fn (array $a, array $b): int => self::compareStructureCodes($a['key'], $b['key']),
        );
        foreach ($childrenByParent as &$rows) {
            usort(
                $rows,
                static fn (array $a, array $b): int => self::compareStructureCodes($a['key'], $b['key']),
            );
        }
        unset($rows);

        return [
            'majors' => $majors,
            'children_by_parent' => $childrenByParent,
        ];
    }

    /**
     * Tier map suitable for the `isco_08` fieldtype from a resolved 1–4 digit
     * structure code, or null when the code is unknown.
     *
     * @return array{major_group: string, sub_major_group: string|null, minor_group: string|null, unit_group: string|null}|null
     */
    public static function tierStoragePayloadForCode(string $code): ?array
    {
        $case = self::tryFrom($code);

        if ($case === null) {
            return null;
        }

        return [
            'major_group' => $case->majorGroup(),
            'sub_major_group' => $case->subMajorGroup(),
            'minor_group' => $case->minorGroup(),
            'unit_group' => $case->unitGroup(),
        ];
    }

    /**
     * Deepest selected 1–4 digit structure code from a partial tier map (stops at first empty tier).
     *
     * Expected keys (when present): `major_group`, `sub_major_group`, `minor_group`, `unit_group`.
     *
     * @param  array<string, mixed>  $partialTiers
     */
    public static function resolvedDeepestStructureCode(array $partialTiers): ?string
    {
        $major = self::normalizeOptionalCodeString($partialTiers['major_group'] ?? null);
        if ($major === null || $major === '') {
            return null;
        }
        $sub = self::normalizeOptionalCodeString($partialTiers['sub_major_group'] ?? null);
        if ($sub === null || $sub === '') {
            return $major;
        }
        $minor = self::normalizeOptionalCodeString($partialTiers['minor_group'] ?? null);
        if ($minor === null || $minor === '') {
            return $sub;
        }
        $unit = self::normalizeOptionalCodeString($partialTiers['unit_group'] ?? null);
        if ($unit === null || $unit === '') {
            return $minor;
        }

        return $unit;
    }

    private static function normalizeOptionalCodeString(mixed $v): ?string
    {
        if ($v === null || $v === false) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    /**
     * Sort order for structure codes: shorter codes first, then lexical within the same length.
     */
    private static function compareStructureCodes(string $a, string $b): int
    {
        $len = strlen($a) <=> strlen($b);
        if ($len !== 0) {
            return $len;
        }

        return strcmp($a, $b);
    }

PHP;
    }
}
