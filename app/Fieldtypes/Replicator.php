<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Fieldtypes\Traits\ConfigSectionWrapper;
use App\Rules\FieldRequiredHelper;
use App\Rules\ReplicatorMinSets;
use App\Rules\ReplicatorRequireAtLeastOneEnabledSet;
use Illuminate\Support\Collection;
use Statamic\Contracts\Forms\Form as FormContract;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fields\Validator;
use Statamic\Fieldtypes\Group;
use Statamic\Fieldtypes\Replicator as BaseReplicator;
use Statamic\Support\Str;
use Statamic\Tags\Concerns\RendersForms;

/**
 * Replicator fieldtype that requires at least one enabled set when the field is
 * required by its validation config, and supports a minimum number of sets via
 * the Boundaries & Limits config.
 *
 * Extends Statamic's Replicator. When the blueprint validation includes any
 * Laravel "required" rule (required, required_if, required_unless, etc.) and
 * that requirement applies for the current request, the value must contain
 * at least one set with `enabled` not false. Disabled sets (`enabled: false`)
 * do not count. When validation contains "sometimes" and the "if" condition
 * is not met, the field is not validated and this rule does not run.
 *
 * When `min_sets` is set in the Boundaries & Limits config, the value must
 * contain at least that many sets (counted as total sets, including disabled).
 * Min-sets validation runs only when the field is also required for the request.
 */
class Replicator extends BaseReplicator
{
    /**
     * Use ConfigSectionWrapper trait to add sections around existing configuration
     * fields.
     */
    use ConfigSectionWrapper;

    /**
     * Indicates whether the fieldtype is selectable in form blueprints.
     *
     * @var bool Indicates whether the fieldtype is selectable in form blueprints
     */
    protected $selectableInForms = true;

    /**
     * Return configuration field items with min_sets added to Boundaries &
     * Limits.
     *
     * @return array<array-key, array<string, mixed>> The configuration field items
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        // Build the min_sets field definition for the Boundaries & Limits section
        $fieldsToAdd = [
            __('Boundaries & Limits') => [
                'min_sets' => [
                    'display' => __('Minimum Sets'),
                    'instructions' => __('statamic::validation.replicator.min_sets_instructions'),
                    'type' => 'integer',
                ],
            ],
        ];

        // Merge min_sets into the parent config and return all sections
        return $this->addFieldsToExistingSections($fieldsToAdd);
    }

    /**
     * Return validation rules for this fieldtype, including the at-least-one-
     * enabled-set rule when the field's validate config contains a required*
     * rule, and a minimum-sets rule when min_sets is configured (min_sets only
     * applies when the field is also required).
     *
     * @return array<int, mixed> Validation rules (Laravel format)
     */
    #[\Override]
    public function rules(): array
    {
        // Get the base rules from the parent class
        /** @var array<int, mixed> $base */
        $base = parent::rules();

        // Get the field config
        /** @var Field $field */
        $field = $this->field;
        /** @var array<string, mixed> $fieldConfig */
        $fieldConfig = $field->config();

        // Check if the field config has a required* rule
        if (FieldRequiredHelper::configHasRequiredRule($fieldConfig)) {
            // Add the at-least-one-enabled-set rule
            $base[] = new ReplicatorRequireAtLeastOneEnabledSet($fieldConfig);
        }

        // Get the min_sets value from the field config
        $minSets = $this->config('min_sets');

        // Check if min_sets is configured and greater than 0
        if (
            $minSets !== null
            && is_scalar($minSets)
            && (int) $minSets > 0
        ) {
            // Add the minimum-sets rule
            $base[] = new ReplicatorMinSets($fieldConfig);
        }

        // Unset variables to release memory
        unset(
            $fieldConfig,
            $minSets
        );

        // Return the base rules with the new rules added
        return $base;
    }

