<?php

declare(strict_types=1);

use App\SearchTransformers\AlternateNameTransformer;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Guards the search-index correctness invariant for the custom
 * `terms_search_index` fieldtype (UAMS-Web/uams-statamic-archive#1415): every field that names a
 * `search_index` must name an index that actually exists in
 * `config/statamic/search.php`. A dangling reference is a silent editor-facing
 * bug — the relationship picker queries a non-existent index and returns
 * nothing.
 *
 * @command  php artisan test --compact tests/Feature/Config/TermsSearchIndexReferencesTest.php
 *
 * No mutable PHP surface: assertions over parsed blueprint/fieldset YAML and the
 * `statamic.search.indexes` config array.
 */

/**
 * Recursively collect every field definition of the given fieldtype across a
 * parsed blueprint/fieldset tree (fields nest inside grids, replicators, bard
 * sets and imported fieldsets, so a flat scan misses them).
 *
 * @param  list<array{file: string, handle: string, search_index: mixed}>  $out
 */
function collectTermsSearchIndexFields(mixed $node, string $file, array &$out): void
{
    if (! is_array($node)) {
        return;
    }

    $fieldConfig = $node['field'] ?? null;
    if (
        is_array($fieldConfig)
        && ($fieldConfig['type'] ?? null) === 'terms_search_index'
    ) {
        $out[] = [
            'file' => $file,
            'handle' => is_string($node['handle'] ?? null) ? $node['handle'] : '?',
            'search_index' => $fieldConfig['search_index'] ?? null,
        ];
    }

    foreach ($node as $child) {
        if (is_array($child)) {
            collectTermsSearchIndexFields($child, $file, $out);
        }
    }
}

/**
 * @return list<array{file: string, handle: string, search_index: mixed}>
 */
function allTermsSearchIndexFields(): array
{
    $out = [];

    foreach (['resources/blueprints', 'resources/fieldsets'] as $dir) {
        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(base_path($dir), FilesystemIterator::SKIP_DOTS)
        );

        foreach ($rii as $file) {
            /** @var SplFileInfo $file */
            if ($file->getExtension() !== 'yaml') {
                continue;
            }

            $rel = str_replace(base_path().'/', '', $file->getPathname());
            collectTermsSearchIndexFields(Yaml::parseFile($file->getPathname()), $rel, $out);
        }
    }

    return $out;
}

it('references only defined indexes from every terms_search_index field', function (): void {
    /** @var array<string, mixed> $indexes */
    $indexes = config('statamic.search.indexes');
    $defined = array_keys($indexes);

    $fields = allTermsSearchIndexFields();

    // Guard the guard: the inventory must actually find fields, or a silently
    // broken walker would make this test pass vacuously.
    expect($fields)->not->toBeEmpty();

    $dangling = [];
    foreach ($fields as $field) {
        /** @var string|null $index */
        $index = $field['search_index'];
        if ($index === null || $index === '') {
            continue; // deliberately index-less (title-scan fallback)
        }
        if (! in_array($index, $defined, true)) {
            $dangling[] = sprintf('%s: %s -> %s', $field['file'], $field['handle'], (string) $index);
        }
    }

    expect($dangling)->toBe([], 'Dangling terms_search_index references: '.implode('; ', $dangling));
});

it('defines the audiences search index the certification/credential audience pickers reference', function (): void {
    /** @var array<string, mixed> $indexes */
    $indexes = config('statamic.search.indexes');

    // The audience pickers on the certifications + credentials term blueprints
    // reference `search_index: audiences` (UAMS-Web/uams-statamic-archive#1415 confirmed gap).
    expect($indexes)->toHaveKey('audiences');

    /** @var array<string, mixed> $audiences */
    $audiences = $indexes['audiences'];

    expect($audiences['driver'])->toBe('local');
    expect($audiences['searchables'])->toBe('taxonomy:audiences');
    expect($audiences['fields'])->toBe(['title', 'name', 'alternate_name']);

    /** @var array<string, mixed> $transformers */
    $transformers = $audiences['transformers'];
    expect($transformers['alternate_name'])->toBe(AlternateNameTransformer::class);
});

it('points the certification and credential audience fields at the audiences index', function (): void {
    // Confirms the pickers that were broken now resolve to a defined index.
    foreach ([
        'resources/blueprints/taxonomies/certifications/certification.yaml',
        'resources/blueprints/taxonomies/credentials/credential.yaml',
    ] as $rel) {
        $fields = [];
        collectTermsSearchIndexFields(Yaml::parseFile(base_path($rel)), $rel, $fields);

        $audience = collect($fields)->firstWhere('handle', 'audience');

        expect($audience)->not->toBeNull("Expected an `audience` field in {$rel}");

        /** @var array{file: string, handle: string, search_index: mixed} $audience */
        expect($audience['search_index'])->toBe('audiences');
    }
});
