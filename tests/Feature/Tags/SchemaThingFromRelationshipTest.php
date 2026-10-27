<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaThingFromRelationshipResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for the `schema_thing_from_relationship` Antlers tag.
 *
 * Mutation: 100% on App\Tags\SchemaThingFromRelationship (33 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Tags/SchemaThingFromRelationshipTest.php
 */
it('passes context and params to the resolver', function () {
    // Define the expected JSON output.
    $expectedJson = '[{"@type":"ImageObject","@id":"https://cdn.example/x.jpg","url":"https://cdn.example/x.jpg"}]';

    // Mock the SchemaThingFromRelationshipResolver and bind it into the container.
    $mock = Mockery::mock(SchemaThingFromRelationshipResolver::class);

    // Expect the resolve method to be called once with the correct arguments.
    $mock->shouldReceive('resolve')
        ->once()
        ->withArgs(fn (array $context, array $params): bool => isset($context['gallery'])
                && $params['current_field'] === 'gallery'
                && ($params['direct_scope'] ?? null) === null
                && ($params['nested_scope'] ?? null) === null
                && $params['current_field_replicator'] === null
                && $params['secondary_field_replicator'] === null
                && $params['current_field_replicator_relationship_field'] === null
                && $params['secondary_field_replicator_relationship_field'] === null
                && $params['global_handle'] === null
                && $params['global_field'] === null
                && $params['schema_object'] === 'template-uuid'
                && $params['mode'] === 'direct')
        ->andReturn($expectedJson);

    app()->instance(SchemaThingFromRelationshipResolver::class, $mock);

    // Parse the Antlers template.
    $output = Antlers::parse(
        '{{ schema_thing_from_relationship current_field="gallery" schema_object="template-uuid" }}',
        [
            'gallery' => [],
        ],
        true
    );

    // Assert that the output is the expected JSON.
    expect(trim((string) $output))->toBe($expectedJson);
});
