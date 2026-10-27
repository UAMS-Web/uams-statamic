<?php

declare(strict_types=1);

use App\Support\ContentDescendantIds;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Real-term round-trip for `ContentDescendantIds::forTerm` (UAMS-Web/uams-statamic-archive#1367): against real
 * Statamic terms in an isolated Stache, the descendant walk resolves the tree
 * from each term's RAW stored `parent` slug — proving the raw `get('parent')`
 * read (no per-term augmentation) matches the augmented behavior it replaced.
 *
 * @covers \App\Support\ContentDescendantIds
 *
 * @command  php artisan test --compact tests/Feature/Support/ContentDescendantIdsTermTreeTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * Create a probe taxonomy.
 */
function makeProbeTaxonomy(string $handle, string $title): void
{
    /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
    $taxonomy = Taxonomy::make($handle);
    $taxonomy->title($title);
    $taxonomy->save();
}

/**
 * Create a term from its raw stored data (no per-term augmentation).
 *
 * @param  array<string, mixed>  $data
 */
function makeProbeTerm(string $taxonomy, string $slug, array $data): void
{
    /** @var Statamic\Taxonomies\Term $term */
    $term = Term::make();
    $term->taxonomy($taxonomy);
    $term->slug($slug);
    foreach ($data as $key => $value) {
        $term->set($key, $value);
    }
    $term->saveQuietly();
}

it('resolves the real term tree descendants from raw parent slugs', function (): void {
    makeProbeTaxonomy('descendant_probe', 'Descendant Probe');

    makeProbeTerm('descendant_probe', 'root', ['title' => 'Root']);
    makeProbeTerm('descendant_probe', 'child-a', ['title' => 'A', 'parent' => 'root']);
    makeProbeTerm('descendant_probe', 'child-b', ['title' => 'B', 'parent' => 'root']);
    makeProbeTerm('descendant_probe', 'grandchild', ['title' => 'G', 'parent' => 'child-a']);

    // Order-independent: the BFS ordering is pinned in the stub-based unit test;
    // here we prove the real raw-parent read resolves the correct descendant SET.
    expect(ContentDescendantIds::forTerm('descendant_probe::root'))
        ->toEqualCanonicalizing([
            'descendant_probe::child-a',
            'descendant_probe::child-b',
            'descendant_probe::grandchild',
        ]);
});

it('returns no descendants for a leaf term', function (): void {
    makeProbeTaxonomy('descendant_probe', 'Descendant Probe');
    makeProbeTerm('descendant_probe', 'root', ['title' => 'Root']);
    makeProbeTerm('descendant_probe', 'leaf', ['title' => 'Leaf', 'parent' => 'root']);

    expect(ContentDescendantIds::forTerm('descendant_probe::leaf'))->toBe([]);
});
