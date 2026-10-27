<?php

declare(strict_types=1);

use App\Scopes\PostsInPostTagBySlug;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Scopes\PostsInPostTagBySlug
 *
 * The constraints the slug lookup puts on its own tag query, with `Entry::query()` mocked.
 * tests/Feature/Scopes/PostsInPostTagScopesTest.php proves the lookup against real entries,
 * but a fixture set cannot show that the collection constraint is there: a slug shared with
 * an entry in another collection resolves to whichever entry the query returns first. This
 * file is separate because that one's fixture teardown queries entries through the same
 * facade.
 *
 * @command  php artisan test --compact tests/Feature/Scopes/PostsInPostTagBySlugQueryTest.php
 */
it('looks the slug up among post_tags on the current site only', function (): void {
    $tag = Mockery::mock(StatamicEntry::class);
    $tag->shouldReceive('id')->andReturn('tag-id-from-lookup');

    /** @var Statamic\Sites\Site $site */
    $site = Site::current();

    $tagQuery = Mockery::mock(EntryQueryBuilder::class);
    $tagQuery->shouldReceive('where')->once()->with('collection', 'post_tags')->andReturnSelf();
    $tagQuery->shouldReceive('where')->once()->with('site', $site->handle())->andReturnSelf();
    $tagQuery->shouldReceive('where')->once()->with('slug', 'some-tag')->andReturnSelf();
    $tagQuery->shouldReceive('first')->once()->andReturn($tag);
    Entry::shouldReceive('query')->once()->andReturn($tagQuery);

    $posts = Mockery::mock(EntryQueryBuilder::class);
    $posts->shouldReceive('whereJsonContains')->once()->with('post_tags', 'tag-id-from-lookup')->andReturnSelf();

    (new PostsInPostTagBySlug)->apply($posts, ['tag_slug' => 'some-tag']);
});
