<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LinkHealth\ExternalProber;
use App\Services\LinkHealth\HostThrottle;
use App\Services\LinkHealth\InternalResolver;
use App\Services\LinkHealth\LinkExtractor;
use App\Services\LinkHealth\LinkKind;
use App\Services\LinkHealth\LinkOccurrence;
use App\Services\LinkHealth\NewlyBrokenDiff;
use App\Services\LinkHealth\ProbeOutcome;
use App\Services\LinkHealth\ResolutionState;
use App\Services\LinkHealth\RunStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Scan content for broken links and report what broke since the last run (UAMS-Web/uams-statamic-archive#1766).
 *
 * Internal targets resolve in-process through `InternalResolver` (UAMS-Web/uams-statamic-archive#1765); external ones are
 * probed over HTTP through `ExternalProber`, which routes every request through the outbound
 * guard. The run is persisted so the next one can be diffed against it, because a standing list
 * of every broken link is ignored within a week and "what broke since Tuesday" is not.
 */
#[Description('Scan content for broken links and report what broke since the last run')]
#[Signature('link-health:scan
    {--site= : Restrict the scan to one site handle}
    {--collection= : Restrict the scan to one collection handle}
    {--entry= : Restrict the scan to one entry id}
    {--skip-external : Resolve internal targets only, issuing no outbound requests}')]
final class LinkHealthScan extends Command
{
    /**
     * Run a scan and report it.
     */
    public function handle(
        LinkExtractor $extractor,
        InternalResolver $resolver,
        ExternalProber $prober,
        HostThrottle $throttle,
        RunStore $store,
    ): int {
        $site = $this->stringOption('site');
        $collection = $this->stringOption('collection');
        $entryId = $this->stringOption('entry');

        $this->info('Collecting link occurrences...');

        // Scoped in the query. `entriesLoaded()` is what makes that assertable, and it is
        // reported here so an operator can see the scope took effect rather than trusting it.
        $occurrences = $extractor->extractScoped($site, $collection, $entryId);

        $this->line(sprintf(
            '  %d occurrence(s) across %d %s.',
            \count($occurrences),
            $extractor->entriesLoaded(),
            $extractor->entriesLoaded() === 1 ? 'entry' : 'entries',
        ));

        $outcomes = [];
        $values = [];
        $bar = $this->output->createProgressBar(\count($occurrences));

        foreach ($occurrences as $occurrence) {
            $key = $this->keyFor($occurrence);
            $values[$key] = $occurrence->value;
            $outcomes[$key] = $this->outcomeFor($occurrence, $resolver, $prober, $throttle);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Read the predecessor BEFORE recording this run, so the run being written is never its
        // own baseline.
        $runId = $store->record($outcomes, $values, $site, $collection, $this->reasonsFor($values, $prober));
        $previousRaw = $store->previousOutcomes($runId, $site, $collection);

        $newly = NewlyBrokenDiff::between($this->rehydrate($previousRaw), $outcomes);

        return $this->report($outcomes, $values, $newly, $prober);
    }

    /**
     * Resolve or probe one occurrence, whichever its kind calls for.
     */
    private function outcomeFor(
        LinkOccurrence $occurrence,
        InternalResolver $resolver,
        ExternalProber $prober,
        HostThrottle $throttle,
    ): ProbeOutcome|ResolutionState {
        $external = $occurrence->kind === LinkKind::External
            || $occurrence->kind === LinkKind::FirstPartyOther;

        if (! $external) {
            return $resolver->resolve($occurrence)->state;
        }

        if ($this->option('skip-external') === true) {
            return ProbeOutcome::Refused;
        }

        // Reserve before every outbound request, including the ones answered from the prober's
        // own cache — cheap, and it keeps the bound honest if the cache is ever removed.
        $throttle->reserve($occurrence->value);

        return $prober->probe($occurrence->value);
    }

    /**
     * The identity the diff keys on: the item, its site, and the location within it.
     *
     * Not the URL. The same URL in two entries is two occurrences with two independent histories,
     * and collapsing them would report a link as newly broken the moment it appears elsewhere.
     */
    private function keyFor(LinkOccurrence $occurrence): string
    {
        return implode(':', [
            $occurrence->itemType->value,
            $occurrence->container,
            $occurrence->id,
            $occurrence->site,
            $occurrence->location(),
        ]);
    }

    /**
     * Turn stored outcome strings back into the enums the diff compares.
     *
     * A value matching neither enum is dropped rather than guessed at: it can only come from a
     * run recorded by a different version, and treating it as healthy would invent a transition.
     *
     * @param  array<string, string>  $raw
     * @return array<string, ProbeOutcome|ResolutionState>
     */
    private function rehydrate(array $raw): array
    {
        $out = [];

        foreach ($raw as $key => $value) {
            $outcome = ProbeOutcome::tryFrom($value) ?? ResolutionState::tryFrom($value);

            if ($outcome !== null) {
                $out[$key] = $outcome;
            }
        }

        return $out;
    }

    /**
     * Print the summary and choose the exit code.
     *
     * @param  array<string, ProbeOutcome|ResolutionState>  $outcomes
     * @param  array<string, string>  $values  The stored value by occurrence key.
     * @param  list<string>  $newly
     */
    private function report(array $outcomes, array $values, array $newly, ExternalProber $prober): int
    {
        $broken = array_filter($outcomes, static fn (ProbeOutcome|ResolutionState $o): bool => $o instanceof ProbeOutcome
            ? $o->isBroken()
            : \in_array($o, [ResolutionState::Missing, ResolutionState::Unpublished, ResolutionState::MissingFile], true));

        // Counted apart from `Broken` and from each other, because each means something different
        // to an editor: a refusal is a host this install may never fetch, and an unverifiable
        // link is an intranet host the scan could not reach from where it ran (UAMS-Web/uams-statamic-archive#1768).
        $refused = array_filter($outcomes, static fn (ProbeOutcome|ResolutionState $o): bool => $o === ProbeOutcome::Refused);
        $unverifiable = array_filter($outcomes, static fn (ProbeOutcome|ResolutionState $o): bool => $o === ProbeOutcome::Unverifiable);

        $this->line(sprintf('Occurrences:   %d', \count($outcomes)));
        $this->line(sprintf('Probed:        %d unique external URL(s)', $prober->probedCount()));
        $this->line(sprintf('Broken:        %d', \count($broken)));
        $this->line(sprintf('Refused:       %d', \count($refused)));
        $this->line(sprintf('Unverifiable:  %d', \count($unverifiable)));
        $this->line(sprintf('Newly broken:  %d', \count($newly)));

        foreach (\array_slice($newly, 0, 20) as $key) {
            $this->line('  '.$key);
        }

        $this->reportUnverifiable($unverifiable, $values, $prober);

        // A scan that found broken links is not a failed scan — it did its job. Exiting non-zero
        // would make every scheduled run look like a tooling failure.
        return self::SUCCESS;
    }

    /**
     * The recorded reason for every occurrence whose target was not reached, by occurrence key.
     *
     * Persisted with the run because the printed report is discarded by every production entry
     * point, and an `unverifiable` outcome without its reason reads as a fault in the checker.
     *
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    private function reasonsFor(array $values, ExternalProber $prober): array
    {
        $reasons = [];

        foreach ($values as $key => $url) {
            $reason = $prober->reasonFor($url);

            if ($reason !== null) {
                $reasons[$key] = $reason;
            }
        }

        return $reasons;
    }

    /**
     * List each unverifiable URL once, with what the outcome means and why it was not reached.
     *
     * Without the reason an unverifiable link reads as a fault in the checker, which is the
     * failure this outcome exists to avoid.
     *
     * @param  array<string, ProbeOutcome|ResolutionState>  $unverifiable
     * @param  array<string, string>  $values
     */
    private function reportUnverifiable(array $unverifiable, array $values, ExternalProber $prober): void
    {
        if ($unverifiable === []) {
            return;
        }

        $this->newLine();
        $this->line(ProbeOutcome::Unverifiable->explanation());

        $urls = array_values(array_unique(array_map(
            static fn (string $key): string => $values[$key] ?? '',
            array_keys($unverifiable),
        )));

        foreach (\array_slice($urls, 0, 20) as $url) {
            $this->line(sprintf('  %s — %s', $url, $prober->reasonFor($url) ?? 'no reason recorded'));
        }
    }

    /** An option as a non-empty string, or null. */
    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return \is_string($value) && $value !== '' ? $value : null;
    }
}
