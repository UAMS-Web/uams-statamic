<?php

declare(strict_types=1);

namespace App\Services\EnumGeneration;

use ReflectionEnum;
use ValueError;

/**
 * Utility class for managing enum data access patterns.
 *
 * This class provides common functionality for enums that use attributes
 * to store metadata and need lazy-loading data maps with reflection.
 * It helps DRY out the repeated patterns found in Country.php and State.php.
 */
final class EnumDataUtils
{
    /**
     * Validates that a case exists in the data map.
     *
     * @param  array<string, array<string, mixed>>  $dataMap  The data map to search
     * @param  string  $caseValue  The enum case value to look up
     * @param  string  $enumName  The enum name for error messages
     *
     * @throws ValueError When the case is not found in the data map
     */
    private static function validateCaseExists(
        array $dataMap,
        string $caseValue,
        string $enumName
    ): void {
        // Check if the case exists in the data map
        if (! isset($dataMap[$caseValue])) {
            // Throw error for unknown case
            throw new ValueError(
                sprintf('Unknown %s: %s', $enumName, $caseValue)
            );
        }
    }

    /**
     * Validates that a field exists for a case in the data map.
     *
     * @param  array<string, array<string, mixed>>  $dataMap  The data map to search
     * @param  string  $caseValue  The enum case value to look up
     * @param  string  $fieldName  The field name to retrieve
     * @param  string  $enumName  The enum name for error messages
     *
     * @throws ValueError When the field is not found for the case
     */
    private static function validateFieldExists(
        array $dataMap,
        string $caseValue,
        string $fieldName,
        string $enumName
    ): void {
        // Check if the field exists for this case
        if (! isset($dataMap[$caseValue][$fieldName])) {
            // Throw error for unknown field
            throw new ValueError(
                sprintf("Unknown field '%s' for %s: %s", $fieldName, $enumName, $caseValue)
            );
        }
    }

    /**
     * Validates that a value is of the expected type.
     *
     * @param  mixed  $value  The value to validate
     * @param  string  $expectedType  The expected type name (e.g., 'string', 'boolean')
     * @param  callable(mixed):bool  $typeChecker  Function to check if value is of expected type
     * @param  string  $fieldName  The field name for error messages
     * @param  string  $enumName  The enum name for error messages
     * @param  string  $caseValue  The enum case value for error messages
     *
     * @throws ValueError When the value is not of the expected type
     */
    private static function validateType(
        mixed $value,
        string $expectedType,
        callable $typeChecker,
        string $fieldName,
        string $enumName,
        string $caseValue
    ): void {
        // Check if value is not of expected type
        if (! $typeChecker($value)) {
            // Throw error for invalid type
            throw new ValueError(
                sprintf("Field '%s' for %s %s is not a %s", $fieldName, $enumName, $caseValue, $expectedType)
            );
        }
    }

    /**
     * Lazily initializes and returns a data map for an enum using reflection.
     *
     * This method builds a map by reflecting over enum cases and reading
     * attributes of the specified type. It caches the result to avoid
     * recomputing on subsequent calls.
     *
     * @template  T of object
     *
     * @param  class-string<\UnitEnum>  $enumClass  The enum class name
     * @param  class-string<T>  $attributeClass  The attribute class to read
     * @param  callable(T,string):array<string,mixed>  $dataExtractor  Function to extract data from attribute and case value
     * @return array<string,array<string,mixed>> The cached data map
     */
    public static function buildLazyDataMap(
        string $enumClass,
        string $attributeClass,
        callable $dataExtractor
    ): array {
        // Cache container to avoid recomputing the map on each call
        /** @var array<string, array<string, array<string, mixed>>> $cache */
        static $cache = [];

        // Create cache key from enum class and attribute class
        $cacheKey = sprintf('%s::%s', $enumClass, $attributeClass);

        // ---
        // Return cached map when already initialized
        // ---

        // Check if cache already contains the map
        if (isset($cache[$cacheKey])) {
            // Return the cached map
            return $cache[$cacheKey];
        }

        // Build map by reflecting over enum cases and reading attributes
        $data = [];

        // Use reflection to access case attributes
        $reflection = new ReflectionEnum($enumClass);

        // ---
        // Populate the map for each enum case
        // ---

        // Loop through each enum case
        foreach ($reflection->getCases() as $case) {
            // Get the attribute instances for this case
            $attributes = $case->getAttributes($attributeClass);

            // ---
            // Skip cases without attributes (should not happen in well-formed enums)
            // ---

            // Check if case has no attributes
            if (empty($attributes)) {
                // Skip this case
                continue;
            }

            // Only backed enum cases carry a backing value to key the map by;
            // skip pure (non-backed) cases rather than fataling on getBackingValue().
            if (! $case instanceof \ReflectionEnumBackedCase) {
                // Skip non-backed case
                continue;
            }

            // Instantiate attribute to read stored metadata
            $info = $attributes[0]->newInstance();

            // Normalize the backing value to a string key; these enums are
            // string-backed and the map is keyed by string case values.
            $backingValue = (string) $case->getBackingValue();

            // Extract data using the provided callback
            $caseData = $dataExtractor(
                $info,
                $backingValue
            );

            // Store in the cache map
            $data[$backingValue] = $caseData;
        }

        // Cache the result
        $cache[$cacheKey] = $data;

        // Clean up variables to free up memory
        unset(
            $reflection,
            $attributes,
            $info,
            $caseData,
            $case,
            $dataExtractor,
            $attributeClass,
            $enumClass,
        );

        // Return initialized map
        return $cache[$cacheKey];
    }

