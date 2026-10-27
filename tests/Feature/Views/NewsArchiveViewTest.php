<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Structural coverage for the Departmental News Hub archive view
 * (`resources/views/posts/_news_archive.antlers.html`, UAMS-Web/uams-statamic-archive#1599) — the single
 * paginated listing every hub "More" / CTA link resolves to.
 *
 * The archive's query/filter/pagination behavior is covered end-to-end at the
 * tag + service level in the `NewsArchiveQueryTest` cases
 * (`tests/Feature/Services/Posts/`). This test guards the *view wiring* against the
 * template source, mirroring `PostsIndexListingTest`'s approach for the sibling
 * `_listing` partial (a full render needs the archive's page route, deferred to
 * UAMS-Web/uams-statamic-archive#1600).
 *
 * @command  php artisan test --compact tests/Feature/Views/NewsArchiveViewTest.php
 */

/** Read the archive view template source. */
function newsArchiveTemplate(): string
{
    return File::get(resource_path('views/posts/_news_archive.antlers.html'));
}

it('drives the listing through the news_archive tag under the posts alias', function (): void {
    expect(newsArchiveTemplate())
        ->toContain('{{ news_archive')
        ->toContain('as="posts"')
        // The forcing per_page param is forwarded; org/type/source stay URL-driven.
        ->toContain(':per_page="per_page"');
});

it('renders a filter notice and empty state from the active-filter state', function (): void {
    expect(newsArchiveTemplate())
        ->toContain('active_type')
        ->toContain('active_source')
        // A clear-filters affordance back to the unfiltered archive.
        ->toContain('href="{{ current_url }}"')
        ->toContain('no_results')
        ->toContain('posts-empty');
});

it('renders the post cards through the shared card partial', function (): void {
    expect(newsArchiveTemplate())
        ->toContain('partial:posts/card')
        ->toContain('https://schema.org/ItemList');
});

it('renders real pagination controls gated on more than one page', function (): void {
    expect(newsArchiveTemplate())
        ->toContain('paginate:total_pages > 1')
        ->toContain('paginate:prev_page')
        ->toContain('paginate:next_page')
        ->toContain('paginate:current_page');
});
