<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaBlueprintDefaultsRepository;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\YAML;
use Tests\Support\StacheIsolatedTestCase;

uses(StacheIsolatedTestCase::class);

/**
 * `SchemaBlueprintDefaultsRepository` reads and writes a blueprint's Schema Templates
 * defaults in its collection's or taxonomy's `inject` cascade, under
 * `schema_templates.blueprint_defaults.<blueprint>` (#2834 coverage batch 12).
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaBlueprintDefaultsRepositoryTest.php
 */

/**
 * Save a collection or taxonomy with the given `inject` cascade.
 *
 * @param  array<mixed>  $cascade
 */
function schemaBlueprintDefaultsSection(string $type, string $handle, array $cascade): Statamic\Entries\Collection|Statamic\Taxonomies\Taxonomy
{
    $section = $type === 'taxonomy'
        ? mustBe(Statamic\Taxonomies\Taxonomy::class, Taxonomy::make($handle), 'the taxonomy')
        : Collection::make($handle);
    $section->cascade($cascade);
    $section->save();

    return $section;
}

/**
 * The `inject` block the section's YAML file holds on disk.
 */
function schemaBlueprintDefaultsOnDisk(Statamic\Entries\Collection|Statamic\Taxonomies\Taxonomy $section): mixed
{
    return YAML::file($section->path())->parse()['inject'] ?? null;
}

it('reads a blueprint\'s defaults from a collection or a taxonomy', function (string $type): void {
    schemaBlueprintDefaultsSection($type, 'blueprint_defaults_probe', ['schema_templates' => ['blueprint_defaults' => [
        'article' => ['enabled' => 'yes', 'inherit_template' => true],
        'other' => ['enabled' => 'no'],
    ]]]);

    expect((new SchemaBlueprintDefaultsRepository)->get('blueprint_defaults_probe', 'article', $type))
        ->toBe(['enabled' => 'yes', 'inherit_template' => true]);
})->with(['collection', 'taxonomy']);

it('looks the handle up as a collection unless the type is taxonomy', function (): void {
    schemaBlueprintDefaultsSection('taxonomy', 'blueprint_defaults_tax_only', ['schema_templates' => ['blueprint_defaults' => ['article' => ['enabled' => 'yes']]]]);

    $repository = new SchemaBlueprintDefaultsRepository;

    expect($repository->get('blueprint_defaults_tax_only', 'article'))->toBe([])
        ->and($repository->get('blueprint_defaults_tax_only', 'article', 'taxonomy'))->toBe(['enabled' => 'yes']);
});

it('returns no defaults for a missing section, blueprint or malformed cascade', function (array $cascade, string $handle): void {
    schemaBlueprintDefaultsSection('collection', 'blueprint_defaults_shapes', $cascade);

    expect((new SchemaBlueprintDefaultsRepository)->get($handle, 'article'))->toBe([]);
})->with([
    'no such collection' => [['schema_templates' => ['blueprint_defaults' => ['article' => ['enabled' => 'yes']]]], 'no_such_collection'],
    'no cascade' => [[], 'blueprint_defaults_shapes'],
    'a root that is not a map' => [['schema_templates' => 'on'], 'blueprint_defaults_shapes'],
    'blueprint defaults that are not a map' => [['schema_templates' => ['blueprint_defaults' => 'on']], 'blueprint_defaults_shapes'],
    'no entry for the blueprint' => [['schema_templates' => ['blueprint_defaults' => ['other' => ['enabled' => 'yes']]]], 'blueprint_defaults_shapes'],
    'an entry that is not a map' => [['schema_templates' => ['blueprint_defaults' => ['article' => 'yes']]], 'blueprint_defaults_shapes'],
]);

