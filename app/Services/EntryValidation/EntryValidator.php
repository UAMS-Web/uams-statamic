<?php

declare(strict_types=1);

namespace App\Services\EntryValidation;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fields\Validator;
use Throwable;

/**
 * Validates an entry against its blueprint the way the Control Panel does on save,
 * and reports which fields are visible under the blueprint's display conditions.
 *
 * Extracted from the Incomplete Published Entries dashboard widget (#275), so the
 * widget, its scheduled scan, and any other caller share one implementation of the
 * Control Panel's validation and condition logic.
 */
class EntryValidator
{
    /**
     * @param  CustomConditionEvaluator  $customConditions  Ports of the editor's custom field conditions
     */
    public function __construct(private readonly CustomConditionEvaluator $customConditions) {}

    /**
     * Validate an entry against its blueprint the way the Control Panel validates a save.
     *
     * Only the fields visible under the blueprint's display conditions are validated,
     * so a field an `if` or `unless` condition hides is never reported as missing.
     *
     * @param  StatamicEntry  $entry  Entry being checked against the blueprint
     * @param  Blueprint  $blueprint  Blueprint that defines fields, conditions, and rules
     * @return EntryValidationResult Validation errors, attribute labels, and the visible field handles
     */
    public function validate(
        StatamicEntry $entry,
        Blueprint $blueprint
    ): EntryValidationResult {
        // Gather raw and computed entry data for validation
        $values = $this->extractValues($entry);

        // Set blueprint parent so field resolution works correctly. The collection
        // shares one blueprint instance across its entries, so it may still be
        // parented to whichever entry resolved it last.
        $blueprint->setParent($entry);

        // Coerce legacy or malformed replicator shapes so Statamic preProcess does not throw
        $values = $this->sanitizeMalformedReplicatorValues($blueprint, $values);

        // Preprocess field values (same as CP)
        try {
            $blueprintFields = $blueprint->fields();

            /** @var Fields $fieldsWithValues */
            $fieldsWithValues = $blueprintFields->addValues($values);

            /** @var Fields $fields */
            $fields = $fieldsWithValues->preProcess();
        } catch (Throwable $throwable) {
            // Resolve collection for log context (may be null)
            /** @var \Statamic\Entries\Collection|null $entryCollection */
            $entryCollection = $entry->collection();

            Log::warning(
                'EntryValidator: field preProcess failed; treating entry as failing validation',
                [
                    'message' => $throwable->getMessage(),
                    'entryId' => $entry->id(),
                    'collection' => $entryCollection?->handle(),
                    'exception' => $throwable::class,
                ]
            );

            // No field values could be read, so no field can be judged visible
            return new EntryValidationResult(
                errors: new MessageBag([
                    '__pre_process__' => [
                        __('Published entry data could not be read for validation. Fix malformed fields in the entry editor.'),
                    ],
                ]),
                attributes: [
                    '__pre_process__' => __('Entry data'),
                ],
                visibleFields: [],
            );
        }

        // Merge title, slug, and published into field values
        /** @var Collection<string, mixed> $fieldValues */
        $fieldValues = $fields->values();

        $mergedValues = $fieldValues->merge([
            'title' => $entry->value('title'),
            'slug' => $entry->slug(),
            'published' => $entry->published(),
        ])->all();

        // Only validate fields that are visible per their conditions (matches CP form submission).
        $visible = $this->filterToVisibleFields($mergedValues, $blueprint);
        $mergedValues = $visible['values'];

        // ---
        // Use merged values for validation without re-running preProcess (avoids Date fieldtype
        // expecting raw input and receiving preProcessed output, e.g. array without 'start').
        // ---
        $blueprintFields = $blueprint->fields();

        /** @var Fields $fields */
        $fields = $blueprintFields->addValues($mergedValues);

        // Resolve collection for validator replacement context
        /** @var \Statamic\Entries\Collection $collection */
        $collection = $entry->collection();

        // Build validator with entry context for replacement rules
        /** @var Validator $fieldsValidator */
        $fieldsValidator = $fields->validator();

        /** @var Validator $statamicValidator */
        $statamicValidator = $fieldsValidator
            ->withReplacements([
                'id' => $entry->id(),
                'collection' => $collection->handle(),
                'site' => $entry->locale(),
            ]);

        // Get underlying Laravel validator instance
        /** @var \Illuminate\Validation\Validator $laravelValidator */
        $laravelValidator = $statamicValidator->validator();

        // Capture validation errors and field labels for messages
        $errors = $laravelValidator->errors();

        // Get attribute names for error message display
        /** @var array<string, string> $attributes */
        $attributes = $statamicValidator->attributes();

        // Free locals before return
        unset(
            $fields,
            $laravelValidator,
            $mergedValues,
            $statamicValidator,
            $values
        );

        // Return errors, attribute names for display, and the visible field handles
        return new EntryValidationResult(
            errors: $errors,
            attributes: $attributes,
            visibleFields: $visible['visible'],
        );
    }

