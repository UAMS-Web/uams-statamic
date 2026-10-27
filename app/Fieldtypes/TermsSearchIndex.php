<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Statamic\Facades\Search;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Facades\User;
use Statamic\Search\Index;
use Statamic\Search\QueryBuilder;
use Statamic\Search\Result;
use Statamic\Stache\Query\TermQueryBuilder;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy as TaxonomyModel;

/**
 * TermsSearchIndex Fieldtype
 *
 * A custom fieldtype extending App\Fieldtypes\Terms (Statamic terms plus the
 * index `queryScopes` merge) to integrate a search index for querying terms in
 * 'default' (Stack Selector) mode only. This implementation removes support for
 * 'typeahead' and 'select' modes to ensure reliability, as we encountered
 * persistent front-end rendering and configuration issues with those UI modes
 * when implementing search index support.
 */
class TermsSearchIndex extends Terms
{
    /**
     * The display title of the fieldtype in the Control Panel.
     *
     * Set to "Taxonomy Terms With Search Index" to differentiate from the parent
     * "Taxonomy Terms" fieldtype, while keeping the class name and filename as
     * TermsSearchIndex.
     *
     * @var string The display title of the fieldtype in the Control Panel.
     */
    protected static $title = 'Terms w/ Search Index';

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        // Narrow the parent's untyped icon name to a string before returning.
        /** @var string $icon */
        $icon = parent::icon();

