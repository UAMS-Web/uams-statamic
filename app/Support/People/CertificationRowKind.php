<?php

declare(strict_types=1);

namespace App\Support\People;

use App\Scopes\BoardCertifications;
use Illuminate\Support\Collection as IlluminateCollection;
use Statamic\Facades\Term;
use Statamic\Fields\Value;
use Statamic\Fields\Values;
use Statamic\Taxonomies\LocalizedTerm;

/**
 * Classifies a person's `certifications` replicator row by the kind of credential
 * it records, and orders and labels rows by that kind (UAMS-Web/uams-statamic-archive#2973).
 *
 * A row is a board certification when its set is `board_certification`. Any
 * other row takes its kind from its certifications term's `credential_kind`,
 * and a term with no recognized kind counts as a certification. The groups
 * render in one list, ordered board certification, focused practice
 * designation, other certification, then assessment-based certificate.
 */
final class CertificationRowKind
{
    /**
     * Replicator set handle of a board certification row, and the kind returned
     * for one.
     */
    public const string BOARD_CERTIFICATION = 'board_certification';

    /**
     * Display label of an assessment-based certificate, shown as the qualifier
     * on a certificate row.
     */
    public const string CERTIFICATE_LABEL = 'Assessment-Based Certificate';

    /**
     * Display label of a focused practice designation, used as the heading
     * prefix on a designation row.
     */
    public const string FOCUSED_PRACTICE_DESIGNATION_LABEL = 'Focused Practice Designation';

    /**
     * Render order of each kind within the one "Certifications" list.
     *
     * @var array<string, int>
     */
    private const array ORDER = [
        self::BOARD_CERTIFICATION => 0,
        BoardCertifications::CREDENTIAL_KIND_FOCUSED_PRACTICE_DESIGNATION => 1,
        BoardCertifications::CREDENTIAL_KIND_CERTIFICATION => 2,
        BoardCertifications::CREDENTIAL_KIND_ASSESSMENT_BASED_CERTIFICATE => 3,
    ];

    /**
     * Resolve the kind of a certifications row.
     *
     * @param  mixed  $row  Stored row array or augmented replicator `Values`
     * @return string `board_certification`, or a `credential_kind` value
     */
    public static function forRow(mixed $row): string
    {
        return self::forActivity(
            self::rowRaw($row, 'activity'),
            self::rowRaw($row, 'type')
        );
    }

    /**
     * Resolve the kind of a row from its activity term and its set handle.
     *
     * @param  mixed  $activity  A certifications term, or a raw stored term value
     * @param  mixed  $setType  The row's replicator set handle
     * @return string `board_certification`, or a `credential_kind` value
     */
    public static function forActivity(mixed $activity, mixed $setType): string
    {
        // Treat a board set as a board certification whatever its term says
        if ($setType === self::BOARD_CERTIFICATION) {
            return self::BOARD_CERTIFICATION;
        }

        // Read the term's recorded kind, if the term resolves
        $kind = self::termKind($activity);

        // Fall back to a plain certification for a missing or unknown kind
        // The kind is a string or null, which never loosely equals a kind string
        // @pest-mutate-ignore: TrueToFalse
        return \in_array($kind, BoardCertifications::NON_BOARD_CREDENTIAL_KINDS, true)
            ? $kind
            : BoardCertifications::CREDENTIAL_KIND_CERTIFICATION;
    }

    /**
     * Whether a row records an assessment-based certificate.
     *
     * @param  mixed  $row  Stored row array or augmented replicator `Values`
     */
    public static function isCertificate(mixed $row): bool
    {
        return self::forRow($row) === BoardCertifications::CREDENTIAL_KIND_ASSESSMENT_BASED_CERTIFICATE;
    }

    /**
     * Order certifications rows by kind, keeping entry order within each kind.
     *
     * @param  mixed  $rows  A list of rows, or a collection of them
     * @return list<mixed> The same rows, grouped by kind
     */
    public static function sortRows(mixed $rows): array
    {
        // Unwrap a collection to its items
        if ($rows instanceof IlluminateCollection) {
            $rows = $rows->all();
        }

        // Leave anything that is not a list of rows as an empty list
        if (! \is_array($rows)) {
            return [];
        }

        // Pair each row with its rank
        $ranked = [];

        foreach ($rows as $row) {
            $ranked[] = [self::ORDER[self::forRow($row)], $row];
        }

        // Sort by rank; usort is stable, so each group keeps its entry order
        usort(
            $ranked,
            static fn (array $a, array $b): int => $a[0] <=> $b[0]
        );

        return array_column($ranked, 1);
    }

