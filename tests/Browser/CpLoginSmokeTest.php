<?php

declare(strict_types=1);

use Statamic\Auth\User;

/*
 * The authenticated entry point: seed a throwaway super user, drive the real
 * login form, and assert the dashboard renders with a clean console. This
 * isolates the auth flow from any fieldtype rendering, so a failure here points
 * at login rather than a component. Uses the shared helpers in
 * tests/Support/CpSmoke.php.
 */

beforeEach(function () {
    $this->cpEmail = 'smoke-'.uniqid().'@example.test';
    $this->cpPassword = 'smoke-password-'.uniqid();
    $this->cpUser = seedCpSuperUser($this->cpEmail, $this->cpPassword);
});

afterEach(function () {
    /** @var User|null $user */
    $user = $this->cpUser;
    $user?->delete();
});

it('logs into the Control Panel without JavaScript errors', function () {
    /** @var string $cpEmail */
    $cpEmail = $this->cpEmail;
    /** @var string $cpPassword */
    $cpPassword = $this->cpPassword;

    $page = loginToCp($cpEmail, $cpPassword);

    assertNoAppJavaScriptErrors($page);
});
