<?php

declare(strict_types=1);

namespace App\Scopes;

use App\Cache\ScopeAliasCache;
use App\Enums\SchemaType;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * Entry query scope that filters entries by Schema.org type — one alias-driven
 * class serving BOTH schema-type surfaces (the UAMS-Web/uams-statamic-archive#1408 unification of the former
 * `EntriesBySchemaType` + `EntriesByResolvedSchemaType`).
 *
 * Aliases are `entries_by_schema_type_<Str::snake(TypeName)>` (one per
 * {@see SchemaType} case), e.g.:
 * - entries_by_schema_type_person
 * - entries_by_schema_type_administrative_area
 * - entries_by_schema_type_medical_therapy
 *
 * `apply()` constrains whichever field the invoking collection actually stores —
 * a query is scoped to one collection, so the other clause matches nothing:
 * - `schema_objects`: the flat resolved leaf on `schema->type`, expanded to the
 *   handle's type PLUS its Schema.org descendants (from the enum).
 * - clinical collections: the chained fieldtype's stored `schema_type->selections`
 *   path, matched on the leaf type — the path already carries every ancestor, so
 *   `whereJsonContains('schema_type->selections', <Type>)` matches the type and
 *   every descendant for free (no enum expansion).
 *
 * `Str::snake` (not the enum's `slug` attribute) is what fixes a real silent
 * no-op: the `place` blueprint referenced `entries_by_schema_type_administrative_area`
 * (snake), but the slug alias was `administrativearea` (no underscore), so that
 * picker matched no alias and showed ALL entries.
 */
class EntriesBySchemaType extends Scope
{
    /**
     * Scope handle for registration and config.
     *
     * @var string
     */
    protected static $handle = 'entries_by_schema_type';

    /**
     * The chained fieldtype's stored selections path on clinical collections.
     */
    private const string SELECTIONS_PATH = 'schema_type->selections';

    /**
     * Cache key for persisted alias list (cleared when SchemaType enum is regenerated).
     *
     * The `_v2` suffix invalidates any list persisted in the former slug format
     * (`administrativearea`) so the CP option list rebuilds in the `Str::snake`
     * format (`administrative_area`) on deploy, without a manual cache clear (UAMS-Web/uams-statamic-archive#1408).
     */
    public const string CACHE_KEY_ALIASES = 'entries_by_schema_type_aliases_v2';

    /**
     * Register dynamic aliases so schema type rules can be encoded in scope
     * handles (one per SchemaType case, keyed by `Str::snake` of the type name).
     *
     * Uses in-process static first, then a dedicated ScopeAliasCache (bound early
     * in bootstrap/app.php), then Laravel Cache when bound, so the list persists
     * across requests and CLI runs. Cache is cleared when the SchemaType enum is
     * regenerated (GenerateSchemaTypeEnum command).
     *
     * @return array<int, string>
     */
    public static function aliases(): array
    {
        // In-process cache (avoids repeated Cache::get in the same request/process).
        /** @var array<int, string>|null $cached */
        static $cached = null;

        // Without this return each call rebuilds the list through the cache, which hands
        // back the list it already stores.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($cached !== null) {
            return $cached;
        }

        // Prefer dedicated alias cache (bound early), then Laravel cache, then build.
        // Mutation testing reports IfNegated on this `if` as a survivor, and it is
        // equivalent: inside the booted application both stores are bound and hold the
        // same list, so the other branch returns the same aliases. A marker above a node
        // cannot suppress a mutator that rewrites that node, so it stays listed.
        if (app()->bound(ScopeAliasCache::class)) {
            $cached = app(ScopeAliasCache::class)->rememberForever(
                self::CACHE_KEY_ALIASES,
                fn (): array => self::buildAliases()
            );
        } elseif (app()->bound('cache')) {
            try {
                $cached = Cache::rememberForever(
                    self::CACHE_KEY_ALIASES,
                    fn (): array => self::buildAliases()
                );
            } catch (\Throwable) {
                $cached = self::buildAliases();
            }
        } else {
            $cached = self::buildAliases();
        }

        // Mutation testing reports AlwaysReturnEmptyArray on this return as a survivor.
        // Only the first call in a process returns from here, and Statamic makes that call
        // while it registers the scope (RegistersItself::register()), so the mutant's empty
        // list reaches only the first application booted in a process; every later boot
        // reads the memo above. No covering test resolves one of these aliases in that first
        // boot. A marker cannot suppress a mutator that rewrites the node it sits above, so
        // it stays listed.
        return $cached;
    }

    /**
     * Build alias list from SchemaType cases (no cache). Used when Cache is unavailable.
     *
     * @return array<int, string>
     */
    protected static function buildAliases(): array
    {
        $prefix = static::$handle.'_';
        $aliases = [];
        foreach (SchemaType::cases() as $case) {
            // Only the keys are read, so the stored value does not matter.
            // @pest-mutate-ignore: TrueToFalse
            $aliases[$prefix.Str::snake($case->value)] = true;
        }

        unset($case);

        return array_keys($aliases);
    }

    /**
     * Clear the persisted alias cache (call after regenerating SchemaType enum).
     *
     * Clears both the dedicated ScopeAliasCache and Laravel cache when bound.
     */
    public static function clearAliasesCache(): void
    {
        if (app()->bound(ScopeAliasCache::class)) {
            app(ScopeAliasCache::class)->forget(self::CACHE_KEY_ALIASES);
        }

        if (app()->bound('cache')) {
            Cache::forget(self::CACHE_KEY_ALIASES);
        }
    }

    /**
     * Rebuild and store the alias list from type names (e.g., from the enum generator).
     * Uses the same `Str::snake` normalization as {@see self::buildAliases()}. Call
     * after regenerating the SchemaType enum so the cache is warm without loading the
     * new enum in this process.
     *
     * Warms both the dedicated ScopeAliasCache and Laravel cache when bound.
     *
     * @param  array<int, string>  $typeNames  Schema.org type names (e.g., Thing, 3DModel)
     */
    public static function warmAliasesCache(array $typeNames): void
    {
        $prefix = static::$handle.'_';
        $aliases = [];
        foreach ($typeNames as $name) {
            // Only the keys are read, so the stored value does not matter.
            // @pest-mutate-ignore: TrueToFalse
            $aliases[$prefix.Str::snake($name)] = true;
        }

        unset($name);
        $keys = array_keys($aliases);

        if (app()->bound(ScopeAliasCache::class)) {
            app(ScopeAliasCache::class)->put(self::CACHE_KEY_ALIASES, $keys);
        }

        if (app()->bound('cache')) {
            Cache::put(
                self::CACHE_KEY_ALIASES,
                $keys,
                now()->addYears(10)
            );
        }

        unset(
            $aliases,
            $keys,
            $prefix
        );
    }

    /**
     * Apply the scope to the entry query.
     *
     * Filters to entries whose Schema.org type matches the handle's type (plus
     * descendants). When the scope is Thing (root type), no filter is applied so
     * all entries are shown. Serves both the flat `schema->type` leaf
     * (`schema_objects`) and the chained `schema_type->selections` path (clinical
     * collections) in one grouped clause; the field the collection does not store
     * matches nothing.
     *
     * @param  mixed  $query  Statamic query builder, or any value when not bound to a query
     * @param  array<string, mixed>  $values  Request values (e.g., queryScopes)
     */
    public function apply(
        $query,
        $values
    ): void {
        // Ignore when not applied to a Statamic query builder.
        if (! $query instanceof Builder) {
            return;
        }

        // Extract the encoded suffixes once and thread them through (avoids
        // re-parsing queryScopes in the leaf-type resolution) (UAMS-Web/uams-statamic-archive#1367).
        $suffixes = $this->encodedScopeSuffixes($values);

        // Thing is the root type; filtering by it would include all schema types, so skip the filter.
        // Its values are non-numeric strings, which loose and strict in_array() compare alike.
        // @pest-mutate-ignore: TrueToFalse
        if (\in_array(Str::snake('Thing'), $suffixes, true)) {
            unset($suffixes);

            return;
        }

        // Resolve the handle(s) to their leaf type name(s) — no descendant expansion.
        $leafTypes = $this->leafTypesFromSuffixes($suffixes);

        // Check if no filter input was provided and skip query changes.
        if ($leafTypes === []) {
            unset($leafTypes, $suffixes);

            return;
        }

        // For the flat leaf surface, expand to the type plus its descendants.
        $expandedTypes = $this->expandWithDescendants($leafTypes);

        // Match whichever schema-type field the collection stores; a query is scoped
        // to one collection, so the field it does not store contributes nothing.
        $query->where(
            function (Builder $q) use ($expandedTypes, $leafTypes): void {
                // schema_objects: flat resolved leaf, as a string or an array.
                $q->whereIn(
                    'schema->type',
                    $expandedTypes
                );
                foreach ($expandedTypes as $type) {
                    $q->orWhereJsonContains(
                        'schema->type',
                        $type
                    );
                }

                unset($type);

                // clinical: chained selections path — leaf only (the path carries
                // every ancestor, so this already matches type + all descendants).
                foreach ($leafTypes as $type) {
                    $q->orWhereJsonContains(
                        self::SELECTIONS_PATH,
                        $type
                    );
                }

                unset($type);
            }
        );

        unset($leafTypes, $expandedTypes, $suffixes);
    }

    /**
     * Resolve already-extracted handle suffixes to their leaf Schema.org type name(s).
     *
     * @param  array<int, string>  $suffixes
     * @return array<int, string>
     */
    private function leafTypesFromSuffixes(array $suffixes): array
    {
        // An empty list makes the loop below add nothing, so it returns [] either way.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($suffixes === []) {
            return [];
        }

        // Resolve each suffix to its type name; drop unrecognized suffixes.
        $types = [];
        foreach ($suffixes as $suffix) {
            $typeName = $this->typeNameFromSlug($suffix);
            if ($typeName !== null) {
                $types[] = $typeName;
            }
        }

        // Each suffix names at most one type and no two suffixes name the same one, and
        // encodedScopeSuffixes() has already removed repeats, so this list has neither repeats nor gaps.
        // @pest-mutate-ignore: UnwrapArrayUnique, UnwrapArrayValues
        return array_values(array_unique($types));
    }

    /**
     * Expand a list of leaf type names to include each type's Schema.org descendants.
     *
     * @param  array<int, string>  $leafTypes
     * @return array<int, string>
     */
    private function expandWithDescendants(array $leafTypes): array
    {
        $expanded = [];
        foreach ($leafTypes as $typeName) {
            $expanded[] = $typeName;
            foreach (SchemaType::descendantsOf($typeName) as $descendant) {
                $expanded[] = $descendant;
            }
        }

        unset($typeName);

        return array_values(array_unique($expanded));
    }

    /**
     * Resolve a `Str::snake` handle suffix (e.g., person, administrative_area) back
     * to its Schema.org type name.
     *
     * Backed by an in-process `snake => type name` map built once from the enum,
     * so each lookup is O(1) — not an O(N) rescan of ~900 cases (calling
     * `Str::snake` per case) on every picker open / search keystroke (UAMS-Web/uams-statamic-archive#1367).
     *
     * @param  string  $slug  Normalized type name from scope handle
     * @return string|null Schema.org type name, or null if not found
     */
    private function typeNameFromSlug(string $slug): ?string
    {
        return self::typeNameBySlugMap()[$slug] ?? null;
    }

    /**
     * Build (once) the reverse `Str::snake(TypeName) => TypeName` lookup map.
     *
     * @return array<string, string>
     */
    private static function typeNameBySlugMap(): array
    {
        // In-process memo: this reverse map is read on every apply(); build it once.
        /** @var array<string, string>|null $map */
        static $map = null;

        // Without this return each call rebuilds the same map from the enum.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($map !== null) {
            return $map;
        }

        $map = [];
        foreach (SchemaType::cases() as $case) {
            $map[Str::snake($case->value)] = $case->value;
        }

        unset($case);

        return $map;
    }

    /**
     * Extract encoded suffixes from query scope handles.
     *
     * @param  array<string, mixed>  $values
     * @return array<int, string>
     */
    private function encodedScopeSuffixes(array $values): array
    {
        $queryScopes = Arr::wrap($values['queryScopes'] ?? []);
        $prefix = static::$handle.'_';
        $suffixes = [];

        foreach ($queryScopes as $queryScope) {
            if (! \is_string($queryScope)) {
                continue;
            }

            if (! str_starts_with($queryScope, $prefix)) {
                continue;
            }

            $suffix = substr($queryScope, \strlen($prefix));
            // An empty suffix names no type, so leafTypesFromSuffixes() drops it anyway.
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            if ($suffix !== '') {
                $suffixes[] = strtolower($suffix);
            }
        }

        // leafTypesFromSuffixes() reads this list with foreach and removes repeats again,
        // so repeats or gaps here change nothing.
        // @pest-mutate-ignore: UnwrapArrayUnique, UnwrapArrayValues
        return array_values(array_unique($suffixes));
    }
}