    /**
     * Normalize replicator field values that are not arrays of set rows (each row needs a string `type`).
     * Legacy or imported content sometimes stores plain strings or lists of strings, which would make
     * Statamic's Replicator fieldtype throw during preProcess.
     *
     * @param  array<string, mixed>  $values  Raw merged entry values keyed by field handle (dot paths supported)
     * @return array<string, mixed> Same array with invalid replicator shapes replaced by valid empty lists or rows
     */
    public function sanitizeMalformedReplicatorValues(
        Blueprint $blueprint,
        array $values
    ): array {
        foreach ($blueprint->fields()->all() as $field) {
            /** @var Field $field */
            if ($field->type() !== 'replicator') {
                continue;
            }

            $handle = $this->fieldHandle($field);

            if (! Arr::has($values, $handle)) {
                continue;
            }

            $raw = Arr::get($values, $handle);

            // Anything that is not an array (null, '', a string, another scalar, an
            // object) is not a list of rows
            if (! \is_array($raw)) {
                Arr::set($values, $handle, []);

                continue;
            }

            $normalized = array_values(array_filter(
                $raw,
                static fn ($row): bool => \is_array($row)
                    && isset($row['type'])
                    && \is_string($row['type'])
            ));

            Arr::set($values, $handle, $normalized);
        }

        /** @var array<string, mixed> $values */
        return $values;
    }

    /**
     * Filter merged values to only include keys for fields that pass their display conditions.
     * Matches CP behavior where only visible fields are submitted and validated.
     *
     * @param  array<string, mixed>  $mergedValues  Preprocessed values merged with title, slug, and published
     * @param  Blueprint  $blueprint  Blueprint whose fields drive condition evaluation
     * @return array{values: array<string, mixed>, visible: list<string>} Values limited to visible field keys
     *                                                                    (and replicator rows filtered per set
     *                                                                    conditions), plus the visible field handles
     */
    private function filterToVisibleFields(
        array $mergedValues,
        Blueprint $blueprint
    ): array {
        // Resolve all blueprint fields with current values
        $blueprintFields = $blueprint->fields();

        /** @var Fields $fieldsWithValues */
        $fieldsWithValues = $blueprintFields->addValues($mergedValues);

        /** @var Collection<string, Field> $fields */
        $fields = $fieldsWithValues->all();

        // Initialize array to store handles of fields that pass their display conditions
        $visibleHandles = [];

        // Hold filtered replicator rows (visible fields only per row)
        $filteredReplicatorValues = [];

        // Loop through each blueprint field and determine visibility
        foreach ($fields as $field) {
            // Get field handle for value lookup and key retention
            $handle = $this->fieldHandle($field);

            // Get this field's value from merged entry data
            $value = Arr::get($mergedValues, $handle);

            // ---
            // Replicator: only include when the field's own conditions pass (e.g. title_options equals hero);
            // then filter each row by that set's field conditions.
            // ---

            // Check if the field is a replicator and has an array value
            if (
                $field->type() === 'replicator'
                && \is_array($value)
            ) {
                // Check if the replicator field's own conditions pass (e.g. title_options equals hero)
                if ($this->fieldPassesConditions($field, $mergedValues)) {
                    /** @var array<int, array<string, mixed>> $value */
                    // Filter each replicator row to visible set-field keys only
                    $filteredReplicatorValues[$handle] = $this->filterReplicatorRowsToVisibleFields(
                        $value,
                        $field
                    );

                    // Mark this replicator as visible (filtered rows already applied)
                    $visibleHandles[] = $handle;
                }

                // Skip to next field (replicator branch already handled)
                continue;
            }

            // Check if the field passes its display conditions
            if ($this->fieldPassesConditions($field, $mergedValues)) {
                // Mark this field as visible
                $visibleHandles[] = $handle;
            }
        }

        // ---
        // Always keep title, slug, published; keep any key that is a visible field.
        // ---

        // Build list of keys to retain in merged values
        $allowedKeys = array_merge(
            $visibleHandles,
            ['title', 'slug', 'published']
        );

        // Restrict merged values to allowed keys only
        $filtered = Arr::only($mergedValues, $allowedKeys);

        // ---
        // Apply filtered replicator values (each row had hidden fields removed).
        // ---

        // Apply each replicator's filtered rows into the result
        foreach ($filteredReplicatorValues as $handle => $rows) {
            // Apply this replicator's filtered rows to the result
            $filtered[$handle] = $rows;
        }

        // Free locals before return
        unset($fields, $filteredReplicatorValues);

        // Return merged values restricted to visible fields, and the visible handles
        /** @var array<string, mixed> $filtered */
        return ['values' => $filtered, 'visible' => $visibleHandles];
    }

