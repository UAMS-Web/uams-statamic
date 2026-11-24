<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\AppendOrphanBanner;
use App\Listeners\AppendPublishDependencyBanner;
use App\Listeners\CaptureRedirectOnEntryDelete;
use App\Listeners\DeleteYamlAssetMetadata;
use App\Listeners\DeriveSpotlightTitleAndSlug;
use App\Listeners\DisambiguateEntryTitle;
use App\Listeners\DispatchLocationRegionBackfill;
use App\Listeners\FlattenLogoOrderValues;
use App\Listeners\ForgetSiteTree;
use App\Listeners\GenerateNewsReleasePdf;
use App\Listeners\HashVisibilityPassword;
use App\Listeners\InvalidateSchemaJsonLdCache;
use App\Listeners\MirrorSpotlightFeaturedProviderRelationships;
use App\Listeners\PreserveGoogleBusinessProfileCategoryGcid;
use App\Listeners\PreventDeletingMounts;
use App\Listeners\PreventMountingToTreeRoot;
use App\Listeners\RedirectOrphanedDescendants;
use App\Listeners\RedirectSuppressedDependents;
use App\Listeners\RefreshKnowledgeBaseMountUris;
use App\Listeners\RemoveNewsReleasePdfOnDelete;
use App\Listeners\ScanLinksOnPublish;
use App\Listeners\SetAssetMetadataFromUpload;
use App\Listeners\SetCurrentEntryForTitleFormat;
use App\Listeners\SyncAreaOfExpertiseSectionSearchIndex;
use App\Listeners\SyncEntryPublishedStatus;
use App\Listeners\ValidateCalculatorEntry;
use App\Listeners\ValidateClinicalExpertiseEntry;
use App\Listeners\ValidateClinicalExpertiseTree;
use App\Listeners\ValidateClinicalLocationsEntry;
use App\Listeners\ValidateClinicalLocationsTree;
use App\Listeners\ValidateKnowledgeBaseEntry;
use App\Listeners\ValidateKnowledgeBaseTree;
use App\Listeners\ValidateSpecialInterestsScope;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as BaseServiceProvider;
use Statamic\Events\AssetDeleting;
use Statamic\Events\AssetReuploaded;
use Statamic\Events\AssetUploaded;
use Statamic\Events\CollectionSaving;
use Statamic\Events\CollectionTreeSaved;
use Statamic\Events\CollectionTreeSaving;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntryDeleting;
use Statamic\Events\EntrySaved;
use Statamic\Events\EntrySaving;
use Statamic\Events\FormSubmitted;
use Statamic\Events\GlobalSetDeleted;
use Statamic\Events\GlobalSetSaved;
use Statamic\Events\GlobalVariablesDeleted;
use Statamic\Events\GlobalVariablesSaved;
use Statamic\Events\ResponseCreated;
use Statamic\Events\StacheCleared;
use Statamic\Events\StacheWarmed;
use Statamic\Events\TermSaved;
use Statamic\Events\TermSaving;
use UAMSWeb\Aether\Fieldtypes\Listeners\EnforceChoiceSubmissionLimits;

/**
 * Service provider for registering event listeners in the application.
 *
 * This provider maps events to their corresponding listeners, enabling
 * event-driven functionality such as processing trigger actions in the Statamic
 * control panel. `$listen` is the only place a listener in `app/Listeners` is
 * registered: listener discovery is off (`bootstrap/app.php`), so each listener
 * runs once per event, in the order listed here (UAMS-Web/uams-statamic-archive#3225, UAMS-Web/uams-statamic-archive#3240).
 */
