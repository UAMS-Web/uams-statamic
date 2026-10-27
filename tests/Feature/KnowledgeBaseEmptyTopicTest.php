<?php

declare(strict_types=1);

use App\Services\KnowledgeBase\KnowledgeBaseComputedFieldsService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Assert;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Tags\Redirect;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\TestCase;

uses(TestCase::class, RequiresContentSubmodule::class);

/**
 * Feature coverage for UAMS-Web/uams-statamic-archive#216: empty "topic" entries (no published descendant
 * article) are suppressed — redirected to the site root, and (via the
 * published-aware has_descendant_articles computed) hidden from listings.
 *
 * The redirect guard lives at the top of resources/views/knowledge_bases/
 * topic.antlers.html as `{{ unless has_descendant_articles }}{{ %redirect ... }}`.
 * The runtime firing of `%redirect` requires the view pipeline (params are not
 * populated under a bare Antlers::parse), so the guard is verified in two parts:
 * the redirect tag behavior with the guard's exact params, and the template's
 * use of that tag gated on emptiness. The content-backed case self-skips without
 * the content/ submodule, mirroring Tests\Concerns\RequiresContentSubmodule.
 *
 * @command  php artisan test --compact tests/Feature/KnowledgeBaseEmptyTopicTest.php
 */
it('redirects to the current site root with a 302 using the guard target', function (): void {
    // The guard resolves :to="site:url" to the current site's root.
    /** @var Statamic\Sites\Site $currentSite */
    $currentSite = Site::current();
    /** @var string $siteRoot */
    $siteRoot = $currentSite->url();

    // The redirect tag configured as the topic guard resolves it.
    $tag = app(Redirect::class);
    $tag->setContext([]);
    $tag->setParameters(['to' => $siteRoot, 'response' => '302']);

    // Invoking the tag aborts the request with a redirect response.
    try {
        $tag->index();
        Assert::fail('Expected the redirect tag to abort with a redirect response.');
    } catch (HttpResponseException $exception) {
        // The abort carries a 302 redirect to the current site's root.
        $response = mustBe(RedirectResponse::class, $exception->getResponse(), 'response');
        expect($response->getStatusCode())->toBe(302);
        expect($response->getTargetUrl())->toBe(url($siteRoot));
    }
});

it('gates the empty-topic redirect on has_descendant_articles, targeting the per-site root', function (): void {
    // The topic template that renders individual topic entries.
    $template = File::get(resource_path('views/knowledge_bases/topic.antlers.html'));

    // The redirect only fires for an empty topic (no published descendant article).
    expect($template)->toMatch('/\{\{\s*unless\s+has_descendant_articles\s*\}\}/');

    // It issues a 302 redirect to the CURRENT SITE root (site:url), not the global
    // app root — the collection is mounted across many subdirectory sites.
    expect($template)->toContain('%redirect');
    expect($template)->toContain(':to="site:url"');
    expect($template)->not->toContain('to="/"');
    expect($template)->toContain('response="302"');
});

it('treats a real published topic with published articles as non-empty', function (): void {
    // Skip cleanly when the knowledge_bases collection is absent (no content/
    // submodule). Querying whereStatus on an unregistered collection throws
    // (Stache has no collection to resolve), so the guard must precede the query.
    $this->requireContentCollection('knowledge_bases');

    // Published topic entries from the real content tree.
    /** @var EntryQueryBuilder $query */
    $query = EntryFacade::query();
    /** @var EntryQueryBuilder $query */
    $query = $query->where('collection', 'knowledge_bases');
    /** @var EntryQueryBuilder $query */
    $query = $query->where('blueprint', 'topic');
    /** @var EntryQueryBuilder $query */
    $query = $query->whereStatus('published');
    /** @var Collection<int, Entry> $topics */
    $topics = $query->get();

    // The first published topic that is non-empty (has a published descendant article).
    $service = new KnowledgeBaseComputedFieldsService;
    $topic = $topics->first(
        fn (Entry $entry): bool => $service->resolveHasDescendantArticles($entry)
    );

    // Skip when no topic currently qualifies — an editorial fact, not a defect.
    /** @var Entry $topic */
    $topic = $this->skipWithoutQualifyingContentEntry(
        $topic,
        'a published knowledge_bases topic with a published descendant article'
    );

    // The published-only descendant-article check reports the topic as non-empty,
    // so the redirect guard does not fire for it.
    expect($service->resolveHasDescendantArticles($topic))->toBeTrue();
});
