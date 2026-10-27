<?php

declare(strict_types=1);

use App\Scopes\CredentialTypeCertification;
use App\Services\Encoding\UrlSafeBase64StringCodec;
use App\Services\SchemaTemplates\SchemaJsonLdAtIdDeduplication;
use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplateEmitContext;
use App\Services\SchemaTemplates\SchemaTemplateInstanceWatermarkBag;
use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;
use App\Services\SchemaTemplates\SchemaThingFromRelationshipResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Assert;
use Statamic\Assets\Asset;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Globals\GlobalSet as GlobalSetContract;
use Statamic\Entries\Collection as StatamicCollection;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Data;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\GlobalSet as GlobalSetFacade;
use Statamic\Facades\Term;
use Statamic\Globals\Variables;
use Statamic\Sites\Site;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

/**
 * Unit tests for {@see SchemaThingFromRelationshipResolver} JSON-LD ImageObject output.
 *
 * @covers \App\Services\SchemaTemplates\SchemaThingFromRelationshipResolver
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaThingFromRelationshipResolverTest.php
 *
 * Mutation, THIS FILE ALONE: 589 of 993 mutants tested (59.32%), 404 untested, measured
 * 2026-10-07 on f86d9c164 (macOS, Herd PHP 8.4, Herd's bundled Xdebug as the coverage
 * driver, serial) for UAMS-Web/uams-statamic-archive#2915:
 *
 *     php -d zend_extension=<Herd's xdebug-84 .so> -d xdebug.mode=coverage vendor/bin/pest \
 *       tests/Feature/Services/SchemaTemplates/SchemaThingFromRelationshipResolverTest.php \
 *       --mutate --covered-only --no-cache --path=app/Services/SchemaTemplates \
 *       --class='App\Services\SchemaTemplates\SchemaThingFromRelationshipResolver'
 *
 * A run over every test file that covers the class is a different measurement and is not
 * recorded. Do not add `--parallel` with `--passthru-php`: every mutant then scores killed
 * without running a test (UAMS-Web/uams-statamic-archive#2926).
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

// One test resolves the real `audio_series::heres-to-your-health` term via `Term::find`;
// register the taxonomy so its `taxonomy()` is never null on the render path (UAMS-Web/uams-statamic-archive#1611).
beforeEach(fn () => ensureAudioSeriesTaxonomyRegistered());

/**
 * Create a resolver instance with mocked dependencies.
 *
 * @param  SchemaOutputService  $schemaOutputService  Mocked schema output service.
 * @return SchemaThingFromRelationshipResolver Resolver under test.
 */
function schemaThingResolverMakeResolver(
    SchemaOutputService $schemaOutputService
): SchemaThingFromRelationshipResolver {
    // Mock cascade service for constructor dependency.
    $cascadeService = Mockery::mock(SchemaTemplatesCascadeService::class);

    // Allow tests to focus on schema output behavior.
    $cascadeService->shouldIgnoreMissing();

    // Build and return resolver with mocked dependencies.
    return new SchemaThingFromRelationshipResolver(
        $schemaOutputService,
        $cascadeService,
        new SchemaJsonLdAtIdDeduplication
    );
}

/**
 * The `biology` entry in the real `subject_matter` collection, which carries a Wikidata `same_as`.
 */
const SCHEMA_THING_RESOLVER_BIOLOGY_SUBJECT_ID = '0345e88a-5350-5d63-84dc-c18a05538cea';

/**
 * Stub one `subject_matter` entry and route `Entry::find($id)` to it.
 *
 * Since UAMS-Web/uams-statamic-archive#2822 a subject reference is an entry id in the `subject_matter` collection, which has
 * no route, so the resolver gives the entry the `/subject-matter/{slug}` @id its term had.
 *
 * @param  string  $id  Entry id the field stores.
 * @param  string  $slug  Entry slug the @id is built from.
 */
function schemaThingResolverStubSubjectMatterEntry(
    string $id,
    string $slug
): void {
    // Collection double: the resolver compares its handle with `direct_scope`
    $collection = Mockery::mock(StatamicCollection::class);
    $collection->shouldReceive('handle')
        ->andReturn('subject_matter');

    // Entry double: routeless, so no URL, and an @id derived from the slug
    $subject = Mockery::mock(EntryModel::class);
    $subject->shouldReceive('id')
        ->andReturn($id);
    $subject->shouldReceive('slug')
        ->andReturn($slug);
    $subject->shouldReceive('collection')
        ->andReturn($collection);
    $subject->shouldReceive('collectionHandle')
        ->andReturn('subject_matter');
    $subject->shouldReceive('absoluteUrl')
        ->andReturnNull();
    $subject->shouldReceive('url')
        ->andReturnNull();

    // Only `find($id)` is stubbed: another `find()` argument has no expectation, and other
    // repository methods run on a repository whose constructor never ran, so the calling
    // test must make no other `Entry` facade call
    EntryFacade::partialMock()
        ->shouldReceive('find')
        ->once()
        ->with($id)
        ->andReturn($subject);
}

/**
 * First `credentials` term slug matching CTDL Credential root plus a credential subtype value.
 */
function schemaThingResolverFirstCredentialsSlugWithCredentialRootAndSubtype(
    string $subtype
): ?string {
    // Walk credentials taxonomy entries for live fixture-driven tests
    foreach (Term::whereTaxonomy(CredentialTypeCertification::TAXONOMY) as $term) {
        /** @var TaxonomyTerm $term */
        $localized = $term->inDefaultLocale();

        /** @var LocalizedTerm $localized */
        // Read bundled CTDL group data from repository-backed terms
        $ctdl = $localized->get('ctdl');

        // Skip payloads without usable CTDL data
        if (! \is_array($ctdl)) {
            unset($ctdl);

            continue;
        }

        // Match CTDL root class before inspecting subtype fields
        if (($ctdl['ceterms_type'] ?? null) !== CredentialTypeCertification::CTDL_CETERMS_TYPE_VALUE_CREDENTIAL) {
            unset($ctdl);

            continue;
        }

        // Match subtype (for example Certification or Degree)
        if (($ctdl['ceterms_credential_type'] ?? null) !== $subtype) {
            unset($ctdl);

            continue;
        }

        $slug = $localized->slug();

        unset($ctdl);

        return \is_string($slug) ? $slug : null;
    }

    return null;
}

/**
 * Missing schema_object yields an empty JSON array without calling the schema service
 */
it('returns empty json when schema object missing', function (): void {
    // Mock the SchemaOutputService
    $schema = Mockery::mock(SchemaOutputService::class);

    // Expect the buildStandaloneSchemaObjectNode method to be never called
    $schema->shouldReceive('buildStandaloneSchemaObjectNode')->never();

    // Create a new SchemaThingFromRelationshipResolver
    $resolver = schemaThingResolverMakeResolver($schema);

    // Resolve the assets
    $out = $resolver->resolve(
        ['gallery' => []],
        [
            'mode' => 'assets',
            'current_field' => 'gallery',
        ]
    );

    // Assert that the output is the expected JSON
    expect($out)->toBe('[]');

    // Unset local variables to free memory
    unset(
        $out,
        $resolver,
        $schema
    );
});

/**
 * With emit context, a pre-registered full-node @id skips standalone template
 * expansion (compact reference only).
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaThingFromRelationshipResolverTest.php --filter=test_emit_context_skips_standalone_build_when_canonical_at_id_already_emitted
 */
it('emit context skips standalone build when canonical at id already emitted', function (): void {
    $canonicalUrl = 'https://credential.example/id-one';
    $templateId = '180b665b-8f2c-4d5e-9b1a-aaaaaaaaaaaa';

    $targetEntry = Mockery::mock(EntryContract::class);
    $targetEntry->shouldReceive('absoluteUrl')->andReturn($canonicalUrl);

    $schema = Mockery::mock(SchemaOutputService::class);
    $schema->shouldReceive('buildFlattenedSchemaAntlersContextFromDataObject')->andReturn([]);
    $schema->shouldReceive('buildStandaloneSchemaObjectNodeSharingEmittedNodeIds')->never();

    $resolver = schemaThingResolverMakeResolver($schema);

    $emittedEntryIds = [];
    $emittedNodeIds = [$canonicalUrl => true];
    $materializedFullNodeAtIds = [$canonicalUrl => true];
    $emitContext = new SchemaTemplateEmitContext(
        $emittedEntryIds,
        $emittedNodeIds,
        $materializedFullNodeAtIds,
        [],
        null,
        new SchemaTemplateInstanceWatermarkBag
    );

    $params = [
        'mode' => 'direct',
        'current_field' => 'credential_ref',
        'schema_object' => $templateId,
        'emit_context' => $emitContext,
    ];
    $context = ['credential_ref' => $targetEntry];

    $out = $resolver->resolve($context, $params);
    $decoded = json_decode($out, true);

    expect($decoded)->toBeArray();
    expect($decoded)->toHaveCount(1);

    /** @var list<array<string,mixed>> $decoded */
    expect($decoded[0])->toBe(['@id' => $canonicalUrl]);

    unset(
        $canonicalUrl,
        $context,
        $decoded,
        $emitContext,
        $emittedEntryIds,
        $emittedNodeIds,
        $out,
        $params,
        $resolver,
        $schema,
        $targetEntry,
        $templateId
    );
});