    /**
     * Build the heading a row renders under in the "Certifications" list.
     *
     * A focused practice designation reads "Focused Practice Designation in
     * {name}", without repeating the kind when the name already ends with it.
     * An assessment-based certificate keeps its official name and adds the kind
     * as a qualifier unless the name already states it.
     *
     * @param  string  $kind  The row's kind, from `forRow()` or `forActivity()`
     * @param  string  $name  The certifications term's name
     * @return string The row heading
     */
    public static function heading(string $kind, string $name): string
    {
        // Prefix a board certification exactly as before
        if ($kind === self::BOARD_CERTIFICATION) {
            return 'Board Certification in '.$name;
        }

        // Prefix a designation, dropping a trailing repeat of the kind
        if ($kind === BoardCertifications::CREDENTIAL_KIND_FOCUSED_PRACTICE_DESIGNATION) {
            return self::FOCUSED_PRACTICE_DESIGNATION_LABEL.' in '.self::withoutTrailing(
                $name,
                ' '.self::FOCUSED_PRACTICE_DESIGNATION_LABEL
            );
        }

        // Qualify a certificate unless its name already names the kind
        if (
            $kind === BoardCertifications::CREDENTIAL_KIND_ASSESSMENT_BASED_CERTIFICATE
            && ! str_contains(strtolower($name), strtolower(self::CERTIFICATE_LABEL))
        ) {
            return $name.' ('.self::CERTIFICATE_LABEL.')';
        }

        // Keep every other row's bare name
        return $name;
    }

    /**
     * Remove a suffix from a name when the name ends with it and keeps
     * something in front of it.
     */
    private static function withoutTrailing(string $name, string $suffix): string
    {
        // Compare case-insensitively so stored capitalization does not matter
        if (
            \strlen($name) > \strlen($suffix)
            && str_ends_with(strtolower($name), strtolower($suffix))
        ) {
            return substr($name, 0, -\strlen($suffix));
        }

        return $name;
    }

    /**
     * Read a certifications term's `credential_kind`.
     *
     * @param  mixed  $activity  A term, a list or collection of terms, or a raw stored
     *                           term value
     * @return ?string The stored kind, or null when the term does not resolve
     */
    private static function termKind(mixed $activity): ?string
    {
        // Unwrap a field value to its stored value, which resolves without augmenting
        if ($activity instanceof Value) {
            $activity = $activity->raw();
        }

        // Unwrap a collection to its items, as a schema template's overlay hands a term over
        if ($activity instanceof IlluminateCollection) {
            $activity = $activity->all();
        }

        // Take the first term from a list of terms
        // A list of slugs or ids resolves to the same first term either way
        // @pest-mutate-ignore: InstanceOfToTrue
        if (\is_array($activity) && reset($activity) instanceof LocalizedTerm) {
            $activity = reset($activity);
        }

        // Resolve a raw stored value (a slug, an id, or a list of them) to its term
        if (\is_string($activity) || \is_array($activity)) {
            $id = PersonKnowsAboutComputer::normalizeTermFieldRaw(
                $activity,
                BoardCertifications::CERTIFICATIONS_TAXONOMY
            )[0] ?? null;

            $activity = $id === null ? null : Term::find($id);
        }

        // Read the kind as stored, ignoring anything that is not a string
        if (! $activity instanceof LocalizedTerm) {
            return null;
        }

        $kind = $activity->get(BoardCertifications::CREDENTIAL_KIND_FIELD);

        return \is_string($kind) ? $kind : null;
    }

    /**
     * Read one raw field from a stored row array or an augmented row.
     */
    private static function rowRaw(mixed $row, string $key): mixed
    {
        // Read an augmented row's raw value, so its term is not augmented first
        if ($row instanceof Values) {
            return $row->raw($key);
        }

        return \is_array($row) ? ($row[$key] ?? null) : null;
    }
}
