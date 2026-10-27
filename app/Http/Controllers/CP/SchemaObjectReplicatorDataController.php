<?php

declare(strict_types=1);

namespace App\Http\Controllers\CP;

use App\Enums\SchemaProperty;
use App\Services\SchemaObject\SchemaPropertyReplicatorSets;
use App\Tags\Schema\SchemaPropertyValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Statamic\Http\Controllers\CP\CpController;

/**
 * Returns replicator config and meta for one or more schema types' properties
 * (union of properties for Multi Typed Entities). Used by the schema_object
 * fieldtype to lazy-load replicator data when type(s) are selected.
 */
class SchemaObjectReplicatorDataController extends CpController
{
    /**
     * Handle the incoming request.
     *
     * Accepts `types` (array of type strings) for MTE, or legacy `type` (single string).
     *
     * @return JsonResponse { config_by_property: {...}, meta_by_property: {...} }
     */
    public function __invoke(Request $request): JsonResponse
    {
        $types = $this->normalizeTypesFromRequest($request);
        if ($types === []) {
            return response()->json([
                'config_by_property' => [],
                'meta_by_property' => [],
                'property_names' => [],
            ]);
        }

        // Union of property names across all types (dedupe, preserve order).
        $propertyNames = $this->propertyNamesForTypes($types);

        $replicatorSetsService = new SchemaPropertyReplicatorSets;
        $configByProperty = [];
        $metaByProperty = [];

        foreach ($propertyNames as $propertyName) {
            $propertyCase = SchemaProperty::tryFrom($propertyName);
            $rangeTypes = $propertyCase !== null
                ? $propertyCase->rangeTypes()
                : ['Text'];
            $setsConfig = $replicatorSetsService->setsForRangeTypes($rangeTypes);
            $configByProperty[$propertyName] = [
                'sets' => $replicatorSetsService->configSetsForVue($setsConfig),
                'button_label' => __('Add Value'),
                'collapse' => true,
            ];
            $metaByProperty[$propertyName] = $replicatorSetsService->metaForSets($setsConfig);
        }

        return response()->json([
            'config_by_property' => $configByProperty,
            'meta_by_property' => $metaByProperty,
            'property_names' => $propertyNames,
        ]);
    }

    /**
     * Normalize request to array of type strings (accept types[] or legacy type).
     *
     * @return array<int, string>
     */
    private function normalizeTypesFromRequest(Request $request): array
    {
        $types = $request->input('types');
        if (\is_array($types)) {
            return array_values(array_unique(array_filter($types, static fn ($t): bool => \is_string($t) && $t !== '')));
        }

        $type = $request->input('type');
        if (\is_string($type) && $type !== '') {
            return [$type];
        }

        return [];
    }

    /**
     * Union of valid property names for the given types (each type's props, deduped).
     *
     * When multiple types are selected, a property is suppressed if it appears in
     * the blacklist for any of those types, so multi-typed entities do not show
     * properties that are hidden for one of their types.
     *
     * @param  array<int, string>  $types
     * @return array<int, string>
     */
    private function propertyNamesForTypes(array $types): array
    {
        $seen = [];
        $propertyNames = [];

        /** @var array{general?: array<int, string>, specific?: array<string, array<int, string>>} $blacklist */
        $blacklist = config('schema_object.property_blacklist', []);
        $general = $blacklist['general'] ?? [];
        $specific = $blacklist['specific'] ?? [];

        // Union of blacklisted properties across all selected types
        $combinedHidden = $general;
        foreach ($types as $type) {
            $typeHidden = $specific[$type] ?? [];
            if ($typeHidden !== []) {
                $combinedHidden = array_merge($combinedHidden, $typeHidden);
            }
        }

        $combinedHidden = array_values(array_unique($combinedHidden));

        foreach ($types as $type) {
            $list = SchemaPropertyValidator::validPropertiesForType($type);
            if ($combinedHidden !== []) {
                $list = array_values(array_diff($list, $combinedHidden));
            }

            foreach ($list as $name) {
                if (! isset($seen[$name])) {
                    $seen[$name] = true;
                    $propertyNames[] = $name;
                }
            }
        }

        unset(
            $blacklist,
            $combinedHidden,
            $general,
            $seen,
            $specific
        );

        return $propertyNames;
    }
}