/**
 * A second tag resolve with the same emit context skips rebuilding after the
 * pass map records the canonical @id (simulating parent JSON decode dedupe).
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaThingFromRelationshipResolverTest.php --filter=test_emit_context_second_resolve_skips_standalone_build_after_at_id_registered
 */
it('emit context second resolve skips standalone build after at id registered', function (): void {
    $canonicalUrl = 'https://credential.example/id-two';
    $templateId = '280b665b-8f2c-4d5e-9b1a-bbbbbbbbbbbb';

    $targetEntry = Mockery::mock(EntryContract::class);
    $targetEntry->shouldReceive('absoluteUrl')->andReturn($canonicalUrl);

    $schema = Mockery::mock(SchemaOutputService::class);
    $schema->shouldReceive('buildFlattenedSchemaAntlersContextFromDataObject')->andReturn([]);
    $schema->shouldReceive('buildStandaloneSchemaObjectNodeSharingEmittedNodeIds')
        ->once()
        ->andReturnUsing(
            static fn (): array => [
                '@id' => 'https://ignored.test/#wrong',
                '@type' => 'EducationalOccupationalCredential',
                'name' => 'MD',
            ]
        );

    $resolver = schemaThingResolverMakeResolver($schema);

    $emittedEntryIds = [];
    $emittedNodeIds = [];
    $materializedFullNodeAtIds = [];
    $emitContext = new SchemaTemplateEmitContext(
        $emittedEntryIds,
        $emittedNodeIds,
        $materializedFullNodeAtIds,
        [],
        null,
        new SchemaTemplateInstanceWatermarkBag
    );

    $params = [
        'mode' => 'direct',
        'current_field' => 'credential_ref',
        'schema_object' => $templateId,
        'emit_context' => $emitContext,
    ];
    $context = ['credential_ref' => $targetEntry];

    $firstOut = $resolver->resolve($context, $params);
    $emittedNodeIds[$canonicalUrl] = true;
    $materializedFullNodeAtIds[$canonicalUrl] = true;
    $secondOut = $resolver->resolve($context, $params);

    $firstDecoded = json_decode($firstOut, true);
    $secondDecoded = json_decode($secondOut, true);

    expect($firstDecoded)->toBeArray();

    /** @var list<array<string,mixed>> $firstDecoded */
    expect($firstDecoded[0])->toHaveKey('@type');
    expect($firstDecoded[0]['@id'])->toBe($canonicalUrl);

    expect($secondDecoded)->toBeArray();

    /** @var list<array<string,mixed>> $secondDecoded */
    expect($secondDecoded[0])->toBe(['@id' => $canonicalUrl]);

    unset(
        $canonicalUrl,
        $context,
        $emitContext,
        $emittedEntryIds,
        $emittedNodeIds,
        $firstDecoded,
        $firstOut,
        $params,
        $resolver,
        $schema,
        $secondDecoded,
        $secondOut,
        $targetEntry,
        $templateId
    );
});

/**
 * Duplicate targets in one invocation share one full standalone build; the
 * second target reuses a compact @id (output list still dedupes by @id).
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaThingFromRelationshipResolverTest.php --filter=test_duplicate_targets_in_one_resolve_call_standalone_build_once
 */
it('duplicate targets in one resolve call standalone build once', function (): void {
    $canonicalUrl = 'https://credential.example/id-three';
    $templateId = '380b665b-8f2c-4d5e-9b1a-cccccccccccc';

    $targetEntry = Mockery::mock(EntryContract::class);
    $targetEntry->shouldReceive('absoluteUrl')->andReturn($canonicalUrl);

    $schema = Mockery::mock(SchemaOutputService::class);
    $schema->shouldReceive('buildFlattenedSchemaAntlersContextFromDataObject')->andReturn([]);
    $schema->shouldReceive('buildStandaloneSchemaObjectNodeSharingEmittedNodeIds')
        ->once()
        ->andReturnUsing(
            static fn (): array => [
                '@id' => 'https://ignored.test/#wrong',
                '@type' => 'EducationalOccupationalCredential',
                'name' => 'Once',
            ]
        );

    $resolver = schemaThingResolverMakeResolver($schema);

    $emittedEntryIds = [];
    $emittedNodeIds = [];
    $materializedFullNodeAtIds = [];
    $emitContext = new SchemaTemplateEmitContext(
        $emittedEntryIds,
        $emittedNodeIds,
        $materializedFullNodeAtIds,
        [],
        null,
        new SchemaTemplateInstanceWatermarkBag
    );

    $params = [
        'mode' => 'direct',
        'current_field' => 'credential_ref',
        'schema_object' => $templateId,
        'emit_context' => $emitContext,
    ];
    $context = [
        'credential_ref' => [
            $targetEntry,
            $targetEntry,
        ],
    ];

    $out = $resolver->resolve($context, $params);
    $decoded = json_decode($out, true);

    expect($decoded)->toBeArray();
    expect($decoded)->toHaveCount(1);

    /** @var list<array<string,mixed>> $decoded */
    expect($decoded[0]['@id'])->toBe($canonicalUrl);
    expect($decoded[0])->toHaveKey('@type');

    unset(
        $canonicalUrl,
        $context,
        $decoded,
        $emitContext,
        $emittedEntryIds,
        $emittedNodeIds,
        $out,
        $params,
        $resolver,
        $schema,
        $targetEntry,
        $templateId
    );
});

/**
 * When the template entry does not resolve to a node, output stays empty
 */
it('returns empty json when template node is null', function (): void {
    // Mock the SchemaOutputService
    $schema = Mockery::mock(SchemaOutputService::class);

    // Expect the buildStandaloneSchemaObjectNode method to never be called
    $schema->shouldReceive('buildStandaloneSchemaObjectNode')->never();

    // Create a new SchemaThingFromRelationshipResolver
    $resolver = schemaThingResolverMakeResolver($schema);

    // Resolve the assets
    $out = $resolver->resolve(
        [],
        [
            'mode' => 'assets',
            'current_field' => 'gallery',
            'schema_object' => 'template-entry-id',
        ]
    );

    // Assert that the output is the expected JSON
    expect($out)->toBe('[]');

    // Unset local variables to free memory
    unset(
        $out,
        $resolver,
        $schema
    );
});

/**
 * Assets mode sets @id from asset URL and preserves template properties.
 */
it('assets mode merges asset into template node', function (): void {
    // Mock the Asset
    $asset = Mockery::mock(Asset::class);

    // Expect the absoluteUrl method to be called and return the expected value
    $asset->shouldReceive('absoluteUrl')->andReturn('https://cdn.example/photo.jpg');

    // Mock the SchemaOutputService
    $schema = Mockery::mock(SchemaOutputService::class);

    // Expect the buildStandaloneSchemaObjectNode method to be called once
    $schema->shouldReceive('buildStandaloneSchemaObjectNode')
        ->once()
        ->andReturnUsing(fn (): array => [
            '@id' => 'https://site.test/#template-uuid',
            '@type' => 'ImageObject',
            'url' => 'https://site.test/path/from-template.jpg',
            'contentUrl' => 'https://site.test/content/from-template.jpg',
            'encodingFormat' => 'image/webp',
            'width' => 640,
            'height' => 480,
            'name' => 'Layer',
        ]);

    // Create a new SchemaThingFromRelationshipResolver
    $resolver = schemaThingResolverMakeResolver($schema);

    // Resolve the assets
    $out = $resolver->resolve(
        [
            'gallery' => [$asset],
        ],
        [
            'mode' => 'assets',
            'current_field' => 'gallery',
            'schema_object' => 'template-entry-id',
        ]
    );

    // Decode the output as an array
    $decoded = json_decode(
        $out,
        true
    );

    // Assert that the decoded output is an array
    expect($decoded)->toBeArray();

    // Assert that the decoded output has one item
    expect($decoded)->toHaveCount(1);

    /** @var list<array<string,mixed>> $decoded */
    // Assert that the decoded output sets @id from asset URL
    expect($decoded[0]['@id'])->toBe('https://cdn.example/photo.jpg');

    // Assert that the decoded output preserves template url
    expect($decoded[0]['url'])->toBe('https://site.test/path/from-template.jpg');

    // Assert that the decoded output preserves template contentUrl
    expect($decoded[0]['contentUrl'])->toBe('https://site.test/content/from-template.jpg');

    // Assert that the decoded output preserves template encodingFormat
    expect($decoded[0]['encodingFormat'])->toBe('image/webp');

    // Assert that the decoded output has the expected width
    expect($decoded[0]['width'])->toBe(640);

    // Assert that the decoded output has the expected height
    expect($decoded[0]['height'])->toBe(480);

    // Assert that the decoded output has the expected name
    expect($decoded[0]['name'])->toBe('Layer');

    // Unset local variables to free memory
    unset(
        $asset,
        $decoded,
        $out,
        $resolver,
        $schema
    );
});