    /**
     * For a replicator field value (array of rows), filter each row to only include keys for
     * fields that pass their display conditions within that row. Recursively filters nested
     * replicators (e.g. items in a stacked block) so conditional fields like button_url
     * are only validated when visible.
     *
     * @param  array<int, array<string, mixed>>  $rows  Replicator rows (type, enabled, set field values)
     * @param  Field  $field  Parent replicator field from the blueprint
     * @return array<int, array<string, mixed>> Rows with keys removed for set fields that fail display conditions (nested
     *                                          replicators filtered recursively)
     */
    public function filterReplicatorRowsToVisibleFields(array $rows, Field $field): array
    {
        // Get fieldtype (e.g. Replicator) to resolve set fields per row
        $fieldtype = $field->fieldtype();

        // ---
        // If fieldtype has no fields() method, return rows unchanged
        // ---

        // Check if the fieldtype does not support resolving set fields. Only replicator
        // fields reach this method, and a replicator fieldtype always has fields(), so
        // the guard never returns and `&&` is indistinguishable from `||`.
        // @pest-mutate-ignore: BooleanOrToBooleanAnd
        if (! is_object($fieldtype) || ! method_exists($fieldtype, 'fields')) {
            // Return rows unchanged when fieldtype has no fields
            return $rows;
        }

        // Initialize array to store filtered rows
        $filtered = [];

        // Loop through each replicator row and filter to visible set-field keys
        foreach ($rows as $index => $row) {
            // ---
            // Pass through rows without set type
            // ---

            // Check if the row has no set type
            if (! isset($row['type'])) {
                // Pass row through unchanged (no set type)
                $filtered[$index] = $row;

                // Skip to next row
                continue;
            }

            // Resolve fields for this set type
            $setType = $row['type'];

            // Get all set fields for this type and index
            /** @var Fields $setFieldsCollection */
            $setFieldsCollection = $fieldtype->fields($setType, $index);

            /** @var Collection<string, Field> $setFields */
            $setFields = $setFieldsCollection->all();

            // Always keep type, _id, and enabled; add handles that pass conditions
            $visibleHandles = ['type', '_id', 'enabled'];

            // Loop through set fields and collect handles that pass conditions
            foreach ($setFields as $setField) {
                // Check if this set field passes its conditions for this row
                if ($this->fieldPassesConditions($setField, $row)) {
                    // Add this field's handle to visible set
                    $visibleHandles[] = $this->fieldHandle($setField);
                }
            }

            // Keep only visible keys in this row
            $filteredRow = Arr::only($row, $visibleHandles);

            // Recursively filter nested replicator values (e.g. items in stacked block).
            foreach ($setFields as $setField) {
                // Get field handle for row value lookup
                $handle = $this->fieldHandle($setField);

                // ---
                // Only process visible replicator fields
                // ---

                // Check if this handle is not in the visible set (skip non-visible fields).
                // Strict, so numeric-string handles such as 10 and 1e1 stay distinct.
                if (! \in_array($handle, $visibleHandles, true)) {
                    // Skip to next set field (this one is not visible)
                    continue;
                }

                // Get this field's value from the row
                $nestedValue = $row[$handle] ?? null;

                // ---
                // Recursively filter nested replicator rows
                // ---

                // Check if this set field is a replicator and has an array value
                if (
                    $setField->type() === 'replicator'
                    && \is_array($nestedValue)
                ) {
                    /** @var array<int, array<string, mixed>> $nestedValue */
                    // Recursively filter nested replicator and assign result
                    $filteredRow[$handle] = $this->filterReplicatorRowsToVisibleFields(
                        $nestedValue,
                        $setField
                    );
                }
            }

            // Store filtered row at original index
            $filtered[$index] = $filteredRow;
        }

        // Return filtered rows (visible set-field keys only per row)
        /** @var array<int, array<string, mixed>> $filtered */
        return $filtered;
    }

