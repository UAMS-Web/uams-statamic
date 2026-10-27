<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#1593: the opt-in `publish_depends_on` tether must exist on the `clinical_resources`
 * dependent collection, pointing at `people` source entries. Asserted against the
 * on-disk fieldset + blueprint YAML in `resources/`, so no content/ submodule is
 * required.
 *
 * @command php artisan test --compact tests/Feature/Blueprints/ClinicalResourcesPublishDependencyFieldTest.php
 */

/** @return array<string, mixed> The `publish_depends_on` field config from the shared fieldset. */
function publishDependencyFieldConfig(): array
{
    $fieldset = Yaml::parseFile(resource_path('fieldsets/uamsweb_publish_dependency.yaml'));

    /** @var list<mixed> $fields */
    $fields = \is_array($fieldset) && \is_array($fieldset['fields'] ?? null) ? array_values($fieldset['fields']) : [];

    $field = collect($fields)->firstWhere('handle', 'publish_depends_on');
    expect($field)->toBeArray();

    /** @var array<string, mixed> $config */
    $config = \is_array($field) && \is_array($field['field'] ?? null) ? $field['field'] : [];

    return $config;
}

it('defines an opt-in publish_depends_on entries field pointing at people', function (): void {
    $config = publishDependencyFieldConfig();

    expect($config['type'])->toBe('entries');
    expect($config['collections'])->toBe(['people']);
    // Not required — the tether is opt-in; leaving it empty must be a valid,
    // never-suppressed state.
    expect($config['validate'] ?? [])->not->toContain('required');
});

it('defaults to the AND suppression policy', function (): void {
    expect(publishDependencyFieldConfig()['suppression_policy'])->toBe('all');
});

it('imports the publish-dependency fieldset into every clinical_resources blueprint', function (string $blueprint): void {
    $yaml = file_get_contents(resource_path("blueprints/collections/clinical_resources/{$blueprint}.yaml"));

    expect($yaml)->toContain('uamsweb_publish_dependency');
})->with(['article', 'audio', 'document', 'infographic', 'video', 'provider_spotlight']);
