<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\TriggerActivated;
use App\Listeners\AppendOrphanBanner;
use App\Listeners\AppendPublishDependencyBanner;
use App\Listeners\AppendSubmissionMetadata;
use App\Listeners\CaptureFormRequestContext;
use App\Listeners\CaptureRedirectOnEntryDelete;
use App\Listeners\DeleteYamlAssetMetadata;
use App\Listeners\DeriveSpotlightTitleAndSlug;
use App\Listeners\DisambiguateEntryTitle;
use App\Listeners\DispatchLocationRegionBackfill;
use App\Listeners\EnforceChoiceSubmissionLimits;
use App\Listeners\EnforcePeopleEditingScope;
use App\Listeners\EnforcePeopleScopeOnProviderSync;
use App\Listeners\FlattenLogoOrderValues;
use App\Listeners\GenerateNewsReleasePdf;
use App\Listeners\HandlePasteTableDataTrigger;
use App\Listeners\HandleTableDataImporterTrigger;
use App\Listeners\HashVisibilityPassword;
use App\Listeners\InvalidateSchemaJsonLdCache;
use App\Listeners\MakeWorkdayFieldsReadOnly;
use App\Listeners\MirrorSpotlightFeaturedProviderRelationships;
use App\Listeners\PreserveGoogleBusinessProfileCategoryGcid;
use App\Listeners\PreventDeletingMounts;
use App\Listeners\PreventMountingToTreeRoot;
use App\Listeners\RecordIncompletePublishedEntriesContentChange;
use App\Listeners\RedirectOrphanedDescendants;
use App\Listeners\RedirectSuppressedDependents;
use App\Listeners\RefreshKnowledgeBaseMountUris;
use App\Listeners\RefreshProviderEligibilityForLocation;
use App\Listeners\RemoveNewsReleasePdfOnDelete;
use App\Listeners\RestoreWorkdayManagedFields;
use App\Listeners\ScanLinksOnPublish;
use App\Listeners\SetAssetMetadataFromUpload;
use App\Listeners\SetCurrentEntryForTitleFormat;
use App\Listeners\StripProviderPeopleVirtualFieldsOnEntrySaving;
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
use App\Listeners\WarnWhenFormEmailQueued;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as BaseServiceProvider;
use Illuminate\Queue\Events\JobQueued;
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
use Statamic\Events\GlobalSetSaved;
use Statamic\Events\ResponseCreated;
use Statamic\Events\RevisionSaving;
use Statamic\Events\SubmissionCreating;
use Statamic\Events\TermSaved;
use Statamic\Events\TermSaving;

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
        // Map TriggerActivated event to its listeners; each ignores triggers it
        // does not own, and the route uses the first non-empty response
        TriggerActivated::class => [
            HandleTableDataImporterTrigger::class,
            HandlePasteTableDataTrigger::class,
        ],

        // Store current entry for {{ disambiguate_title }} when id is null (new entries);
        // validate knowledge base entries when saving;
        // validate clinical expertise (Areas of Expertise) entries when saving
        // (parent-blueprint rules; unbounded depth);
        // validate calculator entries when saving (primary uniqueness,
        // sub-handle reference rules, handle collisions);
        // keep a scoped editor's people entry inside their organizations (UAMS-Web/uams-statamic-archive#3182),
        // and refuse a relationship sync that would change one outside them (UAMS-Web/uams-statamic-archive#3223);
        // keep the Workday-managed people fields at their stored values (UAMS-Web/uams-statamic-archive#3186).
        // The virtual computed fields are stripped first, as they were when a
        // provider registered that listener (UAMS-Web/uams-statamic-archive#3240).
        EntrySaving::class => [
            StripProviderPeopleVirtualFieldsOnEntrySaving::class,
            SetCurrentEntryForTitleFormat::class,
            ValidateKnowledgeBaseEntry::class,
            ValidateClinicalExpertiseEntry::class,
            ValidateClinicalLocationsEntry::class,
            ValidateSpecialInterestsScope::class,
            EnforcePeopleEditingScope::class,
            EnforcePeopleScopeOnProviderSync::class,
            RestoreWorkdayManagedFields::class.'@handleEntrySaving',
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
            MakeWorkdayFieldsReadOnly::class,
        ],

        // Keep the Workday-managed people fields at their stored values in a
        // working copy, as on a save (UAMS-Web/uams-statamic-archive#3186)
        RevisionSaving::class => [
            RestoreWorkdayManagedFields::class.'@handleRevisionSaving',
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

        // Invalidate Incomplete Published Entries widget cache when content changes;
        // Invalidate schema JSON-LD cache when entry is saved;
        // disambiguate duplicate entry titles (append (2), (3), ...);
        // clear current entry used by {{ disambiguate_title }} tag;
        // queue a location's Arkansas region derivation off the save path (UAMS-Web/uams-statamic-archive#1112)
        // re-index linked people's eligibility when a practice location changes (UAMS-Web/uams-statamic-archive#3123)
        EntrySaved::class => [
            RecordIncompletePublishedEntriesContentChange::class,
            ScanLinksOnPublish::class,
            DisambiguateEntryTitle::class,
            SetCurrentEntryForTitleFormat::class,
            GenerateNewsReleasePdf::class,
            InvalidateSchemaJsonLdCache::class,
            RefreshKnowledgeBaseMountUris::class,
            DispatchLocationRegionBackfill::class,
            SyncAreaOfExpertiseSectionSearchIndex::class,
            RefreshProviderEligibilityForLocation::class,
        ],

        // Re-stamp mounted knowledge base URIs when a mount-parent page is deleted;
        // remove a deleted news release's public PDF; drop a deleted Area of Expertise's
        // synthetic section search documents (UAMS-Web/uams-statamic-archive#1527); re-index linked people's
        // eligibility when a practice location is deleted (UAMS-Web/uams-statamic-archive#3123)
        EntryDeleted::class => [
            CaptureRedirectOnEntryDelete::class,
            RefreshKnowledgeBaseMountUris::class,
            RemoveNewsReleasePdfOnDelete::class,
            SyncAreaOfExpertiseSectionSearchIndex::class,
            RefreshProviderEligibilityForLocation::class,
        ],

        // Refuse deleting an entry another collection is mounted on
        EntryDeleting::class => [
            PreventDeletingMounts::class,
        ],

        // Refuse a submission its choice limits reject, then capture the
        // submitter's request context for the form emails. The form-level
        // restrictions gate is the form settings add-on's, which registers it
        // itself, so it runs after these (#3072)
        FormSubmitted::class => [
            EnforceChoiceSubmissionLimits::class,
            CaptureFormRequestContext::class,
        ],

        // Record the submission's metadata before it is saved
        SubmissionCreating::class => [
            AppendSubmissionMetadata::class,
        ],

        // Warn when a form email is pushed to a real (non-sync) queue
        JobQueued::class => [
            WarnWhenFormEmailQueued::class,
        ],

        // Re-stamp mounted knowledge base URIs when the pages structure is reordered
        CollectionTreeSaved::class => [
            RefreshKnowledgeBaseMountUris::class,
        ],

        // Invalidate schema JSON-LD cache when global set is saved
        GlobalSetSaved::class => [
            InvalidateSchemaJsonLdCache::class,
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

    /**
     * Boot the provider and register event listeners.
     *
     * Delegates to the parent to apply the `$listen` mappings.
     */
    #[\Override]
    public function boot(): void
    {
        // Register event listeners
        parent::boot();
    }
}
