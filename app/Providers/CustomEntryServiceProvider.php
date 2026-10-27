<?php

declare(strict_types=1);

namespace App\Providers;

use App\Entries\EntryRepository;
use App\Entries\KnowledgeBaseEntry;
use App\Http\Controllers\CP\Collections\LocalizeEntryController;
use App\Support\Collections\CollectionExtensions;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Entries\EntryRepository as EntryRepositoryContract;
use Statamic\Entries\Entry as BaseEntry;
use Statamic\Http\Controllers\CP\Collections\LocalizeEntryController as StatamicLocalizeEntryController;
use Statamic\Statamic;

/**
 * Service provider for registering custom entry repository, entry class bindings,
 * and controller overrides.
 *
 * This provider customizes Statamic's behavior by:
 *   - Registering a custom `EntryRepository` to override the default Statamic
 *     entry repository.
 *   - Re-binding the `Entry` contract to dynamically instantiate a
 *     `KnowledgeBaseEntry` for the `knowledge_bases` collection, the class a
 *     collection registers through `CollectionExtensions` (`PersonEntry` for
 *     `people`, UAMS-Web/uams-statamic-archive#3180), or the default `Statamic\Entries\Entry` otherwise.
 *   - Overriding the default `LocalizeEntryController` with a custom
 *     implementation that enhances error handling with toast notifications.
 */
class CustomEntryServiceProvider extends ServiceProvider
{
    /**
     * Register services and bindings in the application container.
     *
     * This method performs the following registrations:
     *   - Binds the custom `EntryRepository` to the `EntryRepositoryContract`
     *     interface using Statamic's repository helper, ensuring all entry-related
     *     operations use the custom repository.
     *   - Overrides the default `LocalizeEntryController` with a custom
     *     implementation to improve error handling and user feedback.
     */
    #[\Override]
    public function register(): void
    {
        // ---
        // Bind the custom EntryRepository to the EntryRepositoryContract interface. This
        // ensures Statamic uses our custom repository for all entry operations.
        // ---
        Statamic::repository(
            EntryRepositoryContract::class,
            EntryRepository::class
        );

        // ---
        // Override the default Statamic LocalizeEntryController with our custom version.
        // The custom controller enhances error handling by providing toast notifications.
        // ---
        $this->app->bind(
            StatamicLocalizeEntryController::class,
            LocalizeEntryController::class
        );

        // ---
        // Statamic whitelists its own Entry class for cache unserialization, but
        // PHP's allowed_classes does not cover subclasses. Without this, Stache
        // returns __PHP_Incomplete_Class for any cached KnowledgeBaseEntry, or for
        // an entry class a collection registers (PersonEntry).
        // ---
        $extensions = $this->app->make(CollectionExtensions::class);

        // Get the existing cache.serializable_classes configuration
        /** @var Repository $config */
        $config = $this->app->make('config');
        $existing = $config->get('cache.serializable_classes');

        // Check if the cache.serializable_classes configuration is set and is not true
        if (
            $existing !== null
            && $existing !== true
        ) {
            // ---
            // Merge the existing configuration with the KnowledgeBaseEntry class and the
            // registered entry classes. If the existing configuration is not an array,
            // convert it to an array.
            // ---
            $config->set('cache.serializable_classes', [
                ...(\is_array($existing) ? $existing : []),
                KnowledgeBaseEntry::class,
                ...$extensions->entryClasses(),
            ]);
        }
    }

    /**
     * Bootstrap application services.
     *
     * This method re-binds the `EntryContract` to dynamically instantiate the
     * appropriate entry class based on the collection handle. If the collection is
     * `knowledge_bases`, a `KnowledgeBaseEntry` instance is returned; for a collection
     * that registers an entry class through `CollectionExtensions`, that class;
     * otherwise, the default `BaseEntry` is used.
     */
    public function boot(): void
    {
        // Dynamically bind the EntryContract to instantiate the appropriate entry class.
        $this->app->bind(
            EntryContract::class,
            function (Container $app, array $parameters): BaseEntry {
                // ---
                // Extract the collection handle from the parameters, defaulting to null if not
                // provided.
                // ---
                /** @var array<string, mixed> $parameters */
                $collectionHandle = $parameters['collectionHandle'] ?? null;

                // Check if the collection is 'knowledge_bases'
                if ($collectionHandle === 'knowledge_bases') {
                    // Return a KnowledgeBaseEntry instance
                    return new KnowledgeBaseEntry;
                }

                // A collection that registers its own entry class (people: PersonEntry,
                // which suppresses non-routable profiles)
                $extensions = $app->make(CollectionExtensions::class);
                $class = $extensions->entryClassFor(\is_string($collectionHandle) ? $collectionHandle : null);

                if ($class !== null) {
                    // Return an instance of the registered class
                    return new $class;
                }

                // Otherwise, return the default Statamic Entry instance.
                return new BaseEntry;
            }
        );
    }
}
