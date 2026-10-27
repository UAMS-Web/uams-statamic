<?php

declare(strict_types=1);

use App\Services\PubMed\PubMedEsummaryService;

/**
 * PubMed E-utilities integration settings (identification, pacing, cache TTL).
 *
 * Used by {@see PubMedEsummaryService} and PMID validation.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | E-utilities client identification
    |--------------------------------------------------------------------------
    |
    | NCBI asks every client to identify itself on each request so they can make
    | contact before throttling or blocking it. `tool` names the application and
    | `email` is the address they write to; both are sent on every request.
    |
    | `email` has no default on purpose. Querying anonymously is the behavior
    | UAMS-Web/uams-statamic-archive#2409 exists to remove, so an unset address is a misconfiguration that
    | refuses the request rather than a default that quietly restores it.
    |
    */
    'tool' => env('PUBMED_TOOL', 'uams-statamic'),
    'email' => env('PUBMED_CONTACT_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | E-utilities API key
    |--------------------------------------------------------------------------
    |
    | Optional. When set it is sent as `api_key` and raises the permitted rate
    | from three requests per second to ten; when unset the parameter is omitted
    | from the query entirely rather than sent empty, and the lower ceiling
    | applies. Obtained per-environment from an NCBI account and supplied through
    | the environment only -- never committed.
    |
    */
    'api_key' => env('PUBMED_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Cached E-summary row TTL
    |--------------------------------------------------------------------------
    |
    | Seconds to cache successful or negative PubMed E-summary lookups. Keys use
    | the `pubmed_esummary_row_v1:` prefix so they do not collide with legacy
    | `pubmed_{pmid}` entries.
    |
    */
    'cache_ttl_seconds' => (int) env('PUBMED_API_CACHE_TTL', 604_800),

    /*
    |--------------------------------------------------------------------------
    | E-utilities request timeout
    |--------------------------------------------------------------------------
    |
    | Seconds to wait on an uncached E-summary HTTP call before giving up. Bounds
    | a hung NCBI connection so the out-of-band warming job/command cannot block
    | indefinitely; a timeout surfaces as a ConnectionException the warming job
    | isolates per PMID.
    |
    */
    'request_timeout_seconds' => (int) env('PUBMED_API_TIMEOUT', 10),

];
