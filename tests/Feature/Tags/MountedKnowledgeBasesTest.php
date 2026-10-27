<?php

declare(strict_types=1);

use App\Entries\KnowledgeBaseEntry;
use App\Services\KnowledgeBase\KnowledgeBaseComputedFieldsService;
use App\Tags\MountedKnowledgeBases;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteObject;
use Tests\TestCase;

covers(MountedKnowledgeBases::class);

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * The tag returns the `knowledge_base` roots mounted under a page, suppressing
 * any *empty* knowledge base (no published descendant article) from the auto-
 * generated navigation (UAMS-Web/uams-statamic-archive#1012). Emptiness is delegated to
 * KnowledgeBaseComputedFieldsService (mocked here; its own behavior is pinned by
 * KnowledgeBaseDescendantArticlesQueryTest).
 *
 * Mutation: tracked under App\Tags\MountedKnowledgeBases (--covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Tags/MountedKnowledgeBasesTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

/**
 * Bind a mock KnowledgeBaseComputedFieldsService so the tag's emptiness check is
 * controllable per root.
 *
 * @return KnowledgeBaseComputedFieldsService&MockInterface
 */
function mountedKnowledgeBasesFakeService(): KnowledgeBaseComputedFieldsService
{
    /** @var KnowledgeBaseComputedFieldsService&MockInterface $service */
    $service = Mockery::mock(KnowledgeBaseComputedFieldsService::class);
    app()->instance(KnowledgeBaseComputedFieldsService::class, $service);

    return $service;
}

/**
 * Build the tag with the given parameters.
 *
 * @param  array<string, mixed>  $params  The tag parameters
 */
function mountedKnowledgeBasesTag(array $params): MountedKnowledgeBases
{
    // Resolve the tag, give it an empty context, then attach its parameters.
    $tag = app(MountedKnowledgeBases::class);
    $tag->setContext([]);
    $tag->setParameters($params);

    return $tag;
}

/**
 * Stub Entry::query() and assert the collection / blueprint / site filters.
 *
 * @param  array<int, Entry>  $roots  The root entries to return
 * @param  string  $expectedSite  The site handle the tag must query
 */
function mountedKnowledgeBasesFakeRoots(array $roots, string $expectedSite): void
{
    // Capture where() pairs so collection / blueprint / site mutants die.
    $wheres = [];
    $builder = Mockery::mock();
    $builder->shouldReceive('where')
        ->andReturnUsing(function (string $column, mixed $value) use (&$wheres, $builder) {
            $wheres[$column] = $value;

            return $builder;
        });
    $builder->shouldReceive('get')->andReturnUsing(function () use (&$wheres, $roots, $expectedSite) {
        expect($wheres)->toMatchArray([
            'collection' => 'knowledge_bases',
            'blueprint' => KnowledgeBaseEntry::ROOT_BLUEPRINT,
            'site' => $expectedSite,
        ]);

        return collect($roots);
    });

    // Entry::query() yields the builder double.
    EntryFacade::shouldReceive('query')->andReturn($builder);
}

/**
 * Build a root entry double with the given mount reference and emptiness flag.
 *
 * @param  KnowledgeBaseComputedFieldsService&MockInterface  $service  The mocked emptiness resolver
 * @param  string|array<int, string>  $mount  The raw mount_parent value
 * @param  bool  $hasArticles  Whether the knowledge base has a published descendant article
 */
function mountedKnowledgeBasesFakeRoot(
    string|array $mount,
    KnowledgeBaseComputedFieldsService $service,
    bool $hasArticles = true,
): Entry {
    // A root entry double exposing only its raw mount reference.
    $root = Mockery::mock(Entry::class);
    $root->shouldReceive('get')->with(KnowledgeBaseEntry::MOUNT_FIELD)->andReturn($mount);

    // The tag asks the service whether this root is non-empty (only when it
    // passes the mount filter; allowing zero calls keeps mount-miss roots valid).
    $service->shouldReceive('resolveHasDescendantArticles')->with($root)->andReturn($hasArticles);

    return $root;
}

it('returns only non-empty roots mounted under the given page', function () {
    $service = mountedKnowledgeBasesFakeService();

    // Two roots: one mounted under p1, one under p2.
    $underP1 = mountedKnowledgeBasesFakeRoot(
        ['p1'],
        $service,
    );
    $underP2 = mountedKnowledgeBasesFakeRoot(
        ['p2'],
        $service,
    );
    mountedKnowledgeBasesFakeRoots(
        [
            $underP1,
            $underP2,
        ],
        'default',
    );

    // Query for roots mounted under p1.
    $result = mountedKnowledgeBasesTag([
        'under' => 'p1',
        'in' => 'default',
    ])->index();

    // Only the non-empty root mounted under p1 is returned.
    expect($result)->toHaveCount(1);
    expect($result[0])->toBe($underP1);
});

it('suppresses an empty knowledge base from the navigation', function () {
    $service = mountedKnowledgeBasesFakeService();

    // Two roots mounted under p1: one with published articles, one empty.
    $nonEmpty = mountedKnowledgeBasesFakeRoot(
        ['p1'],
        $service,
        hasArticles: true,
    );
    $empty = mountedKnowledgeBasesFakeRoot(
        ['p1'],
        $service,
        hasArticles: false,
    );
    mountedKnowledgeBasesFakeRoots(
        [
            $nonEmpty,
            $empty,
        ],
        'default',
    );

    // Query for roots mounted under p1.
    $result = mountedKnowledgeBasesTag([
        'under' => 'p1',
        'in' => 'default',
    ])->index();

    // The empty knowledge base is dropped; only the non-empty one remains.
    expect($result)->toHaveCount(1);
    expect($result[0])->toBe($nonEmpty);
});

it('returns empty when no root is mounted under the page', function () {
    $service = mountedKnowledgeBasesFakeService();

    // A single root mounted elsewhere.
    mountedKnowledgeBasesFakeRoots(
        [mountedKnowledgeBasesFakeRoot(
            ['p2'],
            $service,
        )],
        'default',
    );

    // Query for roots mounted under p1.
    $result = mountedKnowledgeBasesTag([
        'under' => 'p1',
        'in' => 'default',
    ])->index();

    // Nothing matches the mount filter.
    expect($result)->toBe([]);
});

it('returns empty without an under parameter', function () {
    // No query should run without a parent page id.
    EntryFacade::shouldReceive('query')->never();

    $result = mountedKnowledgeBasesTag(['in' => 'default'])->index();

    // The tag yields nothing.
    expect($result)->toBe([]);
});

it('defaults the site filter to Site::current when in is omitted', function (): void {
    $service = mountedKnowledgeBasesFakeService();
    $root = mountedKnowledgeBasesFakeRoot(
        ['p1'],
        $service,
    );
    mountedKnowledgeBasesFakeRoots(
        [$root],
        'news',
    );

    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->once()->andReturn('news');
    Site::shouldReceive('current')->once()->andReturn($site);

    $result = mountedKnowledgeBasesTag(['under' => 'p1'])->index();

    expect($result)->toHaveCount(1)
        ->and($result[0])->toBe($root);
});

it('accepts a scalar mount_parent by casting it to an array', function (): void {
    $service = mountedKnowledgeBasesFakeService();
    $root = mountedKnowledgeBasesFakeRoot(
        'p1',
        $service,
    );
    mountedKnowledgeBasesFakeRoots(
        [$root],
        'default',
    );

    $result = mountedKnowledgeBasesTag([
        'under' => 'p1',
        'in' => 'default',
    ])->index();

    expect($result)->toHaveCount(1)
        ->and($result[0])->toBe($root);
});
