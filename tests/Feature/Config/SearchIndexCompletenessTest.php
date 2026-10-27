<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Guards search-index completeness (UAMS-Web/uams-statamic-archive#1417): every transformer referenced by
 * `config/statamic/search.php` must resolve to a real class, and no index may
 * list a structured field (an `entries`/`terms`/replicator/grid/… relationship
 * whose stored value is references or nested rows) without a transformer to
 * reduce it to clean searchable text. Statamic's Comb flattener does index an
 * array field's leaf values, but a foreign reference (`entries`/`terms`) then
 * flattens to another entity's IDs/slugs — noise, not this entity's identity —
 * and a replicator's raw rows include scaffolding; so each structured index
 * field must name a transformer that selects its searchable text, or be dropped.
 *
 * @command  php artisan test --compact tests/Feature/Config/SearchIndexCompletenessTest.php
 *
 * Reads parsed blueprint YAML + the `statamic.search.indexes` config array.
 */

/**
 * Field types whose stored value is structured (references / nested rows / asset
 * handles), not plain searchable text — indexing them bare needs a transformer.
 *
 * @return list<string>
 */
function structuredFieldTypes(): array
{
    return ['entries', 'terms', 'terms_search_index', 'replicator', 'grid', 'bard', 'group', 'assets', 'users'];
}

/**
 * Build a per-container map of every field handle to the set of types it takes
 * across that collection/taxonomy's blueprints.
 *
 * @param  array<string, array<string, bool>>  $map  handle => [type => true]
 */
function collectContainerFieldTypes(mixed $node, array &$map): void
{
    if (! is_array($node)) {
        return;
    }
    $handle = $node['handle'] ?? null;
    $field = $node['field'] ?? null;
    if (
        (is_string($handle) || is_int($handle))
        && is_array($field)
        && isset($field['type'])
        && is_string($field['type'])
    ) {
        $map[(string) $handle][$field['type']] = true;
    }
    foreach ($node as $child) {
        if (is_array($child)) {
            collectContainerFieldTypes($child, $map);
        }
    }
}

/**
 * @return array{collection: array<string, array<string, array<string, bool>>>, taxonomy: array<string, array<string, array<string, bool>>>}
 */
function containerFieldTypeMaps(): array
{
    $maps = ['collection' => [], 'taxonomy' => []];

    foreach (['collection' => 'collections', 'taxonomy' => 'taxonomies'] as $kind => $dir) {
        foreach (glob(base_path("resources/blueprints/$dir/*"), GLOB_ONLYDIR) ?: [] as $containerDir) {
            $handle = basename($containerDir);
            $map = [];
            foreach (glob($containerDir.'/*.yaml') ?: [] as $bp) {
                collectContainerFieldTypes(Yaml::parseFile($bp), $map);
            }
            $maps[$kind][$handle] = $map;
        }
    }

    return $maps;
}

it('references only transformer classes that exist', function (): void {
    /** @var array<string, array<string, mixed>> $indexes */
    $indexes = config('statamic.search.indexes');

    $referenced = [];
    foreach ($indexes as $index) {
        /** @var array<array-key, string> $transformers */
        $transformers = (array) ($index['transformers'] ?? []);
        foreach ($transformers as $class) {
            $referenced[$class] = true;
        }
    }

    expect($referenced)->not->toBeEmpty();

    $missing = array_values(array_filter(
        array_keys($referenced),
        fn (string $class): bool => ! class_exists($class),
    ));

    expect($missing)->toBe([], 'Missing transformer classes: '.implode(', ', $missing));
});

it('indexes no structured field without a transformer to flatten it', function (): void {
    /** @var array<string, array<string, mixed>> $indexes */
    $indexes = config('statamic.search.indexes');
    $maps = containerFieldTypeMaps();
    $structured = structuredFieldTypes();

    $offenders = [];
    foreach ($indexes as $name => $index) {
        $transformed = array_keys((array) ($index['transformers'] ?? []));
        /** @var array<array-key, string> $rawSearchables */
        $rawSearchables = (array) ($index['searchables'] ?? []);
        $searchables = array_map(strval(...), $rawSearchables);

        /** @var list<string> $fields */
        $fields = (array) ($index['fields'] ?? []);
        foreach ($fields as $field) {
            if ($field === 'title' || in_array($field, $transformed, true)) {
                continue;
            }

            foreach ($searchables as $token) {
                [$kind, $handle] = array_pad(explode(':', $token, 2), 2, '');
                $kind = $kind === 'collection' ? 'collection' : 'taxonomy';
                $types = array_keys($maps[$kind][$handle][$field] ?? []);

                if (array_intersect($types, $structured) !== []) {
                    $offenders[] = sprintf('%s.%s (%s)', $name, $field, implode('|', $types));
                }
            }
        }
    }

    expect($offenders)->toBe(
        [],
        'Structured fields indexed without a transformer: '.implode('; ', array_unique($offenders)),
    );
});
