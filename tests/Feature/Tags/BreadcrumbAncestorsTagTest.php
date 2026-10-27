<?php

declare(strict_types=1);

use App\Tags\BreadcrumbAncestors;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Feature tests for the {{ breadcrumb_ancestors }} tag.
 *
 * Tests the 404 ancestor-resolution functionality, including:
 *   - Resolving the chain of existing ancestor entries in order
 *   - Skipping ancestor paths that do not resolve to an entry
 *   - Dropping the final (missing) segment before walking ancestors
 *   - Returning false (rather than an empty array) when nothing resolves
 *   - Stripping a path-mounted site's prefix before lookup
 *   - Preferring nav_alt_name over title for the label
 *   - Falling back to the context current_uri when no url param is given
 *
 * Mutation: 100% on App\Tags\BreadcrumbAncestors (72 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Tags/BreadcrumbAncestorsTagTest.php
 */

/**
 * Create a BreadcrumbAncestors tag instance with mocked context and params.
 *
 * @param  array<string, mixed>  $contextData  Context data (e.g. ['current_uri' => '/a/b/missing'])
 * @param  array<string, mixed>  $params  Tag parameters (e.g. ['url' => '/a/b/missing'])
 * @return BreadcrumbAncestors The configured tag instance
 */
function breadcrumbAncestorsCreateTag(
    array $contextData = [],
    array $params = []
): BreadcrumbAncestors {
    // Create a mock context that reads from the provided context data
    $context = Mockery::mock(Context::class);
    $context->shouldReceive('get')
        ->andReturnUsing(
            fn (string $key) => $contextData[$key] ?? null
        );

    // Create a mock parameters object that reads from the provided params
    $parameters = Mockery::mock(Parameters::class);
    $parameters->shouldReceive('get')
        ->andReturnUsing(
            fn (string $key, mixed $default = null) => $params[$key] ?? $default
        );

    // Instantiate the tag and inject the context and params via reflection
    $tag = new BreadcrumbAncestors;
    $reflection = new ReflectionClass($tag);
    $reflection->getProperty('context')->setValue($tag, $context);
    $reflection->getProperty('params')->setValue($tag, $parameters);

    return $tag;
}

/**
 * Bind the Site facade so current() and get() return a site at the given URL.
 *
 * @param  string  $handle  The current site handle
 * @param  string  $url  The site's URL (its mount point)
 */
function breadcrumbAncestorsFakeSite(
    string $handle,
    string $url
): void {
    // Build a stand-in site exposing only the handle() and url() accessors
    $site = Mockery::mock();
    $site->shouldReceive('handle')->andReturn($handle);
    $site->shouldReceive('url')->andReturn($url);

    // Return the same site for both current() and any get() lookup
    Site::shouldReceive('current')->andReturn($site);
    Site::shouldReceive('get')->andReturn($site);
}

/**
 * Build an entry stub that responds to url() and value() for breadcrumb use.
 *
 * @param  string  $url  The entry URL
 * @param  string  $title  The entry title
 * @param  string|null  $navAltName  The optional navigation alt name
 * @return StatamicEntry&MockInterface The configured entry stub
 */
function breadcrumbAncestorsFakeEntry(
    string $url,
    string $title,
    ?string $navAltName = null
): StatamicEntry&MockInterface {
    $entry = Mockery::mock(StatamicEntry::class);
    $entry->shouldReceive('url')->andReturn($url);
    $entry->shouldReceive('value')->with('nav_alt_name')->andReturn($navAltName);
    $entry->shouldReceive('value')->with('title')->andReturn($title);

    return $entry;
}

it('returns false for a top-level url with no ancestors', function () {
    breadcrumbAncestorsFakeSite('default', 'http://example.test');

    $tag = breadcrumbAncestorsCreateTag(params: ['url' => '/hotdog']);

    // Only segment is the missing page itself, so there is nothing to resolve
    expect($tag->index())->toBeFalse();
});

it('resolves the full ancestor chain in order', function () {
    breadcrumbAncestorsFakeSite('default', 'http://example.test');

    EntryFacade::shouldReceive('findByUri')
        ->with('/a', 'default')
        ->andReturn(breadcrumbAncestorsFakeEntry('/a', 'A'));
    EntryFacade::shouldReceive('findByUri')
        ->with('/a/b', 'default')
        ->andReturn(breadcrumbAncestorsFakeEntry('/a/b', 'B'));

    $tag = breadcrumbAncestorsCreateTag(params: ['url' => '/a/b/missing']);

    expect($tag->index())->toBe([
        ['url' => '/a', 'title' => 'A'],
        ['url' => '/a/b', 'title' => 'B'],
    ]);
});

it('skips intermediate ancestors that do not resolve', function () {
    breadcrumbAncestorsFakeSite('default', 'http://example.test');

    EntryFacade::shouldReceive('findByUri')
        ->with('/a', 'default')
        ->andReturnNull();
    EntryFacade::shouldReceive('findByUri')
        ->with('/a/b', 'default')
        ->andReturn(breadcrumbAncestorsFakeEntry('/a/b', 'B'));

    $tag = breadcrumbAncestorsCreateTag(params: ['url' => '/a/b/missing']);

    // Deepest existing match is kept; the missing /a is omitted
    expect($tag->index())->toBe([
        ['url' => '/a/b', 'title' => 'B'],
    ]);
});

it('returns false when no ancestors resolve', function () {
    breadcrumbAncestorsFakeSite('default', 'http://example.test');

    EntryFacade::shouldReceive('findByUri')->andReturnNull();

    $tag = breadcrumbAncestorsCreateTag(params: ['url' => '/a/b/missing']);

    expect($tag->index())->toBeFalse();
});

it('strips a path-mounted site prefix before lookup', function () {
    breadcrumbAncestorsFakeSite('child', 'http://example.test/medicine/anesthesiology');

    // The site-relative URI ("/test") is looked up, not the full request path
    EntryFacade::shouldReceive('findByUri')
        ->with('/test', 'child')
        ->andReturn(breadcrumbAncestorsFakeEntry('/medicine/anesthesiology/test', 'Test'));

    $tag = breadcrumbAncestorsCreateTag(params: ['url' => '/medicine/anesthesiology/test/missing']);

    expect($tag->index())->toBe([
        ['url' => '/medicine/anesthesiology/test', 'title' => 'Test'],
    ]);
});

it('prefers nav_alt_name over title for the label', function () {
    breadcrumbAncestorsFakeSite('default', 'http://example.test');

    EntryFacade::shouldReceive('findByUri')
        ->with('/a', 'default')
        ->andReturn(breadcrumbAncestorsFakeEntry('/a', 'A', 'Alt'));

    $tag = breadcrumbAncestorsCreateTag(params: ['url' => '/a/missing']);

    expect($tag->index())->toBe([
        ['url' => '/a', 'title' => 'Alt'],
    ]);
});

it('falls back to current_uri from context when no url param', function () {
    breadcrumbAncestorsFakeSite('default', 'http://example.test');

    EntryFacade::shouldReceive('findByUri')
        ->with('/a', 'default')
        ->andReturn(breadcrumbAncestorsFakeEntry('/a', 'A'));

    $tag = breadcrumbAncestorsCreateTag(contextData: ['current_uri' => '/a/missing']);

    expect($tag->index())->toBe([
        ['url' => '/a', 'title' => 'A'],
    ]);
});
