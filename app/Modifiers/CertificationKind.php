<?php

declare(strict_types=1);

namespace App\Modifiers;

use App\Support\People\CertificationRowKind;
use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: the kind of credential a `certifications` row records (UAMS-Web/uams-statamic-archive#2973).
 *
 * Applied to the row's `activity` value, in whatever shape the context holds it
 * (a term, a collection of terms, or the stored slug), with the row's set handle
 * as an optional parameter: `{{ activity | certification_kind(type) }}`. Returns
 * `board_certification` for a board set, otherwise the term's `credential_kind`,
 * falling back to `certification`. The certification schema template uses it to
 * leave a certificate's audit and expiration dates out of its JSON-LD.
 */
class CertificationKind extends Modifier
{
    /**
     * Resolve the kind of a row's credential.
     *
     * @param  mixed  $value  The row's `activity` value
     * @param  array<int, mixed>  $params  The row's replicator set handle, optional
     * @param  array<string, mixed>  $context  Unused Statamic modifier context
     * @return string `board_certification`, or a `credential_kind` value
     */
    public function index(
        $value,
        array $params,
        array $context
    ): string {
        return CertificationRowKind::forActivity($value, $params[0] ?? null);
    }
}
