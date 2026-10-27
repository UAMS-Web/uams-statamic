<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Services\ComputedFields\Ontology\SpecialInterestInverseComputedValues;
use Statamic\Facades\Collection;

/**
 * Registers the special-interest inverse computeds (UAMS-Web/uams-statamic-archive#1064) on the Condition
 * (`clinical_conditions`) and Service (`clinical_services`) collections:
 * `special_interest_providers` and `special_interest_locations` — the published
 * Providers / Locations whose typed special-interest field (`special_interest_clinical_conditions`
 * or `special_interest_clinical_services`) names this C/T entry, derived via
 * {@see SpecialInterestInverseComputedValues} (the computed inverse of the one-sided
 * authored fields, not a stored `stillat` sync).
 */
final readonly class SpecialInterestInverseComputedsRegistrar
{
    /**
     * The Condition/Service collections that receive the inverse computeds.
     *
     * @var list<string>
     */
    private const array COLLECTION_HANDLES = [
        'clinical_conditions',
        'clinical_services',
    ];

    /**
     * @param  SpecialInterestInverseComputedValues  $inverseComputed  The special-interest inverse resolver.
     */
    public function __construct(
        private SpecialInterestInverseComputedValues $inverseComputed
    ) {}

    /**
     * Register the special-interest inverse computeds for both C/T collections.
     */
    public function register(): void
    {
        // Cache the injected service in a local for readable first-class-callable bindings.
        $inverse = $this->inverseComputed;

        // Providers (people) with a special interest in this Condition/Service.
        Collection::computed(
            self::COLLECTION_HANDLES,
            'special_interest_providers',
            $inverse->resolveSpecialInterestProviders(...)
        );

        // Locations (clinical_locations) with a special interest in this Condition/Service.
        Collection::computed(
            self::COLLECTION_HANDLES,
            'special_interest_locations',
            $inverse->resolveSpecialInterestLocations(...)
        );
    }
}
