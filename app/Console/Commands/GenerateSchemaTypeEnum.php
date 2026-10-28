<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EnumGeneration\Schema\SchemaTypeEnumGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Command to generate the SchemaType, SchemaProperty, SchemaDataType, and
 * SchemaEnumerationMember enums from schema.org JSON-LD.
 *
 * Fetches schemaorg-current-https.jsonld once, extracts types, properties,
 * DataTypes, and enumeration members, then writes app/Enums/SchemaType.php,
 * app/Enums/SchemaProperty.php, app/Enums/SchemaDataType.php, and
 * app/Enums/SchemaEnumerationMember.php. Property descriptions are type-agnostic.
 *
 * Usage:
 *   php artisan generate:schema-type-enum
 */
#[Description('Generate SchemaType enum from schema.org JSON-LD vocabulary')]
#[Signature('generate:schema-type-enum')]
class GenerateSchemaTypeEnum extends Command
{
    /**
     * Execute the console command.
     *
     * @param  SchemaTypeEnumGenerator  $schemaTypeEnumGenerator  The schema.org enum generator.
     * @return int Command exit code
     */
    public function handle(SchemaTypeEnumGenerator $schemaTypeEnumGenerator): int
    {
        $this->info(
            'Generating SchemaType, SchemaProperty, SchemaDataType, and SchemaEnumerationMember enums from schema.org JSON-LD...'
        );

        [$typePath, $propertyPath, $dataTypePath, $cacheWarmed, $enumerationMemberPath] = $schemaTypeEnumGenerator->generate();

        $this->info('Wrote '.$typePath);
        $this->info('Wrote '.$propertyPath);
        $this->info('Wrote '.$dataTypePath);
        $this->info('Wrote '.$enumerationMemberPath);
        if ($cacheWarmed) {
            $this->info('Warmed EntriesBySchemaType alias cache.');
        }

        return 0;
    }
}
