<?php

declare(strict_types=1);

namespace App\Scopes;

use Illuminate\Support\Arr;
use Statamic\Facades\Term;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;
use Statamic\Stache\Query\TermQueryBuilder;
use Statamic\Taxonomies\TermCollection;

/**
 * Term query scope for the `credentials` taxonomy.
 *
 * Filters credential terms by the CTDL classification field so term pickers can
 * exclude non-certification credential types. The default handle keeps
 * Certification credentials only. The `credential_type_certification_or_certificate`
 * alias also keeps Certificate credentials, for the `certifications` term's
 * `credentials_term` picker, whose rule then accepts a Certificate only on an
 * assessment-based certificate (UAMS-Web/uams-statamic-archive#2972).
 */
class CredentialTypeCertification extends Scope
{
    /**
     * Scope handle for credentials whose CTDL type is Certification.
     *
     * @var string
     */
    protected static $handle = 'credential_type_certification';

    /**
     * Taxonomy handle this scope is intended for.
     */
    public const string TAXONOMY = 'credentials';

    /**
     * Term data field holding the CTDL credential type selection.
     *
     * Stored as `ctdl.ceterms_credential_type` (nested data).
     */
    public const string CTDL_CREDENTIAL_TYPE_FIELD = 'ctdl.ceterms_credential_type';

    /**
     * Root CTDL classification field (`ceterms_type` under the `ctdl` group).
     */
    public const string CTDL_CETERMS_TYPE_FIELD = 'ctdl.ceterms_type';

    /**
     * Expected `ceterms_type` root value before interpreting `ceterms_credential_type`.
     */
    public const string CTDL_CETERMS_TYPE_VALUE_CREDENTIAL = 'Credential';

    /**
     * Expected value for certification credential types.
     */
    public const string CTDL_CREDENTIAL_TYPE_VALUE_CERTIFICATION = 'Certification';

    /**
     * CTDL credential type for a certificate, which an assessment-based
     * certificate may link.
     */
    public const string CTDL_CREDENTIAL_TYPE_VALUE_CERTIFICATE = 'Certificate';

    /**
     * Scope alias that keeps Certification and Certificate credentials.
     */
    public const string ALIAS_CERTIFICATION_OR_CERTIFICATE = 'credential_type_certification_or_certificate';

    /**
     * Register the wider filter under a dedicated handle.
     *
     * @return array<int, string> Alias handles bound to this scope class.
     */
    public static function aliases(): array
    {
        return [
            self::ALIAS_CERTIFICATION_OR_CERTIFICATE,
        ];
    }

    /**
     * Apply taxonomy and CTDL type filters when this scope handle is active.
     *
     * @param  mixed  $query  Term index query or another builder (ignored).
     * @param  mixed  $values  Context for `Scope::apply`, including `queryScopes` when present.
     */
    public function apply(
        $query,
        $values
    ): void {
        // Check the builder is a term index query the scope can filter
        if (! $query instanceof Builder) {
            // Stop when the query is not a term index `Builder` this scope targets
            return;
        }

        // Check `Scope::apply` context is a map of request-style values
        if (! \is_array($values)) {
            // Stop when the scope context is not an array of request-style values
            return;
        }

        // Narrow the validated context to a string-keyed map for the typed helper
        /** @var array<string, mixed> $values */

        // Apply scope filters when the active scope list includes this handle
        $this->applyWithTypedTermQuery(
            $query,
            $values
        );
    }

