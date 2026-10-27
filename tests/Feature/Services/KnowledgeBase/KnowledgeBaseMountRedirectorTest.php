<?php

declare(strict_types=1);

use App\Services\KnowledgeBase\KnowledgeBaseMountRedirector;
use Illuminate\Http\Request;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry;
use Statamic\Facades\Data;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Services\KnowledgeBase\KnowledgeBaseMountRedirector
 *
 * Mutation: 100% on App\Services\KnowledgeBase\KnowledgeBaseMountRedirector (51 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Services/KnowledgeBase/KnowledgeBaseMountRedirectorTest.php
 */

/**
 * Stub the current site handle so the redirector scopes its lookups.
 */
function knowledgeBaseMountRedirectorFakeCurrentSite(string $handle = 'default'): void
{
    // Build a minimal site double exposing only handle().
    $site = Mockery::mock();
    $site->shouldReceive('handle')->andReturn($handle);

    // Make Site::current() return the double.
    Site::shouldReceive('current')->andReturn($site);
}

/**
 * Build a knowledge base root double with a slug and mount reference.
 *
 * @return Entry&MockInterface
 */
function knowledgeBaseMountRedirectorFakeRoot(string $slug, string $mountId): Entry
{
    // A root entry double exposing slug() and the raw mount reference.
    $root = Mockery::mock(Entry::class);
    $root->shouldReceive('slug')->andReturn($slug);
    $root->shouldReceive('get')->with('mount_parent')->andReturn([$mountId]);

    return $root;
}

/**
 * Stub Entry::query() to return the given mounted root entries.
 *
 * @param  array<int, Entry>  $roots  The mounted root entries to return
 */
function knowledgeBaseMountRedirectorFakeMountedRoots(array $roots): void
{
    // A query builder double that ignores where() and returns the roots.
    $builder = Mockery::mock();
    $builder->shouldReceive('where')->andReturnSelf();
    $builder->shouldReceive('get')->andReturn(collect($roots));

    // Entry::query() yields the builder double.
    EntryFacade::shouldReceive('query')->andReturn($builder);
}

it('redirects an old url to the mounted url', function () {
    knowledgeBaseMountRedirectorFakeCurrentSite('default');

    // A root with slug `kb` mounted under page `p1`.
    knowledgeBaseMountRedirectorFakeMountedRoots([knowledgeBaseMountRedirectorFakeRoot('kb', 'p1')]);

    // The mount parent page resolves to /about.
    $mountPage = Mockery::mock(Entry::class);
    $mountPage->shouldReceive('uri')->andReturn('/about');
    EntryFacade::shouldReceive('find')->with('p1')->andReturn($mountPage);

    // The old URL no longer resolves; the mounted URL does.
    Data::shouldReceive('findByUri')->with('/kb', 'default')->andReturn(null);
    Data::shouldReceive('findByUri')->with('/about/kb', 'default')->andReturn(Mockery::mock(Entry::class));

    $target = (new KnowledgeBaseMountRedirector)->target(Request::create('http://localhost/kb', 'GET'));

    expect($target)->toBe('/about/kb');
});

it('maps descendant old urls', function () {
    knowledgeBaseMountRedirectorFakeCurrentSite('default');
    knowledgeBaseMountRedirectorFakeMountedRoots([knowledgeBaseMountRedirectorFakeRoot('kb', 'p1')]);

    $mountPage = Mockery::mock(Entry::class);
    $mountPage->shouldReceive('uri')->andReturn('/about');
    EntryFacade::shouldReceive('find')->with('p1')->andReturn($mountPage);

    // A topic under the old root path maps to the mounted topic path.
    Data::shouldReceive('findByUri')->with('/kb/topic', 'default')->andReturn(null);
    Data::shouldReceive('findByUri')->with('/about/kb/topic', 'default')->andReturn(Mockery::mock(Entry::class));

    $target = (new KnowledgeBaseMountRedirector)->target(Request::create('http://localhost/kb/topic', 'GET'));

    expect($target)->toBe('/about/kb/topic');
});

it('returns null when no mounted kb matches', function () {
    knowledgeBaseMountRedirectorFakeCurrentSite('default');
    knowledgeBaseMountRedirectorFakeMountedRoots([knowledgeBaseMountRedirectorFakeRoot('kb', 'p1')]);

    // A URL that does not start with the root slug never matches.
    $target = (new KnowledgeBaseMountRedirector)->target(Request::create('http://localhost/something/else', 'GET'));

    expect($target)->toBeNull();
});

it('returns null when the old url still resolves', function () {
    knowledgeBaseMountRedirectorFakeCurrentSite('default');
    knowledgeBaseMountRedirectorFakeMountedRoots([knowledgeBaseMountRedirectorFakeRoot('kb', 'p1')]);

    $mountPage = Mockery::mock(Entry::class);
    $mountPage->shouldReceive('uri')->andReturn('/about');
    EntryFacade::shouldReceive('find')->with('p1')->andReturn($mountPage);

    // The old URL still resolves to content, so it must not be redirected.
    Data::shouldReceive('findByUri')->with('/kb', 'default')->andReturn(Mockery::mock(Entry::class));

    $target = (new KnowledgeBaseMountRedirector)->target(Request::create('http://localhost/kb', 'GET'));

    expect($target)->toBeNull();
});

it('ignores unsafe request methods', function () {
    // No facades should be consulted for a non-safe method.
    $target = (new KnowledgeBaseMountRedirector)->target(Request::create('http://localhost/kb', 'POST'));

    expect($target)->toBeNull();
});
