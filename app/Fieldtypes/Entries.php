<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Fieldtypes\Traits\ConfigSectionWrapper;
use App\Support\People\PeopleEditingScope;
use Illuminate\Http\Request;
use Statamic\Auth\User as UserAuth;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Entries\Collection;
use Statamic\Entries\EntryCollection;
use Statamic\Exceptions\CollectionNotFoundException;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Site as SiteFacade;
use Statamic\Facades\User as UserFacade;
use Statamic\Fields\Blueprint;
use Statamic\Fieldtypes\Entries as BaseEntries;
use Statamic\Query\Builder;
use Statamic\Sites\Site;
use Statamic\Support\Arr;

/**
 * Extended Entries fieldtype that filters creatable blueprints based on
 * the 'blueprints' configuration option.
 *
 * This extension ensures that when a 'blueprints' config is specified,
 * only those blueprints are available for entry creation, matching the
 * behavior of the selection filtering in the base fieldtype.
 */
class Entries extends BaseEntries
{
    /**
     * Trait for adding configuration fields to existing sections.
     *
     * Provides methods to add fields to existing configuration sections
     * without overriding the entire configFieldItems() method structure.
     */
    use ConfigSectionWrapper;

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        // Narrow the parent icon value to a string before returning
        $icon = parent::icon();