/**
 * Assets mode keeps template-provided width/height values when present.
 */
it('assets mode preserves template dimensions when present', function (): void {
    // Mock the Asset
    $asset = Mockery::mock(Asset::class);

    // Expect the absoluteUrl method to be called and return the expected value
    $asset->shouldReceive('absoluteUrl')->andReturn('https://cdn.example/photo.jpg');

    // Expect the mimeType method to be called and return the expected value
    $asset->shouldReceive('mimeType')->andReturn('image/jpeg');

    // Expect the width method to be called and return the expected value
    $asset->shouldReceive('width')->andReturn(640);

    // Expect the height method to be called and return the expected value
    $asset->shouldReceive('height')->andReturn(480);

    // Mock the SchemaOutputService
    $schema = Mockery::mock(SchemaOutputService::class);

    // Expect the buildStandaloneSchemaObjectNode method to be called once
    $schema->shouldReceive('buildStandaloneSchemaObjectNode')
        ->once()
        ->andReturnUsing(fn (): array => [
            '@id' => 'https://site.test/#template-uuid',
            '@type' => 'ImageObject',
            'height' => [
                480,
                '480 px',
                [
                    '@type' => 'https://schema.org/QuantitativeValue',
                    'value' => 480,
                    'unitCode' => 'E37',
                    'unitText' => 'pixel',
                ],
            ],
            'width' => [
                640,
                '640 px',
            ],
        ]);

    // Create a new SchemaThingFromRelationshipResolver
    $resolver = schemaThingResolverMakeResolver($schema);

    // Resolve the assets
    $out = $resolver->resolve(
        [
            'gallery' => [$asset],
        ],
        [
            'mode' => 'assets',
            'current_field' => 'gallery',
            'schema_object' => 'template-entry-id',
        ]
    );

    // Decode the output as an array
    $decoded = json_decode(
        $out,
        true
    );

    // Assert that the decoded output is an array
    expect($decoded)->toBeArray();

    // Assert that the decoded output has one item
    expect($decoded)->toHaveCount(1);

    /** @var list<array<string,mixed>> $decoded */
    // Assert that template-provided width is preserved
    expect($decoded[0]['width'])->toBeArray();

    /** @var array<int,mixed> $widthValue */
    $widthValue = $decoded[0]['width'];
    expect($widthValue[0])->toBe(640);

    // Assert that template-provided height is preserved
    expect($decoded[0]['height'])->toBeArray();

    /** @var array<int,mixed> $heightValue */
    $heightValue = $decoded[0]['height'];
    expect($heightValue[0])->toBe(480);

    // Assert that the nested QuantitativeValue is preserved
    expect($heightValue[2])->toBeArray();

    /** @var array<string,mixed> $heightQuantitativeValue */
    $heightQuantitativeValue = $heightValue[2];
    expect($heightQuantitativeValue['@type'])->toBe('https://schema.org/QuantitativeValue');

    // Unset local variables to free memory
    unset(
        $asset,
        $decoded,
        $heightQuantitativeValue,
        $heightValue,
        $out,
        $resolver,
        $schema,
        $widthValue
    );
});

/**
 * Assets mode keeps template properties and overrides @id.
 */
it('assets mode preserves template identity and format properties', function (): void {
    // Mock the Asset
    $asset = Mockery::mock(Asset::class);

    // Expect the absoluteUrl method to be called and return the expected value
    $asset->shouldReceive('absoluteUrl')->andReturn('https://cdn.example/photo.jpg');

    // Expect the mimeType method to be called and return the expected value
    $asset->shouldReceive('mimeType')->andReturn('image/jpeg');

    // Expect the width method to be called and return the expected value
    $asset->shouldReceive('width')->andReturn(640);

    // Expect the height method to be called and return the expected value
    $asset->shouldReceive('height')->andReturn(480);

    // Mock the SchemaOutputService
    $schema = Mockery::mock(SchemaOutputService::class);

    // Expect the buildStandaloneSchemaObjectNode method to be called once
    $schema->shouldReceive('buildStandaloneSchemaObjectNode')
        ->once()
        ->andReturnUsing(fn (): array => [
            '@id' => 'https://site.test/#template-uuid',
            '@type' => 'ImageObject',
            'url' => 'https://site.test/path/from-template.jpg',
            'contentUrl' => 'https://site.test/content/from-template.jpg',
            'encodingFormat' => 'image/webp',
        ]);

    // Create a new SchemaThingFromRelationshipResolver
    $resolver = schemaThingResolverMakeResolver($schema);

    // Resolve the assets
    $out = $resolver->resolve(
        [
            'gallery' => [$asset],
        ],
        [
            'mode' => 'assets',
            'current_field' => 'gallery',
            'schema_object' => 'template-entry-id',
        ]
    );

    // Decode the output as an array
    $decoded = json_decode(
        $out,
        true
    );

    // Assert that the decoded output is an array
    expect($decoded)->toBeArray();

    // Assert that the decoded output has one item
    expect($decoded)->toHaveCount(1);

    /** @var list<array<string,mixed>> $decoded */
    // Assert that @id is always set from the asset URL
    expect($decoded[0]['@id'])->toBe('https://cdn.example/photo.jpg');

    // Assert that template-provided url is preserved
    expect($decoded[0]['url'])->toBe('https://site.test/path/from-template.jpg');

    // Assert that template-provided contentUrl is preserved
    expect($decoded[0]['contentUrl'])->toBe('https://site.test/content/from-template.jpg');

    // Assert that template-provided encodingFormat is preserved
    expect($decoded[0]['encodingFormat'])->toBe('image/webp');

    // Unset local variables to free memory
    unset(
        $asset,
        $decoded,
        $out,
        $resolver,
        $schema
    );
});

/**
 * Assets mode evaluates the schema template with per-asset Antlers context.
 */
it('assets mode passes asset context to schema template', function (): void {
    // Mock the Asset
    $asset = Mockery::mock(Asset::class);

    // Expect the absolute URL used for merged node output
    $asset->shouldReceive('absoluteUrl')->andReturn('https://cdn.example/photo.jpg');

    // Expect optional metadata to be absent for this assertion-focused test
    $asset->shouldReceive('mimeType')->andReturn(null);
    $asset->shouldReceive('width')->andReturn(null);
    $asset->shouldReceive('height')->andReturn(null);
    $asset->shouldReceive('size')->andReturn(1552597);
    $asset->shouldReceive('lastModified')->andReturn(1771437537);
    $asset->shouldReceive('duration')->andReturn(null);

    // Expect the asset data context to include caption and alt fields
    $assetData = collect([
        'caption' => 'Asset Caption',
        'alt' => 'Asset Alt',
    ]);
    $asset->shouldReceive('data')->andReturn($assetData);

    // Mock the SchemaOutputService
    $schema = Mockery::mock(SchemaOutputService::class);

    // Expect the schema template to receive a per-asset Antlers context array
    $schema->shouldReceive('buildStandaloneSchemaObjectNode')
        ->once()
        ->withArgs(
            fn (
                string $templateId,
                $contextEntity,
                array $antlersContext
            ): bool => $templateId === 'template-entry-id'
                && ($antlersContext['caption'] ?? null) === 'Asset Caption'
                && ($antlersContext['alt'] ?? null) === 'Asset Alt'
                && ($antlersContext['size'] ?? null) === 1552597
                && ($antlersContext['last_modified'] ?? null) === 1771437537
                && \array_key_exists('width', $antlersContext)
                && \array_key_exists('height', $antlersContext)
                && \array_key_exists('mime_type', $antlersContext)
                && \array_key_exists('duration', $antlersContext)
                && isset($antlersContext['asset'])
        )
        ->andReturnUsing(fn (): array => [
            '@id' => 'https://site.test/#template-uuid',
            '@type' => 'ImageObject',
            'name' => 'Context-aware image',
        ]);

    // Create a new SchemaThingFromRelationshipResolver
    $resolver = schemaThingResolverMakeResolver($schema);

    // Resolve the assets
    $out = $resolver->resolve(
        [
            'featured_image_16_9' => [$asset],
        ],
        [
            'mode' => 'assets',
            'current_field' => 'featured_image_16_9',
            'schema_object' => 'template-entry-id',
        ]
    );

    // Decode the output as an array
    $decoded = json_decode(
        $out,
        true
    );

    // Assert that the decoded output is an array
    expect($decoded)->toBeArray();

    // Assert that the decoded output has one item
    expect($decoded)->toHaveCount(1);

    /** @var list<array<string,mixed>> $decoded */
    // Assert that the schema output keeps template-provided properties
    expect($decoded[0]['name'])->toBe('Context-aware image');

    // Unset local variables to free memory
    unset(
        $asset,
        $assetData,
        $decoded,
        $out,
        $resolver,
        $schema
    );
});

