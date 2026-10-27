<?php

declare(strict_types=1);

use App\SearchTransformers\SameAsTransformer;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Verifies Statamic search index configuration for the subject_matter collection.
 *
 * @command  php artisan test --compact tests/Feature/Config/SubjectMatterSearchIndexConfigTest.php
 *
 * No mutable PHP surface: config-only assertions on `statamic.search.indexes`
 * (`SameAsTransformer` is referenced only as a `::class` string, never invoked).
 */
it('configures the subject_matter search index', function (): void {
    // Load the configured Statamic search indexes
    /** @var array<string, mixed> $indexes */
    $indexes = config('statamic.search.indexes');

    // Assert the subject_matter index is defined
    expect($indexes)->toHaveKey('subject_matter');

    /** @var array<string, mixed> $subjectMatter */
    $subjectMatter = $indexes['subject_matter'];

    // Assert the index uses the local Comb driver
    expect($subjectMatter['driver'])->toBe('local');

    // Assert searchables include only the subject_matter collection (UAMS-Web/uams-statamic-archive#2822)
    expect($subjectMatter['searchables'])->toBe('collection:subject_matter');

    // Assert indexed fields match config/statamic/search.php `subject_matter`
    expect($subjectMatter['fields'])->toBe([
        'title',
        'notes',
        'description',
        'disambiguating_description',
        'alternate_name',
        'same_as',
        'keywords',
    ]);

    // Assert same_as replicator rows are flattened to URL strings for search
    /** @var array<string, mixed> $transformers */
    $transformers = $subjectMatter['transformers'];
    expect($transformers['same_as'])->toBe(SameAsTransformer::class);
});

it('searches both concept collections through subject_concepts, on every field either index searches (#2823)', function (): void {
    /** @var array<string, array<string, mixed>> $indexes */
    $indexes = config('statamic.search.indexes');

    $union = array_values(array_unique(array_map(
        fn (mixed $field): string => mustBeString($field, 'an indexed field'),
        [
            ...(array) ($indexes['subject_matter']['fields'] ?? []),
            ...(array) ($indexes['clinical_specializations']['fields'] ?? []),
        ],
    )));

    expect($indexes['subject_concepts']['searchables'] ?? null)->toBe(['collection:subject_matter', 'collection:clinical_specializations'])
        ->and((array) ($indexes['subject_concepts']['fields'] ?? []))->toEqualCanonicalizing($union)
        // A clinical concept's keywords are searchable in its own index too
        ->and((array) ($indexes['clinical_specializations']['fields'] ?? []))->toContain('keywords');
});
