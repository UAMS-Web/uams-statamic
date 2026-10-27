<?php

declare(strict_types=1);

// Hosts built to look like an allowed one, which no dictionary carries
// cspell:ignore uamshealthxcom

use App\Http\Middleware\TrustHosts;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Assert;
use Statamic\Facades\User;
use Statamic\Notifications\PasswordReset;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#3014: a request for an address the site does not serve is
 * refused with a 400 before any route runs, and one for an allowed address is
 * answered as before.
 *
 * Laravel switches the guard off in tests, so each case binds the app's guard
 * with that switch turned on. The redirect-domain cases, which need saved
 * entries, are in `tests/Feature/Redirects/TrustedRedirectDomainsTest.php`.
 *
 * @command  php artisan test --compact tests/Feature/Http/TrustedHostsTest.php
 */

/**
 * The email of the Control Panel user the password reset cases create.
 */
const TRUSTED_HOSTS_RESET_EMAIL = 'trusted-hosts-probe@example.com';

/**
 * The application URL's host, as the guard reads it.
 */
function trustedHostsAppHost(): string
{
    $host = parse_url(config()->string('app.url'), PHP_URL_HOST);
    Assert::assertIsString($host);

    return $host;
}

/**
 * Delete the user the password reset cases create, and only that user.
 */
function deleteTrustedHostsResetUser(): void
{
    $user = User::findByEmail(TRUSTED_HOSTS_RESET_EMAIL);

    if ($user instanceof Statamic\Auth\User) {
        $user->delete();
    }
}

beforeEach(function (): void {
    // Turn the guard on, which Laravel's own switch keeps off in tests
    $this->app->bind(TrustHosts::class, fn (Application $app): TrustHosts => new class($app) extends TrustHosts
    {
        protected function shouldSpecifyTrustedHosts(): bool
        {
            return true;
        }
    });

    deleteTrustedHostsResetUser();
    RateLimiter::clear('statamic.password-reset-form');
});

afterEach(function (): void {
    // Clear the allowed list, which Symfony keeps for the whole process
    Request::setTrustedHosts([]);

    deleteTrustedHostsResetUser();
});

it('refuses a request for an unknown address with a 400 that reaches no route', function (): void {
    $matched = 0;
    Event::listen(RouteMatched::class, function () use (&$matched): void {
        $matched++;
    });

    $this->get('http://attacker.example/up')->assertStatus(400);

    expect($matched)->toBe(0);
});

it('answers the application URL host, its subdomains and the listed site domains as before', function (string $host): void {
    $this->get('http://'.$host.'/up')->assertOk();
})->with([
    'application URL host' => fn (): string => trustedHostsAppHost(),
    'a subdomain of it' => fn (): string => 'www.'.trustedHostsAppHost(),
    'a deeper subdomain' => fn (): string => 'a.b.'.trustedHostsAppHost(),
    'uamshealth.com' => 'uamshealth.com',
]);

it('matches a listed site domain exactly', function (string $host): void {
    $this->get('http://'.$host.'/up')->assertStatus(400);
})->with([
    'a host that contains it' => 'uamshealth.com.attacker.example',
    'a host that ends with it after another label' => 'www.uamshealth.com',
    'a host with a letter where its dot is' => 'uamshealthxcom',
    'a host that ends with the application host without a dot' => fn (): string => 'attacker'.trustedHostsAppHost(),
]);

it('sends no password reset email for a request under an unknown address', function (): void {
    Notification::fake();

    $user = mustBe(Statamic\Auth\User::class, User::make(), 'user');
    $user->email(TRUSTED_HOSTS_RESET_EMAIL);
    $user->save();

    $this->post('http://attacker.example/cp/auth/password/email', ['email' => TRUSTED_HOSTS_RESET_EMAIL])
        ->assertStatus(400);

    Notification::assertNothingSent();
});

it('emails a password reset link on the allowed address the request named', function (): void {
    Notification::fake();

    $user = mustBe(Statamic\Auth\User::class, User::make(), 'user');
    $user->email(TRUSTED_HOSTS_RESET_EMAIL);
    $user->save();

    // Request it under a subdomain, so a link on the application URL host would differ
    $host = 'www.'.trustedHostsAppHost();
    $this->post('http://'.$host.'/cp/auth/password/email', ['email' => TRUSTED_HOSTS_RESET_EMAIL]);

    // The link is built when the mail is, from the request the URL generator still holds
    Notification::assertSentTo($user, PasswordReset::class, function (PasswordReset $notification) use ($user, $host): bool {
        $mail = $notification->toMail($user);
        Assert::assertInstanceOf(MailMessage::class, $mail);

        return parse_url((string) $mail->actionUrl, PHP_URL_HOST) === $host;
    });
});
