<?php

declare(strict_types=1);

use App\Support\Http\OutboundUrlEgressGuard;
use App\Support\Http\OutboundUrlGuard;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The adapter that answers the shared fieldtype add-on's egress-guard contract with the site's
 * `OutboundUrlGuard` (UAMS-Web/uams-statamic#1256). DNS is stubbed through the site guard's own
 * resolver hook and the network through `Http::fake`, so the test stays offline.
 *
 * @command  php artisan test --compact tests/Feature/Support/Http/OutboundUrlEgressGuardTest.php
 */
uses(TestCase::class);

covers(OutboundUrlEgressGuard::class);

beforeEach(function (): void {
    OutboundUrlGuard::setResolver(fn (string $host): array => $host === 'internal.example.test' ? ['10.0.0.5'] : ['8.8.8.8']);
});

afterEach(function (): void {
    OutboundUrlGuard::setResolver(null);
});

it('allows a URL only when the site guard does', function (string $url, bool $allowed): void {
    expect((new OutboundUrlEgressGuard)->isAllowedUrl($url))->toBe($allowed)
        ->and(OutboundUrlGuard::isAllowedUrl($url))->toBe($allowed);
})->with([
    'a public host' => ['https://nominatim.openstreetmap.org/search?q=x', true],
    'a host resolving to a private network' => ['https://internal.example.test/search', false],
    'a link-local literal' => ['http://169.254.169.254/search', false],
    'a loopback literal' => ['http://127.0.0.1/search', false],
    'a scheme other than http' => ['ftp://nominatim.openstreetmap.org/search', false],
]);

it('sends the verb it is given, with the caller configuration applied', function (string $method): void {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response(['ok' => true], 200)]);

    $response = (new OutboundUrlEgressGuard)->fetch(
        $method,
        'https://nominatim.openstreetmap.org/search?q=x',
        fn (PendingRequest $request): PendingRequest => $request->withHeaders(['User-Agent' => 'Kirk']),
    );

    expect($response->status())->toBe(200);
    Http::assertSent(fn (Request $request): bool => $request->method() === strtoupper($method)
        && $request->header('User-Agent') === ['Kirk']);
})->with(['GET', 'HEAD', 'get']);

it('refuses a host the site guard refuses, before any request', function (): void {
    Http::fake();

    expect(fn () => (new OutboundUrlEgressGuard)->fetch('GET', 'https://internal.example.test/search'))
        ->toThrow(RuntimeException::class);

    Http::assertNothingSent();
});

it('refuses a verb the site guard cannot send, before any request', function (string $method): void {
    Http::fake();

    expect(fn () => (new OutboundUrlEgressGuard)->fetch($method, 'https://nominatim.openstreetmap.org/search'))
        ->toThrow(RuntimeException::class, sprintf('The egress guard does not send %s requests.', strtoupper($method)));

    Http::assertNothingSent();
})->with(['POST', 'put', 'DELETE']);
