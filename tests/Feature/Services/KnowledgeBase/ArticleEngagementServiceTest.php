<?php

declare(strict_types=1);

use App\Models\ArticleEngagementCount;
use App\Services\KnowledgeBase\ArticleEngagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * The sideband engagement store (UAMS-Web/uams-statamic-archive#286, UAMS-Web/uams-statamic-archive#287): atomic view / reaction counters keyed
 * by article entry id, with session-relative reaction transitions resolved here.
 *
 * @covers \App\Services\KnowledgeBase\ArticleEngagementService
 *
 * @command php artisan test --compact tests/Feature/Services/KnowledgeBase/ArticleEngagementServiceTest.php
 */
beforeEach(function (): void {
    $this->service = new ArticleEngagementService;
});

it('reads zeros for an article with no stored row', function (): void {
    /** @var ArticleEngagementService $service */
    $service = $this->service;

    expect($service->counts('unknown'))
        ->toBe(['views' => 0, 'likes' => 0, 'dislikes' => 0, 'net' => 0]);
});

it('atomically increments views and accumulates across calls', function (): void {
    /** @var ArticleEngagementService $service */
    $service = $this->service;

    expect($service->incrementViews('a')['views'])->toBe(1);
    expect($service->incrementViews('a')['views'])->toBe(2);
    expect($service->incrementViews('a')['views'])->toBe(3);

    // A single row is kept for the id
    expect(ArticleEngagementCount::where('entry_id', 'a')->count())->toBe(1);
});

it('casts a fresh like from no previous reaction', function (): void {
    /** @var ArticleEngagementService $service */
    $service = $this->service;

    $result = $service->applyReaction('a', null, 'like');

    expect($result['reaction'])->toBe('like')
        ->and($result['counts']['likes'])->toBe(1)
        ->and($result['counts']['dislikes'])->toBe(0)
        ->and($result['counts']['net'])->toBe(1);
});

it('toggles an identical reaction off', function (): void {
    /** @var ArticleEngagementService $service */
    $service = $this->service;

    $service->applyReaction('a', null, 'like');

    $result = $service->applyReaction('a', 'like', 'like');

    expect($result['reaction'])->toBeNull()
        ->and($result['counts']['likes'])->toBe(0);
});

it('switches sides, decrementing the old and incrementing the new', function (): void {
    /** @var ArticleEngagementService $service */
    $service = $this->service;

    $service->applyReaction('a', null, 'like');

    $result = $service->applyReaction('a', 'like', 'dislike');

    expect($result['reaction'])->toBe('dislike')
        ->and($result['counts']['likes'])->toBe(0)
        ->and($result['counts']['dislikes'])->toBe(1)
        ->and($result['counts']['net'])->toBe(-1);
});

it('floors a decrement at zero so an unsigned column never wraps below zero', function (): void {
    // The session claims a previous like the store never recorded (e.g. a reset
    // store): toggling it off must not drive likes negative.
    /** @var ArticleEngagementService $service */
    $service = $this->service;

    $result = $service->applyReaction('a', 'like', 'like');

    expect($result['counts']['likes'])->toBe(0);
    expect(ArticleEngagementCount::where('entry_id', 'a')->value('likes'))->toBe(0);
});

it('ignores an unrecognized requested reaction, preserving the previous', function (): void {
    /** @var ArticleEngagementService $service */
    $service = $this->service;

    $service->applyReaction('a', null, 'like');

    $result = $service->applyReaction('a', 'like', 'bogus');

    expect($result['reaction'])->toBe('like')
        ->and($result['counts']['likes'])->toBe(1);
});

it('recognizes only like and dislike as reactions', function (?string $value, bool $expected): void {
    /** @var ArticleEngagementService $service */
    $service = $this->service;

    expect($service->isReaction($value))->toBe($expected);
})->with([
    'like is a reaction' => ['like', true],
    'dislike is a reaction' => ['dislike', true],
    'love is not a reaction' => ['love', false],
    'null is not a reaction' => [null, false],
]);

it('keeps views and reactions independent on the same row', function (): void {
    /** @var ArticleEngagementService $service */
    $service = $this->service;

    $service->incrementViews('a');
    $service->applyReaction('a', null, 'like');

    $counts = $service->counts('a');

    expect($counts)->toBe(['views' => 1, 'likes' => 1, 'dislikes' => 0, 'net' => 1]);
});
