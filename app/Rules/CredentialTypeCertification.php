<?php

declare(strict_types=1);

namespace App\Rules;

use App\Scopes\BoardCertifications;
use App\Scopes\CredentialTypeCertification as CredentialTypeCertificationScope;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;
use Statamic\Facades\Term;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;

/**
 * Validate `credentials` taxonomy terms whose CTDL type is Certification.
 *
 * Intended to pair with the `credential_type_certification` query scope so the
 * term picker and stored values enforce the same constraint.
 *
 * Given a kind field handle (`credential_type_certification:credential_kind`),
 * the rule also accepts a Certificate credential when that sibling field reads
 * `assessment_based_certificate`, which pairs with the scope's
 * `credential_type_certification_or_certificate` alias (UAMS-Web/uams-statamic-archive#2972).
 */
class CredentialTypeCertification implements ValidationRule, ValidatorAwareRule
{
    /**
     * Sibling field handle holding the certification's credential kind, or an
     * empty string when only Certification credentials are accepted.
     */
    private readonly string $kindFieldHandle;

    /**
     * Active Laravel validator, read for the sibling kind field's value.
     */
    private ?Validator $validator = null;

    /**
     * Create a new rule instance.
     *
     * @param  string|null  $kindFieldHandle  Sibling field handle holding the
     *                                        credential kind, or null or blank to
     *                                        accept Certification credentials
     *                                        only.
     */
    public function __construct(
        ?string $kindFieldHandle = null
    ) {
        // Store the handle trimmed, so a blank one names no field
        $this->kindFieldHandle = trim((string) $kindFieldHandle);
    }

    /**
     * Receive the validator instance from Laravel.
     *
     * @param  Validator  $validator  Current validator.
     */
    public function setValidator(
        Validator $validator
    ): static {
        $this->validator = $validator;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute  The name of the attribute being validated
     * @param  mixed  $value  The field value (term id string or list of them)
     * @param  Closure  $fail  The closure to call if validation fails
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        // Normalize the field value to a list of string term identifiers
        $ids = $this->normalizeTermIds($value);

        // Resolve the CTDL credential types the sibling kind allows
        $allowedTypes = $this->allowedCredentialTypes();

        // Validate each selected term against taxonomy and CTDL type
        foreach ($ids as $id) {
            // Load the term by Statamic id (e.g. `credentials::slug`)
            $term = Term::find($id);

            // Check whether the term exists in the repository
            if ($term === null) {
                // Record a not-found error for ids the repository cannot resolve
                $fail(__(
                    'statamic::validation.credential_type_certification.not_found',
                    [
                        'id' => $id,
                    ]
                ));

                return;
            }

            // Narrow the repository result to `LocalizedTerm` or `Term`
            \assert(
                $term instanceof LocalizedTerm
                || $term instanceof TaxonomyTerm,
                'Term::find must return a LocalizedTerm or Term instance.'
            );

            // Resolve the taxonomy handle once for comparison and error text.
            // A scalar compares unequal and renders the same with or without the
            // cast, so its removal is equivalent.
            $taxonomyHandleRaw = $term->taxonomyHandle();
            // @pest-mutate-ignore: RemoveStringCast
            $taxonomyHandle = \is_scalar($taxonomyHandleRaw)
                ? (string) $taxonomyHandleRaw
                : '';

            // Check whether the term belongs to the credentials taxonomy
            if ($taxonomyHandle !== CredentialTypeCertificationScope::TAXONOMY) {
                // Record a wrong-taxonomy error with the resolved handle for context
                $fail(__(
                    'statamic::validation.credential_type_certification.wrong_taxonomy',
                    [
                        'id' => $id,
                        'taxonomy' => $taxonomyHandle,
                    ]
                ));

                return;
            }

            // Read the CTDL payload from term data. `Term` has no typed `get()`;
            // its `__call` proxies to the default-locale term at runtime.
            $ctdl = $term->get('ctdl'); // @phpstan-ignore method.notFound

            // Require CTDL Credential root before reading credential subtype values
            $rootTypeRaw = \is_array($ctdl)
                ? ($ctdl['ceterms_type'] ?? null)
                : null;

            // Check whether the term is classified as a Credential at the CTDL root
            if ($rootTypeRaw !== CredentialTypeCertificationScope::CTDL_CETERMS_TYPE_VALUE_CREDENTIAL) {
                // Record a mismatch when subtype checks do not apply. The translator
                // renders a scalar replacement as a string, so the cast's removal
                // is equivalent.
                // @pest-mutate-ignore: RemoveStringCast
                $fail(__(
                    'statamic::validation.credential_type_certification.wrong_ctdl_ceterms_type',
                    [
                        'id' => $id,
                        'root' => \is_scalar($rootTypeRaw) ? (string) $rootTypeRaw : '',
                        'expected' => CredentialTypeCertificationScope::CTDL_CETERMS_TYPE_VALUE_CREDENTIAL,
                    ]
                ));

                return;
            }

            // Read the credential type from the CTDL payload. The Credential
            // root check above guarantees `$ctdl` is an array here.
            $typeRaw = $ctdl['ceterms_credential_type'] ?? null;

            // Check whether the stored CTDL type is one the kind allows
            if (! \in_array($typeRaw, $allowedTypes, true)) {
                // Record a type mismatch against the required CTDL classification.
                // The translator renders a scalar replacement as a string, so the
                // cast's removal is equivalent.
                // @pest-mutate-ignore: RemoveStringCast
                $fail(__(
                    'statamic::validation.credential_type_certification.wrong_credential_type',
                    [
                        'id' => $id,
                        'type' => \is_scalar($typeRaw) ? (string) $typeRaw : '',
                        'expected' => implode(' or ', $allowedTypes),
                    ]
                ));

                return;
            }

            unset(
                $ctdl,
                $rootTypeRaw,
                $typeRaw
            );
        }
    }

