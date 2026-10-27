<?php

declare(strict_types=1);

namespace App\Services\LinkHealth;

/**
 * Which content repository an extracted occurrence was read from.
 *
 * All three are in scope for the inventory: the footer navigation lives in a global set and
 * taxonomy content carries links of its own, so walking entries alone under-reports silently.
 */
enum ContentItemType: string
{
    /** A collection entry, in one site's localization. */
    case Entry = 'entry';

    /** A global set localization. */
    case Global = 'global';

    /** A taxonomy term, in one site's localization. */
    case Term = 'term';
}