/**
 * Assets mode resolves context entity from request URL when context id is absent.
 */
it('assets mode uses request entity when context id missing', function (): void {
    // Mock the Asset
    $asset = Mockery::mock(Asset::class);

    // Expect the absolute URL used for merged node output
    $asset->shouldReceive('absoluteUrl')->andReturn('https://cdn.example/photo.jpg');

    // Expect optional metadata to be absent for this assertion-focused test
    $asset->shouldReceive('mimeType')->andReturn(null);
    $asset->shouldReceive('width')->andReturn(null);
    $asset->shouldReceive('height')->andReturn(null);

    // Expect the asset data context to be readable
    $asset->shouldReceive('data')->andReturn(collect([]));

    // Mock the current request context entity resolved by URL
    $requestEntity = Mockery::mock(EntryModel::class);

    // Stub site so mergeSourceContextIntoTemplateContext can resolve source_site
    $requestSite = Mockery::mock(Site::class);
    $requestSite->shouldReceive('handle')->andReturn('default');
    $requestEntity->shouldReceive('site')->andReturn($requestSite);

    Data::shouldReceive('findByRequestUrl')->once()->andReturn($requestEntity);

    // Mock the SchemaOutputService
    $schema = Mockery::mock(SchemaOutputService::class);

    // Expect asset template build: template context entity is null for assets;
    // request entry is merged as source_entry on the Antlers bag
    $schema->shouldReceive('buildStandaloneSchemaObjectNode')
        ->once()
        ->withArgs(
            fn (
                string $templateId,
                $contextEntity,
                array $antlersContext
            ): bool => $templateId === 'template-entry-id'
                && $contextEntity === null
                && ($antlersContext['source_entry'] ?? null) === $requestEntity
                && isset($antlersContext['asset'])
        )
        ->andReturnUsing(fn (): array => [
            '@id' => 'https://site.test/#template-uuid',
            '@type' => 'ImageObject',
            'name' => 'Context-aware image',
        ]);

    // Create a new SchemaThingFromRelationshipResolver
    $resolver = schemaThingResolverMakeResolver($schema);

    // Resolve assets without an id in context so request fallback is used
    $out = $resolver->resolve(
        [
            'featured_image_16_9' => [$asset],
        ],
        [
            'mode' => 'assets',
            'current_field' => 'featured_image_16_9',
            'schema_object' => 'template-entry-id',
        ]
    );

    // Decode the output as an array
    $decoded = json_decode(
        $out,
        true
    );

    // Assert that one merged node is returned
    expect($decoded)->toBeArray();
    expect($decoded)->toHaveCount(1);

    // Unset local variables to free memory
    unset(
        $asset,
        $decoded,
        $out,
        $requestEntity,
        $requestSite,
        $resolver,
        $schema
    );
});

/**
 * Pipe-separated field uses the first handle with assets when a prior handle is empty
 */
it('assets mode pipe field fallback uses first non empty handle', function (): void {
    // Mock the Asset
    $asset = Mockery::mock(Asset::class);

    // Expect the absolute URL used for merged node output
    $asset->shouldReceive('absoluteUrl')->andReturn('https://cdn.example/photo.jpg');

    // Expect optional metadata to be absent for this assertion-focused test
    $asset->shouldReceive('mimeType')->andReturn(null);
    $asset->shouldReceive('width')->andReturn(null);
    $asset->shouldReceive('height')->andReturn(null);

    // Mock the SchemaOutputService
    $schema = Mockery::mock(SchemaOutputService::class);

    // Expect the buildStandaloneSchemaObjectNode method to be called once
    $schema->shouldReceive('buildStandaloneSchemaObjectNode')
        ->once()
        ->andReturnUsing(fn (): array => [
            '@id' => 'https://site.test/#template-uuid',
            '@type' => 'ImageObject',
        ]);

    // Create a new SchemaThingFromRelationshipResolver
    $resolver = schemaThingResolverMakeResolver($schema);

    // Resolve the assets
    $out = $resolver->resolve(
        [
            'featured_image_16_9' => [],
            'featured_image' => [$asset],
        ],
        [
            'mode' => 'assets',
            'current_field' => 'featured_image_16_9|featured_image',
            'schema_object' => 'template-entry-id',
        ]
    );

    // Decode the output as an array
    $decoded = json_decode(
        $out,
        true
    );

    // Assert that the decoded output is an array
    expect($decoded)->toBeArray();

    // Assert that the decoded output has one item
    expect($decoded)->toHaveCount(1);

    /** @var list<array<string,mixed>> $decoded */
    // Assert that @id is always set from the asset URL
    expect($decoded[0]['@id'])->toBe('https://cdn.example/photo.jpg');

    // Unset local variables to free memory
    unset(
        $asset,
        $decoded,
        $out,
        $resolver,
        $schema
    );
});

/**
 * entry_limit in assets mode caps how many assets are emitted
 */
it('assets mode entry limit truncates list', function (): void {
    // Mock the Asset
    $a1 = Mockery::mock(Asset::class);

    // Expect the absoluteUrl method to be called and return the expected value
    $a1->shouldReceive('absoluteUrl')->andReturn('https://cdn.example/a.jpg');

    // Expect the mimeType method to be called and return the expected value
    $a1->shouldReceive('mimeType')->andReturn(null);

    // Expect the width method to be called and return the expected value
    $a1->shouldReceive('width')->andReturn(null);

    // Expect the height method to be called and return the expected value
    $a1->shouldReceive('height')->andReturn(null);

    // Mock the Asset
    $a2 = Mockery::mock(Asset::class);

    // Expect the absoluteUrl method to be called and return the expected value
    $a2->shouldReceive('absoluteUrl')->andReturn('https://cdn.example/b.jpg');

    // Expect the mimeType method to be called and return the expected value
    $a2->shouldReceive('mimeType')->andReturn(null);

    // Expect the width method to be called and return the expected value
    $a2->shouldReceive('width')->andReturn(null);

    // Expect the height method to be called and return the expected value
    $a2->shouldReceive('height')->andReturn(null);

    // Mock the SchemaOutputService
    $schema = Mockery::mock(SchemaOutputService::class);

    // Expect the buildStandaloneSchemaObjectNode method to be called once
    $schema->shouldReceive('buildStandaloneSchemaObjectNode')
        ->once()
        ->andReturnUsing(fn (): array => [
            '@id' => 'https://site.test/#t',
            '@type' => 'ImageObject',
        ]);

    // Create a new SchemaThingFromRelationshipResolver
    $resolver = schemaThingResolverMakeResolver($schema);

    // Resolve the assets
    $out = $resolver->resolve(
        [
            'gallery' => [
                $a1,
                $a2,
            ],
        ],
        [
            'mode' => 'assets',
            'current_field' => 'gallery',
            'current_limit' => 1,
            'schema_object' => 'template-entry-id',
        ]
    );

    // Decode the output as an array
    $decoded = json_decode(
        $out,
        true
    );

    // Assert that the decoded output is an array
    expect($decoded)->toBeArray();

    // Assert that the decoded output has one item
    expect($decoded)->toHaveCount(1);

    /** @var list<array<string,mixed>> $decoded */
    // Assert that the decoded output sets @id from the first asset URL
    expect($decoded[0]['@id'])->toBe('https://cdn.example/a.jpg');

    // Unset local variables to free memory
    unset(
        $a1,
        $a2,
        $decoded,
        $out,
        $resolver,
        $schema
    );
});

/**
 * Terms mode without term_assets_field yields no assets and an empty array
 */
it('terms mode without term assets field yields empty array', function (): void {
    // Mock the SchemaOutputService
    $schema = Mockery::mock(SchemaOutputService::class);

    // Expect the buildStandaloneSchemaObjectNode method to never be called
    $schema->shouldReceive('buildStandaloneSchemaObjectNode')->never();

    // Create a new SchemaThingFromRelationshipResolver
    $resolver = schemaThingResolverMakeResolver($schema);

    // Resolve the assets
    $out = $resolver->resolve(
        [],
        [
            'mode' => 'terms',
            'current_field' => 'topics',
            'taxonomy' => 'tags',
            'schema_object' => 'template-entry-id',
        ]
    );

    // Assert that the output is the expected JSON
    expect($out)->toBe('[]');

    // Unset local variables to free memory
    unset(
        $out,
        $resolver,
        $schema
    );
});

/**
 * Global mode reads relationship items from a global set field.
 */
