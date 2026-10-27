<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EnumGeneration\GeneratorUtils;
use App\Services\EnumGeneration\InternationalCodeDesignator\IcdXmlParser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File as LaravelFile;

/**
 * Command to generate the InternationalCodeDesignator enum from the OpenPEPPOL
 * ISO 6523 ICD code list XML.
 *
 * Fetches the ICD XML from the OpenPEPPOL BIS invoice 3 repository and
 * writes app/Enums/InternationalCodeDesignator.php with IcdInfo attributes
 * and getters for id, name, and description.
 *
 * Data Source:
 *   - OpenPEPPOL BIS invoice 3 codelist icd.xml:
 *     https://raw.githubusercontent.com/OpenPEPPOL/peppol-bis-invoice-3/refs/heads/master/structure/codelist/icd.xml
 *
 * Usage:
 *   php artisan generate:international-code-designator-enum
 */
#[Description('Generate InternationalCodeDesignator enum from OpenPEPPOL ICD XML')]
#[Signature('generate:international-code-designator-enum')]
class GenerateInternationalCodeDesignatorEnum extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Inform the user that we are starting a network fetch
        $this->info('Fetching OpenPEPPOL ICD code list...');

        // Retrieve ICD records from remote XML
        $records = IcdXmlParser::fetchAndParse();

        // Abort on fetch/parse failure
        if ($records === null) {
            $this->error('Failed to fetch or parse ICD XML');

            unset($records);

            return 1;
        }

        // Build full enum PHP content (attribute class, cases, icdData, getters)
        $generatedAt = date('Y-m-d');
        $phpContent = $this->buildEnumPhpContent($records, $generatedAt);

        // Write the enum file to app/Enums/InternationalCodeDesignator.php
        $outputPath = app_path('Enums/InternationalCodeDesignator.php');
        LaravelFile::put(
            $outputPath,
            $phpContent
        );

        $this->info(
            'Generated InternationalCodeDesignator enum with '.\count($records).' cases'
        );
        $this->info('Output file: '.$outputPath);

        unset(
            $records,
            $phpContent,
            $outputPath
        );

        gc_collect_cycles();

        return 0;
    }

    /**
     * Build the full PHP source for the InternationalCodeDesignator enum.
     *
     * @param  array<int, array{id: string, name: string, description: string}>  $records
     * @param  string  $generatedAt  Date the enum was generated (YYYY-MM-DD)
     */
    private function buildEnumPhpContent(array $records, string $generatedAt): string
    {
        $out = "<?php\n\n";
        $out .= "namespace App\\Enums;\n\n";
        $out .= "use App\\Services\\EnumGeneration\\EnumDataUtils;\n";
        $out .= "use Attribute;\n\n";

        // IcdInfo attribute class
        $out .= $this->buildIcdInfoAttributeClass();

        // Enum docblock and opening
        $out .= $this->buildEnumDocblock(\count($records), $generatedAt);
        $out .= "enum InternationalCodeDesignator: string\n";
        $out .= "{\n";

        // Cases
        foreach ($records as $rec) {
            $caseName = 'ICD_'.$rec['id'];
            $idEsc = GeneratorUtils::escapeSingleQuotes($rec['id']);
            $nameEsc = GeneratorUtils::escapeSingleQuotes($rec['name']);
            $descEsc = GeneratorUtils::escapeSingleQuotes($rec['description']);
            $out .= "    #[IcdInfo(id: '{$idEsc}', name: '{$nameEsc}', description: '{$descEsc}')]\n";
            $out .= "    case {$caseName} = '{$rec['id']}';\n\n";
        }

        // icdData() and getters
        $out .= $this->buildIcdDataAndGetters();

        $out .= "}\n";

        unset(
            $records,
            $generatedAt
        );

        return $out;
    }

    /**
     * Build the IcdInfo attribute class source.
     */
    private function buildIcdInfoAttributeClass(): string
    {
        $out = "/**\n";
        $out .= " * Attribute for storing ISO 6523 ICD (International Code Designator)\n";
        $out .= " * information on enum cases.\n";
        $out .= " *\n";
        $out .= " * @param  string  \$id  The 4-digit ICD code\n";
        $out .= " * @param  string  \$name  The official name of the scheme\n";
        $out .= " * @param  string  \$description  Notes on use and issuing agency\n";
        $out .= " */\n";
        $out .= "#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]\n";
        $out .= "class IcdInfo\n";
        $out .= "{\n";
        $out .= "    /**\n";
        $out .= "     * @param  string  \$id  The 4-digit ICD code\n";
        $out .= "     * @param  string  \$name  The official name of the scheme\n";
        $out .= "     * @param  string  \$description  Notes on use and issuing agency\n";
        $out .= "     */\n";
        $out .= "    public function __construct(\n";
        $out .= "        public string \$id,\n";
        $out .= "        public string \$name,\n";
        $out .= "        public string \$description = '',\n";
        $out .= "    ) {}\n";

        return $out."}\n\n";
    }

    /**
     * Build the enum docblock (class-level).
     *
     * @param  int  $count  Number of enum cases
     * @param  string  $generatedAt  Date the enum was generated (YYYY-MM-DD)
     */
    private function buildEnumDocblock(int $count, string $generatedAt): string
    {
        $out = "/**\n";
        $out .= " * ISO 6523 International Code Designator (ICD) code list.\n";
        $out .= " *\n";
        $out .= " * This enum is intended for use wherever a standardized list of\n";
        $out .= " * organization identifier schemes is required (e.g. PEPPOL, e-invoicing).\n";
        $out .= " *\n";
        $out .= " * Data source:\n";
        $out .= " *   https://raw.githubusercontent.com/OpenPEPPOL/peppol-bis-invoice-3/refs/heads/master/structure/codelist/icd.xml\n";
        $out .= " *\n";
        $out .= " * Repository: https://github.com/OpenPEPPOL/peppol-bis-invoice-3\n";
        $out .= " *\n";
        $out .= sprintf(' * Generated: %s%s', $generatedAt, PHP_EOL);
        $out .= " * Regenerate with: php artisan generate:international-code-designator-enum\n";
        $out .= " *\n";
        $out .= " * Each case's backing value is the 4-digit ICD code. Case names use\n";
        $out .= " * the prefix ICD_ (e.g. ICD_0002). Attributes: id, name, description.\n";
        $out .= " */\n";

        unset(
            $count,
            $generatedAt
        );

        return $out;
    }

    /**
     * Build icdData() and getter methods (id, name, description).
     */
    private function buildIcdDataAndGetters(): string
    {
        $out = "    /**\n";
        $out .= "     * Lazily built data map from case value to id, name, description.\n";
        $out .= "     *\n";
        $out .= "     * @return array<string, array<string, mixed>>\n";
        $out .= "     */\n";
        $out .= "    private static function icdData(): array\n";
        $out .= "    {\n";
        $out .= "        \$dataExtractor = fn (\$info, \$caseValue) => [\n";
        $out .= "            'id' => \$info->id,\n";
        $out .= "            'name' => \$info->name,\n";
        $out .= "            'description' => \$info->description,\n";
        $out .= "        ];\n";
        $out .= "        \$result = EnumDataUtils::buildLazyDataMap(\n";
        $out .= "            self::class,\n";
        $out .= "            IcdInfo::class,\n";
        $out .= "            \$dataExtractor\n";
        $out .= "        );\n";
        $out .= "        unset(\$dataExtractor);\n";
        $out .= "\n";
        $out .= "        return \$result;\n";
        $out .= "    }\n\n";

        $out .= "    /**\n";
        $out .= "     * Get the 4-digit ICD code.\n";
        $out .= "     */\n";
        $out .= "    public function id(): string\n";
        $out .= "    {\n";
        $out .= "        \$data = self::icdData();\n";
        $out .= "\n";
        $out .= "        return EnumDataUtils::getFieldValue(\$data, \$this->value, 'id', 'ICD');\n";
        $out .= "    }\n\n";

        $out .= "    /**\n";
        $out .= "     * Get the official name of the scheme.\n";
        $out .= "     */\n";
        $out .= "    public function name(): string\n";
        $out .= "    {\n";
        $out .= "        \$data = self::icdData();\n";
        $out .= "\n";
        $out .= "        return EnumDataUtils::getFieldValue(\$data, \$this->value, 'name', 'ICD');\n";
        $out .= "    }\n\n";

        $out .= "    /**\n";
        $out .= "     * Get the description (notes on use and issuing agency).\n";
        $out .= "     */\n";
        $out .= "    public function description(): string\n";
        $out .= "    {\n";
        $out .= "        \$data = self::icdData();\n";
        $out .= "\n";
        $out .= "        return EnumDataUtils::getFieldValue(\$data, \$this->value, 'description', 'ICD');\n";

        return $out."    }\n";
    }
}
