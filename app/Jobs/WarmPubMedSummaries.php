<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\PubMed\PubMedEsummaryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Warms the PubMed E-summary cache for a batch of PMIDs out of band so the
 * request-time `research_publication_select` computed field can read cache-only
 * and never block a render on PubMed HTTP or pacing sleeps (UAMS-Web/uams-statamic-archive#865).
 *
 * Dispatched by the `pubmed:warm` command (scheduled). Each PMID is fetched
 * through {@see PubMedEsummaryService::rememberSummaryRow()}, which paces every
 * uncached fetch against the application-wide E-utilities limiter,
 * which both honors NCBI rate limits and caches negative lookups so a missing
 * record is not re-fetched on every warm.
 */
final class WarmPubMedSummaries implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  list<string>  $pmids  PubMed identifier strings to warm
     */
    public function __construct(
        public array $pmids
    ) {}

    /**
     * Fetch and cache each PMID's E-summary row, pacing between network calls.
     *
     * Each PMID is isolated: a network failure (e.g. a thrown ConnectionException
     * when NCBI times out or resets a rate-limited call) is logged and skipped so
     * it neither leaves the rest of the batch cold nor — under the `sync` queue,
     * where this runs inline in the `pubmed:warm` command — aborts the remaining
     * chunks. The next scheduled warm retries the cold PMID.
     */
    public function handle(PubMedEsummaryService $pubMedEsummaryService): void
    {
        foreach ($this->pmids as $pmid) {
            try {
                $pubMedEsummaryService->rememberSummaryRow($pmid);
            } catch (\Throwable $throwable) {
                Log::warning('WarmPubMedSummaries: failed to warm PMID', [
                    'pmid' => $pmid,
                    'message' => $throwable->getMessage(),
                ]);
            }
        }
    }
}