it('global mode reads relationship field from global set', function (): void {
    // Mock the Asset
    $asset = Mockery::mock(Asset::class);

    // Expect the absoluteUrl method to be called and return the expected value
    $asset->shouldReceive('absoluteUrl')->andReturn('https://cdn.example/global.jpg');

    // Expect metadata methods used by the asset template context builder
    $asset->shouldReceive('data')->andReturn(collect([]));
    $asset->shouldReceive('size')->andReturn(null);
    $asset->shouldReceive('lastModified')->andReturn(null);
    $asset->shouldReceive('width')->andReturn(null);
    $asset->shouldReceive('height')->andReturn(null);
    $asset->shouldReceive('mimeType')->andReturn(null);
    $asset->shouldReceive('duration')->andReturn(null);
    $asset->shouldReceive('bitrate')->andReturn(null);

    // Mock global variables and the accountable_person relationship field value
    $variables = Mockery::mock(Variables::class);
    $variables->shouldReceive('get')
        ->with('accountable_person')
        ->andReturnUsing(
            fn (): array => [$asset]
        );

    // Mock global set and return current-site variables
    $globalSet = Mockery::mock(GlobalSetContract::class);
    $globalSet->shouldReceive('inCurrentSite')->once()->andReturn($variables);

    // Resolve the global set by handle
    GlobalSetFacade::shouldReceive('findByHandle')
        ->once()
        ->with('uams_multisite_settings')
        ->andReturn($globalSet);

    // Mock the SchemaOutputService
    $schema = Mockery::mock(SchemaOutputService::class);

    // Expect one schema node to be built from the global relationship target
    $schema->shouldReceive('buildStandaloneSchemaObjectNode')
        ->once()
        ->andReturnUsing(fn (): array => [
            '@type' => 'Person',
        ]);

    // Create a new SchemaThingFromRelationshipResolver
    $resolver = schemaThingResolverMakeResolver($schema);

    // Resolve the global relationship field target
    $out = $resolver->resolve(
        [],
        [
            'mode' => 'global',
            'global_handle' => 'uams_multisite_settings',
            'global_field' => 'accountable_person',
            'schema_object' => 'template-entry-id',
        ]
    );

    // Decode the output as an array
    $decoded = json_decode(
        $out,
        true
    );

    // Assert that one node is emitted from the global relationship field
    expect($decoded)->toBeArray();
    expect($decoded)->toHaveCount(1);

    /** @var list<array<string,mixed>> $decoded */
    expect($decoded[0]['@id'])->toBe('https://cdn.example/global.jpg');
    expect($decoded[0]['@type'])->toBe('Person');

    // Unset local variables to free memory
    unset(
        $asset,
        $decoded,
        $globalSet,
        $out,
        $resolver,
        $schema,
        $variables
    );
});

/**
 * Direct mode with replicator field passes row-level context to schema template.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaThingFromRelationshipResolverTest.php --filter=test_direct_mode_replicator_passes_row_level_context_to_schema_template
 */
it('direct mode replicator passes row level context to schema template', function (): void {
    // Mock the Asset used in the replicator relationship subfield
    $asset = Mockery::mock(Asset::class);

    // Expect canonical asset url used for merged node output
    $asset->shouldReceive('absoluteUrl')->andReturn('https://cdn.example/segment.mp3');

    // ---
    // Expect metadata methods used by the asset template context builder
    // ---

    // Expect asset data bag used for context passthrough
    $asset->shouldReceive('data')->andReturn(collect([]));

    // Expect asset file size exposed to template context
    $asset->shouldReceive('size')->andReturn(1552597);

    // Expect asset last modified timestamp exposed to template context
    $asset->shouldReceive('lastModified')->andReturn(1771437537);

    // Expect optional width metadata value exposed to template context
    $asset->shouldReceive('width')->andReturn(null);

    // Expect optional height metadata value exposed to template context
    $asset->shouldReceive('height')->andReturn(null);

    // Expect asset mime type exposed to template context
    $asset->shouldReceive('mimeType')->andReturn('audio/mpeg');

    // Expect asset duration exposed to template context
    $asset->shouldReceive('duration')->andReturn(291.0);

    // Expect asset bitrate exposed to template context
    $asset->shouldReceive('bitrate')->andReturn(192000);

    // Mock the SchemaOutputService
    $schema = Mockery::mock(SchemaOutputService::class);

    // Expect row-level and asset-level context values to be merged for template parsing
    $schema->shouldReceive('buildStandaloneSchemaObjectNode')
        ->once()
        ->withArgs(
            fn (
                string $templateId,
                $contextEntity,
                array $antlersContext
            ): bool => $templateId === 'template-entry-id'
                && ($antlersContext['replicator_row_id'] ?? null) === 'row-abc'
                && ($antlersContext['replicator_row_index'] ?? null) === 0
                && ($antlersContext['replicator_row_position'] ?? null) === 1
                && ($antlersContext['replicator_row_type'] ?? null) === 'local_file'
                && (data_get($antlersContext, 'replicator_row.title_override') === 'Segment Title Override')
                && (data_get($antlersContext, 'replicator_row.excerpt_override') === 'Segment Summary Override')
                && (data_get($antlersContext, 'replicator_row.loop') === false)
                && ($antlersContext['mime_type'] ?? null) === 'audio/mpeg'
                && ($antlersContext['duration'] ?? null) === 291.0
                && ($antlersContext['bitrate'] ?? null) === 192000
                && isset($antlersContext['asset'])
        )
        ->andReturnUsing(fn (): array => [
            '@type' => 'AudioObject',
            'name' => 'Segment Title Override',
            'position' => 1,
        ]);

    // Create a new SchemaThingFromRelationshipResolver
    $resolver = schemaThingResolverMakeResolver($schema);

    // Resolve a direct-mode replicator field with relationship subfield extraction
    $out = $resolver->resolve(
        [
            'segments' => [
                [
                    'type' => 'local_file',
                    '_id' => 'row-abc',
                    'value' => [
                        'file' => [$asset],
                        'title_override' => 'Segment Title Override',
                        'excerpt_override' => 'Segment Summary Override',
                        'loop' => false,
                    ],
                ],
            ],
        ],
        [
            'mode' => 'direct',
            'current_field' => 'segments',
            'current_field_replicator' => 'true',
            'current_field_replicator_relationship_field' => 'file',
            'schema_object' => 'template-entry-id',
        ]
    );

    // Decode the output as an array
    $decoded = json_decode(
        $out,
        true
    );

    // ---
    // Assert that one node is emitted from the replicator relationship field
    // ---

    // Assert that decoded output is a JSON array structure
    expect($decoded)->toBeArray();

    // Assert that exactly one node is emitted
    expect($decoded)->toHaveCount(1);

    /** @var list<array<string,mixed>> $decoded */
    // Assert that canonical node @id comes from asset URL
    expect($decoded[0]['@id'])->toBe('https://cdn.example/segment.mp3');

    // Assert that template node preserves row-level title override
    expect($decoded[0]['name'])->toBe('Segment Title Override');

    // Assert that template node preserves row-level position value
    expect($decoded[0]['position'])->toBe(1);

    // Unset local variables to free memory
    unset(
        $asset,
        $decoded,
        $out,
        $resolver,
        $schema
    );
});

/**
 * Direct mode supports flat replicator row shape from entry front matter.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaThingFromRelationshipResolverTest.php --filter=test_direct_mode_replicator_supports_flat_row_shape
 */
it('direct mode replicator supports flat row shape', function (): void {
    // Mock the Asset used in the flat replicator relationship field
    $asset = Mockery::mock(Asset::class);

    // Expect canonical asset url used for merged node output
    $asset->shouldReceive('absoluteUrl')->andReturn('https://cdn.example/segment-flat.mp3');

    // Expect metadata methods used by the asset template context builder
    $asset->shouldReceive('data')->andReturn(collect([]));
    $asset->shouldReceive('size')->andReturn(null);
    $asset->shouldReceive('lastModified')->andReturn(null);
    $asset->shouldReceive('width')->andReturn(null);
    $asset->shouldReceive('height')->andReturn(null);
    $asset->shouldReceive('mimeType')->andReturn('audio/mpeg');
    $asset->shouldReceive('duration')->andReturn(120.0);
    $asset->shouldReceive('bitrate')->andReturn(192000);

    // Mock the SchemaOutputService
    $schema = Mockery::mock(SchemaOutputService::class);

    // Expect flat row-level context values to be available to template parsing
    $schema->shouldReceive('buildStandaloneSchemaObjectNode')
        ->once()
        ->withArgs(
            fn (
                string $templateId,
                $contextEntity,
                array $antlersContext
            ): bool => $templateId === 'template-entry-id'
                && ($antlersContext['replicator_row_id'] ?? null) === 'row-flat'
                && ($antlersContext['replicator_row_position'] ?? null) === 1
                && (data_get($antlersContext, 'replicator_row.title_override') === 'Flat Row Title')
                && ($antlersContext['mime_type'] ?? null) === 'audio/mpeg'
                && ($antlersContext['bitrate'] ?? null) === 192000
                && isset($antlersContext['asset'])
        )
        ->andReturnUsing(fn (): array => [
            '@type' => 'AudioObject',
            'name' => 'Flat Row Title',
            'position' => 1,
        ]);

    // Create a new SchemaThingFromRelationshipResolver
    $resolver = schemaThingResolverMakeResolver($schema);

    // Resolve direct-mode replicator field using flat row shape
    $out = $resolver->resolve(
        [
            'segments' => [
                [
                    'id' => 'row-flat',
                    'type' => 'local_file',
                    'enabled' => true,
                    'file' => [$asset],
                    'title_override' => 'Flat Row Title',
                ],
            ],
        ],
        [
            'mode' => 'direct',
            'current_field' => 'segments',
            'current_field_replicator' => 'true',
            'current_field_replicator_relationship_field' => 'file',
            'schema_object' => 'template-entry-id',
        ]
    );

    // Decode the output as an array
    $decoded = json_decode(
        $out,
        true
    );

    // Assert that one node is emitted from the flat replicator relationship field
    expect($decoded)->toBeArray();
    expect($decoded)->toHaveCount(1);

    /** @var list<array<string,mixed>> $decoded */
    expect($decoded[0]['@id'])->toBe('https://cdn.example/segment-flat.mp3');
    expect($decoded[0]['name'])->toBe('Flat Row Title');

    // Unset local variables to free memory
    unset(
        $asset,
        $decoded,
        $out,
        $resolver,
        $schema
    );
});

