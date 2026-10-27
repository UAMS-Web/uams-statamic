<?php

declare(strict_types=1);

namespace App\Services\LinkHealth;

/**
 * The classification assigned to a single extracted link occurrence.
 *
 * Every occurrence receives exactly one kind. The kinds are deliberately about the SHAPE of the
 * stored value rather than about whether its target exists — resolution belongs to a later slice
 * and this classification is decided without any network or repository lookup beyond the set of
 * known entry ids.
 */
enum LinkKind: string
{
    /** Absolute `http(s)` URL whose host is not first-party. */
    case External = 'external';

    /** Absolute URL that is first-party but is not a host this Statamic install serves. */
    case FirstPartyOther = 'first_party_other';

    /** Absolute URL on the origin site's own host, or a root-relative path. */
    case Internal = 'internal';

    /** `statamic://entry::<id>`, or a bare `entry::<id>`. */
    case EntryReference = 'entry_reference';

    /** `statamic://asset::<ref>`, or a bare `asset::<ref>`. */
    case AssetReference = 'asset_reference';

    /** `statamic://term::<ref>`, or a bare `term::<ref>`. */
    case TermReference = 'term_reference';

    /**
     * A bare entry id stored by an `entries` fieldtype, with no `entry::` prefix.
     *
     * Recognized by whole-string match against known entry ids, never by matching a UUID shape —
     * this repository stores plenty of UUID-shaped values that are not entry references.
     */
    case EntryRelation = 'entry_relation';

    /** `mailto:`, `tel:`, and other non-HTTP schemes. */
    case NonHttp = 'non_http';

    /** A value starting `#`, addressing only a position within the current document. */
    case FragmentOnly = 'fragment_only';
}
