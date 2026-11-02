<?php

declare(strict_types=1);

namespace App\Services\EntryValidation;

/**
 * Server-side ports of the custom field conditions the Control Panel registers in
 * `resources/js/conditions/` (#508), so a field the editor shows is validated and a
 * field it hides is not.
 *
 * Each port follows its JavaScript function, including JavaScript's truthiness and
 * strict comparisons, and reads the same inputs Statamic passes it: `target` (the
 * value of the field the condition is keyed on), the trimmed `params` after the
 * colon, and the sibling `values`. Only the conditions a blueprint or fieldset uses
 * are ported; {@see self::SUPPORTED} lists them, and an unlisted name returns null.
 */
class CustomConditionEvaluator
{
    /**
     * The custom condition names this class evaluates.
     *
     * @var list<string>
     */
    public const array SUPPORTED = [
        'articleVisible',
        'isNotEmpty',
        'notContainsAny',
        'notSyndicated',
        'parentOverride',
        'parkingVisible',
        'selectionCount',
    ];

    /**
     * Evaluate a custom condition the way its registered JavaScript function does.
     *
     * @param  string  $name  The condition name, e.g. `parkingVisible`
     * @param  list<string>  $params  The trimmed parameters after the colon
     * @param  mixed  $target  The value of the field the condition is keyed on
     * @param  array<string, mixed>  $values  The sibling field values
     * @return bool|null Whether the field shows, or null for a condition this class does not port
     */
    public function evaluate(string $name, array $params, mixed $target, array $values): ?bool
    {
        return match ($name) {
            'articleVisible' => $this->articleVisible($values),
            'isNotEmpty' => $this->isTruthy($target),
            'notContainsAny' => $this->notContainsAny($params, $target),
            'notSyndicated' => $this->isFalse($values['syndication_query'] ?? null),
            'parentOverride' => $this->parentOverride($params[0] ?? '', $values),
            'parkingVisible' => $this->parkingVisible($params[0] ?? '', $values),
            'selectionCount' => $this->selectionCount($params, $target, $values),
            default => null,
        };
    }

    /**
     * `articleVisible`: an original article, or one syndicated from a non-NCI source.
     *
     * @param  array<string, mixed>  $values
     */
    private function articleVisible(array $values): bool
    {
        if ($this->isFalse($values['syndication_query'] ?? null)) {
            return true;
        }

        return ($values['syndication_source'] ?? null) === 'other';
    }

    /**
     * `notContainsAny`: the value shares no item with, and contains none of, the params.
     *
     * @param  list<string>  $params
     */
    private function notContainsAny(array $params, mixed $target): bool
    {
        // The editor's function refuses a condition with no params
        if ($params === []) {
            return false;
        }

        // Check if the target is an array (no param may be in it)
        if (\is_array($target)) {
            /** @var array<array-key, scalar> $target */
            return array_intersect($target, $params) === [];
        }

        // Coerce the target to a string (null and scalars only; array branch returned above)
        $targetString = \is_scalar($target) || $target === null ? (string) $target : '';

        // True when no param appears as a literal, case-sensitive substring
        return array_all($params, fn (string $param): bool => ! str_contains($targetString, $param));
    }

    /**
     * `parentOverride`: which location address groups a child location owns.
     *
     * @param  array<string, mixed>  $values
     */
    private function parentOverride(string $role, array $values): bool
    {
        $isChild = $this->hasValue($values['parent'] ?? null);
        $overridesAddress = $this->isTrue($values['parent_address_override_query'] ?? null);

        // A standalone location owns every group; a child owns one it overrides
        $owns = fn (string $group): bool => ! $isChild
            || ($overridesAddress && $this->isTrue($values['parent_address_override_'.$group.'_query'] ?? null));

        $ownsFacility = $owns('facility');
        $inFacility = $this->isTrue($values['facility_query'] ?? null);

        switch ($role) {
            case 'facility':
                return $ownsFacility;
            case 'street':
            case 'map':
                return $owns($role) && ($isChild
                    ? ! $ownsFacility || ! $inFacility
                    : $this->isFalse($values['facility_query'] ?? null));
            case 'unit':
                return $owns('unit') && ($isChild ? ! $ownsFacility || $inFacility : $inFacility);
        }

        if (! $isChild) {
            return true;
        }

        return match ($role) {
            'address' => $overridesAddress,
            'parking' => $this->isTrue($values['parent_parking_override_query'] ?? null),
            default => false,
        };
    }

