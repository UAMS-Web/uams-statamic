<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | First-party domains
    |--------------------------------------------------------------------------
    |
    | The canonical set of registrable domains owned by UAMS ("first-party"
    | domains). A URL is treated as a "first-party URL" when its host exactly
    | equals one of these domains or is a subdomain of one (e.g. `uams.edu`,
    | `www.uams.edu`, `anything.uams.edu`). Note `uamshealth.com` is first-party
    | but is not a `uams.*` host, which is why the set is named for ownership
    | rather than the UAMS brand.
    |
    | This list is the SINGLE source of truth for first-party-domain detection.
    | The App\Rules\FirstPartyUrl validation rule reads it for both the
    | `first_party_url` (must be first-party) and `non_first_party_url` (must NOT
    | be first-party) directions, so the set can grow here without editing any
    | rule. Entries must be bare, lowercase, registrable domains (no scheme,
    | port, path, or leading dot).
    |
    */

    'domains' => [
        'uams.edu',
        'uamshealth.com',
    ],

];