    /**
     * Apply filters when the merged scope list includes this scope’s handle.
     *
     * @param  Builder|TermQueryBuilder  $query  Index query for taxonomy terms.
     * @param  array<string, mixed>  $values  `Scope::apply` context, including `queryScopes` when present.
     */
    private function applyWithTypedTermQuery(
        Builder|TermQueryBuilder $query,
        array $values
    ): void {
        // Normalize the active scope handles to a list of strings
        /** @var array<int, mixed> $queryScopes */
        $queryScopes = Arr::wrap(
            $values['queryScopes'] ?? []
        );

        // Resolve the credential types the active handle keeps
        $types = $this->activeCredentialTypes($queryScopes);

        // Check whether the scope handle is present in the active scope list
        if ($types === null) {
            // Stop when this scope handle is not enabled for the term index query
            unset($queryScopes);

            return;
        }

        // Constrain the index query to the credentials taxonomy
        $query->whereIn(
            'taxonomies',
            [
                self::TAXONOMY,
            ]
        );

        // Find all term ids in this taxonomy that match the CTDL credential types
        $ids = $this->matchingCredentialTermIds($types);

        // Constrain the index query to only matching term ids
        $query->whereIn(
            'id',
            $ids
        );

        // Unset local variables to free memory
        unset(
            $ids,
            $queryScopes,
            $types
        );
    }

    /**
     * Resolve all credential term ids whose CTDL credential type is one of the
     * given types.
     *
     * Uses full term loading and in-memory filtering to avoid relying on Stache
     * indexes for nested group keys (e.g. `ctdl.ceterms_credential_type`), which
     * are not queryable by default in `TermQueryBuilder`.
     *
     * @param  list<string>  $types  CTDL credential types to keep.
     * @return array<int, string>
     */
    private function matchingCredentialTermIds(array $types): array
    {
        // Build the credentials taxonomy query on a typed term query builder
        $query = Term::query();

        // Constrain the query to the credentials taxonomy
        $query->whereIn(
            'taxonomies',
            [
                self::TAXONOMY,
            ]
        );

        // Query all terms in the credentials taxonomy
        /** @var TermCollection $terms */
        $terms = $query->get();

        // Filter to terms whose nested CTDL value matches Credential + Certification
        $matching = $terms->filter(function ($term) use ($types): bool {
            /** @var \Statamic\Taxonomies\Term $term */
            // @phpstan-ignore method.notFound (Term::get() resolves at runtime via the augmentation __call seam to the default-locale term; the concrete Term declares no static get())
            $ctdl = $term->get('ctdl');

            // Require CTDL Credential root before reading credential subtype
            if (! \is_array($ctdl)) {
                unset($ctdl);

                return false;
            }

            $root = $ctdl['ceterms_type'] ?? null;

            if ($root !== self::CTDL_CETERMS_TYPE_VALUE_CREDENTIAL) {
                unset(
                    $ctdl,
                    $root
                );

                return false;
            }

            $type = $ctdl['ceterms_credential_type'] ?? null;

            unset(
                $ctdl,
                $root
            );

            return \in_array($type, $types, true);
        });

        // Extract Statamic ids (e.g. `credentials::atc`) for use in the term index query
        /** @var array<int, string> $ids */
        $ids = $matching->map(function ($term): string {
            /** @var \Statamic\Taxonomies\Term $term */
            $id = $term->id();

            // Coerce the term id to a string, matching the index query's id format
            return \is_scalar($id) ? (string) $id : '';
        })->values()->all();

        // Unset local variables to free memory
        unset(
            $terms,
            $matching
        );

        return $ids;
    }

    /**
     * Resolve the credential types kept by the first of this scope's handles
     * in the active scope list.
     *
     * @param  array<int, mixed>  $queryScopes  Active scope handles.
     * @return list<string>|null The CTDL credential types, or null when no
     *                           handle of this scope is active.
     */
    private function activeCredentialTypes(
        array $queryScopes
    ): ?array {
        // Scan each handle for the default handle or the wider alias
        foreach ($queryScopes as $queryScope) {
            // Keep Certification credentials only for the default handle
            if ($queryScope === static::handle()) {
                return [
                    self::CTDL_CREDENTIAL_TYPE_VALUE_CERTIFICATION,
                ];
            }

            // Keep Certification and Certificate credentials for the alias
            if ($queryScope === self::ALIAS_CERTIFICATION_OR_CERTIFICATE) {
                return [
                    self::CTDL_CREDENTIAL_TYPE_VALUE_CERTIFICATION,
                    self::CTDL_CREDENTIAL_TYPE_VALUE_CERTIFICATE,
                ];
            }
        }

        // Return null when neither handle is active
        return null;
    }
}
