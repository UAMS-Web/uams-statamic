<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Identifiers\IdentifierAuditSurface;
use App\Support\Identifiers\ResolvesIdentifierValues;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Statamic\Entries\Entry as ConcreteEntry;

/**
 * Report duplicate identifier values across entries (UAMS-Web/uams-statamic-archive#2395).
 *
 * UAMS-Web/uams-statamic-archive#2152's save-time rule is blueprint validation, and blueprint validation runs
 * in the Control Panel only -- `Statamic\Entries\Entry::save()` performs none,
 * and the WordPress importer saves entries directly. So the rule guards the
 * entries an editor types and never sees the entries an import writes, which is
 * the population that will actually contain duplicates. This command covers
 * that population.
 *
 * It is a REPORT and never a repair: which of two colliding entries is correct
 * is not a question this can answer, so it prints and exits zero.
 *
 * What it audits is derived from the blueprints by
 * {@see IdentifierAuditSurface} rather than restated here, so this and the rule
 * cannot disagree about coverage; the comparison semantics come from the same
 * trait the rule uses, so they cannot disagree about what "the same identifier"
 * means either.
 *
 * @command php artisan identifiers:audit-duplicates
 */
#[Description('Report duplicate Workday, NPI, SER and Epic DEP identifiers across entries')]
#[Signature('identifiers:audit-duplicates')]
class AuditDuplicateIdentifiers extends Command
{
    use ResolvesIdentifierValues;

    /**
     * Execute the console command.
     *
     * @param  IdentifierAuditSurface  $surface  Derives the audited fields from the blueprints
     * @return int Always SUCCESS -- a duplicate is a finding to act on, not a command failure
     */
    public function handle(IdentifierAuditSurface $surface): int
    {
        // Resolve what the wired rules actually cover
        $groups = $surface->all();

        // A surface of nothing means no rule is wired, which is a setup failure
        // rather than a clean result -- say so instead of printing "no duplicates"
        if ($groups === []) {
            // Report the blind scan as an error
            $this->error('No uniqueness rules are wired in the blueprints, so nothing was audited.');

            return self::FAILURE;
        }

        // Count what was examined, so a clean report is distinguishable from a
        // scan that found nothing because it looked nowhere
        $entryReads = 0;
        $valuesScanned = 0;
        $collectionsSeen = [];
        $findings = [];

        // Audit each derived group
        foreach ($groups as $group) {
            // Read the collection once per group
            $entries = $this->otherEntriesIn($group['collection'], null);

            // Track how much this group examined. A collection is read once per
            // group, so this counts READS rather than distinct entries -- both
            // are printed, because a reader seeing only the larger number would
            // reasonably take it for the entry count.
            $entryReads += \count($entries);

            // Record the distinct entry count for this collection
            $collectionsSeen[$group['collection']] = \count($entries);

            // Map each comparison form to where it was seen
            $seen = [];

            // Walk every entry in the collection
            foreach ($entries as $entry) {
                // Read every path this group pools, or the one list field
                foreach ($this->pathsFor($group) as $path) {
                    // Collect the entry's comparable values for this path
                    $values = $this->identifierValuesOn($entry, $path, $group['rowKey']);

                    // Record each value against its entry and field
                    foreach ($values as $normalized => $original) {
                        // Count the value as examined
                        $valuesScanned++;

                        // Accumulate the places this value was found
                        $seen[$normalized][] = [
                            'entry' => $this->identifierEntryLabel($entry),
                            'id' => $this->entryIdAsString($entry),
                            'field' => $path,
                            'value' => $original,
                        ];
                    }

                    // A list field repeats within one entry rather than across
                    // paths, so within-entry duplicates are found by re-reading
                    // the raw rows rather than the de-duplicated map above
                    foreach ($this->repeatedRowValues($entry, $path, $group['rowKey']) as $repeated) {
                        // Record the within-entry repeat as its own finding
                        $findings[] = [
                            'kind' => 'within entry',
                            'group' => $group['label'],
                            'value' => $repeated,
                            'where' => $this->identifierEntryLabel($entry),
                        ];
                    }
                }
            }

            // Report any value claimed by more than one place
            foreach ($seen as $places) {
                // A value seen once collides with nothing
                if (\count($places) < 2) {
                    // Move to the next value
                    continue;
                }

                // Distinguish a within-entry collision from a cross-entry one,
                // because the remedies differ
                $ids = array_unique(array_column($places, 'id'));

                $findings[] = [
                    'kind' => \count($ids) === 1 ? 'within entry' : 'across entries',
                    'group' => $group['label'],
                    'value' => $places[0]['value'],
                    'where' => implode('; ', array_map(
                        static fn (array $p): string => $p['entry'].' ('.$p['field'].')',
                        $places
                    )),
                ];
            }
        }

        // Print the denominator FIRST, so a run that dies partway is visibly
        // incomplete rather than reading as a clean scan
        $this->info(\sprintf(
            'Audited %d identifier group(s) over %d entries in %d collection(s): %d entry read(s), %d identifier value(s).',
            \count($groups),
            array_sum($collectionsSeen),
            \count($collectionsSeen),
            $entryReads,
            $valuesScanned
        ));

        // Name what was covered, so the scope is legible without reading code
        foreach ($groups as $group) {
            // List each audited group
            $this->line('  - '.$group['label']);
        }

        // A clean scan is a result, and it has just reported what it examined
        if ($findings === []) {
            // Report the clean outcome
            $this->info('No duplicate identifiers found.');

            return self::SUCCESS;
        }

        // Report each finding as a row
        $this->newLine();
        $this->table(
            ['Kind', 'Group', 'Value', 'Where'],
            array_map(
                static fn (array $f): array => [$f['kind'], $f['group'], $f['value'], $f['where']],
                $findings
            )
        );

        // State the count separately from the table, which can be long
        $this->warn(\sprintf('%d duplicate identifier finding(s).', \count($findings)));

        return self::SUCCESS;
    }

