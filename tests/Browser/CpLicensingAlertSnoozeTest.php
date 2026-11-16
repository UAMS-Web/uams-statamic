<?php

declare(strict_types=1);

use Pest\Browser\Api\ArrayablePendingAwaitablePage;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Api\Webpage;
use Statamic\Auth\User;
use Statamic\Licensing\LicenseManager;

/*
 | Guards the licensing-alert snooze in loginToCp() (tests/Support/CpSmoke.php, #507).
 |
 | Statamic's trial-mode licensing alert is a modal that opens several seconds after a
 | Control Panel page loads. A fill() made while it is open reports success and types
 | into nothing, so a Side-by-Side check once waited 30 seconds on a button text that
 | was never entered. loginToCp() writes the `localStorage` key the alert reads when it
 | mounts. If a Statamic release renames that key, the snooze stops working without an
 | error, and the browser tests go back to racing the alert; this file fails instead.
 |
 | The first test is the control: with the key removed, the same probe sees the alert
 | open, so the second test's "never opened" is a reading and not a blind probe. Both
 | skip, naming the cause, where the server sends no alert at all (an unreachable or
 | failed licensing check, or valid licenses), since neither can then say anything.
 |
 | @command vendor/bin/pest tests/Browser/CpLicensingAlertSnoozeTest.php
 */

/**
 * How long a page gets to open the alert. It opened about 7 seconds after navigation
 * on Windows 11 (#507); a slow runner is given several times that.
 */
const LICENSING_ALERT_BOUND_SECONDS = 30.0;

/**
 * Why the server would send no licensing alert at all, or null when it sends one.
 *
 * Statamic sends the alert only when its licensing server answered and found the
 * licenses invalid. An offline laptop, a rate-limited or failed request, or a valid
 * license key means no alert, so the control could not see one and the closed check
 * would prove nothing. Read in this process, which shares the cache the in-process
 * server wrote when it answered the page.
 */
function licensingAlertAbsentReason(): ?string
{
    $licenses = app(LicenseManager::class);

    if ($licenses->outpostIsOffline()) {
        return 'Statamic\'s licensing server is unreachable, so no licensing alert is sent';
    }

    if ($licenses->requestFailed()) {
        $message = $licenses->requestFailureMessage();

        return 'the licensing request failed ('.(is_string($message) ? $message : 'no message').'), so no licensing alert is sent';
    }

    if (! $licenses->invalid()) {
        return 'the licenses are valid, so no licensing alert is sent';
    }

    return null;
}

/**
 * Whether the licensing alert opened within the bound, polled on the wall clock.
 *
 * @param  AwaitableWebpage|Webpage|PendingAwaitablePage|ArrayablePendingAwaitablePage  $page  The page returned from navigate().
 */
function licensingAlertOpens($page): bool
{
    $deadline = microtime(true) + LICENSING_ALERT_BOUND_SECONDS;

    do {
        $open = $page->script('(() => [...document.querySelectorAll("button")].some((b) => b.textContent.trim() === "Snooze"))()');

        if ($open === true) {
            return true;
        }

        $page->wait(0.5);
    } while (microtime(true) < $deadline);

    return false;
}

beforeEach(function (): void {
    $this->cpEmail = 'licensing-alert-'.uniqid().'@example.test';
    $this->cpPassword = 'licensing-alert-'.uniqid();
    $this->cpUser = seedCpSuperUser($this->cpEmail, $this->cpPassword);
});

afterEach(function (): void {
    /** @var User|null $cpUser */
    $cpUser = $this->cpUser;
    $cpUser?->delete();
});

it('sees the licensing alert open on a Control Panel page when it is not snoozed', function (): void {
    /** @var string $email */
    $email = $this->cpEmail;
    /** @var string $password */
    $password = $this->cpPassword;

    $page = loginToCp($email, $password);
    $page->script('localStorage.removeItem('.json_encode(CP_LICENSING_ALERT_SNOOZE_KEY).')');
    $page = $page->navigate('/cp/dashboard');

    if (($reason = licensingAlertAbsentReason()) !== null) {
        $this->markTestSkipped($reason);
    }

    expect(licensingAlertOpens($page))->toBeTrue();
});

it('keeps the licensing alert closed on a Control Panel page loaded after loginToCp()', function (): void {
    /** @var string $email */
    $email = $this->cpEmail;
    /** @var string $password */
    $password = $this->cpPassword;

    $page = loginToCp($email, $password);
    $page = $page->navigate('/cp/dashboard');

    if (($reason = licensingAlertAbsentReason()) !== null) {
        $this->markTestSkipped($reason);
    }

    expect(licensingAlertOpens($page))->toBeFalse();
});