    /**
     * Safely retrieves a value from a data map with error handling.
     *
     * This method provides a consistent way to access enum data with
     * proper error handling when a case is not found in the data map.
     *
     * @param  array<string, array<string, mixed>>  $dataMap  The data map to search
     * @param  string  $caseValue  The enum case value to look up
     * @param  string  $fieldName  The field name to retrieve
     * @param  string  $enumName  The enum name for error messages
     * @return string The field value
     *
     * @throws ValueError When the case is not found in the data map
     */
    public static function getFieldValue(
        array $dataMap,
        string $caseValue,
        string $fieldName,
        string $enumName
    ): string {
        // Validate case exists
        self::validateCaseExists(
            $dataMap,
            $caseValue,
            $enumName
        );

        // Validate field exists
        self::validateFieldExists(
            $dataMap,
            $caseValue,
            $fieldName,
            $enumName
        );

        // Return the field value
        $value = $dataMap[$caseValue][$fieldName];

        // ---
        // Ensure the value is a string
        // ---

        // Validate value type
        self::validateType(
            $value,
            'string',
            '\is_string',
            $fieldName,
            $enumName,
            $caseValue
        );

        // Clean up variables to free up memory
        unset(
            $dataMap,
            $caseValue,
            $fieldName,
            $enumName
        );

        // Return the field value
        /** @var string $value */
        return $value;
    }

    /**
     * Safely retrieves a boolean value from a data map with error handling.
     *
     * This method provides a consistent way to access boolean enum data with
     * proper error handling when a case is not found in the data map.
     *
     * @param  array<string, array<string, mixed>>  $dataMap  The data map to search
     * @param  string  $caseValue  The enum case value to look up
     * @param  string  $fieldName  The field name to retrieve
     * @param  string  $enumName  The enum name for error messages
     * @param  bool  $defaultValue  Default value if field is not set
     * @return bool The field value
     *
     * @throws ValueError When the case is not found in the data map
     */
    public static function getBooleanFieldValue(
        array $dataMap,
        string $caseValue,
        string $fieldName,
        string $enumName,
        bool $defaultValue = false
    ): bool {
        // Validate case exists
        self::validateCaseExists(
            $dataMap,
            $caseValue,
            $enumName
        );

        // ---
        // If the field is not set, return the default value
        // ---

        // Check if field is not set
        if (! isset($dataMap[$caseValue][$fieldName])) {
            // Clean up variables to free up memory
            unset(
                $dataMap,
                $caseValue,
                $fieldName,
                $enumName
            );

            // Return the default value
            return $defaultValue;
        }

        // Get the field value
        $value = $dataMap[$caseValue][$fieldName];

        // ---
        // Ensure the value is a boolean
        // ---

        // Validate value type
        self::validateType(
            $value,
            'boolean',
            '\is_bool',
            $fieldName,
            $enumName,
            $caseValue
        );

        // Clean up variables to free up memory
        unset(
            $dataMap,
            $caseValue,
            $fieldName,
            $enumName,
            $defaultValue
        );

        // Return the field value
        /** @var bool $value */
        return $value;
    }

    /**
     * Safely retrieves an integer value from a data map with error handling.
     *
     * This method provides a consistent way to access integer enum data with
     * proper error handling when a case is not found in the data map.
     *
     * @param  array<string, array<string, mixed>>  $dataMap  The data map to search
     * @param  string  $caseValue  The enum case value to look up
     * @param  string  $fieldName  The field name to retrieve
     * @param  string  $enumName  The enum name for error messages
     * @return int The field value
     *
     * @throws ValueError When the case is not found in the data map
     */
    public static function getIntegerFieldValue(
        array $dataMap,
        string $caseValue,
        string $fieldName,
        string $enumName
    ): int {
        // Validate case exists
        self::validateCaseExists(
            $dataMap,
            $caseValue,
            $enumName
        );

        // Validate field exists
        self::validateFieldExists(
            $dataMap,
            $caseValue,
            $fieldName,
            $enumName
        );

        // Get the field value
        $value = $dataMap[$caseValue][$fieldName];

        // ---
        // Ensure the value is an integer
        // ---

        // Validate value type
        self::validateType(
            $value,
            'integer',
            '\is_int',
            $fieldName,
            $enumName,
            $caseValue
        );

        // Clean up variables to free up memory
        unset(
            $dataMap,
            $caseValue,
            $fieldName,
            $enumName
        );

        // Return the field value
        /** @var int $value */
        return $value;
    }

