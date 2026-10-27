<?php

declare(strict_types=1);

namespace App\Services\SchemaObject;

use App\Enums\SchemaDataType;
use App\Enums\SchemaType;
use App\Support\ReplicatorSetMeta;
use App\Support\ReplicatorVueSet;
use App\Support\RequestAttributeMemo;
use Illuminate\Support\Collection;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fields\Fieldtype;

/**
 * Builds Statamic replicator set configs from Schema.org property range types
 * (range_includes). Used by the schema_object fieldtype to show
 * type-appropriate inputs per property (e.g., number, date, entries, text).
 *
 * @see https://schema.org/
 */
class SchemaPropertyReplicatorSets
{
    /**
     * Request attribute bag for memoized replicator set definitions per type name.
     */
    private const string SINGLE_TYPE_MEMO_BAG = 'schema_property_replicator_sets_single_type';

    /**
     * Default collection for Thing/entries set when not in config.
     */
    private const string DEFAULT_ENTRIES_COLLECTION = 'schema_objects';

    /**
     * Replicator sets config (main group) for the given range type names.
     * Adds one "Antlers" set for all properties. Set handles are unique per type.
     *
     * @param  list<string>  $rangeTypes  Schema.org range type names (e.g., Text, URL, Thing)
     * @return array<string, mixed> Replicator config: main => [ display, sets => [ handle => set config ] ]
     */
    public function setsForRangeTypes(
        array $rangeTypes
    ): array {
        $sets = [];

        foreach ($rangeTypes as $typeName) {
            $typeSets = $this->setsForSingleType($typeName);
            foreach ($typeSets as $handle => $config) {
                $sets[$handle] = [
                    ['handle' => $handle],
                    ...$config,
                ];
            }
        }

        // Add Antlers set for all value types (code, mode html)
        $sets['antlers'] = [
            ['handle' => 'antlers'],
            ...$this->antlersSet(),
        ];

        return [
            'main' => [
                'display' => __('Main'),
                'sets' => $sets,
            ],
        ];
    }

    /**
     * Build Replicator meta (new + defaults) for each set so the Vue component
     * can add sets without AJAX. Uses Statamic Fields to produce field meta.
     *
     * @param  array<string, mixed>  $setsConfig  Return value of setsForRangeTypes()
     * @return array{new: array<string, mixed>, defaults: array<string, array<string, mixed>>}
     */
    public function metaForSets(array $setsConfig): array
    {
        return ReplicatorSetMeta::forSets($setsConfig, 'schema_property_value');
    }

    /**
     * Replicator config.sets format for Vue: array of groups, each with
     * display and sets (array of set configs with handle, display, fields).
     *
     * @param  array<string, mixed>  $setsConfig  Return value of setsForRangeTypes()
     * @return array<int, array{display: string, sets: array<int, array<int|string, mixed>>}>
     */
    public function configSetsForVue(array $setsConfig): array
    {
        $main = $setsConfig['main'] ?? null;
        if (! \is_array($main) || ! isset($main['sets']) || ! \is_array($main['sets'])) {
            return [];
        }

        /** @var array<int, array<int|string, mixed>> $setsArray */
        $setsArray = [];
        /** @var array<int|string, mixed> $mainSets */
        $mainSets = $main['sets'];
        foreach ($mainSets as $handle => $set) {
            $set = \is_array($set) ? $set : [];
            // Flatten fields so Vue Field gets config.type (expects { handle, type, display, ... }, not { handle, field: { type, ... } }).
            $fields = [];
            $setFields = isset($set['fields']) && \is_array($set['fields']) ? $set['fields'] : [];
            foreach ($setFields as $field) {
                $field = \is_array($field) ? $field : [];
                $fieldConfig = isset($field['field']) && \is_array($field['field']) ? $field['field'] : [];
                $fields[] = [
                    'handle' => $field['handle'] ?? '',
                    ...$fieldConfig,
                ];
            }

            // Carry the resolved handle once, at the top level and at index 0
            $setsArray[] = ReplicatorVueSet::fromSet($handle, $set, $fields);
        }

        // __() already returns text
        // @pest-mutate-ignore: RemoveStringCast
        $display = (string) __('Main');
        if (isset($main['display']) && \is_scalar($main['display'])) {
            $display = (string) $main['display'];
        }

        return [
            [
                'display' => $display,
                'sets' => $setsArray,
            ],
        ];
    }

