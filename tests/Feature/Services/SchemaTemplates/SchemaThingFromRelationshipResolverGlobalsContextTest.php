<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaJsonLdAtIdDeduplication;
use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;
use App\Services\SchemaTemplates\SchemaThingFromRelationshipResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Globals\Variables;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Tests the global-variables template context for schema relationship resolution.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaThingFromRelationshipResolverGlobalsContextTest.php
 */

/**
 * Invoke the private template context builder on a resolver with mocked collaborators.
 *
 * @param  mixed  $entity  Relationship target to build the context for
 * @return array<string, mixed> Template context
 */
function globalsContextBuild(mixed $entity): array
{
    // Initialize resolver with mocked dependencies
    $resolver = new SchemaThingFromRelationshipResolver(
        Mockery::mock(SchemaOutputService::class),
        Mockery::mock(SchemaTemplatesCascadeService::class),
        new SchemaJsonLdAtIdDeduplication
    );

    // Resolve private template context builder
    $method = (new ReflectionClass($resolver))->getMethod('buildTemplateContextForEntity');

    /** @var array<string, mixed> $context */
    $context = $method->invoke($resolver, $entity);

    return $context;
}

it('puts the global field values in the global-variables template context', function (): void {
    // Build global variables carrying two field values
    $variables = new Variables;
    $variables->globalSet('settings');
    $variables->set('phone', '555');
    $variables->set('email', 'info@example.test');

    // Assert the field values sit beside the variables themselves
    expect(globalsContextBuild($variables))->toBe([
        'phone' => '555',
        'email' => 'info@example.test',
        'global' => $variables,
    ]);
});

it('keeps the variables under global when a field is also named global', function (): void {
    // Build global variables whose field handle collides with the context key
    $variables = new Variables;
    $variables->globalSet('settings');
    $variables->set('global', 'field value');

    // Assert the variables themselves win the global key
    expect(globalsContextBuild($variables))->toBe(['global' => $variables]);
});

it('builds a context holding only the variables when the global has no values', function (): void {
    // Build global variables with no field values
    $variables = new Variables;
    $variables->globalSet('settings');

    // Assert only the variables themselves are present
    expect(globalsContextBuild($variables))->toBe(['global' => $variables]);
});