    /**
     * Return a field's handle as a string.
     *
     * @param  Field  $field  Blueprint or set field
     * @return string The field handle
     */
    public function fieldHandle(Field $field): string
    {
        // A blueprint can declare a numeric handle, and Field stores it unconverted
        $handleValue = $field->handle();

        return \is_scalar($handleValue) ? (string) $handleValue : '';
    }

    /**
     * Evaluate whether a field passes its display conditions (server-side, matches CP logic).
     *
     * @param  Field  $field  Blueprint field to evaluate
     * @param  array<string, mixed>  $values  Current context values (merged entry data)
     * @return bool True when the field is visible per its conditions
     */
    public function fieldPassesConditions(Field $field, array $values): bool
    {
        // Get display conditions from field config (if/unless/hide_when)
        $conditions = $field->conditions();

        // Loop through each condition block (if, if_any, unless, hide_when)
        foreach ($conditions as $key => $nested) {
            // ---
            // Skip non-array condition values
            // ---

            // Field::conditions() only returns the named string keys, so the cast is
            // a no-op that exists for phpstan.
            // @pest-mutate-ignore: RemoveStringCast
            $blockKey = (string) $key;

            // A block that is one string is a custom condition with no target field,
            // `custom ` prefix optional, which the editor evaluates on the sibling
            // values alone; a name it has no function for reads as hidden (#508)
            if (\is_string($nested)) {
                $passes = $this->evaluateCustomCondition($this->fieldHandle($field), (string) preg_replace('/^custom /', '', $nested), null, $values);

                // Invert for unless/hide_when, as the array form does below
                $showOnPass = ! str_contains($blockKey, 'unless') && ! str_contains($blockKey, 'hide_when');

                // Field is hidden when this block fails
                if (($showOnPass ? $passes : ! $passes) === false) {
                    return false;
                }

                continue;
            }

            // Check if the condition value is not an array (skip malformed)
            if (! \is_array($nested)) {
                // Skip malformed condition block
                continue;
            }

            /** @var array<string, string> $nested */
            // Detect if this block is if_any (OR) vs if (AND)
            $passOnAny = str_contains($blockKey, '_any');

            // Show when conditions pass vs show when they fail (unless/hide_when)
            $showOnPass = ! str_contains($blockKey, 'unless') && ! str_contains($blockKey, 'hide_when');

            // Evaluate block: any condition passes (OR) or all conditions pass (AND)
            $passes = $passOnAny
                ? $this->passesAnyConditions($nested, $values)
                : $this->passesAllConditions($nested, $values);

            // Invert for unless/hide_when
            $result = $showOnPass ? $passes : ! $passes;

            // Check if this condition block failed (field should be hidden)
            if (! $result) {
                // Field is hidden when this block fails
                return false;
            }
        }

        // All condition blocks passed; field is visible
        return true;
    }

