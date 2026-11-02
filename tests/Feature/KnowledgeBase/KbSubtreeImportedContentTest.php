<?php

declare(strict_types=1);

use App\Services\KnowledgeBase\KnowledgeBaseSubtreeArticleIds;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\File;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Stache\Repositories\CollectionTreeRepository;
use Statamic\Stache\Stache;
use Symfony\Component\Console\Output\NullOutput;
use Tests\Feature\Services\KnowledgeBase\KnowledgeBaseSubtreeArticleIdsTest;
use Tests\Support\StacheIsolatedTestCase;
use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Assets\AssetImporter;
use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Entries\Creators\KbEntryCreator;
use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Entries\EntryCreator;
use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Parsers\XmlParser;
use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Prompts\MinervaKbSettings\Extractor;
use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Prompts\MinervaKbSettings\Loader;
use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Prompts\MinervaKbSettings\Prompter;
use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Prompts\MinervaKbSettings\Selector as MinervaKbSettingsSelector;
use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Prompts\MinervaKbSettings\Validator;
use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Structures\TreeBuilder;
use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Utilities\CollectionStructureManager;
use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Utilities\ContentProcessor;
use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Utilities\MetadataMapper;
use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Utilities\SearchIndexUpdates;
use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Utilities\SiteManager;

uses(StacheIsolatedTestCase::class);

/**
 * @covers \App\Services\KnowledgeBase\KnowledgeBaseSubtreeArticleIds
 *
 * Subtree-scoped KB search (UAMS-Web/uams-statamic-archive#506) against content a real MinervaKB import produced
 * (UAMS-Web/uams-statamic-archive#2119). {@see KnowledgeBaseSubtreeArticleIdsTest}
 * pins the resolver with Mockery doubles — it proves the bulk query is *built*
 * correctly and cannot prove it *resolves* correctly, because no structure exists in
 * that test to resolve against. This one imports a WXR, lets `KbEntryCreator` build
 * the real tree, and asks the resolver the two questions `UAMS-Web/wordpress-importer#432`
 * could not ask in that repository: `App\` is not on its autoloader, so a test there
 * naming this class fails PHPStan outright and every call on it degrades to `mixed`.
 *
 * **The fixture is authored here on purpose.** `kbBuildSelectiveXml()` emits one
 * article with its own primary topic, and sibling-topic *exclusion* cannot be
 * asserted from a single topic. Measured against the importer's own data at
 * `v0.17.0`: all fifteen sample articles sit on `sample-topic-1` except
 * `sample-article-3`, which spans topics 1 and 2 — so no call to that builder yields
 * two topics each carrying a *distinct* article.
 * `tests/Fixtures/wordpress-import/kb-two-topics.xml` was generated from that
 * builder's own emitters and then narrowed: `sample-article-3` keeps only its
 * `sample-topic-2` category, which puts one article under each topic.
 * `sample-topic-3` carries none, which is the empty-subtree case.
 *
 * @command  php artisan test --compact tests/Feature/KnowledgeBase/KbSubtreeImportedContentTest.php
 */
const KB_SUBTREE_FIXTURE = 'tests/Fixtures/wordpress-import/kb-two-topics.xml';

/**
 * The MinervaKB settings a production import resolves, matching the importer's own
 * host-app suite so this exercises the same configuration path.
 *
 * @return array<string, mixed>
 */
function kbSubtreeSettings(): array
{
    return [
        'breadcrumbs_home_label' => 'Knowledge Base',
        'home_sections_switch' => true,
        'home_sections' => 'search,topics',
        'search_title' => 'Search the Knowledge Base',
        'search_placeholder' => 'e.g., Guidelines',
        'cpt_topic_label_name' => 'Topics',
        'home_topics_limit' => '-1',
        'show_all_label' => 'Show All',
        'home_topics_show_description' => true,
        'restrict_on' => false,
        'restrict_article_role' => '["guest"]',
        'restrict_article_message' => null,
        'add_topic_search' => false,
        'show_topic_description' => true,
        'topic_articles_per_page' => '-1',
        'show_search_page_excerpt' => true,
        'show_search_page_last_edit' => true,
        'last_modified_date_text' => 'Last modified:',
        'show_article_author' => false,
        'show_reading_estimate' => false,
        'toc_dynamic_enable' => false,
        'toc_headings_exclude' => '',
        'toc_label' => null,
        'article_attach_label' => 'Attachments',
        'show_related_articles' => true,
        'related_articles_label' => 'Related Articles',
    ];
}

