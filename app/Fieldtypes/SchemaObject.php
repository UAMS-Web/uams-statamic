<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Enums\SchemaType;
use App\Services\SchemaTemplates\SchemaTypeOptions;
use App\Tags\Schema\SchemaPropertyValidator;
use Illuminate\Support\Str;
use Statamic\Facades\Parse;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;

/**
 * Fieldtype for editing a Schema.org object: one or more types (Multi Typed
 * Entities / MTE) plus one text field per valid property across those types.
 *
 * Integrates with SchemaTypeOptions for type options and SchemaPropertyValidator
 * for type-specific properties. Stored value: type (array of type strings) and
 * properties (associative array of property name => string value). Supports
 * schema.org MTEs via @type as array in JSON-LD (e.g. ["HotelRoom","Product"]).
 */
class SchemaObject extends Fieldtype
{
    /**
     * Categories in the Statamic fieldtype selector.
     *
     * @var array<int, string> Category slugs (e.g., special, structured)
     */
    protected $categories = [
        'special',
        'structured',
    ];

    /**
     * Whether the fieldtype is selectable in collection blueprints.
     *
     * @var bool True when the fieldtype can be added to collection blueprints
     */
    protected $selectable = true;

    /**
     * Whether the value can be localized.
     *
     * @var bool False so the field is not per-locale
     */
    protected $localizable = false;

    /**
     * Vue component name for the CP.
     *
     * @var string Component handle registered in cp.js (schema_object-fieldtype)
     */
    protected $component = 'schema_object';

    /**
     * Icon name for the fieldtype.
     *
     * @return string Statamic icon handle (e.g., code)
     */
    #[\Override]
    public function icon(): string
    {
        return 'code';
    }

