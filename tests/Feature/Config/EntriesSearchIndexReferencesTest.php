<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Guards the search-index correctness invariant for the `entries` fieldtype
 * (UAMS-Web/uams-statamic-archive#1416): every field that sets an explicit `search_index` must name an index
 * that exists in `config/statamic/search.php`.
 *
 * Unlike `terms_search_index`, the `entries` fieldtype resolves its search index
 * in three tiers (explicit field config -> shared collection `search_index` ->
 * title scan), so most fields intentionally set no explicit `search_index` and
 * rely on the collection fallback or a title scan. This test only guards the
 * explicit (tier 1) references — a dangling one is a silent editor-facing bug.
 *
 * @command  php artisan test --compact tests/Feature/Config/EntriesSearchIndexReferencesTest.php
 *
 * No mutable PHP surface: assertions over parsed blueprint/fieldset YAML and the
 * `statamic.search.indexes` config array.
 */

/**
 * Recursively collect every `entries` field across a parsed blueprint/fieldset
 * tree (fields nest inside grids, replicators, bard sets and imported
 * fieldsets).
 *
 * @param  list<array{file: string, handle: string, search_index: mixed}>  $out
 */
function collectEntriesFields(mixed $node, string $file, array &$out): void
{
    if (! is_array($node)) {
        return;
    }

    $field = $node['field'] ?? null;

    if (is_array($field) && ($field['type'] ?? null) === 'entries') {
        $out[] = [
            'file' => $file,
            'handle' => is_string($node['handle'] ?? null) ? $node['handle'] : '?',
            'search_index' => $field['search_index'] ?? null,
        ];
    }

    foreach ($node as $child) {
        if (is_array($child)) {
            collectEntriesFields($child, $file, $out);
        }
    }
}

/**
 * @return list<array{file: string, handle: string, search_index: mixed}>
 */
function allEntriesFields(): array
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
            collectEntriesFields(Yaml::parseFile($file->getPathname()), $rel, $out);
        }
    }

    return $out;
}

it('references only defined indexes from every explicit entries search_index', function (): void {
    /** @var array<string, mixed> $indexes */
    $indexes = config('statamic.search.indexes');
    $defined = array_keys($indexes);

    $fields = allEntriesFields();

    // Guard the guard: the inventory must actually find fields.
    expect($fields)->not->toBeEmpty();

    $dangling = [];
    $explicitCount = 0;
    foreach ($fields as $field) {
        $index = $field['search_index'];
        if ($index === null || $index === '') {
            continue; // tier 2 (collection) or tier 3 (title) — not an explicit ref
        }
        $explicitCount++;
        if (! in_array($index, $defined, true)) {
            /** @var string $index */
            $dangling[] = sprintf('%s: %s -> %s', $field['file'], $field['handle'], $index);
        }
    }

    // There is at least one explicit reference to actually exercise the check.
    expect($explicitCount)->toBeGreaterThan(0);
    expect($dangling)->toBe([], 'Dangling entries search_index references: '.implode('; ', $dangling));
});
