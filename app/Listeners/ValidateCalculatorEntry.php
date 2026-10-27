<?php

declare(strict_types=1);

namespace App\Listeners;

use Exception;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaving;

/**
 * Listener for validating entries in the `calculators` collection before saving.
 *
 * Enforces structural rules that go beyond what blueprint validation can express
 * because they involve cross-field consistency:
 *
 *   1. **Exactly one primary**: If the calculator has multiple variables with a
 *      formula (the bidirectional case), exactly one must have `primary: true`.
 *      When there's only one formula-having variable, primary is implied
 *      automatically and the flag may be left off.
 *
 *   2. **No sub-handle references in formulas**: A variable's `split` declares
 *      display-only sub-inputs. Their handles must not appear in any sibling
 *      formula — formulas should reference the composite handle, not the
 *      fragments. This keeps the data model honest: the composite is the
 *      "real" variable, and the splits are just projections.
 *
 *   3. **No handle collisions**: Composite handles and sub-handles share a
 *      single namespace inside one calculator. Two variables can't share a
 *      handle, and a sub-handle can't match any composite handle in the same
 *      entry.
 *
 * On failure, throws an exception with a message naming the offending part so
 * the author can find it in the CP. Errors surface as the standard Statamic
 * save-failed banner.
 */
class ValidateCalculatorEntry
{
    /**
     * Handle the EntrySaving event for calculator entries.
     *
     * @throws Exception When any structural rule is violated.
     */
    public function handle(EntrySaving $event): void
    {
        /** @var Entry $entry */
        $entry = $event->entry;

        // The collection is duck-typed here: production hands us a real Statamic
        // collection and the tests a mock exposing handle(), so narrow the type
        // via PHPDoc rather than a runtime instanceof that would exclude either.
        /** @var Collection|null $collection */
        $collection = $entry->collection();
        if (! $collection || $collection->handle() !== 'calculators') {
            return;
        }

        $parts = $entry->get('parts') ?: [];
        if (! is_array($parts) || $parts === []) {
            return;
        }

        /** @var list<array<string, mixed>> $parts */
        $this->validatePrimary($parts);
        $this->validateHandles($parts);
        $this->validateFormulaReferences($parts);
    }

    /**
     * Exactly one variable must be designated as the displayed equation's LHS.
     *
     * When the calculator has only one variable with a formula, that variable
     * is implicitly primary — no `primary` flag is required. When two or more
     * variables have formulas (bidirectional calculators), exactly one must
     * have `primary: true` because the display direction would otherwise be
     * ambiguous.
     *
     * @param  list<array<string, mixed>>  $parts  The calculator's variable parts.
     */
    protected function validatePrimary(array $parts): void
    {
        $withFormula = array_filter($parts, $this->hasFormula(...));
        $explicitPrimary = array_filter($parts, fn (array $p): bool => ! empty($p['primary']));

        if (count($explicitPrimary) > 1) {
            $handles = array_map(fn (array $p): string => is_scalar($p['handle'] ?? null) ? (string) $p['handle'] : '?', $explicitPrimary);
            throw new Exception(
                'Only one variable may be marked Primary, but the following are: '.implode(', ', $handles).'.'
            );
        }

        // If there's no explicit primary, we need an implicit one — the sole
        // formula-having variable. With zero or many formula variables, the
        // author must choose.
        if ($explicitPrimary === [] && count($withFormula) !== 1) {
            if ($withFormula === []) {
                throw new Exception(
                    'A calculator must have at least one variable with a formula. None of the variables are computed.'
                );
            }

            $handles = array_map(fn (array $p): string => is_scalar($p['handle'] ?? null) ? (string) $p['handle'] : '?', $withFormula);
            throw new Exception(
                'This calculator has '.count($withFormula).' variables with formulas ('.implode(', ', $handles).'); mark exactly one as Primary so the displayed equation has an unambiguous left side.'
            );
        }
    }