    /**
     * Configuration fields for the blueprint (display, instructions, antlers).
     * Layout is fixed group-like: no collapsible/fullscreen/border on top-level;
     * properties nested group has border only.
     *
     * @return array<int, array<string, mixed>> Config sections and field definitions
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        return [
            [
                'display' => __('Schema Object'),
                'fields' => [
                    'type_selector_clearable' => [
                        'display' => __('Clearable'),
                        'instructions' => __(
                            'Allow clearing the type selector so the placeholder is shown again.'
                        ),
                        'type' => 'toggle',
                        'default' => true,
                    ],
                    'type_selector_disabled' => [
                        'display' => __('Disable type selector'),
                        'instructions' => __('Prevent adding or changing schema types in the CP.'),
                        'type' => 'toggle',
                        'default' => false,
                    ],
                    'type_selector_searchable' => [
                        'display' => __('Searchable'),
                        'instructions' => __('Allow searching the type list. Recommended when many types are available.'),
                        'type' => 'toggle',
                        'default' => true,
                    ],
                    'type_selector_multiple' => [
                        'display' => __('Multiple selection'),
                        'instructions' => __(
                            'Allow selecting more than one type (Multi Typed Entities / MTE). When off, only a single type can be chosen.'
                        ),
                        'type' => 'toggle',
                        'default' => true,
                    ],
                    'antlers' => [
                        'display' => __('Allow Antlers'),
                        'instructions' => __(
                            'Parse property values as Antlers (e.g., {{ title }}) when output in templates.'
                        ),
                        'type' => 'toggle',
                        'default' => true,
                        'section' => 'antlers',
                    ],
                ],
            ],
        ];
    }

    /**
     * Preload type options, properties-by-type, and type descriptions for the Vue component.
     *
     * @return array<string, mixed> Keys: type_options (value => label),
     *                              properties_by_type (type => list of property names),
     *                              type_descriptions (type => PHPDoc description),
     *                              property_instructions_by_type (type => (property => instruction))
     */
    #[\Override]
    public function preload(): array
    {
        // Get type options (value => label) from spatie/schema-org
        $typeOptions = SchemaTypeOptions::schemaObjectTypes();

        // ---
        // Build map of type => list of valid property names for that type
        // ---

        // Initialize the properties by type array.
        $propertiesByType = [];

        // Loop through the type options.
        // Each option maps a type name to itself and no name is empty, so the
        // keys equal the values and the empty-name guard never fires
        // @pest-mutate-ignore: UnwrapArrayKeys, EmptyStringToNotEmpty
        foreach (array_keys($typeOptions) as $type) {
            // Check if the type is empty.
            if ($type === '') {
                // Skip the empty type.
                continue;
            }

            // Get the valid properties for the type
            $propertiesByType[$type] = SchemaPropertyValidator::validPropertiesForType($type);
        }

        // ---
        // Apply config blacklist: remove properties that should not be displayed per type
        // ---

        // Load property blacklist from config (general + specific per type)
        /** @var array<string, mixed> $propertyBlacklist */
        $propertyBlacklist = config(
            'schema_object.property_blacklist',
            []
        );
        /** @var array<int, string> $general */
        $general = \is_array($propertyBlacklist['general'] ?? null) ? $propertyBlacklist['general'] : [];
        /** @var array<string, array<int, string>> $specific */
        $specific = \is_array($propertyBlacklist['specific'] ?? null) ? $propertyBlacklist['specific'] : [];

        // ---
        // Filter each type's property list by removing blacklisted properties
        // ---

        // Loop through the properties by type
        foreach (array_keys($propertiesByType) as $type) {
            // Merge general and type-specific hidden properties
            $hidden = array_merge(
                $general,
                $specific[$type] ?? []
            );

            // Check if the hidden properties are not empty
            if ($hidden !== []) {
                // Remove the hidden properties from the properties by type array
                $propertiesByType[$type] = array_values(
                    array_diff(
                        $propertiesByType[$type],
                        $hidden
                    )
                );
            }
        }

        // ---
        // Build map of type => PHPDoc description for CP Alert.
        // ---

        // Initialize the type descriptions array.
        $typeDescriptions = [];

        // Loop through the type options.
        // Each option maps a type name to itself and no name is empty, so the
        // keys equal the values and the empty-name guard never fires
        // @pest-mutate-ignore: UnwrapArrayKeys, EmptyStringToNotEmpty
        foreach (array_keys($typeOptions) as $type) {
            // Check if the type is empty.
            if ($type !== '') {
                // Get the type description.
                $desc = SchemaType::typeDescriptionFor($type);

                // Check if the description is not null.
                if ($desc !== null) {
                    // Add the type description to the array.
                    $typeDescriptions[$type] = $desc;
                }
            }
        }

        // ---
        // Build map of type => (property => instruction) from property method PHPDoc.
        // ---

        // Initialize the property instructions by type array.
        $propertyInstructionsByType = [];

        // Loop through the properties by type (for instructions only).
        // Replicator config/meta are fetched on demand when a type is selected
        // via the schema-object/replicator-data CP endpoint to avoid timeouts.
        foreach (array_keys($propertiesByType) as $type) {
            foreach ($propertiesByType[$type] as $propertyName) {
                $instruction = SchemaType::propertyDescriptionFor(
                    $type,
                    $propertyName
                );
                if ($instruction !== null) {
                    $propertyInstructionsByType[$type][$propertyName] = $instruction;
                }
            }
        }

        unset($instruction);

        // Return type options, properties by type, descriptions, instructions.
        // Replicator data is empty; Vue fetches per-type and caches.
        return [
            'type_options' => $typeOptions,
            'properties_by_type' => $propertiesByType,
            'type_descriptions' => $typeDescriptions,
            'property_instructions_by_type' => $propertyInstructionsByType,
            'property_replicator_sets_by_type' => [],
            'property_replicator_config_by_type' => [],
            'property_replicator_meta_by_type' => [],
        ];
    }

