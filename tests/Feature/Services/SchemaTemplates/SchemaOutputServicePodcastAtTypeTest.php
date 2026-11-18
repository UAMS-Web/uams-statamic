<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\JsonLdCompositionProfiler;
use App\Services\SchemaTemplates\MedicalOrganizationSubtypeAtTypeResolver;
use App\Services\SchemaTemplates\PodcastEpisodeAtTypeResolver;
use App\Services\SchemaTemplates\SchemaClassificationsIdentifiersMerger;
use App\Services\SchemaTemplates\SchemaJsonLdAtIdDeduplication;
use App\Services\SchemaTemplates\SchemaJsonLdSchemaObjectEmissionCoordinator;
use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry;
use Tests\Support\EntriesAndTermsIsolatedTestCase;

uses(EntriesAndTermsIsolatedTestCase::class, MockeryPHPUnitIntegration::class);

covers(SchemaOutputService::class);

beforeEach(function (): void {
    Blink::flush();
    ensureAudioSeriesTaxonomyRegistered();
});

/**
 * Pins the early nulls of {@see SchemaOutputService::resolvePodcastEpisodeAtType()}:
 * no entry or no declared types, and a podcast entry for which the co-typing
 * resolver adds nothing. The podcast entry's `series` points at the real
 * `uams-health-talk` audio series term (subtype `podcast`) on the isolated
 * entries and terms Stache; the resolver is a mock handed to the constructor.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaOutputServicePodcastAtTypeTest.php
 */

/**
 * The service built with the given podcast co-typing resolver.
 */
function sosPodcastService(PodcastEpisodeAtTypeResolver $resolver): SchemaOutputService
{
    return new SchemaOutputService(
        app(SchemaTemplatesCascadeService::class),
        app(SchemaClassificationsIdentifiersMerger::class),
        app(SchemaJsonLdAtIdDeduplication::class),
        app(SchemaJsonLdSchemaObjectEmissionCoordinator::class),
        app(JsonLdCompositionProfiler::class),
        app(MedicalOrganizationSubtypeAtTypeResolver::class),
        $resolver
    );
}

/**
 * An in-memory posts entry whose `series` is the given audio series slug.
 */
function sosPodcastEntry(string $series): StatamicEntry
{
    $entry = Entry::make();

    if (! $entry instanceof StatamicEntry) {
        throw new RuntimeException('Entry::make() did not return a concrete entry');
    }

    $entry->collection('posts');
    $entry->blueprint('audio');
    $entry->locale('health');
    $entry->id('e-podcast');
    $entry->slug('e-podcast');
    $entry->data(['series' => $series]);

    return $entry;
}

/**
 * A co-typing resolver mock that answers the given @type list.
 *
 * @param  array<int, string>  $answer  What `resolve()` returns.
 */
function sosPodcastResolver(array $answer): PodcastEpisodeAtTypeResolver&MockInterface
{
    /** @var PodcastEpisodeAtTypeResolver&MockInterface $resolver */
    $resolver = Mockery::mock(PodcastEpisodeAtTypeResolver::class);
    $resolver->shouldReceive('resolve')->andReturn($answer);

    return $resolver;
}

it('gives no podcast @type without an entry or declared types', function (): void {
    $resolver = Mockery::mock(PodcastEpisodeAtTypeResolver::class);
    $resolver->shouldNotReceive('resolve');
    $service = sosPodcastService($resolver);

    expect($service->resolvePodcastEpisodeAtType(null, ['Article']))->toBeNull();
    expect($service->resolvePodcastEpisodeAtType(sosPodcastEntry('uams-health-talk'), []))->toBeNull();
});

it('gives no podcast @type when the resolver adds nothing for a podcast entry', function (): void {
    expect(sosPodcastService(sosPodcastResolver([]))->resolvePodcastEpisodeAtType(sosPodcastEntry('uams-health-talk'), ['Article']))
        ->toBeNull();
});

it('gives the resolver\'s @type for a podcast entry', function (): void {
    expect(sosPodcastService(sosPodcastResolver(['Article', 'PodcastEpisode']))->resolvePodcastEpisodeAtType(sosPodcastEntry('uams-health-talk'), ['Article']))
        ->toBe(['Article', 'PodcastEpisode']);
});

it('gives no podcast @type for an entry whose series is not a podcast', function (): void {
    $resolver = Mockery::mock(PodcastEpisodeAtTypeResolver::class);
    $resolver->shouldNotReceive('resolve');

    expect(sosPodcastService($resolver)->resolvePodcastEpisodeAtType(sosPodcastEntry('heres-to-your-health'), ['Article']))
        ->toBeNull();
});
