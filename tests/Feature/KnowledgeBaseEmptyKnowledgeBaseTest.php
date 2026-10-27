<?php

declare(strict_types=1);

use App\Services\KnowledgeBase\KnowledgeBaseComputedFieldsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\TestCase;

uses(TestCase::class, RequiresContentSubmodule::class);

/**
 * Feature coverage for UAMS-Web/uams-statamic-archive#215: an empty "knowledge base" entry (a knowledge_base
 * root with no published descendant article) is suppressed from the front end —
 * its template redirects to the current site root.
 *
 * The redirect guard lives at the top of resources/views/knowledge_bases/
 * knowledge-base.antlers.html (the KB root's default template, per the blueprint's
 * hidden `template` default `knowledge_bases/knowledge-base`) as
 * `{{ unless has_descendant_articles }}{{ %redirect ... }}`. The %redirect tag's
 * 302-to-site-root behavior is proven once in KnowledgeBaseEmptyTopicTest (the
 * guard is byte-identical across the topic and knowledge_base templates); this
 * file pins the KB-root template's use of that guard and the real-content path.
 * The blueprint-guard widening that makes has_descendant_articles meaningful for a
 * knowledge_base root is unit-pinned in
 * tests/Feature/Services/KnowledgeBase/KnowledgeBaseDescendantArticlesQueryTest.php.
 *
 * The content-backed case self-skips without the content/ submodule.
 *
 * @command  php artisan test --compact tests/Feature/KnowledgeBaseEmptyKnowledgeBaseTest.php
 */
it('gates the empty knowledge_base redirect on has_descendant_articles, targeting the per-site root', function (): void {
    // The KB-root template that renders individual knowledge_base entries.
    $template = File::get(resource_path('views/knowledge_bases/knowledge-base.antlers.html'));

    // The redirect only fires for an empty knowledge base (no published descendant article).
    expect($template)->toMatch('/\{\{\s*unless\s+has_descendant_articles\s*\}\}/');

    // It issues a 302 redirect to the CURRENT SITE root (site:url), not the global
    // app root — the collection is mounted across many subdirectory sites.
    expect($template)->toContain('%redirect');
    expect($template)->toContain(':to="site:url"');
    expect($template)->not->toContain('to="/"');
    expect($template)->toContain('response="302"');
});

it('treats a real published knowledge_base with published articles as non-empty', function (): void {
    // Skip cleanly when the knowledge_bases collection is absent (no content/
    // submodule). Querying whereStatus on an unregistered collection throws
    // (Stache has no collection to resolve), so the guard must precede the query.
    $this->requireContentCollection('knowledge_bases');

    // Published knowledge_base root entries from the real content tree.
    /** @var EntryQueryBuilder $query */
    $query = EntryFacade::query();

    $query->where('collection', 'knowledge_bases');
    $query->where('blueprint', 'knowledge_base');
    $query->whereStatus('published');

    /** @var Collection<int, Entry> $roots */
    $roots = $query->get();

    // The first published KB root that is non-empty (has a published descendant article).
    $service = new KnowledgeBaseComputedFieldsService;
    $root = $roots->first(
        fn (Entry $entry): bool => $service->resolveHasDescendantArticles($entry)
    );

    // Skip when no KB root currently qualifies — an editorial fact, not a defect.
    /** @var Entry $root */
    $root = $this->skipWithoutQualifyingContentEntry(
        $root,
        'a published knowledge_bases knowledge_base root with a published descendant article'
    );

    // The published-only descendant-article check reports the root as non-empty,
    // so the redirect guard does not fire for it.
    expect($service->resolveHasDescendantArticles($root))->toBeTrue();
});
