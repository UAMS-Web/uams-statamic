<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Services\Seo\DocumentTitle;
use App\Support\Collections\CollectionExtensions;
use Statamic\Facades\Collection;

/**
 * Registers the `document_title` computed field for the templated `people`
 * (provider profiles) and `clinical_locations` collections (UAMS-Web/uams-statamic-archive#1329/UAMS-Web/uams-statamic-archive#1330). The
 * `people` title is registered through {@see CollectionExtensions} (UAMS-Web/uams-statamic-archive#3180).
 *
 * The layout head reads `{{ document_title ?? title ?? site:name }}`, so only
 * these two collections emit the SEO title formula; every other collection keeps
 * rendering its stored `title`.
 */
final readonly class DocumentTitleComputedsRegistrar
{
    /**
     * @param  DocumentTitle  $documentTitle  Composes and disambiguates the location `<title>`.
     * @param  CollectionExtensions  $extensions  Supplies the titles packages register, including the `people` one.
     */
    public function __construct(private DocumentTitle $documentTitle, private CollectionExtensions $extensions)
    {
        //
    }

    /**
     * Register the `document_title` computed for each templated collection.
     */
    public function register(): void
    {
        // Cache the service in a local for readable first-class-callable bindings.
        $title = $this->documentTitle;

        // Collections that bring their own title. Provider profiles:
        // Name | Primary Specialty | City, ST | UAMS Health.
        foreach ($this->extensions->documentTitles() as $collection => $documentTitle) {
            Collection::computed($collection, 'document_title', $documentTitle);
        }

        // Locations: Location Name | City, ST | UAMS Health.
        Collection::computed(
            'clinical_locations',
            'document_title',
            $title->forClinicalLocation(...)
        );
    }
}
