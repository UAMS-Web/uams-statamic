<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaJsonLdAtIdDeduplication;
use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;
use App\Services\SchemaTemplates\SchemaThingFromRelationshipResolver;
use Statamic\Assets\Asset;
use Statamic\Contracts\Data\Augmented as AugmentedContract;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests asset template context building for schema relationship resolution.
 *
 * Mutation: not recorded. An earlier figure here (100% on App\Services\SchemaTemplates\SchemaThingFromRelationshipResolver,
 * 13 mutants, --covered-only) named no command or revision and was removed (UAMS-Web/uams-statamic-archive#2915).
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaThingFromRelationshipResolverAssetContextTest.php
 */
it('merges asset augmented fields into context including computed digital source keys', function () {
    // Mock schema output service dependency
    $schemaOutputService = Mockery::mock(SchemaOutputService::class);

    // Mock cascade service dependency
    $cascadeService = Mockery::mock(SchemaTemplatesCascadeService::class);

    // Initialize resolver with mocked dependencies
    $resolver = new SchemaThingFromRelationshipResolver(
        $schemaOutputService,
        $cascadeService,
        new SchemaJsonLdAtIdDeduplication
    );

    // Mock augmented payload with computed digital source fields
    $augmented = Mockery::mock(AugmentedContract::class);
    $augmented->shouldReceive('all')->andReturn([
        'digital_source_type_value' => 'humanEdits',
        'digital_source_type_value_schema' => 'https://schema.org/MinorHumanEditsDigitalSource',
    ]);

    // Mock asset with blueprint data and augmented computed values
    $asset = Mockery::mock(Asset::class);
    $asset->shouldReceive('data')->andReturn(
        collect([
            'digital_source_type' => [
                'preset' => 'humanEdits_audio_mastered',
            ],
        ])
    );
    $asset->shouldReceive('augmented')->andReturn($augmented);
    $asset->shouldReceive('absoluteUrl')->andReturn(
        'https://cdn.example/audio.mp3'
    );
    $asset->shouldReceive('size')->andReturn(2181241);
    $asset->shouldReceive('lastModified')->andReturn(1774986109);
    $asset->shouldReceive('width')->andReturn(null);
    $asset->shouldReceive('height')->andReturn(null);
    $asset->shouldReceive('mimeType')->andReturn('audio/mpeg');
    $asset->shouldReceive('duration')->andReturn(90.122375);
    $asset->shouldReceive('bitrate')->andReturn(192000);

    // Reflect the resolver class for private method invocation
    $reflection = new ReflectionClass($resolver);

    // Resolve private augmented-context merge method
    $method = $reflection->getMethod('mergeAssetAugmentedFieldsIntoContext');

    // Build initial context as it exists before augmented merge
    $initialContext = [
        'digital_source_type' => [
            'preset' => 'humanEdits_audio_mastered',
        ],
    ];

    // Merge augmented keys into context
    $context = $method->invoke(
        $resolver,
        $asset,
        $initialContext
    );

    // Assert the computed schema key is included in asset context
    expect(data_get($context, 'digital_source_type_value_schema'))
        ->toBe('https://schema.org/MinorHumanEditsDigitalSource');

    // Assert the computed value key is included in asset context
    expect(data_get($context, 'digital_source_type_value'))
        ->toBe('humanEdits');
});
