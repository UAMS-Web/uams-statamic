<?php

declare(strict_types=1);

namespace App\Services\LinkHealth;

/**
 * What became of an internal target when it was looked up (UAMS-Web/uams-statamic-archive#1765).
 *
 * These are deliberately finer-grained than "broken". An HTTP probe of an internal URL answers a
 * weaker question than a lookup does: a `404` says the URL did not resolve, and cannot say whether
 * the entry was deleted, unpublished, moved behind a redirect, or simply not routable on that
 * site. Those are different editorial problems with different fixes, and collapsing them would
 * hand an editor a list they cannot act on.
 */
enum ResolutionState: string
{
    /** The target resolves and is reachable. */
    case Ok = 'ok';

    /** Nothing resolves for this reference — the entry, term, container or path is gone. */
    case Missing = 'missing';

    /**
     * The entry exists but is not published in the occurrence's site.
     *
     * Distinct from `Missing` on purpose: the content is still there and the fix is to publish it
     * or to stop linking it, not to repoint the link at something else.
     */
    case Unpublished = 'unpublished';

    /**
     * The asset reference is well-formed and its container resolves, but the file is not on disk.
     *
     * A stored reference that looks correct is exactly the case a shape-based check cannot catch,
     * which is why this slice confirms the file rather than trusting the reference.
     */
    case MissingFile = 'missing_file';

    /**
     * The path does not resolve directly but a redirect rule serves it, so it is not broken.
     *
     * #1767 turns this into a recommendation for where the link should point instead; this
     * slice only reports it.
     */
    case Redirected = 'redirected';

    /**
     * Outside this slice's remit — an external URL, a `mailto:`, a bare fragment.
     *
     * Reported rather than omitted so a caller can tell "not broken" from "not examined", which a
     * missing row cannot express.
     */
    case NotChecked = 'not_checked';
}
