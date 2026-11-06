<?php

declare(strict_types=1);

use App\Http\Middleware\RedirectGuestToLogin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Statamic\Facades\User;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Pin login destination, return location, and signed-in request passthrough.
 *
 * Linux orb, PHP 8.4/PCOV: 5/5 covered statements and 4 killed mutants.
 * No accepted equivalent mutants; every killable mutant is dead.
 *
 * @command  php artisan test --compact tests/Feature/Http/Middleware/RedirectGuestToLoginTest.php
 */
uses(TestCase::class);

covers(RedirectGuestToLogin::class);

beforeEach(function (): void {
    // Exercise the real middleware and session through a disposable route.
    $probe = Route::get('/__coverage/guest-login', fn (): string => 'protected response')
        ->middleware(['web', RedirectGuestToLogin::class]);

    // Put the probe before Statamic's already-registered catch-all route.
    $routes = new RouteCollection;
    $routes->add($probe);
    foreach (app('router')->getRoutes()->getRoutes() as $route) {
        if ($route !== $probe) {
            $routes->add($route);
        }
    }

    app('router')->setRoutes($routes);
});

it('redirects guests to the configured login and remembers the complete requested URL', function (
    mixed $loginUrl,
    string $expected
): void {
    // Distinguish configured destinations from absent or invalid configuration.
    config()->set('statamic.protect.schemes.logged_in.login_url', $loginUrl);

    // Require a real redirect rather than letting the protected response escape.
    $this->get('/__coverage/guest-login?category=furniture&page=3')
        ->assertRedirect($expected)
        ->assertSessionHas('url.intended', url('/__coverage/guest-login').'?category=furniture&page=3');
})->with([
    'custom login' => ['/sign-in', '/sign-in'],
    'configured default' => ['/login', '/login'],
    'null login' => [null, '/login'],
    'boolean login' => [false, '/login'],
    'numeric login' => [37, '/login'],
    'array login' => [['/sign-in'], '/login'],
]);

it('uses the default destination when the login configuration key is missing', function (): void {
    // Removing the leaf exercises config()'s default argument rather than type fallback.
    config()->set('statamic.protect.schemes.logged_in', []);

    $this->get('/__coverage/guest-login')
        ->assertRedirect('/login')
        ->assertSessionHas('url.intended', url('/__coverage/guest-login'));
});

it('returns the downstream response for a signed-in user without changing the intended URL', function (): void {
    // Authenticate an in-memory user without creating shared user files.
    $this->actingAs(mustBe(Authenticatable::class, User::make(), 'in-memory user'));

    // An allowed request must not redirect or replace a pre-existing return URL.
    $this->withSession(['url.intended' => '/previous-destination'])
        ->get('/__coverage/guest-login')
        ->assertOk()
        ->assertContent('protected response')
        ->assertSessionHas('url.intended', '/previous-destination');
});

it('passes the original request and exact response through for a signed-in user', function (): void {
    // Use real authentication while making request and response identity observable.
    $this->actingAs(mustBe(Authenticatable::class, User::make(), 'in-memory user'));
    $request = Request::create('/__coverage/guest-login?keep=1');
    $response = new Response('downstream identity', 202, ['X-Downstream' => 'preserved']);

    // Returning a fabricated request or response cannot satisfy these identities.
    $actual = (new RedirectGuestToLogin)->handle($request, function (Request $received) use ($request, $response): Response {
        expect($received)->toBe($request);

        return $response;
    });

    expect($actual)->toBe($response);
});
