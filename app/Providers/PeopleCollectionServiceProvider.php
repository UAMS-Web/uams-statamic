<?php

declare(strict_types=1);

namespace App\Providers;

use App\Entries\PersonEntry;
use App\Http\Controllers\CP\People\WorkdayGenderController;
use App\Services\ComputedFields\Registration\SitemapInclusionComputedsRegistrar;
use App\Services\Seo\DocumentTitle;
use App\Support\Collections\CollectionExtensions;
use App\Support\Collections\PeopleCollection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Statamic\Entries\Entry;
use Statamic\Statamic;

/**
 * Registers the `people` collection's behavior through the site's collection extension point (UAMS-Web/uams-statamic-archive#3180).
 *
 * Site core no longer names `people` for these four behaviors; this provider supplies them through
 * {@see CollectionExtensions}, and it moves with the people code into `uams-web/statamic-people` (#3181).
 *
 * - **Entry class:** `people` rows are built as {@see PersonEntry}, which suppresses the route of a
 *   person opted out of a profile page.
 * - **Sitemap inclusion:** the shared status gate, plus exclusion of a person with no profile page (UAMS-Web/uams-statamic-archive#43).
 * - **Document title:** Name | Primary Specialty | City, ST | UAMS Health (UAMS-Web/uams-statamic-archive#1329).
 * - **Scheduled publishing:** `people` is undated and its blueprints carry `publish_at` and `unpublish_at`.
 * - **Gender override review:** the Control Panel routes behind the `WorkdayGenderOverrides` widget (UAMS-Web/uams-statamic-archive#3187).
 */
final class PeopleCollectionServiceProvider extends ServiceProvider
{
    /**
     * Register the people behavior while providers register, so every consumer that runs at boot reads it.
     */
    #[\Override]
    public function register(): void
    {
        $extensions = $this->app->make(CollectionExtensions::class);
        $people = PeopleCollection::handle();

        $extensions->entryClass($people, PersonEntry::class);

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

        $extensions->scheduledPublishing($people);
    }

    /**
     * Register the Control Panel routes that clear a flagged gender override from
     * the `WorkdayGenderOverrides` widget (UAMS-Web/uams-statamic-archive#3187).
     */
    public function boot(): void
    {
        Statamic::pushCpRoutes(function (): void {
            Route::prefix('people/workday-gender/{id}')->name('people-workday-gender.')->group(function (): void {
                Route::post('confirm', [WorkdayGenderController::class, 'confirm'])->name('confirm');
                Route::post('take', [WorkdayGenderController::class, 'take'])->name('take');
            });
        });
    }
}
