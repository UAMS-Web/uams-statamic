<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\SitemapInclusionComputedsRegistrar as SitemapInclusion;

/**
 * The pure sitemap-inclusion predicates that keep non-public / non-routable /
 * orphaned entries out of the public XML sitemap and honor the per-entry
 * `include_in_xml_sitemap` editor toggle. Fully hermetic — no Statamic entry needed.
 *
 * - shouldIncludeInSitemap: the shared status/password/toggle gate (pages, posts,
 *   clinical_expertise).
 * - shouldIncludePerson: that gate plus the non-routable-profile exclusion (people).
 * - shouldIncludeKnowledgeBaseEntry: the orphaned-ancestor gate plus the toggle
 *   (knowledge_bases).
 *
 * Mutation: each covers the excluding branches, the strict `=== false` / `!== null`
 * comparisons (so they cannot weaken to `== false` / loose null checks), the
 * gate-wins-over-toggle interaction, and the default-include path.
 */

// -- shouldIncludeInSitemap (pages / posts / clinical_expertise) ---------------

it('includes a normal, non-private, unprotected entry', function (mixed $status, mixed $password): void {
    expect(SitemapInclusion::shouldIncludeInSitemap($status, $password))->toBeTrue();
})->with([
    'published, not protected' => ['published', false],
    'published, null protect' => ['published', null],
    'no status, no protect' => [null, null],
    'other live status' => ['scheduled', false],
]);

it('excludes a private entry', function (): void {
    expect(SitemapInclusion::shouldIncludeInSitemap('private', false))->toBeFalse();
});

it('excludes a password-protected entry regardless of status', function (mixed $password): void {
    expect(SitemapInclusion::shouldIncludeInSitemap('published', $password))->toBeFalse();
})->with([
    'boolean true' => [true],
    'truthy string' => ['1'],
]);

it('excludes a public entry the editor opted out of the sitemap', function (): void {
    expect(SitemapInclusion::shouldIncludeInSitemap('published', false, false))->toBeFalse();
});

it('keeps a public entry whose editor toggle is on, absent, or unaugmented', function (mixed $editorInclude): void {
    expect(SitemapInclusion::shouldIncludeInSitemap('published', false, $editorInclude))->toBeTrue();
})->with([
    'toggle on' => [true],
    'toggle absent (null)' => [null],
]);

it('lets the automatic gate win over an editor include=true', function (mixed $status, mixed $password): void {
    // An editor cannot force a non-public URL into the sitemap: even with the
    // toggle explicitly on, a private or password-protected entry stays excluded.
    expect(SitemapInclusion::shouldIncludeInSitemap($status, $password, true))->toBeFalse();
})->with([
    'private, toggle on' => ['private', false],
    'password-protected, toggle on' => ['published', true],
]);

// -- shouldIncludePerson (people) ----------------------------------------------

it('includes a routable, public person', function (mixed $profilePageQuery): void {
    // profile_page_query anything other than an explicit false stays routable.
    expect(SitemapInclusion::shouldIncludePerson('published', false, true, $profilePageQuery))->toBeTrue();
})->with([
    'profile query true' => [true],
    'profile query absent (null)' => [null],
]);

it('excludes a non-routable person even when public and opted in', function (): void {
    // profile_page_query === false → PersonEntry::route() returns null → no URI, so
    // the URL would 404. The routability gate wins over an editor include=true.
    expect(SitemapInclusion::shouldIncludePerson('published', false, true, false))->toBeFalse();
});

it('excludes a private or password-protected person (shared gate still applies)', function (mixed $status, mixed $password): void {
    expect(SitemapInclusion::shouldIncludePerson($status, $password, true, true))->toBeFalse();
})->with([
    'private' => ['private', false],
    'password-protected' => ['published', true],
]);

it('excludes a routable public person the editor opted out', function (): void {
    expect(SitemapInclusion::shouldIncludePerson('published', false, false, true))->toBeFalse();
});

// -- shouldIncludeKnowledgeBaseEntry (knowledge_bases) -------------------------

it('includes a knowledge_base entry with a fully published lineage', function (mixed $editorInclude): void {
    // A null orphaned-ancestor redirect URL means no ancestor is offline.
    expect(SitemapInclusion::shouldIncludeKnowledgeBaseEntry(null, $editorInclude))->toBeTrue();
})->with([
    'toggle on' => [true],
    'toggle absent (defaulted true)' => [null],
]);

it('excludes a knowledge_base entry orphaned by an unpublished ancestor', function (): void {
    // A non-null redirect URL means the entry redirects away — its own URL must not
    // be advertised. The orphan gate wins even with the editor toggle explicitly on.
    expect(SitemapInclusion::shouldIncludeKnowledgeBaseEntry('https://example.test/kb/parent', true))->toBeFalse();
});

it('excludes a non-orphaned knowledge_base entry the editor opted out', function (): void {
    expect(SitemapInclusion::shouldIncludeKnowledgeBaseEntry(null, false))->toBeFalse();
});
