<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

use App\Enums\SchemaType;

/**
 * Resolves the JSON-LD `@type` list for a clinical location from its editor-
 * selected `schema_medicalorganization_subtype`.
 *
 * The location blueprint constrains the subtype to a `MedicalOrganization`
 * descendant (Hospital, MedicalClinic, Pharmacy, Dentist, DiagnosticLab, …).
 * Four of the five default options are `LocalBusiness` descendants and need
 * only the single subtype; `DiagnosticLab` is a `MedicalOrganization` that is
 * NOT a `LocalBusiness`, so it is emitted as the multi-type
 * `[DiagnosticLab, LocalBusiness]` to advertise the local-business semantics
 * search engines expect for a physical clinical location (issue UAMS-Web/uams-statamic-archive#967).
 */
class MedicalOrganizationSubtypeAtTypeResolver
{
    /**
     * The Schema.org `LocalBusiness` type appended when the subtype is a
     * `MedicalOrganization` that does not already descend from `LocalBusiness`.
     */
    private const string LOCAL_BUSINESS = 'LocalBusiness';

    /**
     * Root type every accepted subtype must equal or descend from.
     */
    private const string MEDICAL_ORGANIZATION = 'MedicalOrganization';

    /**
     * Build the JSON-LD `@type` list for a `MedicalOrganization` subtype.
     *
     * Returns an empty list for an empty, unknown, or non-`MedicalOrganization`
     * subtype so the caller falls back to the entry's declared `@type`.
     *
     * @param  string  $subtype  Editor-selected Schema.org subtype name
     * @return list<string> `[$subtype]`, or `[$subtype, 'LocalBusiness']` when
     *                      the subtype is not already a `LocalBusiness`
     */
    public function resolve(string $subtype): array
    {
        $subtype = trim($subtype);

        // ancestorsOf yields [] for an empty or unknown type name, so the
        // family gate below rejects those without a separate guard.
        $ancestors = SchemaType::ancestorsOf($subtype);

        // Only augment genuine MedicalOrganization-family types; an empty,
        // unknown, or non-MedicalOrganization subtype has no MedicalOrganization
        // ancestor here, so the caller keeps the entry's declared @type.
        if (
            $subtype !== self::MEDICAL_ORGANIZATION
            && ! in_array(self::MEDICAL_ORGANIZATION, $ancestors, true)
        ) {
            return [];
        }

        // A subtype already under LocalBusiness needs only its single type.
        if (in_array(self::LOCAL_BUSINESS, $ancestors, true)) {
            return [$subtype];
        }

        // A MedicalOrganization that is not a LocalBusiness (e.g. DiagnosticLab)
        // is emitted as a multi-type node so it still advertises LocalBusiness.
        return [$subtype, self::LOCAL_BUSINESS];
    }
}