/**
 * Direct mode replicator without a relationship subfield emits one node per row using
 * row template context and the row URL as merged JSON-LD @id.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaThingFromRelationshipResolverTest.php --filter=test_direct_mode_replicator_without_relationship_field_uses_row_url_for_canonical_id
 */
it('direct mode replicator without relationship field uses row url for canonical id', function (): void {
    // Mock the SchemaOutputService
    $schema = Mockery::mock(SchemaOutputService::class);

    // Expect one standalone node build per replicator row with row context
    $schema->shouldReceive('buildStandaloneSchemaObjectNode')
        ->twice()
        ->andReturnUsing(
            function (
                string $_templateId,
                $_contextEntity,
                array $antlersContext
            ): array {
                // Read row-level title and url; data_get returns mixed for nested keys
                $rowTitle = data_get($antlersContext, 'replicator_row.title');
                $rowUrl = data_get($antlersContext, 'replicator_row.url');

                return [
                    '@type' => 'VideoObject',
                    'name' => \is_string($rowTitle) ? $rowTitle : '',
                    'embedUrl' => \is_string($rowUrl) ? $rowUrl : '',
                ];
            }
        );

    // Create resolver under test
    $resolver = schemaThingResolverMakeResolver($schema);

    // Resolve direct mode without current_field_replicator_relationship_field
    $out = $resolver->resolve(
        [
            'segments' => [
                [
                    'type' => 'youtube',
                    '_id' => 'row-one',
                    'value' => [
                        'title' => 'First',
                        'url' => 'https://www.youtube.com/watch?v=aaaa',
                    ],
                ],
                [
                    'type' => 'youtube',
                    '_id' => 'row-two',
                    'value' => [
                        'title' => 'Second',
                        'url' => 'https://www.youtube.com/watch?v=bbbb',
                    ],
                ],
            ],
        ],
        [
            'mode' => 'direct',
            'current_field' => 'segments',
            'current_field_replicator' => 'true',
            'schema_object' => 'template-entry-id',
        ]
    );

    // Decode JSON output
    $decoded = json_decode(
        $out,
        true
    );

    // Assert that the decoded output is an array
    expect($decoded)->toBeArray();

    // Assert that the decoded output contains exactly two nodes
    expect($decoded)->toHaveCount(2);

    /** @var list<array<string,mixed>> $decoded */
    // Assert that the first node's @id is the first video's embed URL
    expect($decoded[0]['@id'])->toBe('https://www.youtube.com/watch?v=aaaa');

    // Assert that the second node's @id is the second video's embed URL
    expect($decoded[1]['@id'])->toBe('https://www.youtube.com/watch?v=bbbb');

    // Assert that the first node's name is the first video's title
    expect($decoded[0]['name'])->toBe('First');

    // Assert that the second node's name is the second video's title
    expect($decoded[1]['name'])->toBe('Second');

    // Unset local variables to free memory
    unset(
        $decoded,
        $out,
        $resolver,
        $schema
    );
});

/**
 * Direct mode resolves a `subject_matter` entry id when `direct_scope` is the collection handle.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaThingFromRelationshipResolverTest.php --filter=test_direct_mode_resolves_a_subject_matter_entry_id_when_direct_scope_set
 */
it('direct mode resolves a subject_matter entry id when direct scope set', function (): void {
    // Real content: UAMS-Web/uams-statamic-archive#2822 stores every subject reference as an entry id
    if (! EntryFacade::find(SCHEMA_THING_RESOLVER_BIOLOGY_SUBJECT_ID) instanceof EntryContract) {
        ContentSubmodule::reportMissing('subject_matter entry biology');
    }

    // Mock the SchemaOutputService
    $schema = Mockery::mock(SchemaOutputService::class);
    $schema->shouldReceive('buildFlattenedSchemaAntlersContextFromDataObject')->andReturn([]);

    // Expect one standalone node build for the resolved subject
    $schema->shouldReceive('buildStandaloneSchemaObjectNode')
        ->once()
        ->andReturnUsing(static fn (): array => [
            '@type' => 'Thing',
            '@id' => 'https://example.test/subject-matter/biology',
        ]);

    // Create resolver under test
    $resolver = schemaThingResolverMakeResolver($schema);

    // Resolve direct-mode about field stored as entry ids (typical entries field raw)
    $out = $resolver->resolve(
        [
            'about' => [
                SCHEMA_THING_RESOLVER_BIOLOGY_SUBJECT_ID,
            ],
        ],
        [
            'mode' => 'direct',
            'current_field' => 'about',
            'direct_scope' => 'subject_matter',
            'schema_object' => 'template-entry-id',
        ]
    );

    // Decode JSON output
    $decoded = json_decode(
        $out,
        true
    );

    // Assert one JSON-LD node is emitted (merged @id comes from the entry, not the mock)
    expect($decoded)->toBeArray();
    expect($decoded)->toHaveCount(1);

    /** @var list<array<string,mixed>> $decoded */
    expect($decoded[0])->toHaveKey('@id');

    // A routeless subject's @id is app base + # + URL-safe Base64 of the path its term had
    $atIdValue = $decoded[0]['@id'];
    $atId = \is_string($atIdValue) ? $atIdValue : '';
    $parts = explode(
        '#',
        $atId,
        2
    );
    expect($parts)->toHaveCount(2);

    $appUrl = config('app.url');
    expect($parts[0])->toBe(
        rtrim(
            \is_string($appUrl) ? $appUrl : '',
            '/'
        )
    );
    expect(UrlSafeBase64StringCodec::decode($parts[1]))->toBe('/subject-matter/biology');

    // Unset local variables to free memory
    unset(
        $appUrl,
        $atId,
        $atIdValue,
        $decoded,
        $out,
        $parts,
        $resolver,
        $schema
    );
});

/**
 * Direct mode reads `current_field` from `source_entry` when the overlay context
 * has no resolvable entities (e.g., per-asset `about` query is empty).
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaThingFromRelationshipResolverTest.php --filter=test_direct_mode_falls_back_to_source_entry_when_context_field_empty
 */
it('direct mode falls back to source entry when context field empty', function (): void {
    // Stub the subject entry the field references by id (UAMS-Web/uams-statamic-archive#2822)
    schemaThingResolverStubSubjectMatterEntry(
        'subject-matter-cellulitis-entry-id',
        'cellulitis'
    );

    // Mock parent entry: `about` holds a subject entry id (typical entries field raw)
    $sourceEntry = Mockery::mock(EntryContract::class);
    $sourceEntry->shouldReceive('value')
        ->with('about')
        ->andReturnUsing(static fn (): array => [
            'subject-matter-cellulitis-entry-id',
        ]);

    // Mock the SchemaOutputService
    $schema = Mockery::mock(SchemaOutputService::class);
    $schema->shouldReceive('buildFlattenedSchemaAntlersContextFromDataObject')->andReturn([]);

    // Expect one standalone node build for the resolved subject
    $schema->shouldReceive('buildStandaloneSchemaObjectNode')
        ->once()
        ->andReturnUsing(static fn (): array => [
            '@type' => 'Thing',
            '@id' => 'https://example.test/subject-matter/cellulitis',
        ]);

    // Create resolver under test
    $resolver = schemaThingResolverMakeResolver($schema);

    // Overlay context: `about` is empty (simulates asset-augmented empty entries query)
    $out = $resolver->resolve(
        [
            'about' => [],
            'source_entry' => $sourceEntry,
        ],
        [
            'mode' => 'direct',
            'current_field' => 'about',
            'direct_scope' => 'subject_matter',
            'schema_object' => 'template-entry-id',
        ]
    );

    // Decode JSON output
    $decoded = json_decode(
        $out,
        true
    );

    // Assert one node from source_entry fallback
    expect($decoded)->toBeArray();
    expect($decoded)->toHaveCount(1);

    /** @var list<array<string,mixed>> $decoded */
    // The routeless subject's @id path is /subject-matter/{slug}; the fragment encodes that path; stem is config app.url
    $atIdValue = $decoded[0]['@id'];
    $atId = \is_string($atIdValue) ? $atIdValue : '';
    $parts = explode(
        '#',
        $atId,
        2
    );
    expect($parts)->toHaveCount(2);

    $appUrl = config('app.url');
    expect($parts[0])->toBe(
        rtrim(
            \is_string($appUrl) ? $appUrl : '',
            '/'
        )
    );
    expect(UrlSafeBase64StringCodec::decode($parts[1]))->toBe('/subject-matter/cellulitis');

    // Unset local variables to free memory
    unset(
        $appUrl,
        $atId,
        $atIdValue,
        $decoded,
        $out,
        $parts,
        $resolver,
        $schema,
        $sourceEntry,
    );
});

