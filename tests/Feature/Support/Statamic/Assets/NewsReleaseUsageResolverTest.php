<?php

declare(strict_types=1);

use App\Support\Statamic\Assets\NewsReleaseUsageResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry as ConcreteEntry;
use Statamic\Facades\Entry;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/*
|--------------------------------------------------------------------------
| NewsReleaseUsageResolver (UAMS-Web/uams-statamic-archive#1376)
|--------------------------------------------------------------------------
|
| The news-release asset usage indicator turns the system-managed
| `news_release_entry` link into two signals: the linked post (if it still
| exists) and whether it is live (exists and published). These mock the Entry
| facade to cover the acceptance cases — linked-and-published, unpublished,
| missing, and unset — plus the max_items:1 array storage shape.
|
| @covers \App\Support\Statamic\Assets\NewsReleaseUsageResolver
|
| @command php artisan test --compact tests/Feature/Support/Statamic/Assets/NewsReleaseUsageResolverTest.php
*/

function newsReleaseLinkedPost(bool $published): ConcreteEntry
{
    // The Entry repository's find() return type is the concrete Entry, so the stub
    // must be the concrete class, not the contract.
    $entry = Mockery::mock(ConcreteEntry::class);
    $entry->shouldReceive('published')->andReturn($published);

    return $entry;
}

it('resolves the linked post and reports in use when it exists and is published', function (): void {
    $post = newsReleaseLinkedPost(published: true);
    Entry::shouldReceive('find')->with('post-1')->andReturn($post);

    expect(NewsReleaseUsageResolver::resolve('post-1'))->toBe($post)
        ->and(NewsReleaseUsageResolver::isInUse('post-1'))->toBeTrue();
});

it('resolves the linked post but reports not in use when the post is unpublished', function (): void {
    $post = newsReleaseLinkedPost(published: false);
    Entry::shouldReceive('find')->with('post-2')->andReturn($post);

    expect(NewsReleaseUsageResolver::resolve('post-2'))->toBe($post)
        ->and(NewsReleaseUsageResolver::isInUse('post-2'))->toBeFalse();
});

it('returns null and not in use when the linked post is missing', function (): void {
    Entry::shouldReceive('find')->with('gone')->andReturnNull();

    expect(NewsReleaseUsageResolver::resolve('gone'))->toBeNull()
        ->and(NewsReleaseUsageResolver::isInUse('gone'))->toBeFalse();
});

it('short-circuits without a lookup when the link is unset', function (): void {
    // An unset link must never hit the Stache.
    Entry::shouldReceive('find')->never();

    expect(NewsReleaseUsageResolver::resolve(null))->toBeNull()
        ->and(NewsReleaseUsageResolver::resolve(''))->toBeNull()
        ->and(NewsReleaseUsageResolver::resolve([]))->toBeNull()
        ->and(NewsReleaseUsageResolver::isInUse(null))->toBeFalse();
});

it('normalizes a single-element array id from the WordPress import', function (): void {
    $post = newsReleaseLinkedPost(published: true);
    Entry::shouldReceive('find')->with('post-3')->andReturn($post);

    expect(NewsReleaseUsageResolver::resolve(['post-3']))->toBe($post)
        ->and(NewsReleaseUsageResolver::isInUse(['post-3']))->toBeTrue();
});