    /**
     * The placeholder token stamped into a set template's field names + ids, swapped for a
     * concrete row index by the Alpine engine when a new set is added (UAMS-Web/uams-statamic-archive#675).
     *
     * Deliberately free of `_` and `.` so it survives `generateFieldId()` (which maps both to `-`)
     * intact in the generated `id`/`for` as well as the `name`, letting the engine replace every
     * occurrence with a single token swap.
     *
     * @var string
     */
    public const INDEX_TOKEN = 'REPLICATORSETINDEX';

    /**
     * Prefix of the placeholder for a replicator nested inside another replicator's set (UAMS-Web/uams-statamic-archive#2133).
     *
     * A nested replicator's blank template sits inside its parent set's template, so if both used
     * {@see self::INDEX_TOKEN}, cloning a parent set would stamp the parent's index over the child
     * template's placeholder too, and every child set cloned from it would post the same name.
     * Each depth therefore gets its own token, `REPLICATOR-NESTED-{depth}-INDEX`, none of which
     * contains another as a substring, so a single token swap touches only its own level.
     *
     * @var string
     */
    public const NESTED_INDEX_TOKEN_PREFIX = 'REPLICATOR-NESTED-';

    /**
     * Normalize each row's `enabled` flag to a real boolean before validation (UAMS-Web/uams-statamic-archive#675).
     *
     * A public form posts `enabled` as a string (`'0'` / `'1'`) from the paired hidden-input +
     * checkbox, but {@see ReplicatorRequireAtLeastOneEnabledSet} only treats a set as disabled when
     * `enabled === false` (boolean). Coercing here — the value the validator runs against
     * ({@see Validator}) — makes a toggled-off set actually count as disabled.
     *
     * @param  mixed  $value  The raw submitted replicator value.
     * @return mixed The value with each row's `enabled` coerced to a boolean.
     */
    #[\Override]
    public function preProcessValidatable($value)
    {
        return $this->withNormalizedEnabled(parent::preProcessValidatable($value));
    }

    /**
     * Persist each row's `enabled` flag as a real boolean (UAMS-Web/uams-statamic-archive#675), matching the CP's stored shape
     * rather than the raw `'0'` / `'1'` string a public form posts.
     *
     * @param  mixed  $data  The submitted replicator value.
     * @return mixed The processed value with each row's `enabled` coerced to a boolean.
     */
    #[\Override]
    public function process($data)
    {
        return $this->withNormalizedEnabled(parent::process($data));
    }

    /**
     * Coerce every row's `enabled` key to a boolean, leaving non-array rows untouched.
     *
     * @param  mixed  $rows  A processed/validatable replicator value.
     * @return mixed The same shape with boolean `enabled` on each array row.
     */
    private function withNormalizedEnabled(mixed $rows): mixed
    {
        if (! \is_array($rows)) {
            return $rows;
        }

        foreach ($rows as $index => $row) {
            if (\is_array($row) && \array_key_exists('enabled', $row)) {
                $row['enabled'] = $this->normalizeEnabled($row['enabled']);
                $rows[$index] = $row;
            }
        }

        return $rows;
    }

    /**
     * Whether a submitted `enabled` value is truthy — false only for an explicit off value.
     */
    private function normalizeEnabled(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_string($value)) {
            return ! \in_array(strtolower(trim($value)), ['0', 'false', 'no', 'off', ''], true);
        }