    /**
     * The field paths a group reads on each entry.
     *
     * A pooled single-value group names its paths outright. A list group is
     * wired to the replicator field itself, so its path is that field's handle,
     * which the label carries.
     *
     * @param  array{collection: string, paths: list<string>, rowKey: string|null, label: string}  $group
     * @return list<string>
     */
    private function pathsFor(array $group): array
    {
        // A pooled group names every path it covers
        if ($group['paths'] !== []) {
            // Read each pooled path
            return $group['paths'];
        }

        // A list group's field handle is the label's trailing segment
        return [$this->listHandleFor($group['rowKey'])];
    }

    /**
     * The replicator handle carrying a given row key on a collection.
     *
     * The two list fields in this repository are distinguished by their row
     * subfield, which is why the rule takes it as a parameter at all.
     *
     * @param  string|null  $rowKey  Replicator row subfield
     * @return string The replicator field handle
     */
    private function listHandleFor(?string $rowKey): string
    {
        // `epic_dep_ids` rows carry `dep`; the location `npi` rows carry `item`
        return match ($rowKey) {
            'dep' => 'epic_dep_ids',
            'item' => 'npi',
            default => (string) $rowKey,
        };
    }

    /**
     * Values appearing on more than one row of one entry's list field.
     *
     * `identifierValuesOn()` de-duplicates by comparison form, so a repeat
     * inside one entry is invisible to it -- this re-reads the rows to find
     * exactly that case.
     *
     * @param  ConcreteEntry  $entry  The entry to read
     * @param  string  $path  Field path
     * @param  string|null  $rowKey  Replicator row subfield, or null for a single value
     * @return list<string> Repeated values, as stored
     */
    private function repeatedRowValues(ConcreteEntry $entry, string $path, ?string $rowKey): array
    {
        // A single-value field cannot repeat within itself
        if ($rowKey === null) {
            // Report no repeats
            return [];
        }

        // Read the raw rows rather than the de-duplicated map
        $raw = $entry->get($path);

        // A field holding no rows has no repeats
        if (! \is_array($raw)) {
            // Report no repeats
            return [];
        }

        // Track each comparison form and the repeats found
        $seen = [];
        $repeats = [];

        // Walk every row in order
        foreach ($raw as $row) {
            // A row carries its value under the named subfield
            $candidate = \is_array($row) ? ($row[$rowKey] ?? null) : $row;

            // Reduce the row value to its two forms
            $forms = $this->identifierForms($candidate);

            // A blank row is absent rather than a value that can repeat
            if ($forms === null) {
                // Move to the next row
                continue;
            }

            // A comparison form seen before on this entry is a repeat
            if (isset($seen[$forms['compare']])) {
                // Record the repeated value once
                $repeats[$forms['compare']] = $forms['display'];
            }

            // Record the form so a later row can be seen as a repeat
            $seen[$forms['compare']] = true;
        }

        return array_values($repeats);
    }
}
