<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaJsonLdAtIdDeduplication;
use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;
use App\Services\SchemaTemplates\SchemaThingFromRelationshipResolver;
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Tests entry template context for schema relationship resolution.
 *
 * Mutation: not recorded. An earlier figure here (100% on App\Services\SchemaTemplates\SchemaThingFromRelationshipResolver,
 * 7 mutants, --covered-only) named no command or revision and was removed (UAMS-Web/uams-statamic-archive#2915).
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaThingFromRelationshipResolverEntryContextTest.php
 */
it('includes permalink and url in the entry template context', function () {
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

    // Mock entry without permalink/url in stored data (typical collection entry)
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('data')->andReturnUsing(static fn (): Collection => collect([
        'title' => 'Example Person',
    ]));
    $entry->shouldReceive('absoluteUrl')->andReturn(
        'https://uams-statamic.test/people/example-person'
    );
    $entry->shouldReceive('url')->andReturn('/people/example-person');

    // Expect resolver to delegate flattened context to SchemaOutputService
    $flattenedForExamplePerson = [
        'title' => 'Example Person',
        'permalink' => 'https://uams-statamic.test/people/example-person',
        'url' => '/people/example-person',
    ];
    $schemaOutputService
        ->shouldReceive('buildFlattenedSchemaAntlersContextFromDataObject')
        ->once()
        ->with($entry)
        ->andReturnUsing(
            fn (): array => $flattenedForExamplePerson
        );

    // Reflect the resolver class for private method invocation
    $reflection = new ReflectionClass($resolver);

    // Resolve private template context builder
    $method = $reflection->getMethod('buildTemplateContextForEntity');

    // Build template context for the mocked entry
    $context = $method->invoke(
        $resolver,
        $entry
    );

    // Assert flattened permalink is present for schema Antlers
    expect(data_get($context, 'permalink'))
        ->toBe('https://uams-statamic.test/people/example-person');

    // Assert flattened relative url is present
    expect(data_get($context, 'url'))->toBe('/people/example-person');
});

it('preserves existing permalink and url in the entry template context', function () {
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

    // Mock entry with explicit permalink/url in data (edge case / custom fields)
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('data')->andReturnUsing(static fn (): Collection => collect([
        'permalink' => 'https://custom.example/keep',
        'url' => 'https://custom.example/keep-path',
    ]));

    // Expect flattened context to preserve stored permalink/url keys
    $flattenedPreservingUrls = [
        'permalink' => 'https://custom.example/keep',
        'url' => 'https://custom.example/keep-path',
    ];
    $schemaOutputService
        ->shouldReceive('buildFlattenedSchemaAntlersContextFromDataObject')
        ->once()
        ->with($entry)
        ->andReturnUsing(
            fn (): array => $flattenedPreservingUrls
        );

    // Reflect the resolver class for private method invocation
    $reflection = new ReflectionClass($resolver);

    // Resolve private template context builder
    $method = $reflection->getMethod('buildTemplateContextForEntity');

    // Build template context for the mocked entry
    $context = $method->invoke(
        $resolver,
        $entry
    );

    // Assert stored data values are preserved
    expect(data_get($context, 'permalink'))->toBe('https://custom.example/keep');
    expect(data_get($context, 'url'))->toBe('https://custom.example/keep-path');
});
