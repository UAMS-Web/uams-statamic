<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Services\ComputedFields\Reverse\ContentUsageComputedValues;
use Statamic\Facades\Collection;

/**
 * Registers the content-usage computeds (UAMS-Web/uams-statamic-archive#1375) on the transcludable source
 * collections `reusable_content`, `tables`, and `calculators`: `used_by` (the pages
 * that transclude this entry) and `in_use` (a boolean at-a-glance signal) — derived
 * on read by {@see ContentUsageComputedValues} (no stored field, no backfill).
 */
final readonly class ContentUsageComputedsRegistrar
{
    /**
     * The transcludable source collections that receive the usage computeds.
     *
     * @var list<string>
     */
    private const array COLLECTION_HANDLES = [
        'reusable_content',
        'tables',
        'calculators',
    ];

    /**
     * @param  ContentUsageComputedValues  $usage  Resolves the usage of a source entry.
     */
    public function __construct(
        private ContentUsageComputedValues $usage
    ) {}

    /**
     * Register both usage computeds on all three source collections.
     */
    public function register(): void
    {
        // Cache the injected service for readable first-class-callable bindings.
        $usage = $this->usage;

        // The pages/entries that transclude this source entry.
        Collection::computed(
            self::COLLECTION_HANDLES,
            'used_by',
            $usage->resolveUsedBy(...)
        );

        // Whether this source entry is transcluded anywhere.
        Collection::computed(
            self::COLLECTION_HANDLES,
            'in_use',
            $usage->isInUse(...)
        );
    }
}