    /**
     * Augment the value for template/API output. When antlers config is true,
     * each property value string is parsed as Antlers with the parent (e.g.
     * entry) data as context so that e.g. {{ title }} resolves.
     *
     * Type is always returned as an array of strings (single type or MTE) for
     * consistent @type in JSON-LD (e.g. ["HotelRoom","Product"]).
     *
     * @param  mixed  $value  Raw value (array with type and properties)
     * @return array{type: array<int, string>, properties: array<string, string>}|mixed Value with type array and properties parsed when antlers enabled (raw value returned as-is when not a properties array)
     */
    #[\Override]
    public function augment(
        mixed $value
    ): mixed {
        // ---
        // Return as-is when not an array with properties
        // ---

        // Check if the value is not an array with properties.
        if (
            ! \is_array($value)
            || ! isset($value['properties'])
            || ! \is_array($value['properties'])
        ) {
            // Return the value as-is.
            return $value;
        }

        // ---
        // Normalize type to array for output (MTE / single type).
        // ---

        // Get the type from the value
        $typeRaw = $value['type'] ?? [];
        /** @var array<int, string>|string $type */
        $type = \is_array($typeRaw) || \is_string($typeRaw) ? $typeRaw : [];

        // Normalize the type to an array
        $typeArray = self::normalizeTypeToArray($type);

        // Reduce each property to a single string (group-like mode + value or legacy rows).
        /** @var array<string, mixed> $properties */
        $properties = $value['properties'];
        $strings = [];
        foreach ($properties as $key => $propValue) {
            $rows = self::propertyValueToReplicatorRows($propValue);

            // Initialize the use rows flag to true
            $useRows = true;

            // Check if the property value is group-like
            if (self::isPropertyGroupValue($propValue) && \is_array($propValue)) {
                // ---
                // Check if the property value is custom or inherit_custom and set the use rows
                // flag to true if so
                // ---
                // A missing mode falls back to a value that is in neither list (EmptyStringToNotEmpty is equivalent)
                // @pest-mutate-ignore: EmptyStringToNotEmpty
                $useRows = \in_array(
                    $propValue['mode'] ?? '',
                    ['custom', 'inherit_custom'],
                    true
                );
            }

            // Convert the property value to a single string
            $strings[$key] = $useRows ? self::replicatorRowsToEffectiveString($rows) : '';
        }

        // Return reduced strings when Antlers is disabled.
        if ($this->config('antlers') !== true) {
            return [
                'type' => $typeArray,
                'properties' => $strings,
            ];
        }

        // ---
        // Build context from parent (entry, etc.) raw data, excluding this field to avoid recursion
        // ---

        // Get the parent field.
        // Antlers parsing needs the field's own config, so this is never reached without a field (RemoveNullSafeOperator is equivalent)
        // @pest-mutate-ignore: RemoveNullSafeOperator
        $parent = $this->field()?->parent();

        // Check if the parent exists and has a data method.
        $context = [];
        if (
            \is_object($parent)
                && method_exists($parent, 'data')
        ) {
            $data = $parent->data();
            if (\is_object($data) && method_exists($data, 'all')) {
                /** @var array<array-key, mixed> $context */
                $context = $data->all();
            }
        }

        // Get the field handle.
        // Never reached without a field, as above (RemoveNullSafeOperator is equivalent)
        // @pest-mutate-ignore: RemoveNullSafeOperator
        $handle = $this->field()?->handle();

        // Check if the handle is not null and is not empty.
        // Only a non-string or empty handle reaches a mutated branch, and no Antlers
        // variable names such a key, so BooleanAndToBooleanOr and EmptyStringToNotEmpty
        // change no rendered value
        // @pest-mutate-ignore: BooleanAndToBooleanOr, EmptyStringToNotEmpty
        if (\is_string($handle) && $handle !== '') {
            // Unset the handle from the context.
            unset($context[$handle]);
        }

        // Parse each string as Antlers (user content: trusted = false).
        foreach ($strings as $key => $str) {
            $parsed = Parse::template(
                $str,
                $context,
                [],
                false
            );

            $strings[$key] = (string) $parsed;
        }

        return [
            'type' => $typeArray,
            'properties' => $strings,
        ];
    }

    /**
     * Normalize type to an array of type strings (single type or MTE).
     *
     * @param  array<int, string>|string  $type  Stored type (string legacy or array)
     * @return array<int, string> Non-empty strings only, deduped, indexed
     */
    public static function normalizeTypeToArray(
        array|string $type
    ): array {
        // Check if the type is a string
        if (\is_string($type)) {
            // If the type is a string, convert it to an array (the filter below drops an empty one)
            $type = [$type];
        }

        // Filter the type array to remove empty strings and return the unique values
        return array_values(
            array_unique(
                array_filter(
                    $type,
                    static fn (string $t): bool => $t !== ''
                )
            )
        );
    }

    /**
     * Validation rules: value must be an array.
     *
     * @return array<int, string>
     */
    #[\Override]
    public function rules(): array
    {
        return ['array'];
    }

