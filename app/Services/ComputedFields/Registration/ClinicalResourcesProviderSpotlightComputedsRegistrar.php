<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Services\ComputedFields\ProviderSpotlight\ProviderSpotlightComputedValues;
use Statamic\Assets\Asset;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Fields\Blueprint;

/**
 * Registers the provider-derived computeds for the `provider_spotlight`
 * clinical-resource type (UAMS-Web/uams-statamic-archive#1425): the fallback introduction prose, the auto
 * excerpt, and the precedence-resolved featured image. Each is gated to the
 * `provider_spotlight` blueprint so the other four resource types are untouched.
 */
final readonly class ClinicalResourcesProviderSpotlightComputedsRegistrar
{
    public function __construct(
        private ProviderSpotlightComputedValues $spotlight
    ) {}

    /**
     * Register the spotlight computeds on the `clinical_resources` collection.
     */
    public function register(): void
    {
        $service = $this->spotlight;

        Collection::computed(
            'clinical_resources',
            'spotlight_auto_introduction',
            fn (Entry $entry, mixed $_stored): ?string => $this->isSpotlight($entry)
                ? $service->autoIntroduction($entry)
                : null
        );

        Collection::computed(
            'clinical_resources',
            'spotlight_auto_excerpt',
            fn (Entry $entry, mixed $_stored): ?string => $this->isSpotlight($entry)
                ? $service->autoExcerpt($entry)
                : null
        );

        Collection::computed(
            'clinical_resources',
            'spotlight_featured_image',
            fn (Entry $entry, mixed $_stored): ?Asset => $this->isSpotlight($entry)
                ? $service->featuredImage($entry)
                : null
        );

        Collection::computed(
            'clinical_resources',
            'spotlight_provider_name',
            fn (Entry $entry, mixed $_stored): ?string => $this->isSpotlight($entry)
                ? $service->providerName($entry)
                : null
        );

        Collection::computed(
            'clinical_resources',
            'spotlight_provider_title',
            fn (Entry $entry, mixed $_stored): ?string => $this->isSpotlight($entry)
                ? $service->providerTitle($entry)
                : null
        );

        Collection::computed(
            'clinical_resources',
            'spotlight_bookable',
            fn (Entry $entry, mixed $_stored): bool => $this->isSpotlight($entry) && $service->isBookable($entry)
        );

        Collection::computed(
            'clinical_resources',
            'spotlight_referral_required',
            fn (Entry $entry, mixed $_stored): bool => $this->isSpotlight($entry) && $service->referralRequired($entry)
        );

        Collection::computed(
            'clinical_resources',
            'spotlight_appointment_phone_display',
            fn (Entry $entry, mixed $_stored): ?string => $this->isSpotlight($entry)
                ? $service->appointmentPhoneDisplay($entry)
                : null
        );

        Collection::computed(
            'clinical_resources',
            'spotlight_appointment_phone_href',
            fn (Entry $entry, mixed $_stored): ?string => $this->isSpotlight($entry)
                ? $service->appointmentPhoneHref($entry)
                : null
        );
    }

    /**
     * Whether the entry is a provider spotlight.
     */
    private function isSpotlight(Entry $entry): bool
    {
        $blueprint = $entry->blueprint();

        return $blueprint instanceof Blueprint
            && $blueprint->handle() === ProviderSpotlightComputedValues::BLUEPRINT;
    }
}
