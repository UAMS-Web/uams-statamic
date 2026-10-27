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

/**
 * @covers \App\Listeners\RedirectSuppressedDependents
 *
 * UAMS-Web/uams-statamic-archive#1593: on ResponseCreated, a successful page response for a dependent whose
 * declared `publish_depends_on` source(s) are down is rewritten to a 302 toward
 * the resolved target; everything else is left untouched. The dependent's stored
 * status is never read or written here — only the response is rewritten. Mocked
 * resolver + real Response — no content/ submodule required.
 *
 * @command  php artisan test --compact tests/Feature/Listeners/RedirectSuppressedDependentsTest.php
 */

/** A listener bound to a resolver returning the given result for any entry. */
function suppressListener(?PublishDependencyResult $result, bool $expectResolve = true): RedirectSuppressedDependents
{
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

    suppressListener(new PublishDependencyResult('/clinical-resources', ['Dr. Alice'], true))
        ->handle(new ResponseCreated($response, $entry));

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toBe('/clinical-resources');
    expect($response->getContent())->toBe('');
});

it('leaves a response whose data is not an entry untouched', function (): void {
    $response = new Response('page body', 200);

    suppressListener(null, expectResolve: false)
        ->handle(new ResponseCreated($response, 'not-an-entry'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('page body');
});

it('leaves an unsuccessful response untouched and never resolves', function (): void {
    $entry = Mockery::mock(Entry::class);
    $response = new Response('not found', 404);

    suppressListener(null, expectResolve: false)
        ->handle(new ResponseCreated($response, $entry));

    expect($response->getStatusCode())->toBe(404);
    expect($response->getContent())->toBe('not found');
});

it('leaves a non-suppressed entry response untouched', function (): void {
    $entry = Mockery::mock(Entry::class);
    $response = new Response('page body', 200);

    suppressListener(null)
        ->handle(new ResponseCreated($response, $entry));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('page body');
});

it('leaves a Control Panel live-preview render untouched', function (): void {
    // Live preview intentionally renders an entry an editor is working on, which
    // may have down sources — the redirect must not fire, or even resolve.
    $request = Mockery::mock(Request::class)->makePartial();
    $request->shouldReceive('isLivePreview')->andReturn(true);
    app()->instance('request', $request);

    $entry = Mockery::mock(Entry::class);
    $response = new Response('preview body', 200);

    suppressListener(null, expectResolve: false)
        ->handle(new ResponseCreated($response, $entry));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('preview body');
});
