<?php

declare(strict_types=1);

namespace App\Support\Forms;

use App\Listeners\EnforceChoiceSubmissionLimits;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Statamic\Contracts\Forms\Submission;
use Statamic\Fields\Field;
use Statamic\Forms\Form;
use UAMSWeb\FormSettings\Support\FormRestrictions;

/**
 * Evaluates per-choice submission limits for a form's choice fields — the Statamic analog of
 * the Gravity Forms "Limit Submissions" (per-choice) perk (UAMS-Web/uams-statamic-archive#292).
 *
 * A choice field (`radio` / `checkboxes` / `select`) opts in with `enable_choice_limits: true`
 * and a `choice_limits` grid of `{choice: <option value>, limit: <int>}` rows (approach A — an
 * additive config that leaves the flat options map untouched; the shape the `wordpress-importer`
 * `FieldtypeMapping` targets from `gwlimitchoices_enableLimits` + each choice's `limit`).
 *
 * {@see self::fullChoices()} returns the option values that have reached their cap across the
 * form's existing submissions. It backs both the submit-time gate ({@see EnforceChoiceSubmissionLimits},
 * which rejects a POST selecting a full choice) and the render-time state (each choice template
 * disables + flags the full options) from the same computation. The **submit-time gate is
 * authoritative**; the render-time disabling is a best-effort UX hint that may differ in edge cases
 * (e.g. the Antlers `contains` check on distinct numeric-string option keys like `'1'` vs `'01'`),
 * which is safe because the server-side gate makes the final decision.
 *
 * Accepted gap: the count is a best-effort read-then-act on the flat-file submission store, not
 * an atomic counter — two simultaneous submissions can each observe a sub-limit count and both
 * store, slightly overshooting the cap. This matches the accepted race in the form-level
 * {@see FormRestrictions} gate from the form settings add-on; for these public sign-up forms (no seats or money at stake) the
 * race is accepted rather than serialized behind a lock.
 */
final class ChoiceSubmissionLimits
{
    /**
     * Whether a field opts into per-choice limits and configures at least one.
     *
     * @param  Field  $field  The blueprint field to inspect.
     */
    public function enabledFor(Field $field): bool
    {
        return $this->enabled($field->get('enable_choice_limits'))
            && $this->limits($field->get('choice_limits')) !== [];
    }

    /**
     * The option values that have reached their configured cap across the form's submissions.
     *
     * Counts the current (unstored) submission's own selection out, since Statamic's
     * `FormSubmitted` fires before the submission is persisted — so the Nth+1 submission of a
     * choice capped at N sees a count of N and is reported full.
     *
     * @param  Form  $form  The form whose submissions to count.
     * @param  Field  $field  The choice field carrying the limit config.
     * @return list<string> The full option values (empty when none are capped/reached).
     */
    public function fullChoices(Form $form, Field $field): array
    {
        $limits = $this->limits($field->get('choice_limits'));
        $handle = $field->handle();

        if ($limits === [] || ! \is_string($handle) || ! $this->enabled($field->get('enable_choice_limits'))) {
            return [];
        }

        $counts = $this->counts($form, $handle, array_keys($limits));

        // A choice is full once its selection count meets or exceeds its cap.
        $full = [];
        foreach ($limits as $choice => $limit) {
            if (($counts[$choice] ?? 0) >= $limit) {
                $full[] = $choice;
            }
        }

        return $full;
    }

    /**
     * Whether any of a submitted selection's values target a currently-full choice.
     *
     * @param  Form  $form  The form being submitted to.
     * @param  Field  $field  The choice field carrying the limit config.
     * @param  mixed  $submitted  The submitted value (scalar for radio/select, array for checkboxes).
     */
    public function selectionHitsFullChoice(Form $form, Field $field, mixed $submitted): bool
    {
        $full = $this->fullChoices($form, $field);

        if ($full === []) {
            return false;
        }

        // Normalize scalar and array selections to a list of chosen option values.
        $selected = collect(Arr::wrap($submitted))
            ->filter(fn ($value): bool => \is_scalar($value))
            ->map(fn ($value): string => (string) $value);

        return $selected->intersect($full)->isNotEmpty();
    }

    /**
     * Tally how many stored submissions selected each of the given choice values.
     *
     * @param  Form  $form  The form whose submissions to tally.
     * @param  string  $fieldHandle  The choice field's handle.
     * @param  list<string>  $choices  The option values to count.
     * @return array<string, int> Map of option value to selection count.
     */
    private function counts(Form $form, string $fieldHandle, array $choices): array
    {
        // Seed every tracked choice at zero so an unselected choice reads 0, not missing.
        $counts = array_fill_keys($choices, 0);

        foreach ($form->submissions() as $submission) {
            if (! $submission instanceof Submission) {
                continue;
            }

            // A checkboxes value is an array of selections; radio/select is a scalar.
            foreach (Arr::wrap($submission->get($fieldHandle)) as $value) {
                if (! \is_scalar($value)) {
                    continue;
                }

                $value = (string) $value;

                if (\array_key_exists($value, $counts)) {
                    $counts[$value]++;
                }
            }
        }

        return $counts;
    }

    /**
     * Normalize the `choice_limits` grid rows into a `{choice: positive-int}` map.
     *
     * Rows without a scalar `choice` or a positive integer `limit` are dropped, so an
     * unconfigured or zero limit never caps a choice.
     *
     * @param  mixed  $rows  The stored `choice_limits` value (a list of `{choice, limit}` rows).
     * @return array<string, int> Map of option value to its positive cap.
     */
    private function limits(mixed $rows): array
    {
        if (! \is_iterable($rows)) {
            return [];
        }

        $limits = [];

        foreach ($rows as $row) {
            $row = $row instanceof Collection ? $row->all() : $row;

            if (! \is_array($row)) {
                continue;
            }

            $choice = $row['choice'] ?? null;
            $limit = $row['limit'] ?? null;
            if (! \is_scalar($choice)) {
                continue;
            }

            if (! is_numeric($limit)) {
                continue;
            }

            if ((int) $limit <= 0) {
                continue;
            }

            $limits[(string) $choice] = (int) $limit;
        }

        return $limits;
    }

    /**
     * Whether a toggle config value is truthy (boolean true, or an imported 'true'/'1'/'yes'/'on').
     */
    private function enabled(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_string($value)) {
            return \in_array(strtolower(trim($value)), ['true', '1', 'yes', 'on'], true);
        }

        return (bool) $value;
    }
}
