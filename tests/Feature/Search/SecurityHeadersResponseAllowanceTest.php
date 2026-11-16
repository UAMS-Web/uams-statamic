<?php

declare(strict_types=1);

use App\Http\Middleware\SecurityHeaders;
use App\Search\SecurityHeadersResponseAllowance;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Pin the application's Google-only, configuration-owned CSP allowance.
 *
 * Linux orb, PHP 8.4/PCOV: 3/3 covered statements and 3 killed mutants.
 * No accepted equivalent mutants; every killable mutant is dead.
 *
 * @command  php artisan test --compact tests/Feature/Search/SecurityHeadersResponseAllowanceTest.php
 */
uses(TestCase::class);

covers(SecurityHeadersResponseAllowance::class);

it('widens only Google responses using application configuration, never provider needs', function (
    string $provider,
    bool $declareNeeds,
    string $expected
): void {
    // Use distinct application and provider sources to expose ownership mistakes.
    config()->set('security-headers.enabled', true);
    config()->set('security-headers.csp', [
        'enabled' => true,
        'report_only' => false,
        'exclude_paths' => [],
        'nonce_directives' => [],
        'report_uri' => null,
        'directives' => ['default-src' => ["'self'"], 'script-src' => ["'self'"]],
        'allowances' => [
            'google_search' => ['script-src' => ['https://configured-search.example.test']],
        ],
    ]);
    $needs = $declareNeeds ? ['script-src' => ['https://provider-claimed.example.test']] : [];
    $request = Request::create('/search?q=medicine');
    $this->app->instance('request', $request);

    // Inspect the emitted policy after the adapter runs inside the response pipeline.
    $response = (new SecurityHeaders)->handle($request, function () use ($provider, $needs): Response {
        (new SecurityHeadersResponseAllowance)->raise($provider, $needs);

        return new Response('search results');
    });

    expect($response->headers->get('Content-Security-Policy'))->toBe($expected);

    // A subsequent request must start narrow even after a Google response.
    $nextRequest = Request::create('/ordinary-page');
    $this->app->instance('request', $nextRequest);
    $nextResponse = (new SecurityHeaders)->handle($nextRequest, fn (): Response => new Response('ordinary page'));

    expect($nextResponse->headers->get('Content-Security-Policy'))->toBe("default-src 'self'; script-src 'self'");
})->with([
    'Google without declared needs' => ['google', false, "default-src 'self'; script-src 'self' https://configured-search.example.test"],
    'Google with different declared needs' => ['google', true, "default-src 'self'; script-src 'self' https://configured-search.example.test"],
    'another provider' => ['other', true, "default-src 'self'; script-src 'self'"],
    'uppercase provider is not Google' => ['GOOGLE', true, "default-src 'self'; script-src 'self'"],
    'empty provider' => ['', true, "default-src 'self'; script-src 'self'"],
]);
