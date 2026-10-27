<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\ComputedFields\Registration\ArticleEngagementComputedsRegistrar;
use App\Services\ComputedFields\Registration\AugmentedAssetComputedPipelinesRegistrar;
use App\Services\ComputedFields\Registration\ClassifiedsComputedsRegistrar;
use App\Services\ComputedFields\Registration\ClinicalExpertiseCollectionComputedsRegistrar;
use App\Services\ComputedFields\Registration\ClinicalExpertiseStructureComputedsRegistrar;
use App\Services\ComputedFields\Registration\ClinicalLocationsStructureComputedsRegistrar;
use App\Services\ComputedFields\Registration\ClinicalResourcesProviderSpotlightComputedsRegistrar;
use App\Services\ComputedFields\Registration\ContentUsageComputedsRegistrar;
use App\Services\ComputedFields\Registration\DepartmentHubPeopleComputedsRegistrar;
use App\Services\ComputedFields\Registration\DocumentTitleComputedsRegistrar;
use App\Services\ComputedFields\Registration\FacilitiesInheritedAddressComputedsRegistrar;
use App\Services\ComputedFields\Registration\FacilitiesInheritedParkingComputedsRegistrar;
use App\Services\ComputedFields\Registration\FacilitiesReverseReferenceComputedsRegistrar;
use App\Services\ComputedFields\Registration\ImpliedOntologyCollectionComputedsRegistrar;
use App\Services\ComputedFields\Registration\IptcDigitalSourceCollectionComputedsRegistrar;
use App\Services\ComputedFields\Registration\KnowledgeBaseCollectionComputedsRegistrar;
use App\Services\ComputedFields\Registration\LocationsInheritedAddressComputedsRegistrar;
use App\Services\ComputedFields\Registration\OrganizationsReverseReferenceComputedsRegistrar;
use App\Services\ComputedFields\Registration\OrganizationTitleComputedsRegistrar;
use App\Services\ComputedFields\Registration\PeopleOnlyCollectionComputedsRegistrar;
use App\Services\ComputedFields\Registration\PeopleReverseReferenceComputedsRegistrar;
use App\Services\ComputedFields\Registration\PostsReadingMetricsCollectionComputedsRegistrar;
use App\Services\ComputedFields\Registration\PostsReverseReferenceComputedsRegistrar;
use App\Services\ComputedFields\Registration\ProvidersPeopleSharedCollectionComputedsRegistrar;
use App\Services\ComputedFields\Registration\QuantitativeValueDurationComputedsRegistrar;
use App\Services\ComputedFields\Registration\RecognitionListTaxonomyAugmentedRegistrar;
use App\Services\ComputedFields\Registration\RoleTitleMirroredComputedsRegistrar;
use App\Services\ComputedFields\Registration\SideBySideAlignmentComputedsRegistrar;
use App\Services\ComputedFields\Registration\SitemapInclusionComputedsRegistrar;
use App\Services\ComputedFields\Registration\SpecialInterestInverseComputedsRegistrar;
use App\Services\ComputedFields\Registration\SubsiteContentPageHubComputedsRegistrar;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel service provider that wires Statamic computed fields, asset augmented
 * pipelines, and taxonomy augmented hooks by delegating to focused registrar
 * classes in registration order.
 *
 * @see AugmentedAssetComputedPipelinesRegistrar
 * @see RecognitionListTaxonomyAugmentedRegistrar
 * @see ProvidersPeopleSharedCollectionComputedsRegistrar
 * @see KnowledgeBaseCollectionComputedsRegistrar
 * @see PostsReadingMetricsCollectionComputedsRegistrar
 * @see RoleTitleMirroredComputedsRegistrar
 * @see QuantitativeValueDurationComputedsRegistrar
 * @see IptcDigitalSourceCollectionComputedsRegistrar
 * @see PeopleOnlyCollectionComputedsRegistrar
 */
