<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\WarmPubMedSummaries as WarmPubMedSummariesJob;
use App\Support\Collections\PeopleCollection;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Warms the PubMed E-summary cache out of band so the request-time
 * `research_publication_select` computed field reads cache-only (UAMS-Web/uams-statamic-archive#865).
 *
 * Collects every PMID referenced by the providers and people collections and
 * dispatches {@see WarmPubMedSummariesJob} in batches. Scheduled in
 * routes/console.php; can also be run on demand.
 */
#[Description('Warm the PubMed E-summary cache for providers/people publications (out of band)')]
#[Signature('pubmed:warm')]
final class WarmPubMedSummaries extends Command
{
    /**
     * Collections whose `research_publication_select` field references PMIDs.
     *
     * @return list<string>
     */
    private static function collections(): array
    {
        return [
            'clinical_providers',
            PeopleCollection::handle(),
        ];
    }

    /**
     * PMIDs per dispatched job, so a large catalog spreads across queue workers
     * instead of one long-running job.
     */
    private const int BATCH_SIZE = 100;

    public function handle(): int
    {
        $pmids = $this->collectPmids();

        if ($pmids === []) {
            $this->info('No PMIDs found to warm.');

            return self::SUCCESS;
        }

        foreach (array_chunk($pmids, self::BATCH_SIZE) as $batch) {
            WarmPubMedSummariesJob::dispatch($batch);
        }

        $this->info(sprintf('Dispatched warming for %d PMID(s).', \count($pmids)));

        return self::SUCCESS;
    }

    /**
     * Gather the distinct PMID strings across all referenced collections.
     *
     * @return list<string>
     */
    private function collectPmids(): array
    {
        $pmids = [];

        foreach (self::collections() as $collection) {
            /** @var EntryQueryBuilder $query */
            $query = EntryFacade::query();
            /** @var EntryQueryBuilder $query */
            $query = $query->where('collection', $collection);
            /** @var Collection<int, Entry> $entries */
            $entries = $query->get();

            foreach ($entries as $entry) {
                $sets = $entry->get('research_publication_select', []);
                if (! \is_array($sets)) {
                    continue;
                }

                foreach ($sets as $set) {
                    if (! \is_array($set)) {
                        continue;
                    }

                    $pmid = $set['pmid'] ?? null;
                    if (\is_scalar($pmid) && (string) $pmid !== '') {
                        // Collect by value, not as an array key: a numeric-string
                        // key like '111' is coerced to int 111, which would violate
                        // the job's list<string> contract and TypeError against
                        // rememberSummaryRow(string $pmid).
                        $pmids[] = (string) $pmid;
                    }
                }
            }
        }

        // handle() splits the list with array_chunk(), which renumbers each batch, so the
        // gaps array_unique() leaves never reach a job.
        // @pest-mutate-ignore: UnwrapArrayValues
        return array_values(array_unique($pmids));
    }
}
