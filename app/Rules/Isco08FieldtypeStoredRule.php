<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\Isco08;
use App\Support\Isco08FieldtypeStorage;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates cross-tier consistency for an `isco_08` field (after per-key rules).
 */
final readonly class Isco08FieldtypeStoredRule implements ValidationRule
{
    public function __construct(
        private bool $required,
    ) {}

    /**
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Check whether the field value is an array-shaped payload
        if (! \is_array($value)) {
            $fail(__('statamic::fieldtypes.isco_08.validation.not_array'));

            return;
        }

        // Normalize tiers so validation ignores stale `value` and invalid branches
        /** @var array<string, mixed> $value */
        $normalized = Isco08FieldtypeStorage::normalizeChain($value);

        // Allow an entirely empty field when the blueprint does not require it
        if (! $this->required && Isco08FieldtypeStorage::isFullyEmpty($normalized)) {
            return;
        }

        // Require at least a valid major when the field is marked required
        if ($this->required && Isco08FieldtypeStorage::isFullyEmpty($normalized)) {
            $fail(__('statamic::fieldtypes.isco_08.validation.required_major'));

            return;
        }

        // Check if normalization rejected the major (invalid or missing)
        if ($normalized === []) {
            $fail(__('statamic::fieldtypes.isco_08.validation.invalid_major'));

            return;
        }

        // Coerce the major tier to a trimmed string or null
        $major = Isco08FieldtypeStorage::normalizeTierCode($normalized['major_group'] ?? null);

        // Check if the major tier is missing after normalization
        if ($major === null) {
            $fail(__('statamic::fieldtypes.isco_08.validation.required_major'));

            return;
        }

        // Resolve the enum case for the major code
        $majorCase = Isco08::tryFrom($major);

        // Check whether the major exists and is a level-1 structure row
        if ($majorCase === null || $majorCase->level() !== 1) {
            $fail(__('statamic::fieldtypes.isco_08.validation.invalid_major'));

            return;
        }

        // Read the sub-major tier when present
        $sub = Isco08FieldtypeStorage::normalizeTierCode($normalized['sub_major_group'] ?? null);

        // Validate deeper tiers only when a sub-major code is selected
        if ($sub !== null) {
            // Resolve the enum case for the sub-major code
            $subCase = Isco08::tryFrom($sub);

            // Check whether the sub-major exists and belongs under this major
            if ($subCase === null || $subCase->parent() !== $major) {
                $fail(__('statamic::fieldtypes.isco_08.validation.invalid_sub_major'));

                return;
            }

            // Read the minor tier when present
            $minor = Isco08FieldtypeStorage::normalizeTierCode($normalized['minor_group'] ?? null);

            // Validate unit tier only when a minor code is selected
            if ($minor !== null) {
                // Resolve the enum case for the minor code
                $minorCase = Isco08::tryFrom($minor);

                // Check whether the minor exists and belongs under this sub-major
                if ($minorCase === null || $minorCase->parent() !== $sub) {
                    $fail(__('statamic::fieldtypes.isco_08.validation.invalid_minor'));

                    return;
                }

                // Read the unit tier when present
                $unit = Isco08FieldtypeStorage::normalizeTierCode($normalized['unit_group'] ?? null);

                // Validate the unit only when a unit code is provided
                if ($unit !== null) {
                    // Resolve the enum case for the unit code
                    $unitCase = Isco08::tryFrom($unit);

                    // Check whether the unit exists and belongs under this minor
                    if ($unitCase === null || $unitCase->parent() !== $minor) {
                        $fail(__('statamic::fieldtypes.isco_08.validation.invalid_unit'));

                        return;
                    }
                }
            }
        }
    }
}
