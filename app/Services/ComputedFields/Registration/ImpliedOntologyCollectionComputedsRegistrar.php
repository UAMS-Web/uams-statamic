<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Services\ComputedFields\Ontology\ImpliedOntologyComputedValues;
use App\Support\Collections\PeopleCollection;
use Statamic\Facades\Collection;

/**
 * Registers the transitive implied-ontology computeds (D9, UAMS-Web/uams-statamic-archive#1063) on the Provider
 * (`people`) and Location (`clinical_locations`) collections, each inferred from the
 * entry's `expertise` anchor via {@see ImpliedOntologyComputedValues}:
 *
 * - `implied_services` / `implied_conditions` — the specialty overlap **unioned** with the
 *   explicit special-interest override (the on-profile render source, UAMS-Web/uams-statamic-archive#1063).
 * - `transitive_services` / `transitive_conditions` — the specialty overlap with the
 *   override **removed** (the plain-transitive leg for JSON-LD, UAMS-Web/uams-statamic-archive#1067 slice 5, so the
 *   special-interest items surface only in their `Role`-wrapped node).
 */
final readonly class ImpliedOntologyCollectionComputedsRegistrar
{
    /**
     * Collections that carry the implied-ontology computeds — a Provider or a
     * Location, each anchored to specialties by its own `expertise` array.
     *
     * @return list<string>
     */
    private static function collectionHandles(): array
    {
        return [
            PeopleCollection::handle(),
            'clinical_locations',
        ];
    }

    /**
     * @param  ImpliedOntologyComputedValues  $impliedOntologyComputed  Transitive + override implied-set resolver.
     */
    public function __construct(
        private ImpliedOntologyComputedValues $impliedOntologyComputed
    ) {}

    /**
     * Register the implied-services and implied-conditions computeds for both collections.
     */
    public function register(): void
    {
        // Cache the injected service in a local for readable first-class-callable bindings.
        $implied = $this->impliedOntologyComputed;

        // Clinical services implied transitively from the entry's specialties (+ override).
        Collection::computed(
            self::collectionHandles(),
            'implied_services',
            $implied->resolveImpliedServices(...)
        );

        // Clinical conditions implied transitively from the entry's specialties (+ override).
        Collection::computed(
            self::collectionHandles(),
            'implied_conditions',
            $implied->resolveImpliedConditions(...)
        );

        // The plain-transitive legs for JSON-LD (UAMS-Web/uams-statamic-archive#1067 slice 5): the specialty-overlap sets
        // with the explicit special-interest override REMOVED, so the provider `knowsAbout`
        // / location `availableService` bulk edges never double-list an item that is also
        // surfaced as a `Role`-wrapped special interest.
        Collection::computed(
            self::collectionHandles(),
            'transitive_services',
            $implied->resolveTransitiveServices(...)
        );

        Collection::computed(
            self::collectionHandles(),
            'transitive_conditions',
            $implied->resolveTransitiveConditions(...)
        );

        // A scalar flag the schema property Antlers can gate on to suppress the empty
        // special-interest `Role` wrapper (UAMS-Web/uams-statamic-archive#1067 slice 5) — the raw `special_interest_*`
        // relationship fields are not exposed in that flattened context.
        Collection::computed(
            self::collectionHandles(),
            'has_special_interest',
            $implied->resolveHasSpecialInterest(...)
        );

        // A services-only gate for the location `Role` (which emits `availableService`
        // from services alone): the combined flag above would fire on a condition-only
        // location and emit a bare, targetless `Role` (UAMS-Web/uams-statamic-archive#1067 slice 5 review).
        Collection::computed(
            self::collectionHandles(),
            'has_special_interest_services',
            $implied->resolveHasSpecialInterestServices(...)
        );
    }
}
