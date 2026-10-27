<?php

declare(strict_types=1);

use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\TestCase;

uses(TestCase::class, RequiresContentSubmodule::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#2532: `post_tags` was a flat taxonomy, and a flat term exists on
 * every site its taxonomy is enabled for, so the importer had to prefix each title
 * with its site name. `UAMS-Web/wordpress-importer#1215` settled that tags import
 * as entries in a site-scoped collection, following UAMS-Web/uams-statamic-archive#1863.
 *
 * These assert the shipped configuration: a structured, non-propagating collection
 * routed where the term pages were, with its blueprint, and no taxonomy left to
 * resolve the old source. Site scoping at query time — a tag on one site is
 * unknown on another — is covered by `Scopes/PostsInPostTagScopesTest`.
 *
 * @command  php artisan test --compact tests/Feature/PostTagsCollectionTest.php
 */
it('is a structured, site-scoped collection routed where the term pages were', function (): void {
    $this->requireContentCollection('post_tags');

    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::findByHandle('post_tags');

    expect($collection->hasStructure())->toBeTrue()
        // Site-scoped: an entry is authored on one site and never copied to the others.
        ->and($collection->propagate())->toBeFalse()
        ->and(mustBe(Illuminate\Support\Collection::class, $collection->sites(), 'post_tags sites')->all())->toContain('default', 'news')
        // The same URL shape the term pages had, so existing links still resolve.
        ->and($collection->route('default'))->toBe('/post-tags/{{ slug }}')
        ->and($collection->template())->toBe('post_tags/show');

    // Not capped at depth 1: a depth-1 collection is "orderable", and Statamic skips
    // orderable collections when it re-indexes entry URIs after a tree save, so a
    // newly added tag would never get a URI and its archive would 404.
    expect($collection->orderable())->toBeFalse();

    expect($collection->entryBlueprint('post_tag'))->not->toBeNull();

    // The flat taxonomy is gone, so nothing can resolve the old source.
    expect(Taxonomy::findByHandle('post_tags'))->toBeNull();
});