    /**
     * Safely retrieves an array value from a data map with error handling.
     *
     * This method provides a consistent way to access array enum data with
     * proper error handling when a case is not found in the data map.
     *
     * @param  array<string, array<string, mixed>>  $dataMap  The data map to search
     * @param  string  $caseValue  The enum case value to look up
     * @param  string  $fieldName  The field name to retrieve
     * @param  string  $enumName  The enum name for error messages
     * @return array<int,string> The field value
     *
     * @throws ValueError When the case is not found in the data map
     */
    public static function getArrayFieldValue(
        array $dataMap,
        string $caseValue,
        string $fieldName,
        string $enumName
    ): array {
        // Validate case exists
        self::validateCaseExists(
            $dataMap,
            $caseValue,
            $enumName
        );

        // Validate field exists
        self::validateFieldExists(
            $dataMap,
            $caseValue,
            $fieldName,
            $enumName
        );

        // Get the field value
        $value = $dataMap[$caseValue][$fieldName];

        // Ensure the value is an array
        // Validate value type
        self::validateType(
            $value,
            'array',
            '\is_array',
            $fieldName,
            $enumName,
            $caseValue
        );

        // Clean up variables to free up memory
        unset(
            $dataMap,
            $caseValue,
            $fieldName,
            $enumName
        );

        // Return the field value
        /** @var array<int,string> $value */
        return $value;
    }

    /**
     * Creates a data accessor method for an enum field.
     *
     * This method returns a closure that can be used as a method in an enum
     * to access a specific field from the data map. It encapsulates the
     * common pattern of getting the data map and retrieving a field value.
     *
     * @param  class-string<\UnitEnum>  $enumClass  The enum class name
     * @param  class-string<object>  $attributeClass  The attribute class to read
     * @param  callable(object,string):array<string,mixed>  $dataExtractor  Function to extract data from attribute and case value
     * @param  string  $fieldName  The field name to access
     * @param  string  $enumName  The enum name for error messages
     * @return callable(string):string A closure that takes the enum value and returns the field value
     */
    public static function createFieldAccessor(
        string $enumClass,
        string $attributeClass,
        callable $dataExtractor,
        string $fieldName,
        string $enumName
    ): callable {
        // Return closure that accesses the field value
        return function (string $enumValue) use ($enumClass, $attributeClass, $dataExtractor, $fieldName, $enumName): string {
            // Get the data map
            $dataMap = self::buildLazyDataMap(
                $enumClass,
                $attributeClass,
                $dataExtractor
            );

            // Return the field value
            return self::getFieldValue(
                $dataMap,
                $enumValue,
                $fieldName,
                $enumName
            );
        };
    }

    /**
     * Creates a boolean data accessor method for an enum field.
     *
     * This method returns a closure that can be used as a method in an enum
     * to access a specific boolean field from the data map. It encapsulates the
     * common pattern of getting the data map and retrieving a boolean field value.
     *
     * @param  class-string<\UnitEnum>  $enumClass  The enum class name
     * @param  class-string<object>  $attributeClass  The attribute class to read
     * @param  callable(object,string):array<string,mixed>  $dataExtractor  Function to extract data from attribute and case value
     * @param  string  $fieldName  The field name to access
     * @param  string  $enumName  The enum name for error messages
     * @param  bool  $defaultValue  Default value if field is not set
     * @return callable(string):bool A closure that takes the enum value and returns the field value
     */
    public static function createBooleanFieldAccessor(
        string $enumClass,
        string $attributeClass,
        callable $dataExtractor,
        string $fieldName,
        string $enumName,
        bool $defaultValue = false
    ): callable {
        // Return closure that accesses the boolean field value
        return function (string $enumValue) use ($enumClass, $attributeClass, $dataExtractor, $fieldName, $enumName, $defaultValue): bool {
            // Get the data map
            $dataMap = self::buildLazyDataMap(
                $enumClass,
                $attributeClass,
                $dataExtractor
            );

            // Return the field value
            return self::getBooleanFieldValue(
                $dataMap,
                $enumValue,
                $fieldName,
                $enumName,
                $defaultValue
            );
        };
    }
}
