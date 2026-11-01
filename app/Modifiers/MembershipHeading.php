<?php

declare(strict_types=1);

namespace App\Modifiers;

use Illuminate\Support\Str;
use Statamic\Fields\Value;
use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: the heading a `member_of` row renders under (UAMS-Web/uams-statamic#306).
 *
 * Applied to the row's organization name with the row's level key as its
 * parameter: `{{ organization.name | membership_heading(membership_level.value) }}`.
 * A Member row, or one with no level, reads as the organization's name alone; any
 * other level reads "Fellow of the {organization}". A leading "The" in the name is
 * dropped, so The Hip Society reads "Fellow of the Hip Society".
 */
class MembershipHeading extends Modifier
{
    /**
     * Build the row heading from the organization's name and the level key.
     *
     * @param  mixed  $value  The organization's name
     * @param  array<int, mixed>  $params  The row's `membership_level` key
     * @param  array<string, mixed>  $context  Unused Statamic modifier context
     * @return string The heading, or an empty string when there is no name
     */
    public function index(
        $value,
        array $params,
        array $context
    ): string {
        // Unwrap a field value to the name it carries
        if ($value instanceof Value) {
            $value = $value->value();
        }

        // Render nothing for a row whose organization does not resolve
        if (! \is_string($value) || trim($value) === '') {
            return '';
        }

        $name = trim($value);
        $level = $params[0] ?? null;

        // A Member row is the organization alone
        if (! \is_string($level) || $level === '' || $level === 'member') {
            return $name;
        }

        return Str::headline($level).' of the '.preg_replace('/^the\s+/i', '', $name);
    }
}