class ComputedPropertiesProvider extends ServiceProvider
{
    /**
     * Register computed-field registrars with the application container.
     */
    public function boot(): void
    {
        // Register logos and credit-text asset augmented pipeline hooks
        $this->app->make(AugmentedAssetComputedPipelinesRegistrar::class)->register();

        // Register recognition list taxonomy augmented term routing
        $this->app->make(RecognitionListTaxonomyAugmentedRegistrar::class)->register();

        // Register providers and people shared collection computeds
        $this->app->make(ProvidersPeopleSharedCollectionComputedsRegistrar::class)->register();

        // Register knowledge_bases structural and ancestor-inherited computeds
        $this->app->make(KnowledgeBaseCollectionComputedsRegistrar::class)->register();

        // Register the knowledge_bases article engagement computeds (UAMS-Web/uams-statamic-archive#286 ratings,
        // UAMS-Web/uams-statamic-archive#287 views): view_count / like_count / dislike_count / like_count_net,
        // surfaced from the sideband engagement store
        $this->app->make(ArticleEngagementComputedsRegistrar::class)->register();

        // Register clinical_expertise AoE subsite chrome computeds (primary nav)
        $this->app->make(ClinicalExpertiseCollectionComputedsRegistrar::class)->register();

        // Register the subsite-hub computed (UAMS-Web/uams-statamic-archive#1414): the nearest clinical_expertise
        // / clinical_locations ancestor a content page's WebPage node links via
        // isPartOf, on both virtual-subsite collections
        $this->app->make(SubsiteContentPageHubComputedsRegistrar::class)->register();

        // Register the transitive implied-services / implied-conditions computeds
        // for Provider (people) and Location profiles (D9 transitive inference)
        $this->app->make(ImpliedOntologyCollectionComputedsRegistrar::class)->register();

        // Register the special-interest inverse computeds on the Condition/Service
        // collections (the computed inverse of the one-sided special-interest fields)
        $this->app->make(SpecialInterestInverseComputedsRegistrar::class)->register();

        // Register posts reading metrics computeds
        $this->app->make(PostsReadingMetricsCollectionComputedsRegistrar::class)->register();

        // Register optional role-title mirrored string computeds
        $this->app->make(RoleTitleMirroredComputedsRegistrar::class)->register();

        // Register optional quantitative value duration total-years computeds
        $this->app->make(QuantitativeValueDurationComputedsRegistrar::class)->register();

        // Register IPTC digital source value and schema computeds per collection
        $this->app->make(IptcDigitalSourceCollectionComputedsRegistrar::class)->register();

        // Register people-only collection computeds
        $this->app->make(PeopleOnlyCollectionComputedsRegistrar::class)->register();

        // Register the sitemap-inclusion computeds so non-public entries (private /
        // password-protected, non-routable people) are excluded from the public XML
        // sitemap (security). The knowledge_bases orphan gate is registered by
        // KnowledgeBaseCollectionComputedsRegistrar, which sources the orphaned-
        // ancestor signal from the universal OrphanedAncestorResolver (UAMS-Web/uams-statamic-archive#1455).
        $this->app->make(SitemapInclusionComputedsRegistrar::class)->register();

        // Register facilities own-or-inherited street address computed
        $this->app->make(FacilitiesInheritedAddressComputedsRegistrar::class)->register();
        $this->app->make(OrganizationTitleComputedsRegistrar::class)->register();

        // Register facilities and locations own-or-inherited parking computeds
        $this->app->make(FacilitiesInheritedParkingComputedsRegistrar::class)->register();

        // The same computed handle on clinical_locations, resolving a different chain (UAMS-Web/uams-statamic-archive#1269)
        $this->app->make(LocationsInheritedAddressComputedsRegistrar::class)->register();

        // Register facilities reverse-reference computeds (UAMS-Web/uams-statamic-archive#1372): the areas that
        // name this facility for parking + the locations contained within it
        $this->app->make(FacilitiesReverseReferenceComputedsRegistrar::class)->register();

        // Register clinical_locations structure-tree parent computeds (UAMS-Web/uams-statamic-archive#1465): the
        // parent location's name + URL for the directory card's "Part of {parent}" subtitle
        $this->app->make(ClinicalLocationsStructureComputedsRegistrar::class)->register();

        // Register clinical_expertise structure-tree parent computeds (UAMS-Web/uams-statamic-archive#1467): the
        // parent Area of Expertise's name + URL for the index card's "Part of {parent}" subtitle
        $this->app->make(ClinicalExpertiseStructureComputedsRegistrar::class)->register();

        // Register organizations reverse-reference computeds (UAMS-Web/uams-statamic-archive#1373): the members of
        // this service line + the current residents / fellows of this academic department
        $this->app->make(OrganizationsReverseReferenceComputedsRegistrar::class)->register();

        // Register people reverse-reference computeds (UAMS-Web/uams-statamic-archive#1374): the content crediting
        // this person in a creator role + the clinical-provider record that wraps them
        $this->app->make(PeopleReverseReferenceComputedsRegistrar::class)->register();

        // Register the Departmental News Hub people-zone computeds (UAMS-Web/uams-statamic-archive#1557):
        // department_leadership / department_people on organizations, derived on
        // read from job_title via the configurable org-role → role-set mapping
        $this->app->make(DepartmentHubPeopleComputedsRegistrar::class)->register();

        // Register posts reverse-reference computed (UAMS-Web/uams-statamic-archive#1419): the posts that reference
        // this post via their related_posts override (read-only inverse; UAMS-Web/uams-statamic-archive#1155 rejected sync)
        $this->app->make(PostsReverseReferenceComputedsRegistrar::class)->register();

        // Register content-usage computeds (UAMS-Web/uams-statamic-archive#1375): the pages that transclude a
        // reusable_content / tables / calculators entry + an "in use" boolean
        $this->app->make(ContentUsageComputedsRegistrar::class)->register();

        // Register the SEO document_title computed for provider profiles and
        // locations (Name | Specialty | City, ST | UAMS Health), kept unique
        // per collection/site
        $this->app->make(DocumentTitleComputedsRegistrar::class)->register();

        // Register the classifieds `is_expired` computed (UAMS-Web/uams-statamic-archive#2096), sharing one
        // definition of expiry with the board index's `live_classifieds` scope
        $this->app->make(ClassifiedsComputedsRegistrar::class)->register();

        // Register the Side-by-Side block auto-alternating alignment map so the
        // page-builder block can flip image sides down a run without editors
        // hand-alternating
        $this->app->make(SideBySideAlignmentComputedsRegistrar::class)->register();

        // Register the provider-spotlight computeds (UAMS-Web/uams-statamic-archive#1425): the fallback intro
        // prose, the auto excerpt, and the precedence-resolved featured image,
        // all derived from the spotlight's featured provider
        $this->app->make(ClinicalResourcesProviderSpotlightComputedsRegistrar::class)->register();

        // `StripProviderPeopleVirtualFieldsOnEntrySaving` removes these virtual
        // fields before a save; it is listed in `EventServiceProvider::$listen`
        // (UAMS-Web/uams-statamic-archive#3240)
    }
}