        // The parent icon is always a string, so the narrowing cannot change the result
        // @pest-mutate-ignore: RemoveStringCast, InstanceOfToTrue, InstanceOfToFalse, EmptyStringToNotEmpty
        return \is_scalar($icon) || $icon instanceof \Stringable ? (string) $icon : '';
    }

    /**
     * Reduce a computed value to entry ids before the Control Panel reads it.
     *
     * A computed `entries` field returns an `EntryCollection`, and the edit page
     * merges computed values into the field values. The parent would wrap the
     * collection as one item, which `findEntry()` then uses as an array key: a
     * `TypeError` that fails the whole page, even when the collection is empty
     * (UAMS-Web/uams-statamic#500).
     *
     * @param  mixed  $data  The stored ids, or a computed collection of entries
     * @return array<int, mixed> The ids the Control Panel preloads
     */
    #[\Override]
    public function preProcess($data): array
    {
        // Keep the ids, the only shape the relationship field can look up
        if ($data instanceof EntryCollection) {
            $data = $data->map->id()->values()->all();
        }

        // Every other value keeps the parent behavior
        /** @var array<int, mixed> $processed */
        $processed = parent::preProcess($data);

        return $processed;
    }

    /**
     * Get configuration field items, adding blueprints field when create is enabled.
     *
     * @return array<int, array<string, mixed>> Configuration field sections
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        // Section key must match Statamic v6 base Entries: "Appearance".
        // Add blueprints field to Appearance section
        /** @var array<int, array<string, mixed>> $configFieldItems */
        $configFieldItems = $this->addFieldsToExistingSections(
            [
                __('Appearance') => [
                    'blueprints' => [
                        'display' => __('Blueprint Restrictions'),
                        'instructions' => __(
                            'Enter basename of blueprint file (e.g., `foo` out of `path/to/foo.yaml`) to restrict entry creation to specific blueprints. Only the selected blueprints will be available when creating new entries.'
                        ),
                        'type' => 'taggable',
                        'if' => [
                            'create' => true,
                        ],
                    ],
                ],
            ]
        );

        // Return the assembled configuration field sections
        return $configFieldItems;
    }

    /**
     * Get the creatable items, filtered by blueprint config if specified.
     *
     * Overrides the parent method to filter creatable blueprints based on
     * the 'blueprints' configuration option, ensuring consistency between
     * what can be selected and what can be created.
     *
     * @return array<int, array<string, mixed>> Array of creatable items with 'title' and 'url' keys
     */
    #[\Override]
    protected function getCreatables(): array
    {
        // ---
        // Check if a custom create URL is provided.
        //
        // The base class getCreateItemUrl() returns void, but child classes may override
        // to return string. The ?: null operator safely handles both cases.
        // ---
        $url = $this->getCreateItemUrl() ?: null;

        // Check if custom create URL was provided
        if ($url) {
            // Return custom create URL
            return [['url' => $url]];
        }

        // Get configured collections
        /** @var array<int, string> $collections */
        $collections = $this->getConfiguredCollections();

        // Get configured blueprints from field config
        /** @var array<int, string>|null $configuredBlueprints */
        $configuredBlueprints = $this->config('blueprints');

        // Get current user for permission checks
        /** @var UserAuth|null $user */
        $user = UserFacade::current();

        // Map collections to creatable items
        /** @var array<int, array<string, mixed>> $creatables */
        $creatables = collect($collections)->flatMap(
            /**
             * @param  string  $collectionHandle  Collection handle
             * @return \Illuminate\Support\Collection<int, array{title: string, url: string}>|null Creatable items for the collection, or null when skipped
             */
            function (string $collectionHandle) use (// @phpstan-ignore argument.type (the callback returns null for skipped collections; Laravel's flatMap collapses and drops those nulls, but PHPStan cannot bind the flatMap template value type through the nullable union return)
                $collections,
                $user,
                $configuredBlueprints
            ) {
                // Get collection instance by handle
                /** @var Collection|null $collection */
                $collection = CollectionFacade::findByHandle($collectionHandle);

                // Throw exception if collection not found
                if (! $collection) {
                    throw new CollectionNotFoundException($collectionHandle);
                }

                // Check if user has permission to create entries in this collection
                // (this fieldtype only renders in the authenticated Control Panel,
                // so the current user is always present here)
                /** @var UserAuth $user */
                if (
                    ! $user->can(
                        'create',
                        [
                            EntryContract::class,
                            $collection,
                        ]
                    )
                ) {
                    // Skip this collection if user lacks permission
                    return null;
                }

                // Get all blueprints available for this collection
                /** @var \Illuminate\Support\Collection<int, Blueprint> $blueprints */
                $blueprints = $collection->entryBlueprints();

                // ---
                // Filter by configured blueprints if specified
                // ---

                // Check if blueprints are configured for filtering
                if ($configuredBlueprints) {
                    // Filter blueprints to only those in configured list
                    $blueprints = $blueprints->filter(
                        fn ($blueprint): bool => \in_array(
                            $blueprint->handle(),
                            $configuredBlueprints
                        )
                    );
                }

                // Remove hidden blueprints from creatable options
                /** @var \Illuminate\Support\Collection<int, Blueprint> $filteredBlueprints */
                $filteredBlueprints = $blueprints->reject->hidden();

                // Get the currently selected site for the create URL
                /** @var Site $selectedSite */
                $selectedSite = SiteFacade::selected();

                // Map filtered blueprints to creatable item format
                return $filteredBlueprints->map(
                    function (Blueprint $blueprint) use (
                        $collection,
                        $collections,
                        $filteredBlueprints,
                        $selectedSite
                    ): array {
                        // Build the create entry URL for the selected site
                        $createEntryUrlValue = $collection->createEntryUrl(
                            $selectedSite->handle()
                        );
                        // The URL is concatenated below, which casts a Stringable the same way (RemoveStringCast is equivalent)
                        // @pest-mutate-ignore: RemoveStringCast
                        $createEntryUrl = \is_scalar($createEntryUrlValue)
                            || $createEntryUrlValue instanceof \Stringable
                            ? (string) $createEntryUrlValue
                            : '';

                        // Narrow the blueprint handle to a string for the URL
                        // The handle is concatenated below, which casts it the same way (RemoveStringCast is equivalent)
                        // @pest-mutate-ignore: RemoveStringCast
                        $blueprintHandle = (string) $blueprint->handle();

                        // Return the creatable item with title and URL
                        return [
                            'title' => $this->getCreatableTitleForBlueprint(
                                $collection,
                                $blueprint,
                                \count($collections),
                                $filteredBlueprints->count()
                            ),
                            'url' => $createEntryUrl
                                .'?blueprint='.$blueprintHandle,
                        ];
                    }
                );
            }
        )->all();

        // Return the flattened creatable items
        return $creatables;
    }

    /**
     * Get the title for a creatable blueprint entry.
     *
     * This method replicates the logic from the parent class's private
     * getCreatableTitle() method to determine the appropriate title
     * based on collection and blueprint counts.
     *
     * @param  Collection  $collection  The collection
     * @param  Blueprint  $blueprint  The blueprint
     * @param  int  $collectionCount  Number of collections
     * @param  int  $blueprintCount  Number of blueprints
     * @return string The title for the creatable item
     */
    private function getCreatableTitleForBlueprint(
        Collection $collection,
        Blueprint $blueprint,
        int $collectionCount,
        int $blueprintCount
    ): string {
        // Narrow the collection and blueprint titles to strings
        $collectionTitleValue = $collection->title();
        $collectionTitle = \is_scalar($collectionTitleValue)
            || $collectionTitleValue instanceof \Stringable
            ? (string) $collectionTitleValue
            : '';

        $blueprintTitleValue = $blueprint->title();
        $blueprintTitle = \is_scalar($blueprintTitleValue)
            || $blueprintTitleValue instanceof \Stringable
            ? (string) $blueprintTitleValue
            : '';

        // Check if multiple collections with single blueprint
        if (
            $collectionCount > 1 // Multiple collections configured
            && $blueprintCount === 1 // Only one blueprint available
        ) {
            // Return collection title only
            return $collectionTitle;
        }

        // Multiple collections and multiple blueprints: a collection with no
        // creatable blueprint never reaches this method, so any count other than
        // the single blueprint handled above is more than one
        if ($collectionCount > 1) {
            // Return collection and blueprint title combined
            return $collectionTitle.': '.$blueprintTitle;
        }

        // Return blueprint title only (single collection)
        return $blueprintTitle;
    }

    /**
     * Get the picker's index query, keeping a scoped editor to their `people`.
     *
     * The base query filters only by authorized site, so a picker targeting
     * `people` would offer a scoped editor every person (UAMS-Web/uams-statamic-archive#3192). For a user the
     * people editing scope applies to, the `people` entries outside their scope
     * are excluded, by title and by search alike; entries of any other
     * collection the picker targets are left as they are.
     *
     * @param  Request  $request  The picker's index request
     * @return mixed The entry query, or the search query when searching an index
     */
    #[\Override]
    protected function getIndexQuery($request)
    {
        // Build the base query, which filters by site, search and exclusions
        $query = parent::getIndexQuery($request);

        // Check whether the picker can offer `people` entries at all
        $collections = Arr::wrap($this->getConfiguredCollections());

        // Equivalent: the strict flag compares a collection handle string with strings, and
        // excluding `people` ids from a picker with no `people` entries changes nothing, so
        // the early return only skips the scope's query.
        // @pest-mutate-ignore: TrueToFalse, RemoveEarlyReturn
        if (! \in_array(PeopleEditingScope::COLLECTION, $collections, true)) {
            return $query;
        }

        // Check whether the people editing scope applies to the current user
        $user = UserFacade::current();
        $scope = app(PeopleEditingScope::class);

        // Equivalent: the entry query and the search query are both Statamic query
        // builders, so the type guard holds for every query the parent builds. It is
        // checked last so that neither half of the scope check can be dropped unseen.
        // @pest-mutate-ignore: InstanceOfToTrue
        if ($user === null || ! $scope->appliesTo($user) || ! $query instanceof Builder) {
            return $query;
        }

        // Exclude the `people` entries outside the user's scope
        $query->whereNotIn('id', $scope->outOfScopeEntryIds($user));

        return $query;
    }

    /**
     * Apply index query scopes, merging configured query_scopes into params as
     * queryScopes so scopes that read $values['queryScopes']
     * (e.g., EntriesBySchemaType) receive the handles. The CP sends config in the
     * request body/query; scope handles are not in request params by default.
     *
     * This is a fix for a bug in Statamic where query scopes are not passed in the
     * request params. See https://github.com/statamic/cms/issues/14204 for details.
     * If that issue is resolved, this method can be removed.
     *
     * @param  Builder  $query  Entry query builder
     * @param  array<string, mixed>  $params  Request params passed to each scope
     */
    #[\Override]
    protected function applyIndexQueryScopes(
        $query,
        $params
    ): void {
        // Set the configured query scopes on the request params as `queryScopes`
        $params['queryScopes'] = Arr::wrap($this->config('query_scopes'));

        // Call the parent method to apply the scopes
        parent::applyIndexQueryScopes($query, $params);
    }
}
