<?php

declare(strict_types=1);

namespace App\Services\LinkHealth;

/**
 * What became of an EXTERNAL target when it was probed over HTTP (UAMS-Web/uams-statamic-archive#1766).
 *
 * Deliberately separate from {@see ResolutionState}, which UAMS-Web/uams-statamic-archive#1765 scoped to internal targets
 * resolved by lookup. The two answer different questions with different vocabularies — a status
 * class is not a repository miss — and {@see Resolution::isBroken()} enumerates internal states
 * explicitly, so extending that enum would silently require editing a second file. A new case
 * nobody added to that list would report as NOT broken, which is a false green in the one place
 * the report is read.
 */
enum ProbeOutcome: string
{
    /** The URL answered with a success status. */
    case Healthy = 'healthy';

    /** The URL answered, and the status says the target is not there or is failing. */
    case Broken = 'broken';

    /**
     * `OutboundUrlGuard` refused the request — the host resolves to private, loopback or
     * link-local space, or a redirect hop pivoted into it.
     *
     * **A refusal is not a broken link**, and collapsing the two is the mistake this slice exists
     * to avoid: it would report an internal-only host as broken to an editor who cannot fix it,
     * while hiding that the checker never actually reached anything. UAMS-Web/uams-statamic-archive#1768 names it for the
     * Control Panel; this slice only has to keep it distinct.
     */
    case Refused = 'refused';

    /** The request never completed — DNS failure, connection reset, timeout. */
    case Unreachable = 'unreachable';

    /**
     * The link points at a configured intranet host (`link_health.intranet_hosts`) that this
     * scan could not reach — refused by the guard or never answered (UAMS-Web/uams-statamic-archive#1768).
     *
     * **Not broken**, because the failure is a fact about where the scan ran rather than about
     * the link: `inside.uams.edu` answers on the UAMS network and nowhere else, so a scan run
     * off-network would otherwise report every link to it as broken. Only a configured host can
     * produce this outcome; a listed host that answers with a failing status is still `Broken`.
     */
    case Unverifiable = 'unverifiable';

    /** Whether this outcome is something an editor should be shown as a broken link. */
    public function isBroken(): bool
    {
        return $this === self::Broken || $this === self::Unreachable;
    }

    /**
     * One sentence telling an editor what this outcome means, so a link that could not be
     * checked does not read as a defect in the tool or as a link to fix.
     */
    public function explanation(): string
    {
        return match ($this) {
            self::Healthy => 'The link answered successfully.',
            self::Broken => 'The link answered, but with an error status: the page is missing or failing.',
            self::Refused => 'The link checker is not permitted to fetch this address, because it points into a private or internal network. It was not checked.',
            self::Unreachable => 'The link did not answer: its host could not be found, or the connection failed or timed out.',
            self::Unverifiable => 'This intranet host could not be reached from where the link checker ran, so the link could not be checked. It is not reported as broken.',
        };
    }
}