        // Return the icon name
        return $icon;
    }

    /**
     * Extend the keywords for the fieldtype picker.
     *
     * Inherits the parent Terms fieldtype's title-based keywords ("taxonomy",
     * "terms") and adds additional keywords to explicitly to ensure this fieldtype
     * appears alongside the parent when searching "taxonomy" in the blueprint editor.
     *
     * @return array<string> Search keywords used to find this fieldtype in the Control Panel
     */
    #[\Override]
    public function keywords(): array
    {
        // Define static keywords so the fieldtype is discoverable in the picker.
        $keywords = [
            'taxonomy',
            'terms',
            'index',
            'search',
            'search index',
            'with index',
            'w/ index',
            'with search',
            'w/ search',
            'with search index',
            'w/ search index',
            'taxonomy terms',
            'taxonomy terms index',
            'taxonomy terms with index',
            'taxonomy terms w/ index',
            'taxonomy terms search',
            'taxonomy terms with search',
            'taxonomy terms w/ search',
            'taxonomy terms search index',
            'taxonomy terms with search index',
            'taxonomy terms w/ search index',
            'terms index',
            'terms with index',
            'terms w/ index',
            'terms search',
            'terms with search',
            'terms w/ search',
            'terms search index',
            'terms with search index',
            'terms w/ search index',
        ];

        // Narrow the parent's untyped keywords to a string list before merging.
        /** @var array<string> $parentKeywords */
        $parentKeywords = parent::keywords();

        // Merge parent keywords with search-index-specific synonyms.
        /** @var array<string> $result */
        $result = \array_merge(
            $parentKeywords,
            $keywords
        );

        // Unset local variables to free memory
        unset(
            $keywords,
            $parentKeywords
        );

        // Return the merged keywords
        return $result;
    }

    /**
     * Extend the config to add additional configuration fields available for this
     * fieldtype in the blueprint.
     *
     * This method customizes the parent Terms fieldtype's config by adding a
     * 'search_index' option and removing the 'mode' option to enforce 'default' UI
     * mode.
     *
     * @return array<int, array<string, mixed>> The configuration field items
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        // Start with the parent Terms fieldtype's configuration
        /** @var array<int, array<string, mixed>> $config */
        $config = parent::configFieldItems();

        // ---
        // Add a custom 'search_index' field to allow specifying a search index This is
        // inspired by Statamic\Fieldtypes\Entries::configFieldItems() where custom config
        // options like 'create' are added to the 'Advanced' section
        // ---
        // Read the first config section's fields into a typed local to modify it.
        /** @var array<string, mixed> $fields */
        $fields = $config[0]['fields'];

        $fields['search_index'] = [
            'display' => __('Search Index'),
            'instructions' => __('Specify a search index to use for querying terms.'),
            'type' => 'text',
            'default' => null,
        ];

        // ---
        // Remove the 'mode' option to enforce 'default' (Stack Selector) mode.
        // Rationale: 'typeahead' and 'select' modes were removed because they
        // consistently failed to render correctly in the front end due to issues with
        // custom Vue components (e.g., incomplete $config, AJAX failures) and extending
        // relationship-fieldtype (e.g., 'appendedComponents' errors). 'default' mode
        // reliably works with the existing search index logic and Statamic's built-in
        // relationship-fieldtype.
        // ---
        unset($fields['mode']);

        // Write the modified fields back into the config section.
        $config[0]['fields'] = $fields;

        // Return the configuration
        return $config;
    }

    /**
     * Build the query for fetching terms in 'default' mode (Stack Selector).
     *
     * This method overrides the parent to integrate a custom search index when
     * configured. It's adapted from Statamic\Fieldtypes\Terms::getIndexQuery() and
     * incorporates elements from Statamic\Fieldtypes\Entries::getIndexQuery() for
     * handling search constraints and exclusions.
     *
     * @param  mixed  $request  Request-like object containing index query params.
     * @return TermQueryBuilder
     */
    #[\Override]
    protected function getIndexQuery(
        $request
    ) {
        // Delegate to the parent when the relationship stack passes a non-Request.
        if (! $request instanceof Request) {
            // Narrow the parent's untyped builder before returning.
            /** @var TermQueryBuilder $parentQuery */
            $parentQuery = parent::getIndexQuery($request);

            // Return the parent's query
            return $parentQuery;
        }

        // ---
        // Initialize a base query for Terms (concrete builder; the contract has no
        // analyzed query methods)
        // ---
        /** @var TermQueryBuilder $query */
        $query = Term::query();

        // ---
        // Scope to configured + viewable taxonomies, matching core Terms behavior. Prefer
        // request-provided taxonomies, but never allow out-of-config handles. Resolve
        // configured taxonomy handles for this fieldtype.
        // ---
        /** @var list<string> $configuredTaxonomies */
        $configuredTaxonomies = $this->getConfiguredTaxonomies();

        // Read taxonomy handles supplied by the relationship request, if present.
        /** @var list<string> $requestedTaxonomies */
        $requestedTaxonomies = Arr::wrap($request->input('taxonomies'));

        // Compute the effective handle list (request constrained by config).
        /** @var list<string> $effectiveTaxonomies */
        $effectiveTaxonomies = $requestedTaxonomies !== []
            ? \array_values(\array_intersect($requestedTaxonomies, $configuredTaxonomies))
            : $configuredTaxonomies;

        // ---
        // Filter to taxonomies the current user can view.
        // ---

        // Get the current user
        /** @var Authorizable|null $user */
        $user = User::current();

        // Map the effective taxonomies to their handles
        $effectiveTaxonomies = collect($effectiveTaxonomies)
            ->map(Taxonomy::findByHandle(...))
            ->filter()
            ->filter(fn ($taxonomy): bool => $taxonomy instanceof TaxonomyModel)
            ->filter(fn (TaxonomyModel $taxonomy): bool => $user !== null && $user->can('view', $taxonomy))
            ->map(fn (TaxonomyModel $taxonomy) => $taxonomy->handle())
            ->all();

        // Check if there are any effective taxonomies
        if ($effectiveTaxonomies !== []) {
            // Constrain the term query to the effective taxonomy handle list.
            $query->whereIn(
                'taxonomy',
                $effectiveTaxonomies
            );
        } else {
            // No viewable/configured taxonomies; ensure no results.
            $query->where(
                'id'
            );
        }

        // ---
        // Apply custom search index logic if configured, otherwise use default title
        // search. This customization is unique to our needs, inspired by Entries' search
        // handling.
        // ---
        // Read the search term from the request, narrowing to a typed string|null.
        $searchInput = $request->input('search');
        $search = is_string($searchInput) ? $searchInput : null;

        // Narrow the configured search-index name to a string when present.
        $searchIndexInput = $this->config('search_index');
        $searchIndex = is_string($searchIndexInput) ? $searchIndexInput : null;

        // Check if a search index is configured
        if ($searchIndex !== null && $searchIndex !== '') {
            // Apply search-index-constrained term IDs when the index is configured.
            $this->applySearchIndex(
                $query,
                $searchIndex,
                $search
            );
        } else {
            // Fall back to core-like title searching when no index is configured.
            $this->applyDefaultSearch(
                $query,
                $search
            );
        }

        // ---
        // Apply site filter if specified.
        // Borrowed from  Statamic\Fieldtypes\Terms::getIndexQuery().
        // Read an explicit site constraint when provided.
        // ---
        $site = $request->input('site');

        // Check if a site filter was supplied
        if ($site) {
            // Constrain results to the requested site.
            $query->where(
                'site',
                $site
            );
        }

        // ---
        // Exclude specific IDs if provided.
        // Directly taken from Statamic\Fieldtypes\Entries::getIndexQuery().
        // Read exclusion IDs provided by the picker.
        // ---
        $exclusions = $request->input('exclusions');

        // Check if exclusion IDs were supplied
        if ($exclusions) {
            // Remove excluded IDs from the term query.
            $query->whereNotIn(
                'id',
                $exclusions
            );
        }

        // ---
        // Apply any additional query scopes defined in the field config.
        // Inherited from Statamic\Fieldtypes\Terms::getIndexQuery().
        // Apply configured or request-provided query scopes to the index query.
        // ---
        $this->applyIndexQueryScopes(
            $query,
            $request->all()
        );

        // Unset local variables to free memory
        unset(
            $configuredTaxonomies,
            $requestedTaxonomies,
            $effectiveTaxonomies,
            $user,
            $site,
            $exclusions,
            $searchIndex,
            $searchInput,
            $search,
            $request
        );

        // Return the query
        return $query;
    }

    /**
     * Apply a custom search index to filter terms.
     *
     * This helper method integrates Statamic's Search facade to query a specified
     * index, constraining the term query to matching IDs. It's a custom addition not
     * present in the parent Terms fieldtype, designed to leverage an external search
     * index.
     *
     * @param  TermQueryBuilder  $query  The term query to modify
     * @param  string  $searchIndex  The name of the search index to use
     * @param  ?string  $search  The search term from the request
     */
    private function applySearchIndex(
        $query,
        string $searchIndex,
        ?string $search
    ): void {
        // Check if no search term is provided
        if (! $search) {
            // Unset local variables to free memory
            unset(
                $query,
                $searchIndex,
                $search
            );

            // Return to skip the rest of the method
            return;
        }

        // ---
        // Query the search index and extract term IDs
        // ---

        // Get the search index
        /** @var Index $searchIndexInstance */
        $searchIndexInstance = Search::index($searchIndex);

        // Narrow the ensured index back to the concrete Index type.
        /** @var Index $index */
        $index = $searchIndexInstance->ensureExists();

        // Narrow the untyped search builder before fetching results.
        /** @var QueryBuilder $searchQuery */
        $searchQuery = $index->search($search);

        // Get the search results
        /** @var Collection<int, Result> $results */
        $results = $searchQuery->get();

        // Get the term IDs
        $termIds = $results
            ->map(function (mixed $result) {
                // Results are duck-typed: production yields Statamic Result
                // objects, tests pass lightweight doubles. Narrow for analysis
                // rather than a native type hint that would reject the doubles.
                /** @var Result $searchResult */
                $searchResult = $result;

                // Narrow the searchable to the concrete term model exposing id().
                /** @var LocalizedTerm $searchable */
                $searchable = $searchResult->getSearchable();

                return $searchable->id();
            })
            ->all();

        // Filter the query by matching IDs, or return no results if none found
        if (! empty($termIds)) {
            $query->whereIn(
                'id',
                $termIds
            );
        } else {
            $query->where(
                'id'
            ); // No matches
        }

        // Unset local variables to free memory
        unset(
            $searchIndexInstance,
            $index,
            $results,
            $termIds,
            $query,
            $searchIndex,
            $search
        );
    }

    /**
     * Apply a default title-based search when no search index is configured.
     *
     * This fallback mirrors the basic search behavior in Statamic\Fieldtypes\Terms,
     * using a LIKE query on the title field.
     *
     * @param  TermQueryBuilder  $query  The term query to modify
     * @param  ?string  $search  The search term from the request
     */
    private function applyDefaultSearch(
        TermQueryBuilder $query,
        ?string $search
    ): void {
        // Check if a search term is provided
        if ($search) {
            // Apply the search term to the query
            $query->where(
                'title',
                'like',
                sprintf('%%%s%%', $search)
            );
        }
    }

    // ---
    // Note: getResourceCollection() is intentionally omitted to rely on
    // Statamic\Fieldtypes\Terms::getResourceCollection(). This ensures 'default' mode
    // uses the parent's logic, returning terms in the expected {id, title} format
    // without interference from typeahead/select-specific overrides, which were
    // removed to enforce Stack Selector compatibility in the front end.
    // ---
}
