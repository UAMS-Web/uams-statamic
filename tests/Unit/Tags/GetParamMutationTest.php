<?php

declare(strict_types=1);

use App\Support\Forms\FormRequestContext;
use App\Tags\GetParam;
use Illuminate\Http\Request;
use Statamic\Tags\Parameters;
use Tests\TestCase;

uses(TestCase::class);

covers(GetParam::class);

/**
 * Narrow mutate coverage for App\Tags\GetParam.
 *
 * @command  php artisan test --compact tests/Unit/Tags/GetParamMutationTest.php
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
 * Build a GetParam tag with the given parameters and optional query string.
 *
 * A query key may be numeric, which PHP stores as an int.
 *
 * @param  array<string, mixed>  $parameters
 * @param  array<array-key, mixed>  $query
 */
function getParamTag(
    array $parameters = [],
    array $query = [],
): GetParam {
    app()->instance(
        'request',
        Request::create(
            'https://example.test/path',
            'GET',
            $query,
        ),
    );

    $tag = new GetParam;
    $tag->setContext([]);
    $tag->setParameters($parameters);

    return $tag;
}

it('returns an empty string when the name parameter is missing or empty', function (): void {
    expect(getParamTag([])->index())->toBe('')
        ->and(getParamTag(['name' => ''])->index())->toBe('');
});

it('does not fall through an empty() name into a zero-key query lookup', function (): void {
    // empty(0) is true; RemoveEarlyReturn would cast 0 to "0" and leak this value.
    expect(getParamTag(['name' => 0], ['0' => 'zero'])->index())->toBe('');
});

it('returns an empty string when the name parameter is not scalar', function (): void {
    // EmptyStringToNotEmpty replaces the non-scalar arm with this sentinel.
    $tag = getParamTag(
        [],
        ['PEST Mutator was here!' => 'bait'],
    );
    $tag->params = new Parameters([
        'name' => ['nested'],
    ]);

    expect($tag->index())->toBe('');
});

it('string-casts a scalar name before the query lookup', function (): void {
    // RemoveStringCast leaves a float key that PHP coerces to int 1.
    $tag = getParamTag(
        [],
        [
            '1.5' => 'half',
            '1' => 'wrong',
        ],
    );
    $tag->params = new Parameters([
        'name' => 1.5,
    ]);

    expect($tag->index())->toBe('half');
});

it('returns the live query value when the named parameter is present', function (): void {
    expect(getParamTag(['name' => 'foo'], ['foo' => 'bar'])->index())->toBe('bar');
});

it('returns an empty string when the live query lacks the named parameter', function (): void {
    expect(getParamTag(['name' => 'missing'])->index())->toBe('');
});

it('prefers a captured FormRequestContext string query value', function (): void {
    $context = new FormRequestContext(
        ip: '203.0.113.10',
        referrer: 'https://referrer.test/',
        query: ['utm_source' => 'newsletter'],
        siteHandle: 'default',
    );

    $value = $context->during(
        fn (): string|array => getParamTag(
            ['name' => 'utm_source'],
            ['utm_source' => 'live-should-lose'],
        )->index(),
    );

    expect($value)->toBe('newsletter');
});

it('returns a captured FormRequestContext array query value', function (): void {
    $context = new FormRequestContext(
        ip: '203.0.113.10',
        referrer: '',
        query: ['tags' => ['a', 'b']],
        siteHandle: 'default',
    );

    $value = $context->during(
        fn (): string|array => getParamTag(['name' => 'tags'])->index(),
    );

    expect($value)->toBe(['a', 'b']);
});

it('returns an empty string when captured query lacks or cannot yield the parameter', function (): void {
    $context = new FormRequestContext(
        ip: '203.0.113.10',
        referrer: '',
        query: [
            'nullish' => null,
            'numeric' => 42,
        ],
        siteHandle: 'default',
    );

    $missing = $context->during(
        fn (): string|array => getParamTag(['name' => 'absent'])->index(),
    );
    $nullish = $context->during(
        fn (): string|array => getParamTag(['name' => 'nullish'])->index(),
    );
    $numeric = $context->during(
        fn (): string|array => getParamTag(['name' => 'numeric'])->index(),
    );

    expect($missing)->toBe('')
        ->and($nullish)->toBe('')
        ->and($numeric)->toBe('');
});
