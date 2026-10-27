<?php

declare(strict_types=1);

use App\Listeners\RedirectSuppressedDependents;
use App\Services\PublishDependencies\PublishDependencyResolver;
use App\Services\PublishDependencies\PublishDependencyResult;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Events\ResponseCreated;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(RedirectSuppressedDependents::class);

/**
 * Narrow mutate coverage for App\Listeners\RedirectSuppressedDependents.
 *
 * Mirrors the Feature suite with a mutate-timeout pad.
 *
 * @command  php artisan test --compact tests/Unit/Listeners/RedirectSuppressedDependentsMutationTest.php
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
 * A listener bound to a resolver returning the given result for any entry.
 */
function redirectSuppressedListener(
    ?PublishDependencyResult $result,
    bool $expectResolve = true,
): RedirectSuppressedDependents {
    $resolver = Mockery::mock(PublishDependencyResolver::class);

    if ($expectResolve) {
        $resolver->shouldReceive('resolve')->andReturn($result);
    } else {
        $resolver->shouldNotReceive('resolve');
    }

    return new RedirectSuppressedDependents($resolver);
}

it('rewrites a successful suppressed-dependent response to a 302 to the resolved target', function (): void {
    $entry = Mockery::mock(Entry::class);
    $response = new Response('page body', 200);

    redirectSuppressedListener(new PublishDependencyResult('/clinical-resources', ['Dr. Alice'], true))
        ->handle(new ResponseCreated($response, $entry));

    expect($response->getStatusCode())->toBe(Response::HTTP_FOUND)
        ->and($response->headers->get('Location'))->toBe('/clinical-resources')
        ->and($response->getContent())->toBe('');
});

it('leaves a response whose data is not an entry untouched', function (): void {
    $response = new Response('page body', 200);

    redirectSuppressedListener(null, expectResolve: false)
        ->handle(new ResponseCreated($response, 'not-an-entry'));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('page body');
});

it('leaves an unsuccessful response untouched and never resolves', function (): void {
    $entry = Mockery::mock(Entry::class);
    $response = new Response('not found', 404);

    redirectSuppressedListener(null, expectResolve: false)
        ->handle(new ResponseCreated($response, $entry));

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getContent())->toBe('not found');
});

it('leaves a non-suppressed entry response untouched', function (): void {
    $entry = Mockery::mock(Entry::class);
    $response = new Response('page body', 200);

    redirectSuppressedListener(null)
        ->handle(new ResponseCreated($response, $entry));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('page body');
});

it('leaves a Control Panel live-preview render untouched', function (): void {
    $request = Mockery::mock(Request::class)->makePartial();
    $request->shouldReceive('isLivePreview')->andReturn(true);
    app()->instance('request', $request);

    $entry = Mockery::mock(Entry::class);
    $response = new Response('preview body', 200);

    redirectSuppressedListener(null, expectResolve: false)
        ->handle(new ResponseCreated($response, $entry));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('preview body');
});
