<?php

declare(strict_types=1);

use App\Services\ComputedFields\ProviderPeople\ResearchPublicationSelectComputed;
use App\Services\PubMed\PubMedEsummaryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Statamic\Entries\Entry;
use Tests\TestCase;

uses(TestCase::class);

covers(ResearchPublicationSelectComputed::class);

/**
 * UAMS-Web/uams-statamic-archive#865: the request-time computed field must never block on PubMed HTTP or
 * pacing sleeps during augmentation. It reads the E-summary cache only and
 * degrades to an empty citation on a miss.
 */
beforeEach(function () {
    Cache::flush();
});

/**
 * Build a mocked entry exposing the given research_publication_select sets.
 *
 * @param  array<int, mixed>  $sets
 */
function researchComputedEntry(array $sets): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->with('research_publication_select', [])
        ->andReturn($sets);

    return $entry;
}

it('makes no http call during augmentation on a cold cache (#865)', function () {
    Http::fake();

    $computed = new ResearchPublicationSelectComputed(new PubMedEsummaryService);

    $out = $computed(researchComputedEntry([['pmid' => '456']]));

    expect(data_get($out, '0.pubmed_information'))->toBe('');
    Http::assertNothingSent();
});

it('renders the citation from a warmed cache without fetching during augmentation (#865)', function () {
    Http::fake([
        'eutils.ncbi.nlm.nih.gov/*' => Http::response([
            'result' => ['456' => ['title' => 'A Study']],
        ], 200),
    ]);

    $service = new PubMedEsummaryService;
    // Warm out of band (one HTTP call).
    $service->rememberSummaryRow('456');

    $out = (new ResearchPublicationSelectComputed($service))(
        researchComputedEntry([['pmid' => '456']])
    );

    expect(data_get($out, '0.pubmed_information'))->toContain('PMID: 456');
    // Augmentation itself added no HTTP — still just the single warming call.
    Http::assertSentCount(1);
});