/**
 * Drive a MinervaKB import of the given WXR through the real importer services.
 *
 * Wrapped in `SearchIndexUpdates::withDisabled()` because `ImportWordPress` does:
 * a bare import leaves per-save indexing on, which is a different path from the one
 * production takes.
 */
function kbSubtreeImport(string $xmlPath): void
{
    $xmlParser = new XmlParser;
    $xmlParser->parse($xmlPath);

    $assetImporter = new AssetImporter;
    $metadataMapper = new MetadataMapper;
    $contentProcessor = new ContentProcessor;
    $siteManager = new SiteManager;
    $collectionStructureManager = new CollectionStructureManager;

    $creator = new KbEntryCreator(
        new EntryCreator(
            $metadataMapper,
            $contentProcessor,
            $siteManager,
            $collectionStructureManager,
            new TreeBuilder(new CollectionTreeRepository(app(Stache::class)))
        ),
        new MinervaKbSettingsSelector(new Prompter, new Loader, new Validator, new Extractor)
    );

    SearchIndexUpdates::withDisabled(function () use (
        $creator,
        $xmlParser,
        $assetImporter,
        $siteManager,
        $collectionStructureManager
    ): void {
        $creator->process(
            $xmlParser,
            $assetImporter,
            new NullOutput,
            'https://example.com',
            'default',
            $siteManager,
            $collectionStructureManager,
            kbSubtreeSettings()
        );
    });
}

/**
 * Every imported `knowledge_bases` entry.
 *
 * `Entry::query()` is typed to the contract, which declares no `where()`, so the
 * Stache builder is narrowed here once rather than at each call site.
 *
 * @return SupportCollection<int, Entry>
 */
function kbSubtreeEntries(): SupportCollection
{
    /** @var EntryQueryBuilder $query */
    $query = EntryFacade::query();

    $query->where('collection', 'knowledge_bases');

    /** @var SupportCollection<int, Entry> $entries */
    $entries = $query->get();

    return $entries;
}

/**
 * Every imported `knowledge_bases` entry id, keyed by slug.
 *
 * @return array<string, string>
 */
function kbSubtreeIdsBySlug(): array
{
    $bySlug = [];

    foreach (kbSubtreeEntries() as $entry) {
        $slug = $entry->slug();
        $id = $entry->id();

        if (is_string($slug) && is_string($id)) {
            $bySlug[$slug] = $id;
        }
    }

    return $bySlug;
}

/**
 * The `entry::<id>` reference form search expresses, which is what `searchables()`
 * yields and what `Result::getReference()` returns — never a bare id. Comparing a
 * bare id against either silently reports absent for a present entry (UAMS-Web/uams-statamic-archive#432).
 *
 * @param  list<string>  $ids
 * @return list<string>
 */
function kbSubtreeReferences(array $ids): array
{
    return array_map(static fn (string $id): string => 'entry::'.$id, $ids);
}

beforeEach(function (): void {
    // This base repoints Blueprint::directory() at an empty throwaway root so a
    // fixture blueprint cannot clobber resources/blueprints. That protection also
    // makes the production blueprints unreadable, and the resolver's bulk query
    // filters on `blueprint`, so copy the three knowledge_bases blueprints into the
    // isolated root. Copying in is safe for the same reason saving out is not.
    $blueprintSource = base_path('resources/blueprints/collections/knowledge_bases');
    $blueprintTarget = $this->fakeBlueprintDirectory.'/collections/knowledge_bases';

    File::ensureDirectoryExists($blueprintTarget);
    File::copyDirectory($blueprintSource, $blueprintTarget);

    // The isolated Stache root carries no collections, so stand up the structured
    // knowledge_bases collection the importer writes into.
    $kb = CollectionFacade::make('knowledge_bases');
    $kb->title('Knowledge Bases');
    $kb->structureContents(['root' => false]);
    $kb->save();

    kbSubtreeImport(base_path(KB_SUBTREE_FIXTURE));

    $this->kbIds = kbSubtreeIdsBySlug();
});

