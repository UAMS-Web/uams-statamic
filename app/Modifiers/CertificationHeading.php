<?php

declare(strict_types=1);

namespace App\Modifiers;

use App\Support\People\CertificationRowKind;
use Statamic\Fields\Value;
use Statamic\Modifiers\Modifier;
use Statamic\Taxonomies\LocalizedTerm;

/**
 * Antlers modifier: the heading a `certifications` row renders under (UAMS-Web/uams-statamic-archive#2973).
 *
 * Applied to the row's `activity` term with the row's set handle as its
 * parameter: `{{ activity | certification_heading(type) }}`. A board row reads
 * "Board Certification in {name}", a focused practice designation "Focused
 * Practice Designation in {name}", an assessment-based certificate its name
 * with the kind as a qualifier, and any other row its bare name. An `activity`
 * that is a plain array with a `name` (data that never went through
 * augmentation) has no kind to read, so it takes its kind from the set alone,
 * as the template's inline heading did before UAMS-Web/uams-statamic-archive#2973.
 */
class CertificationHeading extends Modifier
{
    /**
     * Build the row heading from its term and set handle.
     *
     * @param  mixed  $value  The row's augmented `activity` term, or an array
     *                        carrying its `name`
     * @param  array<int, mixed>  $params  The row's replicator set handle
     * @param  array<string, mixed>  $context  Unused Statamic modifier context
     * @return string The heading, or an empty string when the term is missing
     */
    public function index(
        $value,
        array $params,
        array $context
    ): string {
        // Unwrap a field value to the term it carries
        if ($value instanceof Value) {
            $value = $value->value();
        }

        // Head an unaugmented array by its name, with its kind from the set alone
        if (\is_array($value) && \is_string($value['name'] ?? null)) {
            return CertificationRowKind::heading(
                CertificationRowKind::forActivity(null, $params[0] ?? null),
                $value['name']
            );
        }

        // Render nothing for a row whose term does not resolve
        if (! $value instanceof LocalizedTerm) {
            return '';
        }

        // Read the term's display name
        $name = $value->get('name');

        return CertificationRowKind::heading(
            CertificationRowKind::forActivity($value, $params[0] ?? null),
            \is_string($name) ? $name : ''
        );
    }
}
