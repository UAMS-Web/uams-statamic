<?php

declare(strict_types=1);

use App\Support\BardRelNoopenerBackfill;
use Tests\TestCase;

uses(TestCase::class);

covers(BardRelNoopenerBackfill::class);

/**
 * Narrow mutate coverage for App\Support\BardRelNoopenerBackfill.
 *
 * @command  php artisan test --compact tests/Unit/Support/BardRelNoopenerBackfillMutationTest.php
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

it('returns scalars unchanged with a zero fix count', function (): void {
    expect(BardRelNoopenerBackfill::apply('plain'))->toBe(['plain', 0])
        ->and(BardRelNoopenerBackfill::apply(null))->toBe([null, 0]);
});

it('backfills _blank link marks with null or empty rel and nests', function (): void {
    $data = [
        'outer' => [
            [
                'type' => 'link',
                'attrs' => [
                    'href' => 'https://a.test',
                    'rel' => null,
                    'target' => '_blank',
                ],
            ],
            [
                'type' => 'link',
                'attrs' => [
                    'href' => 'https://b.test',
                    'rel' => '',
                    'target' => '_blank',
                ],
            ],
        ],
    ];

    [$new, $count] = BardRelNoopenerBackfill::apply($data);

    expect($count)->toBe(2)
        ->and(data_get($new, 'outer.0.attrs.rel'))->toBe('noopener noreferrer')
        ->and(data_get($new, 'outer.1.attrs.rel'))->toBe('noopener noreferrer');
});

it('skips non-link nodes, non-array attrs, same-tab, and author-set rel', function (): void {
    $data = [
        ['type' => 'bold', 'attrs' => ['target' => '_blank', 'rel' => null]],
        ['type' => 'link', 'attrs' => 'not-an-array'],
        ['type' => 'link', 'attrs' => ['href' => '/a', 'rel' => null, 'target' => null]],
        ['type' => 'link', 'attrs' => ['href' => 'https://b.test', 'rel' => 'nofollow', 'target' => '_blank']],
        ['type' => 'link'], // no attrs key
    ];

    [$new, $count] = BardRelNoopenerBackfill::apply($data);

    expect($count)->toBe(0)
        ->and($new)->toBe($data);
});
