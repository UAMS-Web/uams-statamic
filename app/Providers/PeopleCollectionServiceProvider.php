<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\ComputedFields\Registration\SitemapInclusionComputedsRegistrar;
use App\Services\Locations\LocationInheritedAddressService;
use App\Services\PubMed\PubMedEsummaryService;
use App\Services\Seo\DocumentTitle;
use App\Support\Collections\CollectionExtensions;
use App\Support\Multisite\DepartmentHubPeopleRoster;
use App\Support\References\EntryReferenceRepointer;
use Illuminate\Support\ServiceProvider;
use Statamic\Entries\Entry;
use UAMSWeb\Aether\People\Contracts\DepartmentHubRoster;
use UAMSWeb\Aether\People\Contracts\EntryReferenceRepointer as EntryReferenceRepointerContract;
use UAMSWeb\Aether\People\Contracts\LocationRegions;
use UAMSWeb\Aether\People\Contracts\MergeRedirectWriter;
use UAMSWeb\Aether\People\Contracts\PublicationCitations;
use UAMSWeb\Aether\People\Entries\PersonEntry;
use UAMSWeb\Aether\People\Merge\RedirectsGlobalWriter;
use UAMSWeb\Aether\People\Support\PeopleCollection;

/**
 * The site's side of `uams-web/aether-people` (#696).
 *
 * The add-on carries the `people` collection and its provider layer, and declares a
 * contract for each thing it reads from the site. This provider binds those contracts,
 * and registers the `people` behavior that lives in site core through
 * {@see CollectionExtensions} (UAMS-Web/uams-statamic-archive#3180).
 *
 * Contracts bound:
 *
 * - **`EntryReferenceRepointer`:** the site's {@see EntryReferenceRepointer}, which
 *   re-points a merged person's references in entries, global sets and taxonomy terms
 *   (UAMS-Web/aether-people#97).
 * - **`MergeRedirectWriter`:** the add-on's {@see RedirectsGlobalWriter}, which appends the
 *   merge's redirects to each site's localization of the `redirects` global, as the site
 *   did before the move (UAMS-Web/aether-people#101).
 * - **`LocationRegions`:** {@see LocationInheritedAddressService}, which resolves a clinical
 *   location's regions through the facility that contains it.
 * - **`PublicationCitations`:** {@see PubMedEsummaryService}, which reads the PubMed
 *   E-summary cache the site warms.
 * - **`DepartmentHubRoster`:** {@see DepartmentHubPeopleRoster}, which maps a hub site to
 *   its organization and derives that organization's roster.
 *
 * Collection behavior:
 *
 * - **Entry class:** `people` rows are built as {@see PersonEntry}, which suppresses the route of a
 *   person opted out of a profile page.
 * - **Sitemap inclusion:** the shared status gate, plus exclusion of a person with no profile page (UAMS-Web/uams-statamic-archive#43).
 * - **Document title:** Name | Primary Specialty | City, ST | UAMS Health (UAMS-Web/uams-statamic-archive#1329).
 * - **Scheduled publishing:** `people` is undated and its blueprints carry `publish_at` and `unpublish_at`.
 */
final class PeopleCollectionServiceProvider extends ServiceProvider
{
    /**
     * The add-on's contracts and the site classes bound to them.
     *
     * @var array<class-string, class-string> Contract => site or add-on implementation.
     */
    public const array CONTRACT_BINDINGS = [
        EntryReferenceRepointerContract::class => EntryReferenceRepointer::class,
        MergeRedirectWriter::class => RedirectsGlobalWriter::class,
        LocationRegions::class => LocationInheritedAddressService::class,
        PublicationCitations::class => PubMedEsummaryService::class,
        DepartmentHubRoster::class => DepartmentHubPeopleRoster::class,
    ];

    /**
     * Bind the add-on's contracts and register the people behavior while providers
     * register, so every consumer that runs at boot reads them.
     */
    #[\Override]
    public function register(): void
    {
        // Bind each contract the add-on declares to its implementation
        foreach (self::CONTRACT_BINDINGS as $contract => $implementation) {
            $this->app->bind($contract, $implementation);
        }

        // Read the extension point and the collection handle
        $extensions = $this->app->make(CollectionExtensions::class);
        $people = PeopleCollection::handle();

        // Build `people` rows as the add-on's entry class
        $extensions->entryClass($people, PersonEntry::class);

        // Exclude a person with no profile page from the sitemap
        $extensions->sitemapInclusionRule(
            $people,
            fn (Entry $entry, mixed $_stored): bool => SitemapInclusionComputedsRegistrar::shouldIncludePerson(
                $entry->value('status_and_visibility'),
                $entry->value('password_protected'),
                $entry->value(SitemapInclusionComputedsRegistrar::EDITOR_TOGGLE_HANDLE),
                // Read the routability toggle with get() (raw own-data), matching
                // PersonEntry::route() exactly so the sitemap mirrors actual URI
                // resolution rather than diverging via origin inheritance.
                $entry->get(PersonEntry::PROFILE_PAGE_FIELD),
            ),
        );

        // Resolved on first use rather than here, because its dependencies are not all registered yet
        $documentTitle = null;
        $extensions->documentTitle(
            $people,
            function (Entry $entry) use (&$documentTitle): string {
                $documentTitle ??= $this->app->make(DocumentTitle::class);

                return $documentTitle->forPeople($entry);
            },
        );

        // Hold back a scheduled `people` entry until its date
        $extensions->scheduledPublishing($people);
    }
}
