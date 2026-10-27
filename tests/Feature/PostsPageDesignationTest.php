<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Assert;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Feature coverage for the site's news-page designation (UAMS-Web/uams-statamic-archive#179 → UAMS-Web/uams-statamic-archive#1602 / UAMS-Web/uams-statamic-archive#1661).
 *
 * UAMS-Web/uams-statamic-archive#179 originally designated a site's WordPress-style "Posts page" with a
 * per-page `posts_page_query` toggle — unguarded, so any number of pages on a
 * site could claim the role. UAMS-Web/uams-statamic-archive#1555 replaced that with a **guarded per-site
 * designation**: `department_hub_page` on the `uams_multisite_settings` global,
 * one per site by construction. UAMS-Web/uams-statamic-archive#1661 retired the toggle onto it.
 *
 * These assertions cover both halves — the designation is the single mechanism,
 * and the retired toggle is gone — and stay hermetic (blueprint / fieldset /
 * template source), so they run without content.
 *
 * @command  php artisan test --compact tests/Feature/PostsPageDesignationTest.php
 */

/**
 * Read a nested list out of a parsed-YAML node, tolerating any non-list shape.
 *
 * @return array<array-key, mixed>
 */
function postsPageList(mixed $node, string $key): array
{
    if (! \is_array($node)) {
        return [];
    }

    $value = $node[$key] ?? [];

    return \is_array($value) ? $value : [];
}

/**
 * Resolve a field's config out of a parsed blueprint, by handle.
 *
 * @return array<array-key, mixed>|null
 */
function postsPageField(mixed $blueprint, string $handle): ?array
{
    foreach (postsPageList($blueprint, 'tabs') as $tab) {
        foreach (postsPageList($tab, 'sections') as $section) {
            foreach (postsPageList($section, 'fields') as $field) {
                if (\is_array($field) && ($field['handle'] ?? null) === $handle) {
                    $config = $field['field'] ?? null;

                    return \is_array($config) ? $config : null;
                }
            }
        }
    }

    return null;
}

it('designates the site news page with a single per-site global field', function (): void {
    $global = Yaml::parseFile(resource_path('blueprints/globals/uams_multisite_settings.yaml'));
    $field = postsPageField($global, 'department_hub_page');

    Assert::assertIsArray($field, 'department_hub_page is not defined on the multisite global.');

    // One per site by construction — a single-value pointer at a pages entry,
    // which is the guard the retired per-page toggle never had.
    expect($field['type'])->toBe('entries')
        ->and($field['max_items'])->toBe(1)
        ->and($field['collections'])->toBe(['pages'])
        ->and($field['localizable'])->toBeTrue();
});

it('renders the base-rung posts listing through the shared hub template', function (): void {
    $source = File::get(resource_path('views/pages/department_hub.antlers.html'));

    // A site with no department_organization is not a hub, so the designated page
    // degrades to the plain posts listing — the UAMS-Web/uams-statamic-archive#179 base rung, now reached through
    // the one shared news surface rather than a per-page toggle.
    expect($source)
        ->toContain('{{ if is_hub }}')
        ->toContain('{{ else }}')
        ->toContain('partial:posts/listing');
});

it('centralizes the listing page size in code, with no editor override', function (): void {
    $listing = File::get(resource_path('views/posts/_listing.antlers.html'));

    // UAMS-Web/uams-statamic-archive#1661 dropped the per-page editor control for consistency across sites: the
    // count lives in the partial, not in a blueprint field.
    expect($listing)->toContain('10');

    foreach (['default', 'page_landing'] as $template) {
        $source = File::get(resource_path("views/pages/{$template}.antlers.html"));
        expect($source)->not->toContain(':posts_per_page="posts_per_page"');
    }
});

it('has fully retired the unguarded posts_page_query toggle', function (): void {
    // The fieldset itself is gone.
    expect(File::exists(resource_path('fieldsets/uamsweb_posts_page.yaml')))->toBeFalse();

    // Neither page blueprint imports it, and neither page template gates a listing on it.
    foreach (['basic_page', 'landing_page'] as $blueprint) {
        $source = File::get(resource_path("blueprints/collections/pages/{$blueprint}.yaml"));
        expect($source)->not->toContain('uamsweb_posts_page');
    }

    foreach (['default', 'page_landing'] as $template) {
        $source = File::get(resource_path("views/pages/{$template}.antlers.html"));
        expect($source)->not->toContain('posts_page_query');
    }
});
