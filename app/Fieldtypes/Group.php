<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fields\Validator;
use Statamic\Fieldtypes\AddsEntryValidationReplacements;
use Statamic\Fieldtypes\Group as BaseGroup;

/**
 * Statamic's Group fieldtype, with the entry's validation replacements passed to
 * the rules of the fields inside it.
 *
 * The Control Panel substitutes `{id}`, `{collection}` and `{site}` into a
 * blueprint's validation rules, but Statamic's Group builds a separate validator
 * for its own fields and gives it no replacements. That validator substitutes
 * every placeholder it cannot fill with the literal `NULL` before the Control
 * Panel's values can reach it. So a uniqueness rule inside a group excludes
 * nothing, and an entry fails against itself on every save: the four Workday ids
 * inside `uams_workday_id` on `person` are the case that reported it.
 *
 * Grid and Replicator already fill these through Statamic's own
 * `AddsEntryValidationReplacements` trait; Group was never given it. This is
 * statamic/cms#15513. The override is retired by deleting this class once an
 * installed Statamic release applies the trait to Group itself.
 */
class Group extends BaseGroup
{
    use AddsEntryValidationReplacements;

    /**
     * Rules for the group's fields, keyed by their dotted path, with the entry's
     * validation replacements applied.
     *
     * Falls back to Statamic's own rules whenever a step returns something other
     * than the type it documents, so the override never drops a rule it could
     * not rebuild.
     *
     * @return array<mixed> Validation rules keyed by `<group>.<field>`
     */
    #[\Override]
    public function extraRules(): array
    {
        $field = $this->field;
        $fields = $this->fields();

        // Defer to Statamic when there is no field or nested fieldset to read
        if (! $field instanceof Field || ! $fields instanceof Fields) {
            // Use the unmodified rules
            return parent::extraRules();
        }

        $groupHandle = $field->handle();
        $withValues = $fields->addValues((array) $field->value());

        // Defer to Statamic when the group has no handle or the values did not attach
        if (! \is_string($groupHandle) || ! $withValues instanceof Fields) {
            // Use the unmodified rules
            return parent::extraRules();
        }

        // Build the validator for the group's own fields
        $validator = $withValues->validator();

        // Defer to Statamic when the fields do not yield a validator
        if (! $validator instanceof Validator) {
            // Use the unmodified rules
            return parent::extraRules();
        }

        // Carry the group's validation prefix into its own fields
        $validator->withContext([
            'prefix' => $field->validationContext('prefix'),
        ]);

        // Fill `{id}`, `{collection}` and `{site}` from the entry that owns the field
        $rules = $this
            ->addEntryValidationReplacements($field, $validator)
            ->rules();

        // Defer to Statamic when the rules are not a list keyed by field
        if (! \is_array($rules)) {
            // Use the unmodified rules
            return parent::extraRules();
        }

        // Key each field's rules by its path inside the group
        $keyed = [];

        foreach ($rules as $handle => $fieldRules) {
            $keyed["{$groupHandle}.{$handle}"] = $fieldRules;
        }

        return $keyed;
    }
}
