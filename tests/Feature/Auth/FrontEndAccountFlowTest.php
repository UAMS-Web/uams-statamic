<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Statamic\Facades\Antlers;
use Statamic\Facades\User;
use Tests\TestCase;

uses(TestCase::class);

/*
 * #2113 — the front-end account pages.
 *
 * The DISPLAY pages are `pages` entries with a `template:` override, and those entries
 * are #2305's. The POST handlers Statamic registers are reachable now, so the flow is
 * testable here and only the GET pages wait on #2305 — which is why these drive the
 * handlers directly rather than visiting a URL.
 */

/**
 * A saved member, typed for static analysis.
 *
 * `User::make()` is `mixed` to PHPStan, so every call site would otherwise need its own
 * annotation -- and the one that gets missed is the one that rots.
 */
function member(string $email, string $password): Statamic\Contracts\Auth\User
{
    // Annotated BEFORE the calls, not after: `User::make()` is `mixed`, so a docblock
    // on the finished chain still leaves `->email()` and `->password()` unresolved.
    /** @var Statamic\Auth\User $user */
    $user = User::make();
    $user->email($email);
    $user->password($password);
    $user->save();

    return $user;
}

/**
 * Only the accounts these tests create. NEVER `User::all()`.
 *
 * Statamic's user repository here is the FILE driver, and `users/` is a tracked git
 * submodule holding the real login accounts. `collect(User::all())->each->delete()`
 * therefore deletes those files from the working tree — it did, in this branch, and the
 * slot-release guard is what caught it rather than any test. A suite that eats the
 * developer's own login is a destructive side effect no green run would report.
 */
function deleteTestMembers(): void
{
    foreach (['ada@example.com', 'grace@example.com'] as $email) {
        $user = User::findByEmail($email);

        // An `instanceof` narrowing rather than `assert(`, which Rule E of the isolation
        // guard bans anywhere under tests/. A fixture legitimately may not exist yet, so
        // this is a genuine conditional and not a type assertion dressed as one.
        if ($user instanceof Statamic\Auth\User) {
            $user->delete();
        }
    }
}

beforeEach(function (): void {
    deleteTestMembers();

    // Statamic ships `throttle:statamic.auth` on the login route and this app's cache
    // store is `file`, so the limiter's counters live on DISK and survive the process.
    // Without this the suite passes once and then fails on every subsequent run, with a
    // message ("The user is not authenticated") that names authentication rather than
    // rate limiting — the failure looks like a broken login. Found by the suite going
    // green, then red, with no code change in between.
    RateLimiter::clear('statamic.auth');
    RateLimiter::clear('statamic.password-reset-form');
    Cache::flush();
});

afterEach(function (): void {
    deleteTestMembers();
});