    /**
     * Return true only when every condition in the map passes against the given values.
     *
     * @param  array<string, string>  $conditions  Map of field handle => rhs (e.g. 'not empty')
     * @param  array<string, mixed>  $values  Current context values
     * @return bool True when all conditions pass
     */
    private function passesAllConditions(array $conditions, array $values): bool
    {
        return array_all($conditions, fn (string $rhs, string $fieldHandle): bool => $this->evaluateCondition($fieldHandle, $rhs, $values));
    }

    /**
     * Return true when at least one condition in the map passes against the given values.
     *
     * @param  array<string, string>  $conditions  Map of field handle => rhs
     * @param  array<string, mixed>  $values  Current context values
     * @return bool True when at least one condition passes
     */
    private function passesAnyConditions(array $conditions, array $values): bool
    {
        return array_any($conditions, fn (string $rhs, string $fieldHandle): bool => $this->evaluateCondition($fieldHandle, $rhs, $values));
    }

    /**
     * Evaluate a single condition (field handle => rhs string) against values.
     *
     * @param  string  $fieldHandle  Field handle for the left-hand value
     * @param  string  $rhs  Right-hand side (e.g. "empty", "equals true", "not empty")
     * @param  array<string, mixed>  $values  Current context values
     * @return bool True when the condition passes
     */
    public function evaluateCondition(string $fieldHandle, string $rhs, array $values): bool
    {
        // Left-hand side: value for the field from the current context
        $lhs = Arr::get($values, $fieldHandle);

        // Normalize right-hand side by trimming whitespace
        $rhs = trim($rhs);

        // ---
        // Literals
        // ---

        // Check if the right-hand side is a bare "null", "true" or "false" literal
        if (\in_array($rhs, ['null', 'true', 'false'], true)) {
            // Compare the way the editor's loose `==` does (#508)
            return $this->passesLiteralComparison($lhs, $rhs, equal: true);
        }

        // ---
        // Empty / not empty
        // ---

        // Determine whether the left-hand side is considered empty
        $empty = in_array($lhs, [null, '', []], true);

        // Check if the condition is "empty"
        if ($rhs === 'empty') {
            // Return whether lhs is empty
            return $empty;
        }

        // ---
        // equals (e.g. "equals true", "equals scheduled")
        // ---

        // Check if the condition is "equals <value>"
        if (preg_match('/^equals\s+(.+)$/s', $rhs, $m)) {
            // Extract and trim the target value from the match
            $target = trim($m[1]);

            // Check if the target is "empty" (the editor tests emptiness, not the word)
            if ($target === 'empty') {
                // Return whether lhs is empty
                return $empty;
            }

            // Check if the target is a "null", "true" or "false" literal
            if (\in_array($target, ['null', 'true', 'false'], true)) {
                // Compare the way the editor's loose `==` does (#508)
                return $this->passesLiteralComparison($lhs, $target, equal: true);
            }

            // Compare lhs to target (loose)
            return $lhs == $target;
        }

        // Check if the condition is "not <value>" (e.g. "not empty")
        if (preg_match('/^not\s+(.+)$/s', $rhs, $m)) {
            // Extract and trim the target value from the match
            $target = trim($m[1]);

            // Check if the target is "empty" (special case for "not empty")
            if ($target === 'empty') {
                // Special case: not empty
                return ! $empty;
            }

            // Check if the target is a "null", "true" or "false" literal
            if (\in_array($target, ['null', 'true', 'false'], true)) {
                // Compare the way the editor's loose `!=` does (#508)
                return $this->passesLiteralComparison($lhs, $target, equal: false);
            }

            // Compare lhs to target (not equal, loose)
            return $lhs != $target;
        }

        // Check if the condition is "contains_any" (comma-separated options)
        if (preg_match('/^contains_any\s+(.+)$/s', $rhs, $m)) {
            // Parse comma-separated options and trim each
            $options = array_map(trim(...), explode(',', $m[1]));

            // Check if the left-hand side is an array (intersect with options)
            if (\is_array($lhs)) {
                /** @var array<array-key, scalar> $lhs */
                // True when lhs array intersects any option
                return array_intersect($lhs, $options) !== [];
            }

            // Coerce lhs to string for matching (null and scalars only; array branch returned above)
            $lhsString = \is_scalar($lhs) || $lhs === null ? (string) $lhs : '';

            // Treat lhs as string and check if it matches any option (regex)
            return (bool) preg_match('/'.implode('|', array_map(preg_quote(...), $options)).'/i', $lhsString);
        }

        // Check if the condition is "contains" (single value or substring)
        if (preg_match('/^contains\s+(.+)$/s', $rhs, $m)) {
            // Extract and trim the needle from the match
            $needle = trim($m[1]);

            // Check if the left-hand side is an array (needle in array)
            if (\is_array($lhs)) {
                // True when needle is in lhs array
                return \in_array($needle, $lhs);
            }

            // Coerce lhs to string for substring check (null and scalars only; array branch returned above)
            $lhsString = \is_scalar($lhs) || $lhs === null ? (string) $lhs : '';

            // True when lhs string contains needle
            return str_contains($lhsString, $needle);
        }

        // Check if the condition is a custom one registered in resources/js/conditions
        if (preg_match('/^custom\s+(.*)$/s', $rhs, $m)) {
            return $this->evaluateCustomCondition($fieldHandle, $m[1], $lhs, $values);
        }

        // Default: loose equality
        return $lhs == $rhs;
    }