/**
 * Direct mode resolves a bare term slug from `source_entry` when `direct_scope` is a taxonomy
 * handle, as the `audio_series` templates do: the slug is looked up as `{scope}::{slug}`.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaThingFromRelationshipResolverTest.php --filter=test_direct_mode_resolves_a_bare_audio_series_term_slug_from_the_source_entry
 */
it('direct mode resolves a bare audio_series term slug from the source entry', function (): void {
    // Mock localized term URL for mergeTemplateNodeWithTarget canonical @id
    $localizedTerm = Mockery::mock(LocalizedTerm::class);
    $localizedTerm->shouldReceive('url')
        ->andReturn('https://example.test/audio-series/heres-to-your-health');

    // buildTemplateContextForEntity calls data()->all() on the localized term
    $localizedData = Mockery::mock();
    $localizedData->shouldReceive('all')
        ->andReturn([]);
    $localizedTerm->shouldReceive('data')
        ->andReturn($localizedData);

    // Mock taxonomy term returned from Term::find(audio_series::heres-to-your-health)
    $taxonomyTerm = Mockery::mock(TaxonomyTerm::class);
    $taxonomyTerm->shouldReceive('inDefaultLocale')
        ->andReturn($localizedTerm);

    // Stub Term facade so the resolver does not require real taxonomy YAML
    Term::shouldReceive('find')
        ->once()
        ->with('audio_series::heres-to-your-health')
        ->andReturn($taxonomyTerm);

    // Mock parent entry: `series` holds a bare term slug (typical terms field raw)
    $sourceEntry = Mockery::mock(EntryContract::class);
    $sourceEntry->shouldReceive('value')
        ->with('series')
        ->andReturnUsing(static fn (): array => [
            'heres-to-your-health',
        ]);

    // Mock the SchemaOutputService
    $schema = Mockery::mock(SchemaOutputService::class);

    // Expect one standalone node build for the resolved term
    $schema->shouldReceive('buildStandaloneSchemaObjectNode')
        ->once()
        ->andReturnUsing(static fn (): array => [
            '@type' => 'Thing',
            '@id' => 'https://example.test/audio-series/heres-to-your-health',
        ]);

    // Create resolver under test
    $resolver = schemaThingResolverMakeResolver($schema);

    // Overlay context: `series` is empty (simulates asset-augmented empty terms query)
    $out = $resolver->resolve(
        [
            'series' => [],
            'source_entry' => $sourceEntry,
        ],
        [
            'mode' => 'direct',
            'current_field' => 'series',
            'direct_scope' => 'audio_series',
            'schema_object' => 'template-entry-id',
        ]
    );

    // Decode JSON output
    $decoded = json_decode(
        $out,
        true
    );

    // Assert one node from source_entry fallback
    expect($decoded)->toBeArray();
    expect($decoded)->toHaveCount(1);

    /** @var list<array<string,mixed>> $decoded */
    // Mock term URL path /audio-series/heres-to-your-health -> fragment encodes that path; stem is config app.url
    $atIdValue = $decoded[0]['@id'];
    $atId = \is_string($atIdValue) ? $atIdValue : '';
    $parts = explode(
        '#',
        $atId,
        2
    );
    expect($parts)->toHaveCount(2);

    $appUrl = config('app.url');
    expect($parts[0])->toBe(
        rtrim(
            \is_string($appUrl) ? $appUrl : '',
            '/'
        )
    );
    expect(UrlSafeBase64StringCodec::decode($parts[1]))->toBe('/audio-series/heres-to-your-health');

    // Unset local variables to free memory
    unset(
        $appUrl,
        $atId,
        $atIdValue,
        $decoded,
        $localizedData,
        $localizedTerm,
        $out,
        $parts,
        $resolver,
        $schema,
        $sourceEntry,
        $taxonomyTerm
    );
});

/**
 * Direct mode resolves `current_field="source_entry.{handle}"` via entry `value()`.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaThingFromRelationshipResolverTest.php --filter=test_direct_mode_reads_prefixed_source_entry_field
 */
it('direct mode reads prefixed source entry field', function (): void {
    // Stub the subject entry the field references by id (UAMS-Web/uams-statamic-archive#2822)
    schemaThingResolverStubSubjectMatterEntry(
        'subject-matter-cellulitis-entry-id',
        'cellulitis'
    );

    $sourceEntry = Mockery::mock(EntryContract::class);
    $sourceEntry->shouldReceive('value')
        ->with('about')
        ->andReturnUsing(static fn (): array => [
            'subject-matter-cellulitis-entry-id',
        ]);

    $schema = Mockery::mock(SchemaOutputService::class);
    $schema->shouldReceive('buildFlattenedSchemaAntlersContextFromDataObject')->andReturn([]);
    $schema->shouldReceive('buildStandaloneSchemaObjectNode')
        ->once()
        ->andReturnUsing(static fn (): array => [
            '@type' => 'Thing',
            '@id' => 'https://example.test/subject-matter/cellulitis',
        ]);

    $resolver = schemaThingResolverMakeResolver($schema);

    $out = $resolver->resolve(
        [
            'about' => [],
            'source_entry' => $sourceEntry,
        ],
        [
            'mode' => 'direct',
            'current_field' => 'source_entry.about',
            'direct_scope' => 'subject_matter',
            'schema_object' => 'template-entry-id',
        ]
    );

    $decoded = json_decode(
        $out,
        true
    );

    expect($decoded)->toBeArray();
    expect($decoded)->toHaveCount(1);

    unset(
        $decoded,
        $out,
        $resolver,
        $schema,
        $sourceEntry,
    );
});

/**
 * Direct mode resolves `current_field="replicator_row.{handle}"` from the row map.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaThingFromRelationshipResolverTest.php --filter=test_direct_mode_reads_prefixed_replicator_row_field
 */
it('direct mode reads prefixed replicator row field', function (): void {
    // Stub the subject entry the field references by id (UAMS-Web/uams-statamic-archive#2822)
    schemaThingResolverStubSubjectMatterEntry(
        'subject-matter-cellulitis-entry-id',
        'cellulitis'
    );

    $schema = Mockery::mock(SchemaOutputService::class);
    $schema->shouldReceive('buildFlattenedSchemaAntlersContextFromDataObject')->andReturn([]);
    $schema->shouldReceive('buildStandaloneSchemaObjectNode')
        ->once()
        ->andReturnUsing(static fn (): array => [
            '@type' => 'Thing',
            '@id' => 'https://example.test/subject-matter/cellulitis',
        ]);

    $resolver = schemaThingResolverMakeResolver($schema);

    $out = $resolver->resolve(
        [
            'replicator_row' => [
                'about' => [
                    'subject-matter-cellulitis-entry-id',
                ],
            ],
        ],
        [
            'mode' => 'direct',
            'current_field' => 'replicator_row.about',
            'direct_scope' => 'subject_matter',
            'schema_object' => 'template-entry-id',
        ]
    );

    $decoded = json_decode(
        $out,
        true
    );

    expect($decoded)->toBeArray();
    expect($decoded)->toHaveCount(1);

    unset(
        $decoded,
        $out,
        $resolver,
        $schema,
    );
});

/**
 * Subject-matter keyword JSON includes title and same_as URLs from a real subject entry.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaThingFromRelationshipResolverTest.php --filter=test_resolve_subject_matter_keywords_direct_includes_title_and_same_as
 */