    /**
     * `parkingVisible`: the parking fields that apply to a location.
     *
     * @param  array<string, mixed>  $values
     */
    private function parkingVisible(string $role, array $values): bool
    {
        $contained = $this->isTrue($values['facility_query'] ?? null) && $this->hasValue($values['facility'] ?? null);
        $standaloneWithParking = $this->isFalse($values['facility_query'] ?? null)
            && $this->isTrue($values['parking_query'] ?? null);
        $childOverride = $this->hasValue($values['parent'] ?? null)
            && $this->isTrue($values['parent_parking_override_query'] ?? null);

        $parkingApplies = $contained || $standaloneWithParking || $childOverride;

        return match ($role) {
            'facilityInherit' => $contained,
            'facility' => ($contained && $this->isFalse($values['parking_facility_inherit_query'] ?? null))
                || $standaloneWithParking
                || $childOverride,
            'inheritToggle' => $parkingApplies,
            'instructions' => $this->isFalse($values['parking_instructions_inherit_query'] ?? null) && $parkingApplies,
            'directions' => $this->isFalse($values['directions_from_parking_inherit_query'] ?? null) && $parkingApplies,
            default => false,
        };
    }

    /**
     * `selectionCount`: compare how many items the target holds against an operand.
     *
     * @param  list<string>  $params  The operator, then one operand (two for `between`)
     * @param  array<string, mixed>  $values
     */
    private function selectionCount(array $params, mixed $target, array $values): bool
    {
        if ($params === []) {
            return false;
        }

        // An array counts its items, an empty value counts none, anything else counts one
        $count = match (true) {
            \is_array($target) => \count($target),
            $target === null, $target === '' => 0,
            default => 1,
        };

        // NAN stands for JavaScript's NaN: every comparison with it fails except "not equal"
        $first = $this->numericOperand($params[1] ?? null, $values);

        switch ($params[0]) {
            case 'gt':
                return $count > $first;
            case 'gte':
                return $count >= $first;
            case 'lt':
                return $count < $first;
            case 'lte':
                return $count <= $first;
            case 'equals':
            case 'equal':
            case 'eq':
                return (float) $count === $first;
            case 'not':
            case 'notequal':
            case 'notequals':
            case 'neq':
                return (float) $count !== $first;
            case 'between':
                $second = $this->numericOperand($params[2] ?? null, $values);

                if (is_nan($first) || is_nan($second)) {
                    return false;
                }

                return $count >= $first && $count <= $second;
        }

        return false;
    }

    /**
     * Resolve a `selectionCount` operand: a number literal, or a sibling field read
     * as a number (an array by its item count), else NAN.
     *
     * @param  array<string, mixed>  $values
     */
    private function numericOperand(?string $token, array $values): float
    {
        $token = trim((string) $token);

        if ($token === '') {
            return NAN;
        }

        if (preg_match('/^-?\d+(\.\d+)?$/', $token) === 1) {
            return (float) $token;
        }

        $raw = data_get($values, $token);

        return match (true) {
            $raw === null => NAN,
            \is_array($raw) => (float) \count($raw),
            \is_bool($raw) => $raw ? 1.0 : 0.0,
            \is_int($raw), \is_float($raw) => (float) $raw,
            // JavaScript's Number() reads a blank string as zero
            \is_string($raw) && trim($raw) === '' => 0.0,
            \is_string($raw) && is_numeric(trim($raw)) => (float) trim($raw),
            default => NAN,
        };
    }

    /**
     * JavaScript truthiness: false for null, false, 0, '' and NaN; true otherwise,
     * including for an empty array.
     */
    private function isTruthy(mixed $value): bool
    {
        if (\is_float($value)) {
            return $value !== 0.0 && ! is_nan($value);
        }

        return ! \in_array($value, [null, false, 0, ''], true);
    }

    /**
     * The editor's spellings of an answered "yes".
     */
    private function isTrue(mixed $value): bool
    {
        return \in_array($value, [true, 'true', 1, 1.0], true);
    }

    /**
     * The editor's spellings of an answered "no".
     */
    private function isFalse(mixed $value): bool
    {
        return \in_array($value, [false, 'false', 0, 0.0], true);
    }

    /**
     * Whether a value is set: a non-empty array, or anything but null and ''.
     */
    private function hasValue(mixed $value): bool
    {
        return \is_array($value) ? $value !== [] : $value !== null && $value !== '';
    }
}
