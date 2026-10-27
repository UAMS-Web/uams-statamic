<?php

declare(strict_types=1);

namespace App\Services\LinkHealth;

/**
 * Which occurrences broke SINCE the last run (UAMS-Web/uams-statamic-archive#1766).
 *
 * A standing report of four hundred broken links is ignored within a week. What an editor can act
 * on is "seven links broke since Tuesday", and producing that is the whole reason runs are
 * persisted rather than merely printed.
 *
 * The diff keys on the OCCURRENCE, not the bare URL, and that distinction is the one a naive
 * implementation loses. Keying on the URL would report a link as newly broken the moment it
 * appears in a second entry, even though it has been broken for weeks somewhere else — which is
 * precisely the noise the feature exists to remove.
 */
final class NewlyBrokenDiff
{
    /**
     * Occurrence keys whose outcome went from healthy to not-healthy between two runs.
     *
     * An occurrence with NO previous record is deliberately **not** newly broken. It is newly
     * SEEN, which is a different fact: a URL already broken elsewhere, appearing in one more
     * entry, has not broken since the last run and must not be reported as though it had.
     * Treating absence as a transition is the mistake that makes the list grow every time an
     * editor adds a page.
     *
     * @param  array<string, ProbeOutcome|ResolutionState>  $previous  Last run, keyed by occurrence.
     * @param  array<string, ProbeOutcome|ResolutionState>  $current  This run, keyed the same way.
     * @return list<string> Occurrence keys that broke since the previous run, in current order.
     */
    public static function between(array $previous, array $current): array
    {
        $newly = [];

        foreach ($current as $key => $outcome) {
            // No previous record: newly seen, not newly broken.
            if (! array_key_exists($key, $previous)) {
                continue;
            }

            // Was it healthy before, and is it not now? Both halves are required — a link that
            // was already broken and still is has not changed, and is reported by the standing
            // list rather than by this one.
            if (self::isHealthy($previous[$key]) && ! self::isHealthy($outcome)) {
                $newly[] = $key;
            }
        }

        return $newly;
    }

    /**
     * Whether an outcome counts as healthy for the purpose of a transition.
     *
     * `Redirected` and `Refused` are both healthy HERE, and for the same reason rather than by
     * coincidence: neither is a link an editor must fix today. A redirect resolves, and a guard
     * refusal means this install may not reach the host at all — reporting either as "broke since
     * Tuesday" would spend the editor's attention on something that did not break.
     */
    private static function isHealthy(ProbeOutcome|ResolutionState $outcome): bool
    {
        return match (true) {
            $outcome instanceof ProbeOutcome => ! $outcome->isBroken(),
            default => in_array($outcome, [
                ResolutionState::Ok,
                ResolutionState::Redirected,
                ResolutionState::NotChecked,
            ], true),
        };
    }
}