it('resolve subject matter keywords direct includes title and same as', function (): void {
    // Real content: UAMS-Web/uams-statamic-archive#2822 stores every subject reference as an entry id
    if (! EntryFacade::find(SCHEMA_THING_RESOLVER_BIOLOGY_SUBJECT_ID) instanceof EntryContract) {
        ContentSubmodule::reportMissing('subject_matter entry biology');
    }

    // Mock schema output service (unused by keyword resolution)
    $schema = Mockery::mock(SchemaOutputService::class);

    // Create resolver under test
    $resolver = schemaThingResolverMakeResolver($schema);

    // Resolve keywords from a subject entry id on about (same shape as entries field storage)
    $out = $resolver->resolveSubjectMatterKeywords(
        [
            'about' => [
                SCHEMA_THING_RESOLVER_BIOLOGY_SUBJECT_ID,
            ],
        ],
        [
            'mode' => 'direct',
            'current_field' => 'about',
            'direct_scope' => 'subject_matter',
        ]
    );

    // Decode JSON output
    $decoded = json_decode(
        $out,
        true
    );

    // Assert list of keyword strings
    expect($decoded)->toBeArray();
    expect($decoded)->toContain('biology');
    expect($decoded)->toContain('https://www.wikidata.org/wiki/Q420');

    // Unset local variables to free memory
    unset(
        $decoded,
        $out,
        $resolver,
        $schema
    );
});

/**
 * Tertiary mode without `tertiary_field` yields an empty JSON array.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaThingFromRelationshipResolverTest.php --filter=test_tertiary_mode_without_tertiary_field_yields_empty_array
 */
it('tertiary mode without tertiary field yields empty array', function (): void {
    // Mock the SchemaOutputService
    $schema = Mockery::mock(SchemaOutputService::class);

    // Expect no standalone node builds when no targets resolve
    $schema->shouldReceive('buildStandaloneSchemaObjectNode')->never();

    // Create resolver under test
    $resolver = schemaThingResolverMakeResolver($schema);

    // Resolve tertiary mode with missing tertiary_field (invalid configuration)
    $out = $resolver->resolve(
        [
            'segments' => [],
        ],
        [
            'mode' => 'tertiary',
            'current_field' => 'segments',
            'secondary_context' => 'assets',
            'secondary_field' => 'series',
            'schema_object' => 'f15f2efa-258e-4b16-aa4e-9010bce7514c',
        ]
    );

    // Assert empty JSON array output
    expect($out)->toBe('[]');

    // Unset local variables to free memory
    unset(
        $out,
        $resolver,
        $schema
    );
});

/**
 * Pipe-separated taxonomy scope resolves bare slugs using the first matching taxonomy.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaThingFromRelationshipResolverTest.php --filter=test_normalize_to_term_accepts_pipe_separated_taxonomy_scope
 */
it('normalize to term accepts pipe separated taxonomy scope', function (): void {
    // Skip when taxonomy content is unavailable in this environment
    if (Term::find('audio_series::heres-to-your-health') === null) {
        Assert::markTestSkipped('audio_series::heres-to-your-health term missing');
    }

    // Mock the SchemaOutputService
    $schema = Mockery::mock(SchemaOutputService::class);

    // Allow unused mock interactions in this reflection-focused test
    $schema->shouldIgnoreMissing();

    // Create resolver under test
    $resolver = schemaThingResolverMakeResolver($schema);

    // Reflect private normalizeToTerm for direct verification
    $reflection = new ReflectionClass($resolver);

    // Read the private normalization method
    $normalizeMethod = $reflection->getMethod('normalizeToTerm');

    // Resolve a bare slug against a pipe-separated taxonomy list (video first, then audio)
    $term = $normalizeMethod->invoke(
        $resolver,
        'heres-to-your-health',
        'video_series|audio_series'
    );

    // Assert the audio_series term was resolved
    expect($term)->not->toBeNull();

    /** @var TaxonomyTerm $term */
    expect($term->slug())->toBe('heres-to-your-health');

    // Unset local variables to free memory
    unset(
        $normalizeMethod,
        $reflection,
        $resolver,
        $schema,
        $term
    );
});

/**
 * Secondary mode with a global first hop returns an empty array when the
 * global set handle does not resolve.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaThingFromRelationshipResolverTest.php --filter=test_secondary_mode_global_first_hop_returns_empty_when_global_set_missing
 */
it('secondary mode global first hop returns empty when global set missing', function (): void {
    // Stub global lookup so the resolver exits before schema output
    GlobalSetFacade::shouldReceive('findByHandle')
        ->with('missing_global_xyz')
        ->once()
        ->andReturn(null);

    // Mock the SchemaOutputService
    $schema = Mockery::mock(SchemaOutputService::class);

    // Expect no schema node builds when the global set is missing
    $schema->shouldReceive('buildStandaloneSchemaObjectNode')->never();

    // Create resolver under test
    $resolver = schemaThingResolverMakeResolver($schema);

    // Resolve with global first hop and secondary traversal parameters
    $out = $resolver->resolve(
        [],
        [
            'mode' => 'secondary',
            'global_handle' => 'missing_global_xyz',
            'global_field' => 'media_contact',
            'secondary_context' => 'terms',
            'secondary_scope' => 'media_contact_groups',
            'secondary_field' => 'people',
            'nested_scope' => 'people',
            'schema_object' => '1a834c0c-f4af-4223-be3b-ee45b218cd39',
        ]
    );

    // Assert empty JSON array output
    expect($out)->toBe('[]');

    // Unset local variables to free memory
    unset(
        $out,
        $resolver,
        $schema
    );
});

/**
 * Direct mode `direct_term_data_equals_*` keeps only terms whose field matches.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaThingFromRelationshipResolverTest.php --filter=test_direct_term_data_equals_filters_credentials_terms_by_ctdl_type
 */
it('direct term data equals filters credentials terms by ctdl type', function (): void {
    // Skip when credentials taxonomy terms are unavailable in this environment
    if (
        Term::find('credentials::m-a') === null
        || Term::find('credentials::ma') === null
    ) {
        Assert::markTestSkipped('credentials taxonomy terms missing');
    }

    // Mock the SchemaOutputService
    $schema = Mockery::mock(SchemaOutputService::class);

    // Expect one node for the Credential-type term only (not the Occupation term)
    $schema->shouldReceive('buildStandaloneSchemaObjectNode')
        ->once()
        ->andReturnUsing(static fn (): array => [
            '@type' => 'EducationalOccupationalCredential',
            '@id' => 'https://example.test/#cred',
        ]);

    // Create resolver under test
    $resolver = schemaThingResolverMakeResolver($schema);

    // Resolve mixed credentials: M.A. (Credential) vs MA occupation term
    $out = $resolver->resolve(
        [
            'credentials' => [
                'm-a',
                'ma',
            ],
        ],
        [
            'mode' => 'direct',
            'current_field' => 'credentials',
            'direct_scope' => 'credentials',
            'direct_term_data_equals_field' => 'ctdl.ceterms_type',
            'direct_term_data_equals_value' => 'Credential',
            'schema_object' => 'template-entry-id',
        ]
    );

    // Decode JSON output
    $decoded = json_decode(
        $out,
        true
    );

    // Assert exactly one JSON-LD node is emitted
    expect($decoded)->toBeArray();
    expect($decoded)->toHaveCount(1);

    // Unset local variables to free memory
    unset(
        $decoded,
        $out,
        $resolver,
        $schema
    );
});

/**
 * Direct mode filter on `ctdl_credential_type` keeps certification subtype terms only.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaThingFromRelationshipResolverTest.php --filter=test_direct_term_data_equals_filters_credentials_terms_by_credential_type
 */
it('direct term data equals filters credentials terms by credential type', function (): void {
    // Resolve a Credential + Certification fixture term slug (CNP models Occupation, not Credential)
    $certSlug = schemaThingResolverFirstCredentialsSlugWithCredentialRootAndSubtype(
        CredentialTypeCertification::CTDL_CREDENTIAL_TYPE_VALUE_CERTIFICATION
    );

    // Skip when credentials taxonomy terms are unavailable in this environment
    if (
        Term::find('credentials::m-a') === null
        || $certSlug === null
    ) {
        Assert::markTestSkipped('credentials taxonomy terms missing');
    }

    // Mock the SchemaOutputService
    $schema = Mockery::mock(SchemaOutputService::class);

    // Expect one node for the Credential + Certification fixture only (not Degree)
    $schema->shouldReceive('buildStandaloneSchemaObjectNode')
        ->once()
        ->andReturnUsing(static fn (): array => [
            '@type' => 'Certification',
            '@id' => 'https://example.test/#cert',
        ]);

    // Create resolver under test
    $resolver = schemaThingResolverMakeResolver($schema);

    // Resolve mixed credentials: degree vs Credential + Certification
    $out = $resolver->resolve(
        [
            'credentials' => [
                'm-a',
                $certSlug,
            ],
        ],
        [
            'mode' => 'direct',
            'current_field' => 'credentials',
            'direct_scope' => 'credentials',
            'direct_term_data_equals_field' => 'ctdl.ceterms_credential_type',
            'direct_term_data_equals_value' => 'Certification',
            'schema_object' => 'template-entry-id',
        ]
    );

    // Decode JSON output
    $decoded = json_decode(
        $out,
        true
    );

    // Assert exactly one JSON-LD node is emitted
    expect($decoded)->toBeArray();
    expect($decoded)->toHaveCount(1);

    // Unset local variables to free memory
    unset(
        $decoded,
        $out,
        $resolver,
        $schema
    );
});
