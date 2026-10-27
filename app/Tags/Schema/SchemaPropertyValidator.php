<?php

declare(strict_types=1);

namespace App\Tags\Schema;

use App\Enums\SchemaProperty;
use App\Enums\SchemaType;
use ReflectionClass;
use ReflectionMethod;
use Spatie\SchemaOrg\BaseType;

/**
 * Validates schema node property names against valid Schema.org properties for
 * a given @type.
 *
 * Source of truth: generated Schema.org enums (`SchemaType`, `SchemaProperty`),
 * which include our project’s pragmatic conventions (e.g. Role-wrappable
 * relationship properties) and range/domain normalization rules.
 *
 * Spatie reflection is used only as a compatibility fallback for types that
 * are missing from the enum (or in the unlikely case the enum returns no
 * properties for a known type).
 */
class SchemaPropertyValidator
{
    /**
     * JSON-LD reserved keys that are always allowed in a node (not validated
     * against a Schema.org type).
     *
     * @var array<int, string>
     */
    private const array RESERVED_KEYS = [
        '@context',
        '@type',
        '@id',
    ];

    /**
     * Cache for valid property names for each Schema.org type.
     *
     * @var array<string, list<string>>
     */
    private static array $cache = [];

    /**
     * Map schema.org @type values to Spatie class names when they differ
     * (e.g., 3DModel is not a valid PHP class name; Spatie uses ThreeDimensionalModel).
     *
     * @var array<string, string> Schema type name => Spatie short class name
     */
    private const array TYPE_TO_SPATIE_CLASS = [
        '3DModel' => 'ThreeDimensionalModel',
    ];

    /**
     * Valid property names for the given Schema.org type.
     *
     * Uses the generated Schema.org enums first (so `Role` includes the curated
     * Role-wrappable relationship properties, and other project conventions are
     * respected). Spatie reflection is used only as a fallback.
     *
     * @param  string  $type  Schema.org @type (e.g., WebPage, CollectionPage)
     * @return list<string> Property names (e.g., url, name, about). Does not include @context, @type, @id.
     */
    public static function validPropertiesForType(
        string $type
    ): array {
        // ---
        // Return cached valid properties when already computed
        // ---

        // Check if the type is already cached
        if (isset(self::$cache[$type])) {
            // Return the cached valid properties
            return self::$cache[$type];
        }

        // Prefer generated Schema.org vocabulary (includes our project’s conventions).
        $valid = SchemaType::propertyNamesFor($type);

        // Compatibility fallback: if enum yields no properties, try Spatie reflection.
        if ($valid === []) {
            // Resolve type name to Spatie Schema.org type class (map names that are not valid PHP identifiers).
            // Unreachable map today: the enum covers 3DModel, so it never reaches this fallback.
            // @pest-mutate-ignore: CoalesceRemoveLeft
            $shortName = self::TYPE_TO_SPATIE_CLASS[$type] ?? ltrim($type, '\\');
            $className = 'Spatie\SchemaOrg\\'.$shortName;

            // Check if the Spatie type class exists for reflection-based discovery
            if (class_exists($className)) {
                // Build lookup of BaseType method names to exclude from valid properties
                $baseMethods = array_flip(self::baseTypeMethodNames());

                // Reflect the type class to discover property setter methods
                $ref = new ReflectionClass($className);

                // Loop through public methods on the type class
                foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                    // Get the method name (used as the schema.org property name)
                    $name = $method->getName();

                    // Skip methods inherited from BaseType (not schema.org properties)
                    if (isset($baseMethods[$name])) {
                        continue;
                    }

                    // Get the method's parameters to check for single-argument setters
                    $params = $method->getParameters();

                    // Skip methods that are not single-argument setters. Equivalent as
                    // break: for every class under spatie/schema-org's src, in any letter
                    // case, the other-arity methods come after the last single-argument one.
                    // @pest-mutate-ignore: ContinueToBreak
                    if (\count($params) !== 1) {
                        continue;
                    }

                    // Treat method name as valid schema.org property name
                    $valid[] = $name;
                }
            }
        }

        // Exclude properties that are superseded (SchemaProperty enum superseded_by non-empty).
        // Equivalent without array_values(): sort() below reindexes the list anyway.
        // @pest-mutate-ignore: UnwrapArrayValues
        $valid = array_values(
            array_filter(
                $valid,
                function (string $propertyName): bool {
                    $case = SchemaProperty::tryFrom($propertyName);
                    if ($case === null) {
                        return true;
                    }

                    $info = $case->info();
                    if ($info === null) {
                        return true;
                    }

                    $supersededBy = $info->superseded_by;

                    // Equivalent for the '' check: no SchemaProperty case has an empty
                    // superseded_by, only null or a replacement name.
                    // @pest-mutate-ignore: EmptyStringToNotEmpty
                    return $supersededBy === null || $supersededBy === '';
                }
            )
        );

        // Sort for consistent cache and comparison
        sort($valid);

        // Store valid properties in cache for this type
        self::$cache[$type] = $valid;

        // Return the list of valid properties for the type
        return self::$cache[$type];
    }

    /**
     * Property keys in the node that are not valid for the given @type.
     *
     * @param  string  $type  Schema.org @type (e.g., WebPage, CollectionPage)
     * @param  array<string, mixed>  $node  Schema node (must contain @type or pass type explicitly)
     * @return list<string> Invalid property keys (empty if valid)
     */
    public static function invalidProperties(
        string $type,
        array $node
    ): array {
        // Build allowed keys: reserved JSON-LD keys plus type-specific properties.
        // Equivalent for the fill value: array_diff_key() below reads only the keys.
        // @pest-mutate-ignore: TrueToFalse
        $allowed = array_fill_keys(
            [
                ...self::RESERVED_KEYS,
                ...self::validPropertiesForType($type),
            ],
            true
        );

        // Find node keys that are not in the allowed set
        $invalid = array_diff_key(
            $node,
            $allowed
        );

        // Return the list of invalid property keys
        return array_keys($invalid);
    }

    /**
     * BaseType method names to exclude from valid properties (not schema.org
     * properties).
     *
     * @return list<string> Public method names on BaseType (e.g., setProperty,
     *                      toArray)
     */
    private static function baseTypeMethodNames(): array
    {
        // Reflect BaseType to discover its public methods
        $ref = new ReflectionClass(BaseType::class);

        // Initialize list of BaseType method names
        $names = [];

        // Loop through public methods on BaseType
        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Collect the method name for exclusion from valid properties
            $names[] = $method->getName();
        }

        // Return the list of BaseType method names
        return $names;
    }
}
