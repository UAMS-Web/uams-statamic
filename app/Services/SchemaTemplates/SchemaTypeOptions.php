<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

use App\Enums\SchemaType;

/**
 * Schema.org type options for CP fields and validation. Type list and hierarchy
 * come from the SchemaType enum (descendantsOf, etc.). Results are cached in
 * static properties for the lifetime of the PHP process (e.g. one FPM worker).
 *
 * @see https://schema.org/
 */
class SchemaTypeOptions
{
    /**
     * Cache of all type names from SchemaType enum (declaration order).
     *
     * @var list<string>|null Type names, or null if not yet built
     */
    private static ?array $allTypeNames = null;

    /**
     * Cache of schemaObjectTypes() result (type name => label).
     *
     * @var array<string, string>|null Type name => label, or null if not yet built
     */
    private static ?array $schemaObjectTypes = null;

    /**
     * All Schema.org types for schema object entries (e.g., Thing, Person,
     * Organization). Used by the schema_objects collection to populate the
     * type select and to drive property fields via spatie/schema-org.
     *
     * @return array<string, string> Type name => label (type name as label)
     */
    public static function schemaObjectTypes(): array
    {
        return self::$schemaObjectTypes ??= self::allTypesOptions();
    }

    /**
     * All Schema.org type options from the enum (sorted, name => label).
     *
     * @return array<string, string> Type name => label
     */
    private static function allTypesOptions(): array
    {
        return self::typeNamesToOptions(self::allTypeNamesFromEnum());
    }

    /**
     * All Schema.org type names from the SchemaType enum. Cached so
     * schemaObjectTypes only iterates the enum once per PHP process. Order
     * matches the generator's sorted case list.
     *
     * @return list<string> Type names (backing values from enum cases)
     */
    private static function allTypeNamesFromEnum(): array
    {
        // Check if the all type names are already cached.
        if (self::$allTypeNames !== null) {
            // Return the cached all type names.
            return self::$allTypeNames;
        }

        // Get all type names from the enum (generator emits cases sorted)
        $names = array_map(
            fn (SchemaType $case) => $case->value,
            SchemaType::cases()
        );

        // Assign type names to cache
        self::$allTypeNames = $names;

        // Return cached all type names
        return self::$allTypeNames;
    }

    /**
     * Build type name => label options from a list of type names.
     *
     * @param  list<string>  $names  Schema.org type names to use as both keys and labels
     * @return array<string, string> Type name => label (same value for both)
     */
    private static function typeNamesToOptions(
        array $names
    ): array {
        return array_combine(
            $names,
            $names
        );
    }
}
