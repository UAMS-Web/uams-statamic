<?php

declare(strict_types=1);

use App\Scopes\KnowledgeBaseSubtreeArticles;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Scope;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for knowledge-base search (UAMS-Web/uams-statamic-archive#506): the in-context KB search bar submits
 * the shared `/search` page a `knowledge_bases` index plus a `kb_scope` entry id,
 * which switches the page into subtree-scoped KB mode and runs results through the
 * `knowledge_base_subtree_articles` query scope. The template wiring, the config
 * index, and the scope-handle resolution are asserted content-free; KB-mode
 * rendering is driven by injecting the `get` cascade (no index build).
 *
 * @command  php artisan test --compact tests/Feature/KnowledgeBaseSearchTest.php
 */
it('registers a knowledge_bases search index over the collection', function (): void {
    /** @var array{driver: string, searchables: string, fields: array<int, string>} $index */
    $index = config('statamic.search.indexes.knowledge_bases');

    expect($index['driver'])->toBe('local')
        ->and($index['searchables'])->toBe('collection:knowledge_bases')
        // The article body/summary fields are indexed so KB search matches content,
        // not just titles.
        ->and($index['fields'])->toContain('title')
        ->and($index['fields'])->toContain('content')
        ->and($index['fields'])->toContain('excerpt');
});

it('resolves the knowledge_base_subtree_articles handle to the subtree scope', function (): void {
    // Ties the query-scope string threaded from the /search page to the concrete
    // scope class; renaming either side would silently unscope KB search.
    expect(Scope::find('knowledge_base_subtree_articles'))
        ->toBeInstanceOf(KnowledgeBaseSubtreeArticles::class);
});

it('submits the KB search bar to /search scoped by index and the current entry id', function (): void {
    $bar = File::get(resource_path('views/knowledge_bases/_search_bar.antlers.html'));

    expect($bar)
        // Submits to the shared /search page (GET).
        ->toContain('action="/search"')
        ->toContain('name="q"')
        // Carries the KB index + the current entry id as the subtree root.
        ->toContain('name="index"')
        ->toContain('value="knowledge_bases"')
        ->toContain('name="kb_scope"')
        ->toContain('value="{{ id }}"')
        // The topic heading reads the real field handle — the stub read the
        // non-existent `topic_search_title` and rendered an empty heading.
        ->toContain('topic_search_bar_title');
    expect($bar)->not->toContain('topic_search_title }}');
});

it('switches the /search page into subtree-scoped KB mode', function (): void {
    $page = File::get(resource_path('views/search/index.antlers.html'));

    expect($page)
        // The KB index is honored only alongside a scope root.
        ->toContain("get:index == 'knowledge_bases' && \$kb_scope")
        ->toContain('$is_kb_search')
        // The scope root is array-guarded, then sanitized before it is reflected.
        ->toContain('$raw_kb_scope | is_array')
        ->toContain('$raw_kb_scope | sanitize')
        // Results run through the registered subtree scope, rooted at the entry id.
        ->toContain('query_scope="knowledge_base_subtree_articles"')
        ->toContain(':scope_root="$kb_scope"')
        // A one-click broaden to the whole site: the cascade-escaped query is decoded,
        // then url-encoded for the href (UAMS-Web/uams-statamic-archive#1510).
        ->toContain('Search the whole site')
        ->toContain('$q | decode | urlencode')
        ->toContain('/search?q={{ $q_url }}');
});

it('threads the scope_root param through the shared results listing', function (): void {
    expect(File::get(resource_path('views/search/_results.antlers.html')))
        ->toContain(':scope_root="scope_root"');
});

it('renders KB mode without the type filter and preserves the subtree scope', function (): void {
    // Inject the KB-mode cascade with no query, so search:results short-circuits and
    // no index build is needed; the form still renders in KB mode.
    $html = (string) view('search/index', ['get' => [
        'index' => 'knowledge_bases',
        'kb_scope' => 'kb-123',
    ]])->render();

    expect($html)
        // The subtree root is carried as a hidden field so a refined query stays scoped.
        ->toContain('name="kb_scope"')
        ->toContain('value="kb-123"')
        ->toContain('name="index"')
        // The broaden affordance is offered.
        ->toContain('Search the whole site');
    // The site-wide typed tabs (UAMS-Web/uams-statamic-archive#1524) are absent in KB mode.
    expect($html)->not->toContain('class="site-search-tabs');
});

it('escapes a hostile scope root rather than reflecting it as live markup', function (): void {
    // A tampered kb_scope is reflected into the hidden field value; Antlers is raw by
    // default, so it must be escaped to avoid attribute-injection.
    $payload = '"><script>alert(1)</script>';

    $html = (string) view('search/index', ['get' => [
        'index' => 'knowledge_bases',
        'kb_scope' => $payload,
    ]])->render();

    expect($html)
        ->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;');
});

it('fails closed to non-KB search on an array-valued kb_scope instead of crashing', function (): void {
    // A tampered `?kb_scope[]=x` arrives as an array; piping it through `sanitize`
    // would throw an uncaught TypeError (HTTP 500). It must be coerced to '' so the
    // page falls back to ordinary site search rather than 500ing.
    $html = (string) view('search/index', ['get' => [
        'index' => 'knowledge_bases',
        'kb_scope' => ['x'],
    ]])->render();

    // Renders the ordinary typed tabs (UAMS-Web/uams-statamic-archive#1524), not KB mode.
    expect($html)->toContain('class="site-search-tabs');
    expect($html)->not->toContain('name="kb_scope"');
});

it('keeps the type filter and no KB scope in ordinary site search', function (): void {
    // With no KB params, the page renders the normal type-filter dropdown and no
    // KB-scope field — a regression guard on the KB branch not leaking into default mode.
    $html = (string) view('search/index', ['get' => []])->render();

    expect($html)->toContain('class="site-search-tabs');
    expect($html)->not->toContain('name="kb_scope"');
});
