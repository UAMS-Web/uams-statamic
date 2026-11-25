<?php

declare(strict_types=1);

namespace App\Http\Controllers\CP;

use App\Services\SchemaObject\SchemaPropertyReplicatorSets;
use App\Tags\Schema\SchemaPropertyValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Statamic\Http\Controllers\CP\CpController;
use UAMSWeb\Aether\Enums\SchemaProperty;

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
        // Union of property names across all types (dedupe, preserve order).
        $propertyNames = $this->propertyNamesForTypes($this->normalizeTypesFromRequest($request));

        $replicatorSetsService = new SchemaPropertyReplicatorSets;
        $configByProperty = [];
        $metaByProperty = [];

        foreach ($propertyNames as $propertyName) {
            $propertyCase = SchemaProperty::tryFrom($propertyName);
            $rangeTypes = $propertyCase?->rangeTypes() ?? ['Text'];
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
     * An empty or repeated type adds no property, so neither needs dropping here.
     *
     * @return array<string>
     */
    private function normalizeTypesFromRequest(Request $request): array
    {
        $types = $request->input('types');
        if (\is_array($types)) {
            return array_filter($types, \is_string(...));
        }

        $type = $request->input('type');

        return \is_string($type) ? [$type] : [];
    }

    /**
     * Union of valid property names for the given types (each type's props, deduped).
     *
     * When multiple types are selected, a property is suppressed if it appears in
     * the blacklist for any of those types, so multi-typed entities do not show
     * properties that are hidden for one of their types.
     *
     * @param  array<string>  $types
     * @return list<string>
     */
    private function propertyNamesForTypes(array $types): array
    {
        /** @var array{general?: array<int, string>, specific?: array<string, array<int, string>>} $blacklist */
        $blacklist = config('schema_object.property_blacklist', []);
        $specific = $blacklist['specific'] ?? [];

        // Union of blacklisted properties across all selected types
        $hidden = $blacklist['general'] ?? [];
        foreach ($types as $type) {
            $hidden = array_merge($hidden, $specific[$type] ?? []);
        }

        $propertyNames = [];
        foreach ($types as $type) {
            $propertyNames = array_merge(
                $propertyNames,
                array_diff(SchemaPropertyValidator::validPropertiesForType($type), $hidden)
            );
        }

        return array_values(array_unique($propertyNames));
    }
}