    /**
     * Resolve the CTDL credential types the sibling credential kind allows.
     *
     * @return list<string> Certification alone, or Certification and
     *                      Certificate for an assessment-based certificate.
     */
    private function allowedCredentialTypes(): array
    {
        // Read the sibling kind field when a validator is present; a blank handle
        // names no field, so it reads null
        $kind = $this->validator instanceof Validator
            ? ($this->validator->getData()[$this->kindFieldHandle] ?? null)
            : null;

        // Allow a Certificate credential on an assessment-based certificate
        if ($kind === BoardCertifications::CREDENTIAL_KIND_ASSESSMENT_BASED_CERTIFICATE) {
            return [
                CredentialTypeCertificationScope::CTDL_CREDENTIAL_TYPE_VALUE_CERTIFICATION,
                CredentialTypeCertificationScope::CTDL_CREDENTIAL_TYPE_VALUE_CERTIFICATE,
            ];
        }

        // Allow a Certification credential otherwise
        return [
            CredentialTypeCertificationScope::CTDL_CREDENTIAL_TYPE_VALUE_CERTIFICATION,
        ];
    }

    /**
     * Build a list of non-empty string term ids from the field value.
     *
     * @param  mixed  $value  Raw field value (string, list of strings, etc.).
     * @return array<int, string> Term ids to validate.
     */
    private function normalizeTermIds(mixed $value): array
    {
        // Check if the field submitted a list of term identifiers
        if (\is_array($value)) {
            // Initialize the normalized id list for array input
            $out = [];
            // Walk each array entry and collect usable string ids
            foreach ($value as $item) {
                // Skip entries that are not a non-empty string handle
                if (! \is_string($item)) {
                    continue;
                }

                if ($item === '') {
                    continue;
                }

                // Append a normalized string id to the output list
                $out[] = $item;
            }

            // Return all string ids extracted from the array
            return $out;
        }

        // Check if the field submitted a single non-empty string id
        if (
            \is_string($value)
            && $value !== ''
        ) {
            // Return a one-element list for scalar string input
            return [
                $value,
            ];
        }

        // Return an empty list when the value is not a usable string or list
        return [];
    }
}
