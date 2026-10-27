<?php

declare(strict_types=1);

namespace App\Fieldtypes\Traits;

use App\Fieldtypes\Assets;
use App\Fieldtypes\Checkboxes;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;

/**
 * Reusable helper for computing a Statamic fieldtype's own (field-level)
 * validation rules.
 *
 *   - Supplies a default getValidationRules() (returns []) that a fieldtype
 *     overrides to compute rules for ITS OWN value.
 *   - Supplies a setField() passthrough.
 *   - Agnostic to config normalization; the fieldtype using this must provide
 *     normalization if needed.
 *
 * IMPORTANT — wiring: getValidationRules() is a PURE HELPER with no automatic
 * wiring. To make its rules actually fire, the fieldtype MUST return them from
 * {@see Fieldtype::rules()} (which Statamic merges into the field's own handle
 * bucket), e.g.
 * `array_values(array_merge(parent::rules(), $this->getValidationRules(null, $this->field()->config())))`
 * — see {@see Checkboxes::rules()} / {@see Assets::rules()}. Do NOT route them
 * through extraRules(): Statamic keys extraRules() as a `[fieldHandle => rules]`
 * MAP, so a flat list mis-keys to numeric phantom fields that never validate
 * (UAMS-Web/uams-statamic-archive#1301). See the extraRules() note below.
 *
 * @see  vendor/statamic/cms/resources/js/components/field-validation/Rules.js
 * @see  https://laravel.com/docs/12.x/validation#available-validation-rules
 */
trait FieldtypeValidationRules
{
    /**
     * Compute this fieldtype's own (field-level) validation rules. A fieldtype
     * overrides this to return rules for its value.
     *
     * NOTE: the return value is INERT until the fieldtype also returns it from
     * {@see Fieldtype::rules()} — this helper does not wire itself up. See the
     * trait-level note and {@see extraRules()}.
     *
     * @param  mixed  $value  The value to validate
     * @param  array<string, mixed>  $config  The field config
     * @return list<mixed> Array of validation rules (Laravel format)
     */
    public function getValidationRules(
        mixed $value,
        array $config = []
    ): array {
        // Default: no rules. A fieldtype overrides this and wires the result via rules().
        return [];
    }

    /**
     * Set the field for validation rule processing.
     *
     * This method is necessary because the extraRules() method depends on
     * $this->field() being set to access the field's configuration and handle for
     * validation rule generation. Statamic's validation system calls setField()
     * before calling extraRules().
     *
     * @param  Field  $field  The field instance to set
     * @return static Returns self for method chaining
     */
    public function setField(
        Field $field
    ): static {
        /** @var static $self */
        $self = parent::setField($field);

        return $self;
    }

    /**
     * A deliberate no-op default — do NOT route a fieldtype's own validation rules
     * through here.
     *
     * Statamic's `Field::rules()` treats an `extraRules()` return value as a
     * `[fieldHandle => rules]` MAP: it `->map()`s each entry and PRESERVES the
     * entry key. A FLAT list of rules meant for THIS field therefore lands under
     * numeric phantom fields (`0`, `1`, …) that never exist in the payload, so the
     * rules never fire (UAMS-Web/uams-statamic-archive#1301) — and worse, a rule OBJECT/Closure entry throws a
     * TypeError in `Field::addNullableRule()` (`in_array('nullable', $closure)`).
     *
     * Field-level rules (rules for this field's own value) must instead be returned
     * from {@see Fieldtype::rules()}, which Statamic merges into
     * the field's OWN handle bucket — see {@see Assets::rules()} and
     * {@see Checkboxes::rules()} for the pattern (typically
     * `array_merge(parent::rules(), $this->getValidationRules(null, $this->field()->config()))`).
     * Returning `[]` here keeps a fieldtype that only overrides
     * `getValidationRules()` from silently reintroducing the mis-key. A compound
     * fieldtype with genuine SUB-field rules should override `extraRules()` with a
     * real `[subfieldHandle => rules]` / `[parent.sub => rules]` MAP (real string
     * keys, never a numeric list).
     *
     * @return array<string, mixed> Always empty; see the note above.
     */
    public function extraRules(): array
    {
        return [];
    }
}