    /**
     * One or more replicator set configs for a single schema.org range type.
     *
     * Request-memoized; see `computeSetsForSingleType` for the returned shape.
     *
     * @param  string  $typeName  Schema.org type (e.g., Text, Number, Person)
     * @return array<string, array{display: string, icon: string, fields: array<int, array<string, mixed>>}>
     */
    private function setsForSingleType(string $typeName): array
    {
        $entriesCollection = $this->entriesCollection();

        $memoKey = $typeName."\x1e".$entriesCollection;

        /** @var array<string, array{display: string, icon: string, fields: array<int, array<string, mixed>>}> $resolved */
        $resolved = RequestAttributeMemo::remember(
            self::SINGLE_TYPE_MEMO_BAG,
            $memoKey,
            fn (): array => $this->computeSetsForSingleType($typeName, $entriesCollection)
        );

        unset(
            $entriesCollection,
            $memoKey
        );

        return $resolved;
    }

    /**
     * Build replicator set map for one schema.org range type name (uncached logic).
     *
     * @param  string  $typeName  Schema.org type (e.g., Text, Number, Person)
     * @param  string  $entriesCollection  Collection handle for an entries set, the one the memo key carries
     * @return array<string, array{display: string, icon: string, fields: array<int, array<string, mixed>>}>
     */
    private function computeSetsForSingleType(string $typeName, string $entriesCollection): array
    {
        $schemaType = SchemaType::tryFrom($typeName);
        if ($schemaType !== null) {
            // Enumeration (ancestors contain Enumeration) → select with members
            if ($this->isEnumerationType($typeName)) {
                return [$this->handleForEnumeration($typeName) => $this->enumerationSet($typeName)];
            }

            // Thing or subclass → entries set
            return [$this->handleForThing($typeName) => $this->thingSet($typeName, $entriesCollection)];
        }

        // DataType or subclass (SchemaDataType)
        $dataType = SchemaDataType::tryFrom($typeName);
        if ($dataType !== null) {
            return $this->setsForDataType($dataType);
        }

        // Unknown type: treat as text
        return ['text_'.$this->slug($typeName) => $this->textSet()];
    }

    /**
     * Replicator sets for a SchemaDataType enum case.
     *
     * @return array<string, array{display: string, icon: string, fields: array<int, array<string, mixed>>}>
     */
    private function setsForDataType(SchemaDataType $dataType): array
    {
        return match ($dataType) {
            SchemaDataType::Number => ['number' => $this->numberSet()],
            SchemaDataType::Float => ['float' => $this->floatSet()],
            SchemaDataType::Integer => ['integer' => $this->integerSet()],
            SchemaDataType::Boolean => ['boolean' => $this->booleanSet()],
            SchemaDataType::DataTypeFalse => ['false' => $this->falseSet()],
            SchemaDataType::DataTypeTrue => ['true' => $this->trueSet()],
            SchemaDataType::DateTime => ['datetime' => $this->dateTimeSet()],
            SchemaDataType::Date => ['date' => $this->dateSet()],
            SchemaDataType::Text => [
                'text_short' => $this->textSet(),
                'text_long' => $this->textareaSet(),
            ],
            SchemaDataType::CssSelectorType => ['css_selector' => $this->cssSelectorSet()],
            SchemaDataType::PronounceableText => [
                'pronounceable_text' => $this->pronounceableTextSet(),
            ],
            SchemaDataType::URL => ['url' => $this->urlSet()],
            SchemaDataType::XPathType => ['xpath' => $this->xpathSet()],
            SchemaDataType::Time => ['time' => $this->timeSet()],
            default => ['text_'.$this->slug($dataType->value) => $this->textSet()],
        };
    }