it('imports MinervaKB content spanning two topics under one knowledge-base root', function (): void {
    // Guard the fixture itself. Every assertion below is vacuous if the import
    // stopped producing the tree, and a resolver returning [] would satisfy the
    // exclusion assertions without filtering anything.
    expect($this->kbIds)->toHaveKeys([
        'kb',
        'sample-topic-1',
        'sample-topic-2',
        'sample-topic-3',
        'sample-article-1',
        'sample-article-3',
    ]);

    $blueprints = [];

    foreach (kbSubtreeEntries() as $entry) {
        // Narrowed rather than cast: an entry whose blueprint did not resolve is a
        // broken import, and `(string) null` would record it as an empty handle and
        // let the expectations below fail somewhere less informative.
        $slug = mustBeString($entry->slug(), 'the imported entry slug');

        /** @var Blueprint|null $blueprint */
        $blueprint = $entry->blueprint();

        $blueprints[$slug] = mustBeString(
            $blueprint?->handle(),
            sprintf('the %s blueprint handle', $slug)
        );
    }

    expect($blueprints['kb'])->toBe('knowledge_base')
        ->and($blueprints['sample-topic-1'])->toBe('topic')
        ->and($blueprints['sample-topic-2'])->toBe('topic')
        ->and($blueprints['sample-article-1'])->toBe('article')
        ->and($blueprints['sample-article-3'])->toBe('article');
});

it('returns imported articles from every topic when rooted at the knowledge base', function (): void {
    $articleIds = (new KnowledgeBaseSubtreeArticleIds)->forRoot($this->kbIds['kb']);

    expect($articleIds)->toHaveCount(2)
        ->and($articleIds)->toContain($this->kbIds['sample-article-1'])
        ->and($articleIds)->toContain($this->kbIds['sample-article-3']);
});

it('returns only the articles within a topic subtree, not a sibling topic', function (): void {
    $resolver = new KnowledgeBaseSubtreeArticleIds;

    $topicOne = $resolver->forRoot($this->kbIds['sample-topic-1']);
    $topicTwo = $resolver->forRoot($this->kbIds['sample-topic-2']);

    expect($topicOne)->toBe([$this->kbIds['sample-article-1']])
        ->and($topicTwo)->toBe([$this->kbIds['sample-article-3']]);

    // The exclusion is the assertion the single-topic fixture cannot make, and it is
    // what fails first if the scope stops filtering: an unfiltered resolver returns
    // the whole knowledge base from either topic.
    expect($topicOne)->not->toContain($this->kbIds['sample-article-3'])
        ->and($topicTwo)->not->toContain($this->kbIds['sample-article-1']);
});

it('compares as entry references, which is the form search actually yields', function (): void {
    $resolver = new KnowledgeBaseSubtreeArticleIds;

    $references = kbSubtreeReferences($resolver->forRoot($this->kbIds['sample-topic-1']));

    expect($references)->toBe(['entry::'.$this->kbIds['sample-article-1']])
        ->and($references)->not->toContain('entry::'.$this->kbIds['sample-article-3']);

    // A bare id is not a reference. Pinned so the shape that produced UAMS-Web/uams-statamic-archive#432's false
    // negative cannot be reintroduced by "simplifying" the comparison above.
    expect($references[0])->toStartWith('entry::')
        ->and($references[0])->not->toBe($this->kbIds['sample-article-1']);
});

it('resolves a topic with no published descendant article to an empty list', function (): void {
    $resolver = new KnowledgeBaseSubtreeArticleIds;

    // sample-topic-3 is imported and carries no article, which is the UAMS-Web/uams-statamic-archive#215/UAMS-Web/uams-statamic-archive#216
    // empty-entry suppression case: an empty topic must surface nothing rather than
    // a result that 302-redirects on click.
    expect($resolver->forRoot($this->kbIds['sample-topic-3']))->toBe([]);

    // And an id that is not in the tree at all resolves the same way, so the empty
    // result above is not merely the missing-root branch.
    expect($resolver->forRoot('not-an-entry-id'))->toBe([]);
});