    /**
     * Compare a value with a `null`, `true` or `false` literal as the editor does.
     *
     * Statamic's condition validator turns an empty string into null, hides the
     * field when the value is an object or array, and otherwise applies
     * JavaScript's loose `==`: null equals only null, a boolean equals itself,
     * and a number or string is read as a number against 1 or 0, so `'1'`
     * equals `true` and `'on'` equals neither.
     *
     * @param  mixed  $lhs  The value of the field the condition is keyed on
     * @param  string  $literal  `null`, `true` or `false`
     * @param  bool  $equal  True for `==` (`equals` or a bare literal), false for `!=` (`not`)
     * @return bool True when the condition passes
     */
    private function passesLiteralComparison(mixed $lhs, string $literal, bool $equal): bool
    {
        // The editor hides a field when the value it compares is an object or array
        if (\is_array($lhs) || \is_object($lhs)) {
            return false;
        }

        // The editor reads an unanswered field as null
        if ($lhs === '') {
            $lhs = null;
        }

        $matches = match (true) {
            // null equals only null; true and false never equal null
            $literal === 'null', $lhs === null => $literal === 'null' && $lhs === null,
            // A boolean compares as itself
            \is_bool($lhs) => $lhs === ($literal === 'true'),
            // A number or string compares as a number against 1 or 0
            default => $this->jsNumber($lhs) === ($literal === 'true' ? 1.0 : 0.0),
        };

        return $equal ? $matches : ! $matches;
    }

    /**
     * Read a scalar as JavaScript's `Number()` does, NAN where it reads NaN.
     */
    private function jsNumber(mixed $value): float
    {
        if (\is_int($value) || \is_float($value)) {
            return (float) $value;
        }

        // The editor trims a string value before comparing it
        $string = trim(\is_scalar($value) ? (string) $value : '');

        return match (true) {
            // Number('') is 0
            $string === '' => 0.0,
            preg_match('/^[+-]?(\d+\.?\d*|\.\d+)(e[+-]?\d+)?$/i', $string) === 1 => (float) $string,
            preg_match('/^0x[0-9a-f]+$/i', $string) === 1 => (float) hexdec($string),
            preg_match('/^0b[01]+$/i', $string) === 1 => (float) bindec($string),
            preg_match('/^0o[0-7]+$/i', $string) === 1 => (float) octdec($string),
            // Anything else, Infinity included, can equal neither 1 nor 0
            default => NAN,
        };
    }

