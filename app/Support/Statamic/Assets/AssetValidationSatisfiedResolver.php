<?php

declare(strict_types=1);

namespace App\Support\Statamic\Assets;

use Illuminate\Support\Collection;
use Statamic\Assets\Asset as StatamicAsset;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fields\Validator;

/**
 * Resolve whether an asset satisfies its blueprint validation rules.
 */
final class AssetValidationSatisfiedResolver
{
    /**
     * Determine whether the asset's current metadata passes blueprint validation.
     *
     * @param  StatamicAsset  $asset  Asset instance to validate
     * @return bool True when all blueprint field validation rules pass
     */
    public static function resolve(
        StatamicAsset $asset
    ): bool {
        // Resolve the blueprint field definitions for the current asset
        $fields = $asset->blueprint()->fields()->all();

        // Resolve the raw metadata values from the current asset
        /** @var Collection<string, mixed> $dataCollection */
        $dataCollection = $asset->data();

        /** @var array<string, mixed> $rawData */
        $rawData = $dataCollection->all();

        // Normalize metadata values for CP/storage shape differences
        $normalizedData = self::normalizeDataForValidation(
            $fields,
            $rawData
        );

        // Build fields with normalized metadata values for validation
        /** @var Fields $validatedFields */
        $validatedFields = $asset->blueprint()->fields()->addValues(
            $normalizedData
        );

        // Build the Laravel validator from Statamic field validation rules
        /** @var Validator $statamicValidator */
        $statamicValidator = $validatedFields->validator();

        /** @var \Illuminate\Validation\Validator $validator */
        $validator = $statamicValidator->validator();

        // Resolve whether validation passes for current metadata
        $isValid = ! $validator->fails();

        // Unset local variables to free memory
        unset(
            $fields,
            $rawData,
            $normalizedData,
            $validatedFields,
            $statamicValidator,
            $validator
        );

        // Return validation result
        return $isValid;
    }

    /**
     * Normalize raw asset metadata into the shape expected by field validation.
     *
     * @param  iterable<Field>  $fields  Blueprint fields to inspect
     * @param  array<string, mixed>  $data  Raw asset metadata values
     * @return array<string, mixed> Normalized metadata values
     */
    private static function normalizeDataForValidation(
        iterable $fields,
        array $data
    ): array {
        // Initialize normalized data with the original metadata payload
        $normalized = $data;

        // Loop through each blueprint field definition
        foreach ($fields as $field) {
            // Resolve the current field handle
            /** @var string $handle */
            $handle = $field->handle();

            // Check if the current field handle is not present in metadata
            if (! \array_key_exists($handle, $normalized)) {
                // Skip missing values
                continue;
            }

            // Resolve the current field type
            $fieldType = $field->type();

            // Check if the field type is not terms
            if ($fieldType !== 'terms') {
                // Skip non-terms fields
                continue;
            }

            // Resolve the max items configuration for current field
            /** @var scalar|null $maxItems */
            $maxItems = $field->get('max_items');

            // Check if max items does not indicate single-select terms
            if ((int) $maxItems !== 1) {
                // Skip multi-select terms fields
                continue;
            }

            // Resolve the current metadata value for the field
            $value = $normalized[$handle];
            // Check if the current value is already an array or null
            if (\is_array($value)) {
                // Skip values already in normalized shape
                continue;
            }

            if ($value === null) {
                // Skip values already in normalized shape
                continue;
            }

            // Normalize single-select terms scalar into single-item array
            $normalized[$handle] = [$value];
        }

        // Unset local loop variables to free memory
        unset(
            $field,
            $handle,
            $fieldType,
            $maxItems,
            $value
        );

        // Return normalized metadata
        return $normalized;
    }
}