it('memoizes a read for the request until a put clears it, keyed by type, section and blueprint', function (): void {
    schemaBlueprintDefaultsSection('collection', 'blueprint_defaults_memo', ['schema_templates' => ['blueprint_defaults' => [
        'article' => ['enabled' => 'yes'],
        'page' => ['enabled' => 'no'],
    ]]]);
    schemaBlueprintDefaultsSection('taxonomy', 'blueprint_defaults_memo', ['schema_templates' => ['blueprint_defaults' => ['article' => ['enabled' => 'taxonomy']]]]);
    $repository = new SchemaBlueprintDefaultsRepository;

    expect($repository->get('blueprint_defaults_memo', 'article'))->toBe(['enabled' => 'yes']);

    // A change saved behind the repository's back is not seen until the memo is cleared
    $stored = Collection::find('blueprint_defaults_memo');
    expect($stored)->not->toBeNull();
    $stored?->cascade(['schema_templates' => ['blueprint_defaults' => ['article' => ['enabled' => 'changed']]]]);
    $stored?->save();

    expect($repository->get('blueprint_defaults_memo', 'article'))->toBe(['enabled' => 'yes'])
        // Another blueprint, or the same handles as a taxonomy, are separate reads
        ->and($repository->get('blueprint_defaults_memo', 'page'))->toBe([])
        ->and($repository->get('blueprint_defaults_memo', 'article', 'taxonomy'))->toBe(['enabled' => 'taxonomy']);

    $repository->put('blueprint_defaults_memo', 'other', 'collection', ['enabled' => 'yes']);

    expect($repository->get('blueprint_defaults_memo', 'article'))->toBe(['enabled' => 'changed']);
});

it('saves a blueprint\'s defaults, dropping null and empty values and keeping the rest of the cascade', function (string $type): void {
    $section = schemaBlueprintDefaultsSection($type, 'blueprint_defaults_put', [
        'seo' => ['title' => 'kept'],
        'schema_templates' => ['site_flag' => true, 'blueprint_defaults' => ['other' => ['enabled' => 'no']]],
    ]);

    (new SchemaBlueprintDefaultsRepository)->put('blueprint_defaults_put', 'article', $type, [
        'enabled' => 'yes',
        'inherit_template' => false,
        'parameter' => '',
        'template' => null,
    ]);

    expect(schemaBlueprintDefaultsOnDisk($section))->toBe([
        'seo' => ['title' => 'kept'],
        'schema_templates' => ['site_flag' => true, 'blueprint_defaults' => [
            'other' => ['enabled' => 'no'],
            'article' => ['enabled' => 'yes', 'inherit_template' => false],
        ]],
    ]);
})->with(['collection', 'taxonomy']);

it('removes the blueprint, then the defaults, then the root as each is emptied', function (): void {
    $section = schemaBlueprintDefaultsSection('collection', 'blueprint_defaults_prune', [
        'seo' => ['title' => 'kept'],
        'schema_templates' => ['blueprint_defaults' => ['article' => ['enabled' => 'yes'], 'page' => ['enabled' => 'no']]],
    ]);
    $repository = new SchemaBlueprintDefaultsRepository;

    $repository->put('blueprint_defaults_prune', 'article', 'collection', ['enabled' => '', 'parameter' => null]);

    expect(schemaBlueprintDefaultsOnDisk($section))->toBe([
        'seo' => ['title' => 'kept'],
        'schema_templates' => ['blueprint_defaults' => ['page' => ['enabled' => 'no']]],
    ]);

    $repository->put('blueprint_defaults_prune', 'page', 'collection', []);

    expect(schemaBlueprintDefaultsOnDisk($section))->toBe(['seo' => ['title' => 'kept']]);
});

it('replaces a malformed root or defaults map when it saves', function (array $cascade): void {
    $section = schemaBlueprintDefaultsSection('collection', 'blueprint_defaults_malformed', $cascade);

    (new SchemaBlueprintDefaultsRepository)->put('blueprint_defaults_malformed', 'article', 'collection', ['enabled' => 'yes']);

    expect(schemaBlueprintDefaultsOnDisk($section))->toBe(['schema_templates' => ['blueprint_defaults' => ['article' => ['enabled' => 'yes']]]]);
})->with([
    'a root that is not a map' => [['schema_templates' => 'on']],
    'blueprint defaults that are not a map' => [['schema_templates' => ['blueprint_defaults' => 'on']]],
]);

it('saves nothing for a section that does not exist', function (): void {
    (new SchemaBlueprintDefaultsRepository)->put('no_such_collection', 'article', 'collection', ['enabled' => 'yes']);

    expect(Collection::find('no_such_collection'))->toBeNull();
});
