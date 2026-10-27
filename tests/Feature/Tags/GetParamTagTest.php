<?php

declare(strict_types=1);

use App\Tags\GetParam;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @covers \App\Tags\GetParam
 *
 * Mutation: 100% on App\Tags\GetParam (9 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Tags/GetParamTagTest.php
 */
it('returns an empty string when the name parameter is missing', function () {
    app()->instance(
        'request',
        Request::create('https://example.test/path', 'GET', [])
    );

    $tag = new GetParam;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->index())->toBe('');
});

it('returns the query value when the named parameter is present', function () {
    app()->instance(
        'request',
        Request::create(
            'https://example.test/path',
            'GET',
            [
                'foo' => 'bar',
            ]
        )
    );

    $tag = new GetParam;
    $tag->setContext([]);
    $tag->setParameters([
        'name' => 'foo',
    ]);

    expect($tag->index())->toBe('bar');
});
