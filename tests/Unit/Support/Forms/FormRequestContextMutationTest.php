<?php

declare(strict_types=1);

use App\Support\Forms\FormRequestContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteObject;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(FormRequestContext::class);

/**
 * Narrow mutate coverage for App\Support\Forms\FormRequestContext.
 *
 * @command  php artisan test --compact tests/Unit/Support/Forms/FormRequestContextMutationTest.php
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

afterEach(function (): void {
    $reflection = new ReflectionClass(FormRequestContext::class);
    $reflection->getProperty('active')->setValue(null, null);
    $reflection->getProperty('pending')->setValue(null, []);
});

it('captures ip, referrer, query, and a scalar site handle from the live request', function (): void {
    $request = Request::create(
        'https://forms.test/submit?foo=bar',
        'GET',
        [],
        [],
        [],
        [
            'REMOTE_ADDR' => '203.0.113.9',
            'HTTP_REFERER' => 'https://referrer.test/page',
        ],
    );
    app()->instance('request', $request);

    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->andReturn('health');
    Site::shouldReceive('findByUrl')->once()->andReturn($site);

    $context = FormRequestContext::capture();

    expect($context->ip)->toBe('203.0.113.9')
        ->and($context->referrer)->toBe('https://referrer.test/page')
        ->and($context->query)->toBe(['foo' => 'bar'])
        ->and($context->siteHandle)->toBe('health');
});

it('captures empty defaults when ip and referrer are absent', function (): void {
    $request = Mockery::mock(Request::class)->shouldIgnoreMissing();
    $request->shouldReceive('ip')->andReturn(null);
    // Return the caller-supplied default so EmptyStringToNotEmpty on the
    // `header('Referer', '')` literal surfaces as a non-empty referrer.
    $request->shouldReceive('header')
        ->withArgs(fn (mixed $key, mixed $default = null): bool => $key === 'Referer')
        ->andReturnUsing(fn (mixed $key, mixed $default = null): mixed => $default);
    $request->shouldReceive('query')->andReturn([]);
    app()->instance('request', $request);

    // The mocked request carries no headers bag, so answer the referrer lookup
    // the submission site is named from
    URL::shouldReceive('previous')->andReturn('');

    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->andReturn('default');
    Site::shouldReceive('findByUrl')->once()->andReturn($site);

    $context = FormRequestContext::capture();

    expect($context->ip)->toBe('')
        ->and($context->referrer)->toBe('')
        ->and($context->siteHandle)->toBe('default');
});

it('string-casts a scalar non-string site handle', function (): void {
    $request = Request::create('https://forms.test/submit', 'GET');
    app()->instance('request', $request);

    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->andReturn(42);
    Site::shouldReceive('findByUrl')->once()->andReturn($site);

    expect(FormRequestContext::capture()->siteHandle)->toBe('42');
});

it('captures an empty site handle when the submission site handle is not scalar', function (): void {
    $request = Request::create('https://forms.test/submit', 'GET');
    app()->instance('request', $request);

    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->andReturn(['not' => 'scalar']);
    Site::shouldReceive('findByUrl')->once()->andReturn($site);

    expect(FormRequestContext::capture()->siteHandle)->toBe('');
});

it('stashes and pulls a pending context once', function (): void {
    $context = new FormRequestContext('1.1.1.1', 'https://r.test', ['q' => 1], 'default');

    FormRequestContext::stash('a', $context);

    expect(FormRequestContext::pull('a'))->toBe($context)
        ->and(FormRequestContext::pull('a'))->toBeNull()
        ->and(FormRequestContext::pull('missing'))->toBeNull();
});

it('peeks at a pending context without removing it', function (): void {
    $context = new FormRequestContext('1.1.1.2', '', [], 'default');

    FormRequestContext::stash('b', $context);

    // Every read finds the context, so each notification job of a submission gets it
    expect(FormRequestContext::peek('b'))->toBe($context)
        ->and(FormRequestContext::peek('b'))->toBe($context)
        ->and(FormRequestContext::peek('missing'))->toBeNull();

    FormRequestContext::pull('b');
});

it('keeps only the latest submission stashed', function (): void {
    $first = new FormRequestContext('1.1.1.3', '', [], 'default');
    $second = new FormRequestContext('1.1.1.4', '', [], 'default');

    FormRequestContext::stash('first', $first);
    FormRequestContext::stash('second', $second);

    // The earlier entry is dropped, so the map cannot grow in a long-running process
    expect(FormRequestContext::peek('first'))->toBeNull()
        ->and(FormRequestContext::peek('second'))->toBe($second);

    FormRequestContext::pull('second');
});

it('round-trips via toArray and fromArray with malformed payload defaults', function (): void {
    $context = new FormRequestContext('9.9.9.9', 'https://ref.test', ['x' => 'y'], 'news');

    expect($context->toArray())->toBe([
        'ip' => '9.9.9.9',
        'referrer' => 'https://ref.test',
        'query' => ['x' => 'y'],
        'site' => 'news',
    ]);

    $restored = FormRequestContext::fromArray($context->toArray());
    expect($restored->ip)->toBe('9.9.9.9')
        ->and($restored->referrer)->toBe('https://ref.test')
        ->and($restored->query)->toBe(['x' => 'y'])
        ->and($restored->siteHandle)->toBe('news');

    // Pin fromArray's fallbacks for a malformed stored payload
    $defaults = FormRequestContext::fromArray([
        'ip' => 123,
        'referrer' => null,
        'query' => 'nope',
        'site' => ['x'],
    ]);
    expect($defaults->ip)->toBe('')
        ->and($defaults->referrer)->toBe('')
        ->and($defaults->query)->toBe([])
        ->and($defaults->siteHandle)->toBe('');
});

it('activates during a callback and restores previous active on success and throw', function (): void {
    $outer = new FormRequestContext('o', 'or', [], 'outer');
    $inner = new FormRequestContext('i', 'ir', [], 'inner');

    expect(FormRequestContext::active())->toBeNull();

    $outer->during(function () use ($outer, $inner): void {
        expect(FormRequestContext::active())->toBe($outer);

        $inner->during(function () use ($inner): void {
            expect(FormRequestContext::active())->toBe($inner);
        });

        expect(FormRequestContext::active())->toBe($outer);

        try {
            $inner->during(function (): void {
                throw new RuntimeException('render failed');
            });
        } catch (RuntimeException) {
            // expected
        }

        expect(FormRequestContext::active())->toBe($outer);
    });

    expect(FormRequestContext::active())->toBeNull();
});