        return (bool) $value;
    }

    /**
     * Expose the data the public-form replicator template needs to render sets and add new ones (UAMS-Web/uams-statamic-archive#675).
     *
     * Statamic renders composite form fields by handing the fieldtype a callback that produces a
     * child field's renderable data ({@see RendersForms::getRenderableField()},
     * the same mechanism {@see Group}). Each set's subfields are rendered by
     * dotting their handle under the field + row index (`{handle}.{index}.{subfield}`), which the
     * form tag converts to the nested input name `{handle}[{index}][{subfield}]` that
     * {@see BaseReplicator::process()} consumes (flat subfields per row, alongside `type`/`enabled`).
     *
     * Two collections are produced: the field's existing/old-input rows at concrete indices (so a
     * value or a re-render after a validation error repopulates), and one blank template per set
     * type at the {@see self::INDEX_TOKEN} placeholder for the Alpine "Add Set" engine to clone.
     *
     * @param  array<string, mixed>  $data  The renderable field data assembled so far.
     * @param  callable(Field): array<string, mixed>  $recursiveCallback  Renders a child field's data.
     * @return array<string, mixed> The data with replicator rows, set templates, and limits added.
     */
    #[\Override]
    public function preProcessTagRenderable($data, $recursiveCallback)
    {
        /** @var Field $field */
        $field = $this->field;
        $handle = $field->handle();

        if (! \is_string($handle)) {
            return $data;
        }

        $form = $field->form();
        $form = $form instanceof FormContract ? $form : null;

        $rawSetsConfig = $this->flattenedSetsConfig();
        $setsConfig = $rawSetsConfig instanceof Collection ? $rawSetsConfig : collect();

        // Render a set's subfields under a given row-index token, namespacing each handle so the
        // form tag emits `{handle}[{index}][{subfield}]` — the shape process() expects.
        $renderSet = function (mixed $fields, string|int $indexToken) use ($recursiveCallback, $form, $handle): array {
            if (! $fields instanceof Fields) {
                return [];
            }

            return $fields->all()
                ->map(function (mixed $child) use ($form, $handle, $indexToken, $recursiveCallback): ?array {
                    if (! $child instanceof Field) {
                        return null;
                    }

                    $childHandle = $child->handle();
                    $childHandle = \is_string($childHandle) ? $childHandle : '';

                    // Bracketed handle so every subfield partial's `name="{{ handle }}"` emits the
                    // nested `{handle}[{index}][{subfield}]` shape process() consumes — for any
                    // nested fieldtype, without touching the shared field partials.
                    $child->setHandle(sprintf('%s[%s][%s]', $handle, $indexToken, $childHandle));

                    // The form scopes child rendering; it is always present in the form pipeline
                    // and only absent in isolated tests, where setForm(null) would throw.
                    if ($form instanceof FormContract) {
                        $child->setForm($form);
                    }

                    return $recursiveCallback($child);
                })
                ->filter()
                ->values()
                ->all();
        };

        // Existing rows: prefer old input (validation-error re-render) over any bound value.
        $bound = $field->value();
        $value = old($handle, \is_array($bound) ? $bound : null);

        $data['replicator_rows'] = collect(\is_array($value) ? $value : [])
            ->values()
            ->map(function (mixed $row, int $index) use ($renderSet, $setsConfig): ?array {
                $type = \is_array($row) ? ($row['type'] ?? null) : null;

                if (! \is_string($type) || ! $setsConfig->has($type)) {
                    return null;
                }

                $setFields = $this->fields($type, $index);

                if (! $setFields instanceof Fields) {
                    return null;
                }

                // Reaching here, $row is a set array (its `type` was read above). Coerce `enabled`
                // so a validation-error re-render (old input posts `enabled` as the string '0')
                // preserves a user-disabled set instead of flipping its checkbox back on.
                // Seed BEFORE rendering: $renderSet() rewrites each child's handle to the
                // bracketed submission shape, so reading handles afterwards would key the row
                // scope by `parent[0][child]` — the very identifier Alpine cannot resolve.
                $setValues = self::setValuesJson($setFields, $row);

                return [
                    'type' => $type,
                    'enabled' => $this->normalizeEnabled($row['enabled'] ?? true),
                    'index' => $index,
                    'fields' => $renderSet($setFields->addValues($row), $index),
                    'set_values' => $setValues,
                ];
            })
            ->filter()
            ->values()
            ->all();

        // Choose this level's placeholder, distinct from any enclosing replicator's
        $indexToken = self::indexTokenFor($handle);

        // A blank template per set type, keyed on the placeholder index for client-side cloning.
        $data['replicator_set_templates'] = $setsConfig
            ->map(function (mixed $set, int|string $type) use ($renderSet, $indexToken): array {
                $display = \is_array($set) && \is_scalar($set['display'] ?? null) ? (string) $set['display'] : Str::studly((string) $type);

                // Seeded before rendering, for the same handle-rewrite reason as the rows above.
                $setValues = self::setValuesJson($this->fields((string) $type), []);

                return [
                    'type' => (string) $type,
                    'display' => $display,
                    'fields' => $renderSet($this->fields((string) $type), $indexToken),
                    'set_values' => $setValues,
                ];
            })
            ->values()
            ->all();

        $minSets = $this->config('min_sets');
        $data['replicator_min_sets'] = is_numeric($minSets) ? max(0, (int) $minSets) : 0;
        $data['replicator_index_token'] = $indexToken;

        return $data;
    }

    /**
     * The set-template placeholder for a replicator whose handle is given (UAMS-Web/uams-statamic-archive#2133).
     *
     * A top-level replicator keeps {@see self::INDEX_TOKEN}. A replicator inside another's set has
     * a handle carrying one `[index][child]` pair per enclosing level, so its depth is half the
     * number of bracket groups, and it gets `REPLICATOR-NESTED-{depth}-INDEX`.
     *
     * @param  string  $handle  The replicator field's handle as rendered.
     * @return string The placeholder token for that nesting depth.
     */
    public static function indexTokenFor(string $handle): string
    {
        // Count the enclosing `[index][child]` pairs
        $depth = intdiv(substr_count($handle, '['), 2);

        // A top-level replicator keeps the original token
        if ($depth === 0) {
            return self::INDEX_TOKEN;
        }

        return self::NESTED_INDEX_TOKEN_PREFIX.$depth.'-INDEX';
    }

    /**
     * Seed a set's Alpine row scope with its subfield values, keyed by bare handle (UAMS-Web/uams-statamic-archive#2056).
     *
     * Each set renders inside its own `x-data="replicatorSet"` scope so a subfield's condition can
     * name a sibling by its bare handle — the same row-relative model the Control Panel uses. That
     * scope needs its properties to exist *before* Alpine binds the descendants' `x-model`, so the
     * values are seeded server-side from a data attribute rather than discovered from the DOM.
     *
     * @param  mixed  $fields  The set's Fields collection.
     * @param  array<mixed, mixed>  $row  The submitted row, empty for a blank template.
     * @return string An HTML-escaped JSON object of bare handle => value, safe as an attribute value.
     */
    private static function setValuesJson(mixed $fields, array $row): string
    {
        if (! $fields instanceof Fields) {
            return '{}';
        }

        $values = [];

        foreach ($fields->all() as $child) {
            if (! $child instanceof Field) {
                continue;
            }

            $handle = $child->handle();
            if (! \is_string($handle)) {
                continue;
            }

            if ($handle === '') {
                continue;
            }

            // Absent keys seed as null so the property exists and the condition reads "empty"
            // rather than resolving against an undefined Alpine identifier. A multi-valued choice
            // seeds as an empty list instead (UAMS-Web/uams-statamic-archive#2687): `x-model` treats a checkbox bound to a
            // non-array as a single boolean, so one click would check every box in the group.
            $values[$handle] = $row[$handle] ?? (self::isMultipleChoice($child) ? [] : null);
        }

        $json = json_encode($values, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';

        // Antlers emits `{{ set_values }}` raw, so the JSON's own double quotes would terminate
        // the HTML attribute and leave the row scope empty. Escape here rather than relying on a
        // single-quoted attribute, which a value containing an apostrophe would break instead.
        return htmlspecialchars($json, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Whether a subfield posts a list of choices: a `checkboxes` field, or a `select` with
     * `multiple` on (UAMS-Web/uams-statamic-archive#2687).
     *
     * These are the fields Statamic's own form data preprocesses from null to an empty list, so a
     * set scope seeded the same way gives `x-model` the array it needs for the field to hold
     * more than one choice.
     *
     * @param  Field  $field  The subfield.
     * @return bool Whether its value is a list.
     */
    private static function isMultipleChoice(Field $field): bool
    {
        return match ($field->type()) {
            'checkboxes' => true,
            'select' => (bool) $field->get('multiple'),
            default => false,
        };
    }
}