it('registers a visitor and signs them in', function (): void {
    $this->post(route('statamic.register'), [
        'name' => 'Ada Lovelace',
        'username' => 'ada',
        'email' => 'ada@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertSessionHasNoErrors();

    $user = mustBe(Statamic\Auth\User::class, User::findByEmail('ada@example.com'), 'the registered member');
    expect($user->get('username'))->toBe('ada');
    $this->assertAuthenticated();
});

it('signs in an existing member with the right password', function (): void {
    member('grace@example.com', 'correct-horse-battery');

    $this->post(route('statamic.login'), [
        'email' => 'grace@example.com',
        'password' => 'correct-horse-battery',
    ])->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});

it('refuses a sign-in with the wrong password', function (): void {
    member('grace@example.com', 'correct-horse-battery');

    $this->post(route('statamic.login'), [
        'email' => 'grace@example.com',
        'password' => 'not-the-password',
    ])->assertSessionHasErrors();

    $this->assertGuest();
});

it('signs a member out', function (): void {
    $user = member('grace@example.com', 'correct-horse-battery');

    $this->actingAs($user)->get('/!/auth/logout');

    $this->assertGuest();
});

/*
 * The profile page decides between a sign-in prompt and the two account forms on
 * `logged_out`, which the cascade publishes. It is asserted through the parser with the
 * flag supplied explicitly: rendering the view outside a request leaves the cascade
 * unpopulated, so `logged_out` is merely UNDEFINED there and the template silently takes
 * its signed-in branch. A render-and-eyeball check cannot tell that apart from a working
 * guard, which is why this drives the branch directly.
 */
it('shows a sign-in prompt on the profile page when signed out', function (): void {
    $template = (string) file_get_contents(resource_path('views/pages/account_profile.antlers.html'));
    $body = str($template)->after('{{ if logged_out }}')->before('{{ else }}')->toString();

    $out = (string) Antlers::parse($body, ['logged_out' => true]);

    expect($out)->toContain('need to be signed in');
});

/*
 * The emailed reset link must reach the front-end page rather than the Control Panel.
 *
 * The override is registered from AppServiceProvider's `booted()` callback, NOT from
 * routes/web.php: Laravel's RouteCollection keys on method+URI and the last registration
 * wins, and Statamic registers this URI after the app's route file loads. An override
 * there is replaced silently — measured during #2113, the route was absent from all 2,662
 * registered routes while the Statamic handler was still listed, with no error either way.
 * This asserts the survivor, so a future move back into routes/web.php cannot pass.
 */
it('routes the emailed reset link to the front end, not the control panel', function (): void {
    $route = mustBe(Illuminate\Routing\Route::class, Route::getRoutes()->getByName('statamic.password.reset'), 'the reset route');

    expect($route->uri())->toBe('!/auth/password/reset/{token}')
        ->and($route->getActionName())->toBe('Closure');
});

it('keeps the throttle on the overridden reset route', function (): void {
    // Statamic ships `throttle:statamic.password-reset-form` on the route being replaced.
    // Re-registering without it would silently drop rate limiting from a public auth
    // endpoint, which is AC6's subject and exactly what a replacement quietly loses.
    $route = mustBe(Illuminate\Routing\Route::class, Route::getRoutes()->getByName('statamic.password.reset'), 'the reset route');

    expect($route->gatherMiddleware())->toContain('throttle:statamic.password-reset-form');
});

/*
 * AC6 — "rate limiting on the auth routes is confirmed active rather than assumed".
 *
 * This asserts it by TRIPPING it rather than by reading the middleware list: a route can
 * carry `throttle:` and still not limit anything if the named limiter is undefined, and
 * the middleware assertion above cannot tell those apart. Repeated failed sign-ins must
 * eventually be refused with 429 rather than merely rejected with a validation error.
 */
it('rate limits repeated failed sign-ins', function (): void {
    member('grace@example.com', 'correct-horse-battery');

    $statuses = [];
    foreach (range(1, 12) as $attempt) {
        $statuses[] = $this->post(route('statamic.login'), [
            'email' => 'grace@example.com',
            'password' => 'not-the-password',
        ])->getStatusCode();
    }

    expect($statuses)->toContain(429);
});

/*
 * The site chrome must show the MEMBER's name, not the entry's.
 *
 * The cascade publishes the signed-in member as `current_user`; a bare `{{ name }}`
 * resolves against the entry, and 21 blueprints in this site carry a `name` field. On any
 * organizations or facilities page the chrome would have shown that entry's name where the
 * member's belongs — on every page, since this partial is global. Found by adversarial
 * review, reproduced, then locked here.
 */
it('shows the member name in the chrome, not a colliding entry name', function (): void {
    $template = (string) file_get_contents(resource_path('views/layout/header/_account.antlers.html'));
    $body = str($template)->after('{{ if logged_in }}')->before('{{ else }}')->toString();

    $out = (string) Antlers::parse($body, [
        'logged_in' => true,
        'name' => 'Schmieding Center',
    ]);

    expect($out)->not->toContain('Schmieding Center');
});

/*
 * AC3's reset half, end to end against the real routes.
 *
 * Asserted by actually changing the password, not by finding the token string in the
 * rendered page: `reset_password_form` reads the token from `request('token')`, which is
 * INPUT, while the override supplies it as a ROUTE PARAMETER. Those are different sources,
 * and a page that displays the token can still post a reset that fails.
 */
it('completes a password reset through the front-end route', function (): void {
    $user = member('grace@example.com', 'the-old-password');
    // Statamic's user implements CanResetPassword at runtime, but the declared return type
    // does not carry it, so the broker's parameter is narrowed here. `mustBe` rather than
    // `assert`: Rule E of the isolation guard bans `assert(` anywhere under tests/, and it
    // is explicit that neither the serial quarantine nor the allowlist exempts it, because
    // this is a typing defect rather than a parallelism one.
    $resettable = mustBe(CanResetPassword::class, $user, 'the member');
    $token = Password::broker('users')->createToken($resettable);

    $this->get("/!/auth/password/reset/{$token}")->assertOk();

    $this->post(route('statamic.password.reset.action'), [
        'token' => $token,
        'email' => 'grace@example.com',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasNoErrors();

    // The old password must no longer work and the new one must.
    auth()->logout();
    expect(auth()->attempt(['email' => 'grace@example.com', 'password' => 'the-old-password']))->toBeFalse();
    expect(auth()->attempt(['email' => 'grace@example.com', 'password' => 'a-brand-new-password']))->toBeTrue();
});
