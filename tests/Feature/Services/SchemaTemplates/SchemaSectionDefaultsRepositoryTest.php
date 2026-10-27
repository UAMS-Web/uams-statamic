<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaSectionDefaultsRepository;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\YAML;
use Tests\Support\StacheIsolatedTestCase;

uses(StacheIsolatedTestCase::class);

/**
 * `SchemaSectionDefaultsRepository` reads and writes a collection's or taxonomy's own
 * Schema Templates defaults in its `inject` cascade, under
 * `schema_templates.section_defaults` (#2834 coverage batch 12).
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaSectionDefaultsRepositoryTest.php
 */

/**
 * Save a collection or taxonomy with the given `inject` cascade.
 *
 * @param  array<mixed>  $cascade
 */
function schemaSectionDefaultsSection(string $type, string $handle, array $cascade): Statamic\Entries\Collection|Statamic\Taxonomies\Taxonomy
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
function schemaSectionDefaultsOnDisk(Statamic\Entries\Collection|Statamic\Taxonomies\Taxonomy $section): mixed
{
    return YAML::file($section->path())->parse()['inject'] ?? null;
}

it('reads a section\'s defaults from a collection or a taxonomy', function (string $type): void {
    schemaSectionDefaultsSection($type, 'section_defaults_probe', ['schema_templates' => [
        'section_defaults' => ['enabled' => 'yes', 'template' => 'article'],
        'blueprint_defaults' => ['article' => ['enabled' => 'no']],
    ]]);

    expect((new SchemaSectionDefaultsRepository)->get('section_defaults_probe', $type))
        ->toBe(['enabled' => 'yes', 'template' => 'article']);
})->with(['collection', 'taxonomy']);

it('looks the handle up as a collection unless the type is taxonomy', function (): void {
    schemaSectionDefaultsSection('taxonomy', 'section_defaults_tax_only', ['schema_templates' => ['section_defaults' => ['enabled' => 'yes']]]);

    $repository = new SchemaSectionDefaultsRepository;

    expect($repository->get('section_defaults_tax_only'))->toBe([])
        ->and($repository->get('section_defaults_tax_only', 'taxonomy'))->toBe(['enabled' => 'yes']);
});

it('returns no defaults for a missing section or a malformed cascade', function (array $cascade, string $handle): void {
    schemaSectionDefaultsSection('collection', 'section_defaults_shapes', $cascade);

    expect((new SchemaSectionDefaultsRepository)->get($handle))->toBe([]);
})->with([
    'no such collection' => [['schema_templates' => ['section_defaults' => ['enabled' => 'yes']]], 'no_such_collection'],
    'no cascade' => [[], 'section_defaults_shapes'],
    'a root that is not a map' => [['schema_templates' => 'on'], 'section_defaults_shapes'],
    'section defaults that are not a map' => [['schema_templates' => ['section_defaults' => 'on']], 'section_defaults_shapes'],
]);

it('memoizes a read for the request until a put clears it, keyed by type and section', function (): void {
    schemaSectionDefaultsSection('collection', 'section_defaults_memo', ['schema_templates' => ['section_defaults' => ['enabled' => 'yes']]]);
    schemaSectionDefaultsSection('collection', 'section_defaults_memo_other', ['schema_templates' => ['section_defaults' => ['enabled' => 'other']]]);
    schemaSectionDefaultsSection('taxonomy', 'section_defaults_memo', ['schema_templates' => ['section_defaults' => ['enabled' => 'taxonomy']]]);
    $repository = new SchemaSectionDefaultsRepository;

    expect($repository->get('section_defaults_memo'))->toBe(['enabled' => 'yes']);

    // A change saved behind the repository's back is not seen until the memo is cleared
    $stored = Collection::find('section_defaults_memo');
    expect($stored)->not->toBeNull();
    $stored?->cascade(['schema_templates' => ['section_defaults' => ['enabled' => 'changed']]]);
    $stored?->save();

    expect($repository->get('section_defaults_memo'))->toBe(['enabled' => 'yes'])
        // Another section, or the same handle as a taxonomy, are separate reads
        ->and($repository->get('section_defaults_memo_other'))->toBe(['enabled' => 'other'])
        ->and($repository->get('section_defaults_memo', 'taxonomy'))->toBe(['enabled' => 'taxonomy']);

    $repository->put('section_defaults_memo_other', 'collection', ['enabled' => 'saved']);

    expect($repository->get('section_defaults_memo'))->toBe(['enabled' => 'changed']);
});

it('saves a section\'s defaults, dropping null and empty values and keeping the rest of the cascade', function (string $type): void {
    $section = schemaSectionDefaultsSection($type, 'section_defaults_put', [
        'seo' => ['title' => 'kept'],
        'schema_templates' => ['blueprint_defaults' => ['article' => ['enabled' => 'no']], 'section_defaults' => ['enabled' => 'old']],
    ]);

    (new SchemaSectionDefaultsRepository)->put('section_defaults_put', $type, [
        'enabled' => 'yes',
        'inherit_template' => false,
        'template' => '',
        'parameter' => null,
    ]);

    expect(schemaSectionDefaultsOnDisk($section))->toBe([
        'seo' => ['title' => 'kept'],
        'schema_templates' => [
            'blueprint_defaults' => ['article' => ['enabled' => 'no']],
            'section_defaults' => ['enabled' => 'yes', 'inherit_template' => false],
        ],
    ]);
})->with(['collection', 'taxonomy']);

it('removes the defaults, then the root, as each is emptied', function (): void {
    $section = schemaSectionDefaultsSection('collection', 'section_defaults_prune', [
        'seo' => ['title' => 'kept'],
        'schema_templates' => ['blueprint_defaults' => ['article' => ['enabled' => 'no']], 'section_defaults' => ['enabled' => 'yes']],
    ]);

    (new SchemaSectionDefaultsRepository)->put('section_defaults_prune', 'collection', ['enabled' => '', 'template' => null]);

    expect(schemaSectionDefaultsOnDisk($section))->toBe([
        'seo' => ['title' => 'kept'],
        'schema_templates' => ['blueprint_defaults' => ['article' => ['enabled' => 'no']]],
    ]);

    $alone = schemaSectionDefaultsSection('collection', 'section_defaults_prune_alone', [
        'seo' => ['title' => 'kept'],
        'schema_templates' => ['section_defaults' => ['enabled' => 'yes']],
    ]);

    (new SchemaSectionDefaultsRepository)->put('section_defaults_prune_alone', 'collection', []);

    expect(schemaSectionDefaultsOnDisk($alone))->toBe(['seo' => ['title' => 'kept']]);
});

it('replaces a malformed root when it saves', function (): void {
    $section = schemaSectionDefaultsSection('collection', 'section_defaults_malformed', ['schema_templates' => 'on']);

    (new SchemaSectionDefaultsRepository)->put('section_defaults_malformed', 'collection', ['enabled' => 'yes']);

    expect(schemaSectionDefaultsOnDisk($section))->toBe(['schema_templates' => ['section_defaults' => ['enabled' => 'yes']]]);
});

it('saves nothing for a section that does not exist', function (): void {
    (new SchemaSectionDefaultsRepository)->put('no_such_collection', 'collection', ['enabled' => 'yes']);

    expect(Collection::find('no_such_collection'))->toBeNull();
});