class EventServiceProvider extends BaseServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * Maps event classes to their corresponding listener classes.
     *
     * @var array<string, array<int, string>> Array mapping event classes to arrays of listener classes
     */
    protected $listen = [
        // Store current entry for {{ disambiguate_title }} when id is null (new entries);
        // validate knowledge base entries when saving;
        // validate clinical expertise (Areas of Expertise) entries when saving
        // (parent-blueprint rules; unbounded depth);
        // validate calculator entries when saving (primary uniqueness,
        // sub-handle reference rules, handle collisions). The people listeners
        // (editing scope, Workday-managed fields, the computed fields' virtual
        // values) are registered by `uams-web/aether-people` (#696).
        EntrySaving::class => [
            SetCurrentEntryForTitleFormat::class,
            ValidateKnowledgeBaseEntry::class,
            ValidateClinicalExpertiseEntry::class,
            ValidateClinicalLocationsEntry::class,
            ValidateSpecialInterestsScope::class,
            ValidateCalculatorEntry::class,
            FlattenLogoOrderValues::class,
            SyncEntryPublishedStatus::class,
            MirrorSpotlightFeaturedProviderRelationships::class,
            DeriveSpotlightTitleAndSlug::class,
            HashVisibilityPassword::class,
        ],

        // Validate knowledge base + clinical expertise + clinical locations tree structure when saving
        CollectionTreeSaving::class => [
            ValidateKnowledgeBaseTree::class,
            ValidateClinicalExpertiseTree::class,
            ValidateClinicalLocationsTree::class,
        ],

        // Reject saving a collection whose mount resolves to its target site
        // tree's root (a site home page) — any collection, not just clinical_*
        CollectionSaving::class => [
            PreventMountingToTreeRoot::class,
        ],

        // Append the read-only orphaned-ancestor CP banner to handled
        // (routable + hierarchical) collections' blueprints at resolve time, and
        // the publish-dependency CP banner to blueprints declaring the tether
        // field (UAMS-Web/uams-statamic-archive#1593)
        EntryBlueprintFound::class => [
            AppendOrphanBanner::class,
            AppendPublishDependencyBanner::class,
        ],

        // Redirect a published front-end entry orphaned by an unpublished
        // structural ancestor to its nearest live ancestor / mount root / site root,
        // and render-suppress a dependent whose declared publish_depends_on
        // source(s) have gone dark (UAMS-Web/uams-statamic-archive#1593)
        ResponseCreated::class => [
            RedirectOrphanedDescendants::class,
            RedirectSuppressedDependents::class,
        ],

        // Delete asset metadata YAML file when asset is being deleted
        AssetDeleting::class => [
            DeleteYamlAssetMetadata::class,
        ],

        // Set an uploaded or replaced asset's metadata from the file
        AssetUploaded::class => [
            SetAssetMetadataFromUpload::class,
        ],
        AssetReuploaded::class => [
            SetAssetMetadataFromUpload::class,
        ],

        // ---
        // `AssetSaved` deliberately does NOT bust the JSON-LD output cache: a
        // single asset save (e.g. one import or CP upload) used to cold-bust the
        // whole site (issue UAMS-Web/uams-statamic-archive#937); asset-driven staleness is bounded by
        // `schema_templates.json_ld_cache.ttl_seconds` instead.
        // ---

        // Invalidate schema JSON-LD cache when entry is saved;
        // disambiguate duplicate entry titles (append (2), (3), ...);
        // clear current entry used by {{ disambiguate_title }} tag;
        // queue a location's Arkansas region derivation off the save path (UAMS-Web/uams-statamic-archive#1112)
        EntrySaved::class => [
            ScanLinksOnPublish::class,
            DisambiguateEntryTitle::class,
            SetCurrentEntryForTitleFormat::class,
            GenerateNewsReleasePdf::class,
            InvalidateSchemaJsonLdCache::class,
            RefreshKnowledgeBaseMountUris::class,
            DispatchLocationRegionBackfill::class,
            SyncAreaOfExpertiseSectionSearchIndex::class,
        ],

        // Re-stamp mounted knowledge base URIs when a mount-parent page is deleted;
        // remove a deleted news release's public PDF; drop a deleted Area of Expertise's
        // synthetic section search documents (UAMS-Web/uams-statamic-archive#1527)
        EntryDeleted::class => [
            CaptureRedirectOnEntryDelete::class,
            RefreshKnowledgeBaseMountUris::class,
            RemoveNewsReleasePdfOnDelete::class,
            SyncAreaOfExpertiseSectionSearchIndex::class,
        ],

        // Refuse deleting an entry another collection is mounted on
        EntryDeleting::class => [
            PreventDeletingMounts::class,
        ],

        // Refuse a submission its choice limits reject. The form settings add-on
        // registers its own listeners: its restrictions gate and, with its
        // submission data switch on, the request-context capture, the submission
        // metadata and the queued-email warning, which it registers after the
        // application's providers so they run after this (#263, #265)
        FormSubmitted::class => [
            EnforceChoiceSubmissionLimits::class,
        ],

        // Re-stamp mounted knowledge base URIs when the pages structure is reordered
        CollectionTreeSaved::class => [
            RefreshKnowledgeBaseMountUris::class,
        ],

        // Invalidate schema JSON-LD cache when global set is saved, and forget the multisite tree
        // search reads (#480) whenever the global sets, their variables or the Stache change
        GlobalSetSaved::class => [
            InvalidateSchemaJsonLdCache::class,
            ForgetSiteTree::class,
        ],
        GlobalSetDeleted::class => [
            ForgetSiteTree::class,
        ],
        GlobalVariablesSaved::class => [
            ForgetSiteTree::class,
        ],
        GlobalVariablesDeleted::class => [
            ForgetSiteTree::class,
        ],
        StacheCleared::class => [
            ForgetSiteTree::class,
        ],
        StacheWarmed::class => [
            ForgetSiteTree::class,
        ],

        // Invalidate schema JSON-LD cache when term is saved
        TermSaved::class => [
            InvalidateSchemaJsonLdCache::class,
        ],

        // Keep a Google Business Profile category's `gcid` once it is set (UAMS-Web/uams-statamic-archive#2613)
        TermSaving::class => [
            PreserveGoogleBusinessProfileCategoryGcid::class,
        ],
    ];
}
