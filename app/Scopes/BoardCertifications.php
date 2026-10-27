<?php

declare(strict_types=1);

namespace App\Scopes;

use Illuminate\Support\Arr;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;
use Statamic\Stache\Query\TermQueryBuilder;

/**
 * Term query scope for the `certifications` taxonomy.
 *
 * Limits terms by the blueprint `board_certification_query` flag and the
 * term's `credential_kind`. The default scope handle (`board_certifications`)
 * keeps only board certifications: terms whose flag is true and whose kind is
 * not a focused practice designation or an assessment-based certificate. The
 * `other_certifications` alias keeps every other certifications term, so the
 * two handles split the taxonomy with no term in both and none in neither.
 */
class BoardCertifications extends Scope
{
    /**
     * Blueprint boolean used to separate board vs other certifications.
     */
    public const string BOARD_CERTIFICATION_QUERY_FIELD = 'board_certification_query';

    /**
     * Term field recording what kind of credential a certifications term is.
     */
    public const string CREDENTIAL_KIND_FIELD = 'credential_kind';

    /**
     * `credential_kind` value for a certification, the only kind that can be a
     * board certification.
     */
    public const string CREDENTIAL_KIND_CERTIFICATION = 'certification';

    /**
     * `credential_kind` value for a focused practice designation.
     */
    public const string CREDENTIAL_KIND_FOCUSED_PRACTICE_DESIGNATION = 'focused_practice_designation';

    /**
     * `credential_kind` value for an assessment-based certificate.
     */
    public const string CREDENTIAL_KIND_ASSESSMENT_BASED_CERTIFICATE = 'assessment_based_certificate';

    /**
     * Kinds that can never be a board certification, whatever the stored flag.
     *
     * @var list<string>
     */
    public const array NON_BOARD_CREDENTIAL_KINDS = [
        self::CREDENTIAL_KIND_FOCUSED_PRACTICE_DESIGNATION,
        self::CREDENTIAL_KIND_ASSESSMENT_BASED_CERTIFICATE,
    ];

    /**
     * Taxonomy handle this scope is intended for.
     */
    public const string CERTIFICATIONS_TAXONOMY = 'certifications';

    /**
     * Query scope alias for every certifications term that is not a board
     * certification.
     */
    public const string ALIAS_OTHER_CERTIFICATIONS = 'other_certifications';

    /**
     * Register the inverted filter under a dedicated handle.
     *
     * @return array<int, string> Alias handles bound to this scope class.
     */
    public static function aliases(): array
    {
        return [
            self::ALIAS_OTHER_CERTIFICATIONS,
        ];
    }

    /**
     * Apply taxonomy and boolean filters when a matching scope handle is active.
     *
     * @param  mixed  $query  Term index query or another builder (ignored).
     * @param  mixed  $values  Context for `Scope::apply`, including `queryScopes` when present.
     */
    public function apply(
        $query,
        $values
    ): void {
        // Check whether the builder is a Statamic index query this scope supports
        if (! $query instanceof Builder) {
            return;
        }

        // Check whether scope context is an array (request-style values)
        if (! \is_array($values)) {
            return;
        }

        // Delegate filtering to the typed term-query implementation
        /** @var array<string, mixed> $values */
        $this->applyWithTypedTermQuery(
            $query,
            $values
        );
    }

    /**
     * Apply filters when the merged scope list includes this scope’s handles.
     *
     * @param  Builder|TermQueryBuilder  $query  Index query for taxonomy terms.
     * @param  array<string, mixed>  $values  `Scope::apply` context, including `queryScopes` when present.
     */
    private function applyWithTypedTermQuery(
        Builder|TermQueryBuilder $query,
        array $values
    ): void {
        // Resolve desired boolean from active scope handles in context
        $desiredTrue = $this->desiredBoardCertificationQueryTrue($values);

        // Check whether any board/other certification handle matched
        if ($desiredTrue === null) {
            return;
        }

        // Restrict results to the certifications taxonomy
        $query->whereIn(
            'taxonomies',
            [
                self::CERTIFICATIONS_TAXONOMY,
            ]
        );

        // Check whether the active handle selects board certifications
        if ($desiredTrue) {
            // Keep terms whose flag is true and whose kind can be a board certification
            $query->where(
                self::BOARD_CERTIFICATION_QUERY_FIELD,
                true
            );
            $query->whereNotIn(
                self::CREDENTIAL_KIND_FIELD,
                self::NON_BOARD_CREDENTIAL_KINDS
            );

            return;
        }

        // Keep every term the board handle excludes: a flag that is not true, or
        // a kind that can never be a board certification
        $query->where(function ($nested): void {
            /** @var Builder $nested */
            $nested->where(
                self::BOARD_CERTIFICATION_QUERY_FIELD,
                '!=',
                true
            );
            $nested->orWhereIn(
                self::CREDENTIAL_KIND_FIELD,
                self::NON_BOARD_CREDENTIAL_KINDS
            );
        });
    }

    /**
     * Whether stored term data makes the term a board certification.
     *
     * The rules and the query scope agree on this definition: the flag must be
     * strictly true and the kind must not be one that can never be a board
     * certification. A term with no kind is treated as a certification.
     *
     * @param  mixed  $boardCertificationQuery  The term's `board_certification_query` value.
     * @param  mixed  $credentialKind  The term's `credential_kind` value.
     * @return bool True when the term is a board certification.
     */
    public static function isBoardCertification(
        mixed $boardCertificationQuery,
        mixed $credentialKind
    ): bool {
        return $boardCertificationQuery === true
            && ! \in_array($credentialKind, self::NON_BOARD_CREDENTIAL_KINDS, true);
    }

    /**
     * Resolve whether the active handle requests `true` or `false` on the field.
     *
     * Uses the first matching handle in `queryScopes` order.
     *
     * @param  array<string, mixed>  $values  Scope context including `queryScopes`.
     * @return bool|null True or false when a known handle matches, otherwise null.
     */
    private function desiredBoardCertificationQueryTrue(
        array $values
    ): ?bool {
        // Normalize `queryScopes` to a list, even when it is a single value
        $queryScopes = Arr::wrap(
            $values['queryScopes'] ?? []
        );

        // Resolve the default scope handle for the board-certifications filter
        $boardHandle = static::handle();

        // Walk scope handles in order and return the first board/other match
        foreach ($queryScopes as $handle) {
            // Skip entries that are not a string handle
            if (! \is_string($handle)) {
                continue;
            }

            // Check if the active handle selects non-board certifications only
            if ($handle === self::ALIAS_OTHER_CERTIFICATIONS) {
                unset(
                    $queryScopes,
                    $boardHandle,
                    $handle
                );

                return false;
            }

            // Check if the active handle selects board certifications only
            if ($handle === $boardHandle) {
                unset(
                    $queryScopes,
                    $boardHandle,
                    $handle
                );

                return true;
            }
        }

        // Unset local variables after no board or other handle matched
        unset(
            $queryScopes,
            $boardHandle
        );

        // Return null when `queryScopes` omitted both known handles
        return null;
    }
}
