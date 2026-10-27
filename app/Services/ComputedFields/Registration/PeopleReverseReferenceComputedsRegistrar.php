<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Services\ComputedFields\Reverse\PeopleReverseReferenceComputedValues;
use App\Support\Collections\PeopleCollection;
use Statamic\Facades\Collection;

/**
 * Registers the `people` reverse-reference computeds (UAMS-Web/uams-statamic-archive#1374): `credited_content`
 * and `clinical_provider_entry` — the content that credits this person in a creator
 * role, and the provider record that wraps this person — derived on read by
 * {@see PeopleReverseReferenceComputedValues} (no stored back-reference).
 */
final readonly class PeopleReverseReferenceComputedsRegistrar
{
    /**
     * The collection that receives the reverse-reference computeds.
     */
    private static function collectionHandle(): string
    {
        return PeopleCollection::handle();
    }

    /**
     * @param  PeopleReverseReferenceComputedValues  $reverse  Resolves each reverse set.
     */
    public function __construct(
        private PeopleReverseReferenceComputedValues $reverse
    ) {}

    /**
     * Register both people reverse-reference computeds.
     */
    public function register(): void
    {
        // Cache the injected service for readable first-class-callable bindings.
        $reverse = $this->reverse;

        // Content that credits this person in any creator role.
        Collection::computed(
            self::collectionHandle(),
            'credited_content',
            $reverse->resolveCreditedContent(...)
        );

        // The clinical-provider record that wraps this person (1:1).
        Collection::computed(
            self::collectionHandle(),
            'clinical_provider_entry',
            $reverse->resolveClinicalProviderEntry(...)
        );
    }
}
