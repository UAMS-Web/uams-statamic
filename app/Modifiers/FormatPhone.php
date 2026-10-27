<?php

declare(strict_types=1);

namespace App\Modifiers;

use App\Services\PhoneUtil;
use Statamic\Modifiers\Modifier;

/**
 * Formats E.164 phone strings for display using PhoneUtil::formatForDisplay().
 *
 * Stored values from the Phone fieldtype are E.164 (e.g., `+15016868998`). This
 * modifier renders human-readable national or international forms.
 *
 * Usage (positional first parameter only; no `key="value"` pipe segments):
 *
 * - `{{ telephone | format_phone }}` — national (U.S. → `501-686-8998`)
 * - `{{ telephone | format_phone:international }}` — e.g., `+1 501-686-8998`
 */
class FormatPhone extends Modifier
{
    /**
     * Format a phone value for Antlers output.
     *
     * @param  mixed  $value  E.164 or other phone string
     * @param  array<int|string, mixed>  $params  `0` = `national` or `international`
     * @param  array<int|string, mixed>  $context  Unused
     * @return string Display string, or empty when input is blank
     */
    public function index(
        mixed $value,
        array $params,
        array $context
    ): string {
        // Normalize to trimmed string
        $e164 = \trim(\is_scalar($value) ? (string) $value : '');

        // Return empty when there is nothing to format. formatForDisplay('') is
        // also empty, so without this return the result is the same.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (
            // A blank number that falls through is formatted as '' as well
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            $e164 === ''
        ) {
            return '';
        }

        // Resolve display mode from first positional parameter
        $mode = isset($params[0]) && \is_scalar($params[0]) ? \strtolower((string) $params[0]) : 'national';
        $format = $mode === 'international' ? 'international' : 'national';

        // Delegate to shared phone formatting
        return PhoneUtil::formatForDisplay(
            $e164,
            $format
        );
    }
}
