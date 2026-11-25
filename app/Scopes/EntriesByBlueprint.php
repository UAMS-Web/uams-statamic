<?php

declare(strict_types=1);

namespace App\Scopes;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Blueprint as FieldsBlueprint;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * Entry query scope that filters entries by their blueprint handle.
 *
 * Resolves the blueprint from encoded query scope handles:
 * - entries_by_blueprint_building_complex
 * - entries_by_blueprint_building
 * - entries_by_blueprint_building_division
 * - entries_by_blueprint_parking_facility
 * - entries_by_blueprint_parking_division
 * - entries_by_blueprint_clinical_expertise
 *
 * An `entries` fieldtype targets a collection, not a blueprint, so a
 * multi-blueprint collection (the `facilities` collection, or the
 * `clinical_expertise` collection) needs this to constrain a relationship
 * picker to one kind of entry: a location's `building` picker to Building /
 * Building Division entries, a facility's `parking` picker to Parking Facility
 * entries, and an area of expertise's related-AoE picker to Area of Expertise
 * entries (never its `basic_page` / `landing_page` content pages). One scope
 * class serves every blueprint via aliases; listing several handles on one
 * field unions them into a single whereIn, because every invocation reads the
 * full `queryScopes` list.
 *
 * The recognized blueprint handles are DERIVED from the opt-in collections'
 * blueprints (see {@see self::recognizedBlueprints()}) rather than a
 * hand-maintained literal, so renaming a blueprint no longer requires editing
 * this scope (only the field's `entries_by_blueprint_<handle>` reference has to
 * follow the rename).
 */
class EntriesByBlueprint extends Scope
{
    /**
     * Scope handle prefix for registration and alias encoding.
     *
     * @var string
     */
    protected static $handle = 'entries_by_blueprint';

    /**
     * Collections whose entry blueprints are exposed as blueprint-scoped
     * relationship pickers. The recognized blueprint HANDLES are derived from
     * these collections' blueprints at read time, so a blueprint rename within
     * one of them is picked up automatically — only adding a whole collection
     * (or renaming one) edits this list.
     *
     * @var array<int, string>
     */
    public const array COLLECTIONS = [
        'facilities',
        'clinical_expertise',
    ];

    /**
     * Shared content-page blueprints that are body content, never a pickable
     * entity — excluded from the derived set. This is what keeps the
     * `clinical_expertise` related-AoE picker offering the `clinical_expertise`
     * blueprint but not its `basic_page` / `landing_page` content pages (the
     * `facilities` collection has no content-page blueprints).
     *
     * @var array<int, string>
     */
    private const array EXCLUDED_BLUEPRINTS = [
        'basic_page',
        'landing_page',
    ];

    /**
     * Register the scope handle now and its blueprint aliases once the
     * application has booted.
     *
     * Statamic registers app scopes while the service providers register, and
     * the inherited `register()` reads {@see self::aliases()} there. The aliases
     * come from the collection blueprints, and on `statamic/cms` 6.41.0 and
     * later a blueprint is parsed through the cache store, whose deferred
     * binding does not exist yet at that point, so reading them then stops the
     * application booting (UAMS-Web/uams-statamic#1515). Deferring the aliases
     * to `booted()` reads the blueprints after every provider has registered;
     * when the application has already booted, the callback runs at once.
     */
    public static function register(): void
    {
        self::bind([static::$handle]);

        app()->booted(static fn () => self::bind(static::aliases()));
    }

    /**
     * Bind each handle to this class in Statamic's scope registry, keeping
     * every binding already there.
     *
     * @param  array<int, string>  $handles
     */
    private static function bind(array $handles): void
    {
        $extensions = app('statamic.extensions');

        // Start from the scopes already registered, then add these handles.
        $bindings = $extensions->get(Scope::class);
        $bindings = $bindings instanceof Collection ? $bindings : collect();

        foreach ($handles as $handle) {
            $bindings->put($handle, static::class);
        }

        $extensions->put(Scope::class, $bindings);
    }

    /**
     * Register one alias per recognized blueprint so a single class serves them all.
     *
     * @return array<int, string>
     */
    public static function aliases(): array
    {
        $prefix = static::$handle.'_';

        return array_map(
            static fn (string $handle): string => $prefix.$handle,
            self::recognizedBlueprints()
        );
    }

    /**
     * The blueprint handles this scope may filter to, derived from the opt-in
     * collections' blueprints minus the shared content pages.
     *
     * Reading through Statamic's blueprint registry keeps the recognized set in
     * lockstep with the blueprints themselves — a blueprint rename is picked up
     * with no scope edit, where the old hand-maintained literal drifted silently
     * (a missed rename left `entries_by_blueprint_<oldhandle>` filtering on a
     * handle no entry has → an empty picker). `Blueprint::in()` is Blink-cached
     * per request, so repeated reads during scope discovery stay cheap.
     *
     * @return array<int, string>
     */
    public static function recognizedBlueprints(): array
    {
        // Collect the blueprint handles of every opt-in collection.
        $handles = [];

        foreach (self::COLLECTIONS as $collectionHandle) {
            /** @var Collection<int, FieldsBlueprint> $blueprints */
            $blueprints = Blueprint::in('collections/'.$collectionHandle);

            foreach ($blueprints as $blueprint) {
                // Read the blueprint's own handle from the registry.
                $handle = $blueprint->handle();
                // Skip handleless blueprints and the shared content-page blueprints
                // (body content, not picker targets).
                if ($handle === null) {
                    continue;
                }

                if (\in_array($handle, self::EXCLUDED_BLUEPRINTS, true)) {
                    continue;
                }

                $handles[] = $handle;
            }
        }

        // Dedupe for a stable, minimal recognized set.
        return array_values(array_unique($handles));
    }

    /**
     * Filter to entries whose blueprint is in the set encoded by the invoking
     * scope handle(s). With no recognized handle, no filter applies.
     *
     * @param  Builder  $query  Entry query builder
     * @param  array<string, mixed>  $values  Request values (e.g., queryScopes)
     */
    public function apply(
        $query,
        $values
    ): void {
        $blueprints = $this->blueprintsFromScopeHandles($values);

        // No recognized blueprint handle: leave the query unfiltered.
        if ($blueprints === []) {
            return;
        }

        $query->whereIn(
            'blueprint',
            $blueprints
        );

        unset($blueprints);
    }

    /**
     * Extract recognized blueprint suffixes from the query scope handles.
     *
     * @param  array<string, mixed>  $values
     * @return array<int, string>
     */
    private function blueprintsFromScopeHandles(array $values): array
    {
        $queryScopes = Arr::wrap($values['queryScopes'] ?? []);
        $prefix = static::$handle.'_';
        $recognized = self::recognizedBlueprints();
        $blueprints = [];

        foreach ($queryScopes as $queryScope) {
            if (! \is_string($queryScope)) {
                continue;
            }

            if (! str_starts_with($queryScope, $prefix)) {
                continue;
            }

            $suffix = substr($queryScope, \strlen($prefix));
            if (\in_array($suffix, $recognized, true)) {
                $blueprints[] = $suffix;
            }
        }

        unset($queryScope);

        return array_values(array_unique($blueprints));
    }
}
