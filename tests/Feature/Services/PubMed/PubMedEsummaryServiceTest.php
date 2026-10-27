<?php

declare(strict_types=1);

use App\Services\PubMed\PubMedEsummaryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for PubMedEsummaryService HTTP, caching, and citation enrichment.
 *
 * Mutation: 100% on App\Services\PubMed\PubMedEsummaryService (111 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Services/PubMed/PubMedEsummaryServiceTest.php
 */
beforeEach(function () {
    // Clear the cache before each test so PubMed fakes are isolated.
    Cache::flush();
});

it('returns true from pmidExists when the esummary contains a result row', function () {
    Http::fake([
        'eutils.ncbi.nlm.nih.gov/*' => Http::response([
            'result' => [
                '123' => [
                    'uid' => '123',
                    'title' => 'Example',
                ],
            ],
        ], 200),
    ]);

    $service = new PubMedEsummaryService;

    expect($service->pmidExists('123'))->toBeTrue();
    Http::assertSentCount(1);
});

it('adds pubmed_information citation html from a warmed cache without any further http (#865)', function () {
    Http::fake([
        'eutils.ncbi.nlm.nih.gov/*' => Http::response([
            'result' => [
                '456' => [
                    'authors' => [
                        ['name' => 'Doe J'],
                    ],
                    'title' => 'A Study',
                    'source' => 'Journal',
                    'pubdate' => '2020',
                    'volume' => '1',
                    'issue' => '2',
                    'pages' => '3-4',
                    'elocationid' => '',
                ],
            ],
        ], 200),
    ]);

    $service = new PubMedEsummaryService;

    // Warm the cache out of band (one HTTP call), then enrich must read cache-only.
    $service->rememberSummaryRow('456');

    $out = $service->enrichResearchPublicationSelectSets([
        ['pmid' => '456'],
    ]);

    expect($out[0])->toHaveKey('pubmed_information');
    expect(data_get($out, '0.pubmed_information'))->toContain('PMID: 456');
    expect(data_get($out, '0.pubmed_information'))->toContain('View in PubMed');

    // Enrichment added no HTTP of its own: still just the single warming call.
    Http::assertSentCount(1);
});

it('returns empty pubmed_information on a cache miss and never fetches at request time (#865)', function () {
    Http::fake([
        'eutils.ncbi.nlm.nih.gov/*' => Http::response([
            'result' => ['456' => ['title' => 'A Study']],
        ], 200),
    ]);

    $service = new PubMedEsummaryService;

    $out = $service->enrichResearchPublicationSelectSets([
        ['pmid' => '456'],
    ]);

    expect($out[0])->toHaveKey('pubmed_information');
    expect(data_get($out, '0.pubmed_information'))->toBe('');

    // The synchronous augmentation path must never block on PubMed HTTP.
    Http::assertNothingSent();
});

it('cachedSummaryRow never fetches on a miss (#865)', function () {
    Http::fake();

    $service = new PubMedEsummaryService;

    expect($service->cachedSummaryRow('999'))->toBeNull();
    Http::assertNothingSent();
});

it('caches a negative esummary lookup so a second call makes no further http request', function () {
    Http::fake([
        'eutils.ncbi.nlm.nih.gov/*' => Http::response('', 500),
    ]);

    $service = new PubMedEsummaryService;

    expect($service->rememberSummaryRow('789'))->toBeFalse();
    expect($service->rememberSummaryRow('789'))->toBeFalse();
    Http::assertSentCount(1);
});