    private function handleForThing(string $typeName): string
    {
        return 'thing_'.$this->slug($typeName);
    }

    private function handleForEnumeration(string $typeName): string
    {
        return 'enumeration_'.$this->slug($typeName);
    }

    private function slug(string $value): string
    {
        return str_replace([' ', '-'], '_', strtolower($value));
    }

    /**
     * Check whether the schema type is an enumeration (has Enumeration in ancestors).
     */
    private function isEnumerationType(string $typeName): bool
    {
        $ancestors = SchemaType::ancestorsOf($typeName);

        // Ancestors are type names, never numeric strings, so a loose match is the same
        // @pest-mutate-ignore: TrueToFalse
        return \in_array('Enumeration', $ancestors, true);
    }

    /**
     * Replicator set for a Schema.org enumeration type: select field with options
     * from the type's members (SchemaTypeInfo::members).
     *
     * @param  string  $typeName  Schema.org enumeration type name (e.g., ActionStatusType)
     * @return array{display: string, icon: string, fields: array<int, array<string, mixed>>}
     */
    private function enumerationSet(string $typeName): array
    {
        $members = SchemaType::membersFor($typeName);
        $options = [];
        foreach ($members as $memberName) {
            $options[$memberName] = $this->labelForEnumerationMember($memberName);
        }

        return [
            'display' => $typeName,
            'icon' => 'select',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'select',
                        'display' => $typeName,
                        'options' => $options,
                        'clearable' => true,
                        'multiple' => true,
                    ],
                ],
            ],
        ];
    }

    /**
     * Human-readable label for an enumeration member value (e.g., ActiveActionStatus → Active Action Status).
     */
    private function labelForEnumerationMember(string $memberName): string
    {
        $spaced = preg_replace('/([A-Z])/', ' $1', $memberName) ?? $memberName;

        return trim($spaced);
    }

    /**
     * Collection handle for entries sets: the configured one as text, or the default when the setting is not a
     * scalar. The memo key and the entries set both read this value, so a set is never served for another setting.
     */
    private function entriesCollection(): string
    {
        $entriesCollectionConfig = config(
            'schema_object.entries_collection',
            self::DEFAULT_ENTRIES_COLLECTION
        );

        return \is_scalar($entriesCollectionConfig)
            ? (string) $entriesCollectionConfig
            : self::DEFAULT_ENTRIES_COLLECTION;
    }

    /**
     * @param  string  $typeName  Schema.org type the entries must have
     * @param  string  $collection  Collection handle the entries field reads
     * @return array{display: string, icon: string, fields: array<int, array<string, mixed>>}
     */
    private function thingSet(string $typeName, string $collection): array
    {
        // Scope handle: EntriesBySchemaType resolves type + descendants internally.
        $scopeHandle = 'entries_by_schema_type_'.$this->slug($typeName);

        // Validation rule: pass only the root type; EntrySchemaTypeIn expands to
        // type + descendants internally (keeps config small, avoids 414 in URLs).
        $validateRule = 'entry_schema_type_in:'.$typeName;

        return [
            'display' => $typeName,
            'icon' => 'fieldtype-entries',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'entries',
                        'display' => $typeName,
                        'collections' => [$collection],
                        'query_scopes' => [$scopeHandle],
                        'validate' => [$validateRule],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{display: string, icon: string, fields: array<int, array<string, mixed>>}
     */
    private function numberSet(): array
    {
        return [
            'display' => __('Number'),
            'icon' => 'fieldtype-float',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'float',
                        'display' => __('Number'),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{display: string, icon: string, fields: array<int, array<string, mixed>>}
     */
    private function floatSet(): array
    {
        return [
            'display' => __('Decimal Number'),
            'icon' => 'fieldtype-float',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'float',
                        'display' => __('Decimal Number'),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{display: string, icon: string, fields: array<int, array<string, mixed>>}
     */
    private function integerSet(): array
    {
        return [
            'display' => __('Integer'),
            'icon' => 'fieldtype-integer',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'integer',
                        'display' => __('Integer'),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{display: string, icon: string, fields: array<int, array<string, mixed>>}
     */
    private function booleanSet(): array
    {
        return [
            'display' => __('True/False'),
            'icon' => 'fieldtype-toggle',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'true_false',
                        'display' => __('True/False'),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{display: string, icon: string, fields: array<int, array<string, mixed>>}
     */
    private function falseSet(): array
    {
        return [
            'display' => __('False'),
            'icon' => 'fieldtype-toggle',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'toggle',
                        'display' => __('False'),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{display: string, icon: string, fields: array<int, array<string, mixed>>}
     */
    private function trueSet(): array
    {
        return [
            'display' => __('True'),
            'icon' => 'fieldtype-toggle',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'toggle',
                        'display' => __('True'),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{display: string, icon: string, fields: array<int, array<string, mixed>>}
     */
    private function dateTimeSet(): array
    {
        return [
            'display' => __('Date and Time'),
            'icon' => 'fieldtype-date',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'date',
                        'display' => __('Date and Time'),
                        'time_enabled' => true,
                        'format' => 'c',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{display: string, icon: string, fields: array<int, array<string, mixed>>}
     */
    private function dateSet(): array
    {
        return [
            'display' => __('Date'),
            'icon' => 'fieldtype-date',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'date',
                        'display' => __('Date'),
                        'time_enabled' => false,
                        'format' => 'c',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{display: string, icon: string, fields: array<int, array<string, mixed>>}
     */
    private function textSet(): array
    {
        return [
            'display' => __('Text'),
            'icon' => 'fieldtype-text',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'text',
                        'display' => __('Text'),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{display: string, icon: string, fields: array<int, array<string, mixed>>}
     */
    private function textareaSet(): array
    {
        return [
            'display' => __('Textarea'),
            'icon' => 'fieldtype-textarea',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'textarea',
                        'display' => __('Textarea'),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{display: string, icon: string, fields: array<int, array<string, mixed>>}
     */
    private function cssSelectorSet(): array
    {
        return [
            'display' => __('CSS Selector'),
            'icon' => 'code-inline',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'css_selector',
                        'display' => __('CSS Selector'),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{display: string, icon: string, fields: array<int, array<string, mixed>>}
     */
    private function pronounceableTextSet(): array
    {
        return [
            'display' => __('Pronounceable Text'),
            'icon' => 'fieldtype-text',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'text',
                        'display' => __('Pronounceable Text'),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{display: string, icon: string, fields: array<int, array<string, mixed>>}
     */
    private function urlSet(): array
    {
        return [
            'display' => __('URL'),
            'icon' => 'web',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'url',
                        'display' => __('URL'),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{display: string, icon: string, fields: array<int, array<string, mixed>>}
     */
    private function xpathSet(): array
    {
        return [
            'display' => __('XPath'),
            'icon' => 'code-inline',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'xpath',
                        'display' => __('XPath'),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{display: string, icon: string, fields: array<int, array<string, mixed>>}
     */
    private function timeSet(): array
    {
        return [
            'display' => __('Time'),
            'icon' => 'fieldtype-time',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'time',
                        'display' => __('Time'),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{display: string, icon: string, fields: array<int, array<string, mixed>>}
     */
    private function antlersSet(): array
    {
        return [
            'display' => __('Antlers'),
            'icon' => 'fieldtype-code',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'code',
                        'display' => __('Antlers'),
                        'mode' => 'html',
                        'mode_selectable' => false,
                    ],
                ],
            ],
        ];
    }
}
