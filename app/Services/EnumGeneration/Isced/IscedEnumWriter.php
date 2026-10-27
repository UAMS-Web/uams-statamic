<?php

declare(strict_types=1);

namespace App\Services\EnumGeneration\Isced;

use ValueError;

/**
 * Renders an ISCED enum PHP file from a normalized list of code rows.
 *
 * Shared by `IscedA2011EnumGenerator`, `IscedP2011EnumGenerator`, and
 * `IscedF2013EnumGenerator`. Each sub-generator loads its source and passes
 * a fully-populated record list (with hierarchy fields) plus a config object.
 */
final class IscedEnumWriter
{
    /**
     * Render a complete enum file and write it to disk.
     *
     * @param  array<int, array{code: string, label: string, parent: ?string, ancestors: array<int, string>, children: array<int, string>, descendants: array<int, string>, tier: int}>  $rows
     * @return string Absolute path to the written enum file
     */
    public function write(array $rows, IscedEnumConfig $config): string
    {
        if ($rows === []) {
            throw new ValueError('No rows provided for enum '.$config->enumClass);
        }

        $php = $this->buildPhpContent($rows, $config);
        $outputPath = app_path(sprintf('Enums/%s.php', $config->enumClass));
        if (file_put_contents($outputPath, $php) === false) {
            throw new ValueError('Failed to write enum file: '.$outputPath);
        }

        return $outputPath;
    }

    /**
     * @param  array<int, array{code: string, label: string, parent: ?string, ancestors: array<int, string>, children: array<int, string>, descendants: array<int, string>, tier: int}>  $rows
     */
    private function buildPhpContent(array $rows, IscedEnumConfig $config): string
    {
        $generatedAt = date('Y-m-d');
        $count = count($rows);
        $out = "<?php\n\n";
        $out .= "namespace App\\Enums;\n\n";
        $out .= "use App\\Enums\\Attributes\\IscedInfo;\n";
        $out .= "use App\\Services\\EnumGeneration\\EnumDataUtils;\n\n";
        $out .= $this->classDocblock($count, $generatedAt, $config);
        $out .= "enum {$config->enumClass}: string\n{\n";
        foreach ($rows as $rec) {
            $out .= $this->emitCase($rec, $config);
        }

        $out .= $this->emitDataMethod();
        $out .= $this->emitGetters($config);

        return $out."}\n";
    }

    private function classDocblock(int $count, string $generatedAt, IscedEnumConfig $config): string
    {
        return <<<DOC
/**
 * {$config->classification} ({$config->classificationShort}) code list.
 *
 * Source: {$config->sourceTitle}
 *   {$config->sourceUrl}
 *
 * {$config->emitNotes}
 *
 * Generated: {$generatedAt}
 * Regenerate: php artisan generate:isced-enums
 *
 * Case names use the prefix {$config->casePrefix}_ (backing value is the canonical code).
 * Total cases: {$count}
 */
DOC."\n";
    }

    /**
     * @param  array{code: string, label: string, parent: ?string, ancestors: array<int, string>, children: array<int, string>, descendants: array<int, string>, tier: int}  $rec
     */
    private function emitCase(array $rec, IscedEnumConfig $config): string
    {
        $code = $rec['code'];
        $caseName = $config->casePrefix.'_'.$code;
        $lines = [];
        $lines[] = '    #[IscedInfo(';
        $lines[] = '        code: '.$this->phpString($code).',';
        $lines[] = '        label: '.$this->phpString($rec['label']).',';
        $lines[] = '        parent: '.$this->phpNullableString($rec['parent']).',';
        $lines[] = '        ancestors: '.$this->phpStringArray($rec['ancestors']).',';
        $lines[] = '        children: '.$this->phpStringArray($rec['children']).',';
        $lines[] = '        descendants: '.$this->phpStringArray($rec['descendants']).',';
        $lines[] = '        tier: '.((int) $rec['tier']).',';
        $lines[] = '    )]';
        $lines[] = "    case {$caseName} = '{$code}';\n";

        return implode("\n", $lines)."\n";
    }