    /**
     * Extra validation: when the field is required, require at least one
     * Schema.org type (type subfield must be non-empty array).
     *
     * @return array<string, string> Dot-notated field path => rule (e.g., handle.type => required)
     */
    #[\Override]
    public function extraRules(): array
    {
        $field = $this->field();
        if (! $field instanceof Field) {
            return [];
        }

        $config = $field->config();
        $required = $config['required'] ?? false;
        $validate = $config['validate'] ?? [];
        $validateHasRequired = \is_array($validate) && \in_array('required', $validate, true);
        if (! $required && ! $validateHasRequired) {
            return [];
        }

        $handle = $field->handle();
        // The handle is only concatenated, which casts a number the same way (RemoveStringCast is equivalent)
        // @pest-mutate-ignore: RemoveStringCast
        $handle = \is_scalar($handle) ? (string) $handle : '';

        return [$handle.'.type' => 'required|array|min:1'];
    }

    /**
     * Pre-process the value before validation so it is always an array with type and properties.
     *
     * Ensures the nested rule "handle.type" => required runs; avoids parent "array" failing on null.
     *
     * @param  mixed  $value  Raw value from request
     * @return array{type: array<int, string>, properties: array<string, mixed>}
     */
    #[\Override]
    public function preProcessValidatable(
        mixed $value
    ): array {
        if (! \is_array($value)) {
            return $this->defaultValue();
        }

        $typeRaw = $value['type'] ?? [];
        /** @var array<int, string>|string $type */
        $type = \is_array($typeRaw) || \is_string($typeRaw) ? $typeRaw : [];
        $typeArray = self::normalizeTypeToArray($type);
        /** @var array<string, mixed> $properties */
        $properties = isset($value['properties']) && \is_array($value['properties']) ? $value['properties'] : [];

        return [
            'type' => $typeArray,
            'properties' => $properties,
        ];
    }

    /**
     * Default value when the field is empty.
     *
     * @return array{type: array<int, string>, properties: array{}} Empty type array and properties map
     */
    #[\Override]
    public function defaultValue(): array
    {
        return [
            'type' => [],
            'properties' => [],
        ];
    }

    /**
     * Pre-process the value for the Vue component (ensure shape).
     * Converts legacy string type to single-element array; converts legacy
     * string property values to one replicator row so the Replicator UI can display them.
     *
     * @param  mixed  $data  Raw stored value (array or null)
     * @return array{type: array<int, string>, properties: array<array{mode: string, value: array<int, array<string, mixed>>}>} Normalized type array and properties (replicator rows or legacy string)
     */
    #[\Override]
    public function preProcess(
        mixed $data
    ): array {
        // Check if the data is an array.
        if (! \is_array($data)) {
            // Return the default value.
            return $this->defaultValue();
        }

        // Normalize type to array (legacy string or array).
        $typeRawValue = $data['type'] ?? [];
        /** @var array<int, string>|string $typeRaw */
        $typeRaw = \is_array($typeRawValue) || \is_string($typeRawValue) ? $typeRawValue : [];
        $type = self::normalizeTypeToArray($typeRaw);

        // Get the properties from the data.
        /** @var array<string, mixed> $rawProperties */
        $rawProperties = (
            isset($data['properties'])
                && \is_array($data['properties'])
        ) ? $data['properties']
            : [];

        // Normalize each property to group-like shape (mode + value) for the Vue component.
        $properties = [];
        foreach ($rawProperties as $key => $propValue) {
            $properties[$key] = $this->normalizePropertyToGroupShape($propValue);
        }

        return [
            'type' => $type,
            'properties' => $properties,
        ];
    }

    /**
     * Whether the property value is group-like (has "mode" key).
     *
     * @param  mixed  $value  Raw property value
     */
    public static function isPropertyGroupValue(mixed $value): bool
    {
        return \is_array($value)
            && \array_key_exists('mode', $value);
    }

    /**
     * Get replicator rows from a property value (group-like or legacy).
     *
     * @param  mixed  $value  Group-like { mode, value } or legacy rows/string
     * @return array<int, array<string, mixed>>
     */
    public static function propertyValueToReplicatorRows(mixed $value): array
    {
        if (self::isPropertyGroupValue($value) && \is_array($value)) {
            $inner = $value['value'] ?? null;

            /** @var array<int, array<string, mixed>> $rows */
            $rows = \is_array($inner) ? $inner : [];

            return $rows;
        }

        if (\is_array($value)) {
            /** @var array<int, array<string, mixed>> $value */
            return $value;
        }

        if (\is_string($value) && $value !== '') {
            return [
                [
                    '_id' => Str::random(9),
                    'type' => 'text_short',
                    'value' => $value,
                    'enabled' => true,
                ],
            ];
        }

        return [];
    }

