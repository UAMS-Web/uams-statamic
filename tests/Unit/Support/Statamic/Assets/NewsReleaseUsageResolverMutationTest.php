<?php

declare(strict_types=1);

use App\Support\Statamic\Assets\NewsReleaseUsageResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry as ConcreteEntry;
use Statamic\Facades\Entry;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(NewsReleaseUsageResolver::class);

/**
 * Narrow mutate coverage for NewsReleaseUsageResolver.
 *
 * @command  php artisan test --compact tests/Unit/Support/Statamic/Assets/NewsReleaseUsageResolverMutationTest.php
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

function newsReleaseUsageLinkedPost(bool $published): ConcreteEntry
{
    $entry = Mockery::mock(ConcreteEntry::class);
    $entry->shouldReceive('published')->andReturn($published);

    return $entry;
}

it('resolves a published linked post and reports in use', function (): void {
    $post = newsReleaseUsageLinkedPost(published: true);
    Entry::shouldReceive('find')->with('post-1')->andReturn($post);

    expect(NewsReleaseUsageResolver::resolve('post-1'))->toBe($post)
        ->and(NewsReleaseUsageResolver::isInUse('post-1'))->toBeTrue();
});

it('resolves an unpublished linked post but reports not in use', function (): void {
    $post = newsReleaseUsageLinkedPost(published: false);
    Entry::shouldReceive('find')->with('post-2')->andReturn($post);

    expect(NewsReleaseUsageResolver::resolve('post-2'))->toBe($post)
        ->and(NewsReleaseUsageResolver::isInUse('post-2'))->toBeFalse();
});

it('returns null when the linked post is missing', function (): void {
    Entry::shouldReceive('find')->with('gone')->andReturnNull();

    expect(NewsReleaseUsageResolver::resolve('gone'))->toBeNull()
        ->and(NewsReleaseUsageResolver::isInUse('gone'))->toBeFalse();
});

it('short-circuits without a lookup when the link cannot normalize to an id', function (): void {
    Entry::shouldReceive('find')->never();

    expect(NewsReleaseUsageResolver::resolve(null))->toBeNull()
        ->and(NewsReleaseUsageResolver::resolve(''))->toBeNull()
        ->and(NewsReleaseUsageResolver::resolve([]))->toBeNull()
        ->and(NewsReleaseUsageResolver::resolve([null]))->toBeNull()
        ->and(NewsReleaseUsageResolver::resolve([123]))->toBeNull()
        ->and(NewsReleaseUsageResolver::isInUse(null))->toBeFalse();
});

it('normalizes a single-element array id from the WordPress import', function (): void {
    $post = newsReleaseUsageLinkedPost(published: true);
    Entry::shouldReceive('find')->with('post-3')->andReturn($post);

    expect(NewsReleaseUsageResolver::resolve(['post-3']))->toBe($post)
        ->and(NewsReleaseUsageResolver::isInUse(['post-3']))->toBeTrue();
});