    /**
     * Every handle used in the calculator — composite and split sub-handles —
     * must be unique within the entry.
     *
     * @param  list<array<string, mixed>>  $parts  The calculator's variable parts.
     */
    protected function validateHandles(array $parts): void
    {
        $seen = [];
        foreach ($parts as $part) {
            $handle = $part['handle'] ?? null;
            if (is_scalar($handle) && $handle) {
                $handle = (string) $handle;
                if (isset($seen[$handle])) {
                    throw new Exception(sprintf("Handle '%s' is used more than once. Every variable and split sub-handle must be unique within the calculator.", $handle));
                }

                $seen[$handle] = true;
            }

            $splits = $part['split'] ?? [];
            foreach (is_iterable($splits) ? $splits : [] as $split) {
                $subHandle = is_array($split) ? ($split['handle'] ?? null) : null;
                if (! is_scalar($subHandle)) {
                    continue;
                }

                if (! $subHandle) {
                    continue;
                }

                $subHandle = (string) $subHandle;
                if (isset($seen[$subHandle])) {
                    throw new Exception(sprintf("Handle '%s' is used more than once. Every variable and split sub-handle must be unique within the calculator.", $subHandle));
                }

                $seen[$subHandle] = true;
            }
        }
    }

    /**
     * Formulas may reference composite handles but never split sub-handles.
     *
     * Sub-handles are display-only — they're projections of the composite, not
     * independent variables, so referencing them in a formula would break the
     * mental model and create ordering ambiguity (when does the projection
     * settle, before or after the formula runs?). The composite handle is the
     * stable reference; use it instead.
     *
     * @param  list<array<string, mixed>>  $parts  The calculator's variable parts.
     */
    protected function validateFormulaReferences(array $parts): void
    {
        $subHandles = [];
        foreach ($parts as $part) {
            $splits = $part['split'] ?? [];
            foreach (is_iterable($splits) ? $splits : [] as $split) {
                $subHandle = is_array($split) ? ($split['handle'] ?? null) : null;
                if (is_scalar($subHandle) && $subHandle) {
                    $compositeHandle = $part['handle'] ?? '?';
                    $subHandles[(string) $subHandle] = is_scalar($compositeHandle) ? (string) $compositeHandle : '?';
                }
            }
        }

        if ($subHandles === []) {
            return;
        }

        foreach ($parts as $part) {
            $formula = $this->extractFormula($part);
            if ($formula === '') {
                continue;
            }

            // Match every identifier in the formula. We then check membership
            // against the sub-handle set — function names from mathjs (floor,
            // sqrt, etc.) would only collide if an author named a sub-handle
            // after one, which the ascii_identifier rule allows but is
            // vanishingly unlikely in practice; a false positive here gives a
            // clear error and the author renames the sub-handle.
            preg_match_all('/[a-zA-Z_]\w*/', $formula, $matches);
            foreach (array_unique($matches[0]) as $identifier) {
                if (isset($subHandles[$identifier])) {
                    $compositeOfSub = $subHandles[$identifier];
                    $partHandle = is_scalar($part['handle'] ?? null) ? (string) $part['handle'] : '?';
                    throw new Exception(
                        sprintf("Formula on '%s' references '%s', which is a split sub-handle of '%s'. Sub-handles are display-only; reference the composite handle '%s' instead.", $partHandle, $identifier, $compositeOfSub, $compositeOfSub)
                    );
                }
            }
        }
    }

    /**
     * Whether a part has a non-empty formula. Pure inputs (`kind: input`) have
     * no formula and don't count toward the primary tally.
     *
     * @param  array<string, mixed>  $part  A single calculator variable part.
     */
    protected function hasFormula(array $part): bool
    {
        return $this->extractFormula($part) !== '';
    }

    /**
     * Read the formula's code string out of a part. The code fieldtype stores
     * `{ code, mode }`; older shapes might store a bare string. Handle both.
     *
     * @param  array<string, mixed>  $part  A single calculator variable part.
     */
    protected function extractFormula(array $part): string
    {
        $formula = $part['formula'] ?? null;
        if (is_array($formula)) {
            $formula = $formula['code'] ?? '';
        }

        return is_string($formula) ? trim($formula) : '';
    }
}
