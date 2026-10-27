<?php

declare(strict_types=1);

use App\Listeners\RedirectOrphanedDescendants;
use App\Services\OrphanedAncestors\OrphanedAncestorResolver;
use App\Services\OrphanedAncestors\OrphanedAncestorResult;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Events\ResponseCreated;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Listeners\RedirectOrphanedDescendants
 *
 * UAMS-Web/uams-statamic-archive#1429: the universal front-end redirect. On ResponseCreated, a successful page
 * response for an entry orphaned by an unpublished ancestor is rewritten to a 302
 * toward the resolved live target. Everything else is left untouched. Mocked
 * resolver + real Response — no content/ submodule required.
 *
 * @command  php artisan test --compact tests/Feature/Listeners/RedirectOrphanedDescendantsTest.php
 */

/** A listener bound to a resolver returning the given result for any entry. */
function redirectListener(?OrphanedAncestorResult $result, bool $expectResolve = true): RedirectOrphanedDescendants
{
    $resolver = Mockery::mock(OrphanedAncestorResolver::class);

    if ($expectResolve) {
        $resolver->shouldReceive('resolve')->andReturn($result);
    } else {
        $resolver->shouldNotReceive('resolve');
    }

    return new RedirectOrphanedDescendants($resolver);
}

it('rewrites a successful orphaned-entry response to a 302 to the resolved target', function () {
    $entry = Mockery::mock(Entry::class);
    $response = new Response('page body', 200);

    redirectListener(new OrphanedAncestorResult('/health/expertise', 'Cardiology'))
        ->handle(new ResponseCreated($response, $entry));

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toBe('/health/expertise');
    expect($response->getContent())->toBe('');
});

it('leaves a response whose data is not an entry untouched', function () {
    $response = new Response('page body', 200);

    redirectListener(null, expectResolve: false)
        ->handle(new ResponseCreated($response, 'not-an-entry'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('page body');
});

it('leaves an unsuccessful response untouched and never resolves', function () {
    $entry = Mockery::mock(Entry::class);
    $response = new Response('not found', 404);

    redirectListener(null, expectResolve: false)
        ->handle(new ResponseCreated($response, $entry));

    expect($response->getStatusCode())->toBe(404);
    expect($response->getContent())->toBe('not found');
});

it('leaves a non-orphaned entry response untouched', function () {
    $entry = Mockery::mock(Entry::class);
    $response = new Response('page body', 200);

    redirectListener(null)
        ->handle(new ResponseCreated($response, $entry));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('page body');
});

it('leaves a Control Panel live-preview render untouched', function () {
    // Live preview intentionally renders an entry that may sit under an
    // unpublished ancestor — the redirect must not fire, and must not even
    // consult the resolver.
    // Partial mock so Laravel's `request` rebind handler (setUserResolver) still
    // hits the real method while isLivePreview() is stubbed.
    $request = Mockery::mock(Request::class)->makePartial();
    $request->shouldReceive('isLivePreview')->andReturn(true);
    app()->instance('request', $request);

    $entry = Mockery::mock(Entry::class);
    $response = new Response('preview body', 200);

    redirectListener(null, expectResolve: false)
        ->handle(new ResponseCreated($response, $entry));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('preview body');
});
