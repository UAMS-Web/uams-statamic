<?php

declare(strict_types=1);

namespace App\Modifiers;

use App\Support\People\CertificationRowKind;
use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: order a person's `certifications` rows by kind (UAMS-Web/uams-statamic-archive#2973).
 *
 * Rows come out grouped board certification, focused practice designation,
 * other certification, then assessment-based certificate, keeping entry order
 * within each group. Used by the provider "Credentials" block and the general
 * people profile, which render one "Certifications" list.
 */
class CertificationsByKind extends Modifier
{
    /**
     * Order certifications rows by kind.
     *
     * @param  mixed  $value  The augmented `certifications` field value
     * @param  array<int, mixed>  $params  Unused
     * @param  array<string, mixed>  $context  Unused Statamic modifier context
     * @return list<mixed> The same rows, grouped by kind
     */
    public function index(
        $value,
        array $params,
        array $context
    ): array {
        return CertificationRowKind::sortRows($value);
    }
}
