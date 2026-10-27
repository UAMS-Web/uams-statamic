<?php

declare(strict_types=1);

namespace App\Services\Redirects;

use App\Support\Redirects\MigrationRedirect;

/**
 * The outcome of one {@see MigrationRedirectGenerator::generate()} run (UAMS-Web/uams-statamic-archive#1641).
 *
 * Carries the rules the generator decided to write (or would write, on a dry
 * run), split by provenance for the command's summary, plus the count of
 * candidate sources skipped because an existing redirect already covers them —
 * the signal that a re-run is idempotent.
 */
final readonly class MigrationRedirectReport
{
    /**
     * @param  list<MigrationRedirect>  $entryMoves  301s for entries whose URL changed
     * @param  list<MigrationRedirect>  $assetMoves  301s for assets whose URL changed
     * @param  list<MigrationRedirect>  $structural  Synthesized structural-URL rules — archives, feeds, pagination, attachment pages, ugly permalinks (UAMS-Web/uams-statamic-archive#1657)
     * @param  list<MigrationRedirect>  $ancestorMoves  301s for retired permalinks to a surviving ancestor
     * @param  list<MigrationRedirect>  $gone  410s for retired permalinks with no surviving ancestor
     * @param  int  $skippedExisting  Candidate sources an existing redirect already covers
     * @param  bool  $written  Whether the rules were persisted (false on a dry run)
     * @param  list<MigrationRedirect>  $dedupMoves  301s from a dropped duplicate's WordPress path to its canonical entry (UAMS-Web/uams-statamic-archive#2943)
     * @param  list<array{source: string, target: string, reason: string}>  $dedupRefused  Duplicate pairs reported and not written (UAMS-Web/uams-statamic-archive#2943)
     */
    public function __construct(
        public array $entryMoves,
        public array $assetMoves,
        public array $structural,
        public array $ancestorMoves,
        public array $gone,
        public int $skippedExisting,
        public bool $written,
        public array $dedupMoves = [],
        public array $dedupRefused = [],
    ) {}

    /**
     * Every rule the run produced, in write order.
     *
     * @return list<MigrationRedirect> The combined planned rules
     */
    public function all(): array
    {
        return [
            ...$this->entryMoves,
            ...$this->dedupMoves,
            ...$this->assetMoves,
            ...$this->structural,
            ...$this->ancestorMoves,
            ...$this->gone,
        ];
    }

    /**
     * The total number of rules produced across every category.
     */
    public function total(): int
    {
        return \count($this->entryMoves)
            + \count($this->dedupMoves)
            + \count($this->assetMoves)
            + \count($this->structural)
            + \count($this->ancestorMoves)
            + \count($this->gone);
    }
}