    /**
     * Evaluate a `custom <name>:<params>` condition the way the editor does.
     *
     * The name ends at the first colon and the params run to the next one, each
     * split on commas and trimmed, as Statamic's condition validator parses them. A
     * name {@see CustomConditionEvaluator} does not port reads as hidden, which is
     * what the editor does with an unregistered one, and is logged so it is seen.
     *
     * @param  string  $fieldHandle  The field the condition is keyed on
     * @param  string  $condition  The condition after `custom `, e.g. `selectionCount:lt,6`
     * @param  mixed  $target  The value of the field the condition is keyed on
     * @param  array<string, mixed>  $values  Current context values
     * @return bool True when the condition passes
     */
    private function evaluateCustomCondition(string $fieldHandle, string $condition, mixed $target, array $values): bool
    {
        $parts = explode(':', $condition);
        $name = $parts[0];
        $params = ($parts[1] ?? '') === '' ? [] : array_map(trim(...), explode(',', $parts[1]));

        $passes = $this->customConditions->evaluate($name, $params, $target, $values);

        if ($passes === null) {
            Log::warning(
                'EntryValidator: custom field condition has no server-side port; treating the field as hidden',
                [
                    'condition' => $name,
                    'field' => $fieldHandle,
                ]
            );

            return false;
        }

        return $passes;
    }

    /**
     * Extract entry values for validation (mirrors ExtractsFromEntryFields).
     *
     * @param  StatamicEntry  $entry  Entry whose data and origin chain supply values
     * @return array<string, mixed> Merged data and computed values, plus structure parent and dated `date` when
     *                              the collection requires them
     */
    public function extractValues(
        StatamicEntry $entry
    ): array {
        // Merge data from this entry and all origins (localization chain)
        $values = collect();

        // Start with this entry and walk origins
        $target = $entry;

        // Loop through this entry and all origins to merge data (localization chain)
        while ($target !== null) {
            // Resolve this target's raw and computed data collections
            /** @var Collection<string, mixed> $targetData */
            $targetData = $target->data();

            /** @var Collection<string, mixed> $targetComputedData */
            $targetComputedData = $target->computedData();

            // Merge this target's data and computed data into values (later overrides earlier)
            $values = $targetData->merge($targetComputedData)->merge($values);

            // Move to origin for next iteration
            /** @var StatamicEntry|null $target */
            $target = $target->origin();
        }

        // Convert collection to plain array for validation
        /** @var array<string, mixed> $values */
        $values = $values->all();

        // ---
        // Add structure parent when entry is in a structure
        // ---

        // Check if the entry belongs to a structure (add parent for validation)
        if ($entry->hasStructure()) {
            // Resolve structure parent entry (may be null when entry is a root)
            /** @var StatamicEntry|null $structureParent */
            $structureParent = $entry->parent();

            // Set parent to structure parent id (filter null)
            $values['parent'] = array_filter([$structureParent?->id()]);

            // ---
            // Use explicit parent value when revisions are enabled
            // ---

            // Check if revisions are enabled and entry has an explicit parent value
            if (
                $entry->revisionsEnabled()
                && ($parent = $entry->get('parent')) !== null
            ) {
                // Override parent with explicit stored value
                $values['parent'] = [$parent];
            }
        }

        // ---
        // Add date in format expected by blueprint when collection is dated
        // ---

        // Resolve collection to check whether it is dated
        /** @var \Statamic\Entries\Collection $collection */
        $collection = $entry->collection();

        // Check if the collection is dated (add date in blueprint-expected format)
        if ($collection->dated()) {
            // Resolve the entry date for formatting
            /** @var Carbon $entryDate */
            $entryDate = $entry->date();

            // Get date as Y-m-d H:i:s (first 19 chars)
            $datetime = substr((string) $entryDate->toDateTimeString(), 0, 19);

            // Use date-only when entry has no time
            $datetime = $entry->hasTime() ? $datetime : substr($datetime, 0, 10);

            // Add date to values for validation
            $values['date'] = $datetime;
        }

        // Capture result before unsetting
        $result = $values;

        // Free locals before return
        unset($target, $values);

        // Return merged entry values for validation
        return $result;
    }
}
