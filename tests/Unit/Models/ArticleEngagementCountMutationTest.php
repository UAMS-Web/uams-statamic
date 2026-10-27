<?php

declare(strict_types=1);

use App\Models\ArticleEngagementCount;
use Tests\TestCase;

uses(TestCase::class);

covers(ArticleEngagementCount::class);

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
 * Narrow mutate coverage for App\Models\ArticleEngagementCount casts / fillable.
 *
 * Avoids RefreshDatabase so mutant children stay under Pest's timeout.
 *
 * @command  php artisan test --compact tests/Unit/Models/ArticleEngagementCountMutationTest.php
 */
it('casts counter attributes to integers on read', function (): void {
    // AlwaysReturnEmptyArray / RemoveArrayItem on casts(): string attrs must become ints.
    $row = new ArticleEngagementCount;
    $row->setRawAttributes([
        'entry_id' => 'article-1',
        'views' => '3',
        'likes' => '2',
        'dislikes' => '1',
    ]);

    expect($row->views)->toBe(3)
        ->and($row->likes)->toBe(2)
        ->and($row->dislikes)->toBe(1)
        ->and($row->getCasts())->toMatchArray([
            'views' => 'integer',
            'likes' => 'integer',
            'dislikes' => 'integer',
        ]);
});

it('mass-assigns only the fillable engagement columns', function (): void {
    $row = new ArticleEngagementCount;
    $row->fill([
        'entry_id' => 'article-2',
        'views' => 9,
        'likes' => 4,
        'dislikes' => 1,
        'not_a_column' => 'ignored',
    ]);

    expect($row->getAttributes())->toHaveKeys(['entry_id', 'views', 'likes', 'dislikes'])
        ->and($row->getAttributes())->not->toHaveKey('not_a_column');
});