    /**
     * Normalize raw property value to group-like shape (mode + value) for CP.
     *
     * Legacy string or replicator rows become mode 'custom' with that value.
     * Valid modes include inherit, disable, custom, and inherit_custom.
     *
     * @param  mixed  $propValue  Stored value (group-like, array of rows, or string)
     * @return array{mode: string, value: array<int, array<string, mixed>>}
     */
    private function normalizePropertyToGroupShape(mixed $propValue): array
    {
        if (self::isPropertyGroupValue($propValue) && \is_array($propValue)) {
            $modeRaw = $propValue['mode'] ?? 'inherit';
            $mode = \is_string($modeRaw) ? $modeRaw : 'inherit';
            $valueRaw = $propValue['value'] ?? [];
            /** @var array<int, array<string, mixed>> $value */
            $value = \is_array($valueRaw) ? $valueRaw : [];

            return ['mode' => $mode, 'value' => $value];
        }

        if (\is_string($propValue)) {
            return [
                'mode' => 'custom',
                'value' => $this->legacyStringToReplicatorRows($propValue),
            ];
        }

        if (\is_array($propValue)) {
            /** @var array<int, array<string, mixed>> $propValue */
            return [
                'mode' => 'custom',
                'value' => $propValue,
            ];
        }

        return ['mode' => 'inherit', 'value' => []];
    }

    /**
     * Convert a legacy string property value to one replicator row.
     *
     * @param  string  $str  Stored string (e.g., from before replicator was used)
     * @return array<int, array{_id: string, type: string, value: string, enabled: bool}>
     */
    private function legacyStringToReplicatorRows(string $str): array
    {
        if ($str === '') {
            return [];
        }

        return [
            [
                '_id' => Str::random(9),
                'type' => 'text_short',
                'value' => $str,
                'enabled' => true,
            ],
        ];
    }

    /**
     * Process the value from the Vue component for storage.
     * Accepts type as array of strings (or legacy single string); accepts
     * property values as string (legacy) or replicator rows (array).
     *
     * @param  mixed  $data  Value from the CP (array with type and properties)
     * @return array{type: array<int, string>, properties: array<array{mode: string, value: array<int, array<string, mixed>>}>} Normalized value for storage
     */
    #[\Override]
    public function process(
        mixed $data
    ): array {
        // Check if the data is an array.
        if (! \is_array($data)) {
            // Return the default value.
            return $this->defaultValue();
        }

        // Normalize type to array for storage (Vue sends array; legacy may send string).
        $typeRawValue = $data['type'] ?? [];
        /** @var array<int, string>|string $typeRaw */
        $typeRaw = \is_array($typeRawValue) || \is_string($typeRawValue) ? $typeRawValue : [];
        $type = self::normalizeTypeToArray($typeRaw);

        // Get the properties from the data (group-like or legacy).
        /** @var array<string, mixed> $rawProperties */
        $rawProperties = (
            isset($data['properties'])
                && \is_array($data['properties'])
        ) ? $data['properties']
            : [];

        // Normalize each property to group-like shape for storage.
        $properties = [];
        foreach ($rawProperties as $key => $propValue) {
            // Normalize the property value to group-like shape for storage
            $properties[$key] = $this->normalizePropertyToGroupShape($propValue);
        }

        return [
            'type' => $type,
            'properties' => $properties,
        ];
    }

    /**
     * Reduce replicator rows to a single string (first enabled row's value).
     *
     * @param  array<int, array<string, mixed>>|string  $propValue  Replicator rows or legacy string
     */
    public static function replicatorRowsToEffectiveString(
        array|string $propValue
    ): string {
        if (\is_string($propValue)) {
            return $propValue;
        }

        foreach ($propValue as $row) {
            $enabled = $row['enabled'] ?? true;
            if (! $enabled) {
                continue;
            }

            $val = $row['value'] ?? null;
            if ($val !== null && $val !== '') {
                return \is_scalar($val) ? (string) $val : (string) json_encode(
                    $val
                );
            }
        }

        return '';
    }
}