    private function phpString(string $s): string
    {
        // var_export emits single-quoted PHP string literals (with `'` and `\`
        // escaped); matches Pint's `single_quote` rule so generated enums stay
        // formatter-clean without a post-pass.
        return var_export($this->normalizeTypographicQuotationToAscii($s), true);
    }

    /**
     * Replace curly quotes with ASCII straight quotes so labels match plain-text conventions.
     */
    private function normalizeTypographicQuotationToAscii(string $s): string
    {
        /** @var array<string, string> $map */
        static $map = [
            "\u{2018}" => "'",
            "\u{2019}" => "'",
            "\u{201C}" => '"',
            "\u{201D}" => '"',
        ];

        return strtr($s, $map);
    }

    private function phpNullableString(mixed $s): string
    {
        if ($s === null || $s === '') {
            return 'null';
        }

        return $this->phpString(is_scalar($s) ? (string) $s : '');
    }

    private function phpStringArray(mixed $items): string
    {
        if (! is_array($items) || $items === []) {
            return '[]';
        }

        $parts = [];
        foreach ($items as $item) {
            $parts[] = $this->phpString(is_scalar($item) ? (string) $item : '');
        }

        return '['.implode(', ', $parts).']';
    }

    private function emitDataMethod(): string
    {
        return <<<'PHP'
    /**
     * Lazily built data map from case value to metadata fields.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function iscedData(): array
    {
        $dataExtractor = static fn (IscedInfo $info, string $caseValue): array => [
            'code' => $info->code,
            'label' => $info->label,
            'parent' => $info->parent ?? '',
            'ancestors' => $info->ancestors,
            'children' => $info->children,
            'descendants' => $info->descendants,
            'tier' => $info->tier,
        ];
        $result = EnumDataUtils::buildLazyDataMap(
            self::class,
            IscedInfo::class,
            $dataExtractor
        );
        unset($dataExtractor);

        return $result;
    }


PHP;
    }

    private function emitGetters(IscedEnumConfig $config): string
    {
        $enumName = $config->enumClass;

        return <<<PHP
    public function code(): string
    {
        return EnumDataUtils::getFieldValue(
            self::iscedData(),
            \$this->value,
            'code',
            '{$enumName}',
        );
    }

    public function label(): string
    {
        return EnumDataUtils::getFieldValue(
            self::iscedData(),
            \$this->value,
            'label',
            '{$enumName}',
        );
    }

    public function parent(): ?string
    {
        \$v = EnumDataUtils::getFieldValue(
            self::iscedData(),
            \$this->value,
            'parent',
            '{$enumName}',
        );

        return \$v === '' ? null : \$v;
    }

    /**
     * @return array<int, string>
     */
    public function ancestors(): array
    {
        return EnumDataUtils::getArrayFieldValue(
            self::iscedData(),
            \$this->value,
            'ancestors',
            '{$enumName}',
        );
    }

    /**
     * @return array<int, string>
     */
    public function children(): array
    {
        return EnumDataUtils::getArrayFieldValue(
            self::iscedData(),
            \$this->value,
            'children',
            '{$enumName}',
        );
    }

    /**
     * @return array<int, string>
     */
    public function descendants(): array
    {
        return EnumDataUtils::getArrayFieldValue(
            self::iscedData(),
            \$this->value,
            'descendants',
            '{$enumName}',
        );
    }

    public function tier(): int
    {
        return EnumDataUtils::getIntegerFieldValue(
            self::iscedData(),
            \$this->value,
            'tier',
            '{$enumName}',
        );
    }

    /**
     * Human-readable labels from the broadest ancestor through this code.
     *
     * @param  string|null  \$separator  When null, returns an ordered list of labels; when set, the same sequence joined by this string (for example `', '`, `'; '`, or `' — '`).
     * @return array<int, string>|string
     */
    public function labelsWithAncestors(?string \$separator = null): array|string
    {
        \$labels = [];
        foreach (\$this->ancestors() as \$ancestorCode) {
            \$labels[] = self::from(\$ancestorCode)->label();
        }
        \$labels[] = \$this->label();

        if (\$separator === null) {
            return \$labels;
        }

        return implode(\$separator, \$labels);
    }

PHP;
    }
}
