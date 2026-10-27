<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Support\QuantitativeValueConfig;
use App\Support\QuantitativeValueDurationTotalYears;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;

/**
 * Optional `Collection::computed` merges of virtual `total_years` for root-level
 * `quantitative_value` fields in duration mode.
 */
final class QuantitativeValueDurationComputedsRegistrar
{
    /**
     * Tuples of collection handle and root `quantitative_value` field handle to augment.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const array QUANTITATIVE_VALUE_DURATION_TOTAL_YEARS_COMPUTED_FIELDS = [];

    /**
     * Create the registrar over the tuples to register.
     *
     * The container builds it with the constant, so production registers nothing
     * until a collection opts in there; a test passes its own tuples.
     *
     * @param  list<array{0: string, 1: string}>  $fields  Collection and root field handles
     */
    public function __construct(
        private readonly array $fields = self::QUANTITATIVE_VALUE_DURATION_TOTAL_YEARS_COMPUTED_FIELDS
    ) {}

    /**
     * Register duration-mode quantitative value computeds from the configured tuple list.
     */
    public function register(): void
    {
        // Walk each configured collection and field handle pair
        foreach ($this->fields as [$collectionHandle, $fieldHandle]) {
            // Register a computed merger that injects virtual total_years when applicable
            Collection::computed(
                $collectionHandle,
                $fieldHandle,
                function (Entry $entry, mixed $stored) use ($fieldHandle): mixed {
                    // Resolve the blueprint field definition for the configured handle
                    /** @var Blueprint $blueprint */
                    $blueprint = $entry->blueprint();
                    /** @var Field|null $field */
                    $field = $blueprint->field($fieldHandle);

                    // Return the stored value unchanged when the field is absent
                    if ($field === null) {
                        return $stored;
                    }

                    // Build quantitative value config from the field definition
                    /** @var array<string, mixed> $fieldConfig */
                    $fieldConfig = $field->config();
                    $c = QuantitativeValueConfig::from($fieldConfig);

                    // Return the stored value unchanged when not in duration mode
                    if (! $c->isDurationMode()) {
                        return $stored;
                    }

                    // Normalize stored payload to an array for merging
                    /** @var array<string, mixed> $arr */
                    $arr = \is_array($stored) ? $stored : [];

                    // Merge virtual total_years into duration majors using config rules
                    return QuantitativeValueDurationTotalYears::mergeIntoDurationStored($arr, $c);
                }
            );
        }
    }
}
