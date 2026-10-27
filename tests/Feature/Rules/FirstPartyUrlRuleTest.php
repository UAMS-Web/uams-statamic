<?php

declare(strict_types=1);

use App\Rules\FirstPartyUrl;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Behavior of the {@see FirstPartyUrl} validation rule, which classifies a URL as UAMS
 * vs non-UAMS from a single class usable in both directions: `non_first_party_url`
 * (constructed with `$mustBeUams = false`) must NOT be a UAMS URL, and `first_party_url`
 * (default `$mustBeUams = true`) must be a UAMS URL. Covers subdomain matching,
 * look-alike-host rejection, the empty/optional skip, and the URL precheck.
 *
 * Mutation note: a handful of surviving mutants on FirstPartyUrl are equivalent mutants
 * (no observable behavior change), intentionally left rather than chased:
 *   - removing the `(string)` cast on a config entry — `trim()` already coerces
 *     scalars to string, so the cast is redundant;
 *   - the empty-domain skip `$normalized !== ''` — an empty domain would expand to
 *     `str_ends_with($host, '.')`, which is false for every real host, so it never
 *     matches anything anyway;
 *   - the `$host === ''` no-host guard and its early `return false` —
 *     `parse_url(..., PHP_URL_HOST)` returns null (covered) rather than '' for a
 *     hostless value, so that branch is belt-and-suspenders.
 *
 * @command php artisan test --compact tests/Feature/Rules/FirstPartyUrlRuleTest.php
 */

/**
 * Run the rule for a direction and report whether it failed.
 */
function uamsUrlFails(bool $mustBeUams, mixed $value): bool
{
    $failed = false;

    (new FirstPartyUrl($mustBeUams))->validate('external_website_url', $value, function () use (&$failed): void {
        $failed = true;
    });

    return $failed;
}

/**
 * Run the rule constructed with NO argument (default direction) and report whether
 * it failed. Used to pin the constructor's default `$mustBeUams = true`.
 */
function uamsUrlFailsDefault(mixed $value): bool
{
    $failed = false;

    (new FirstPartyUrl)->validate('external_website_url', $value, function () use (&$failed): void {
        $failed = true;
    });

    return $failed;
}

it('rejects UAMS URLs for the non_first_party_url direction and passes external URLs', function (string $url, bool $shouldPass): void {
    expect(uamsUrlFails(false, $url))->toBe(! $shouldPass);
})->with([
    // UAMS URLs must FAIL non_first_party_url.
    'apex uams.edu fails' => ['https://uams.edu/x', false],
    'www.uams.edu fails' => ['https://www.uams.edu/x', false],
    'arbitrary uams.edu subdomain fails' => ['https://anything.uams.edu', false],
    'uamshealth.com fails' => ['https://uamshealth.com', false],
    'uamshealth.com subdomain fails' => ['https://my.uamshealth.com/clinic', false],
    'uppercase host still UAMS fails' => ['https://WWW.UAMS.EDU/x', false],
    // Genuine external URLs must PASS non_first_party_url.
    'external example.org passes' => ['https://example.org', true],
    'external example.com passes' => ['https://example.com/path', true],
]);

it('requires UAMS URLs for the first_party_url direction (inverse of non_first_party_url)', function (string $url, bool $shouldPass): void {
    expect(uamsUrlFails(true, $url))->toBe(! $shouldPass);
})->with([
    // UAMS URLs must PASS first_party_url.
    'apex uams.edu passes' => ['https://uams.edu/x', true],
    'www.uams.edu passes' => ['https://www.uams.edu/x', true],
    'arbitrary uams.edu subdomain passes' => ['https://anything.uams.edu', true],
    'uamshealth.com passes' => ['https://uamshealth.com', true],
    // Non-UAMS URLs must FAIL first_party_url.
    'external example.org fails' => ['https://example.org', false],
]);

it('does not misclassify look-alike hosts as UAMS', function (string $url): void {
    // Look-alikes are NOT UAMS, so they pass non_first_party_url and fail first_party_url.
    expect(uamsUrlFails(false, $url))->toBeFalse();
    expect(uamsUrlFails(true, $url))->toBeTrue();
})->with([
    'uams.edu as a left-label of evil.com' => ['https://uams.edu.evil.com/x'],
    'notuams.edu (suffix, not subdomain)' => ['https://notuams.edu'],
    'uamshealth.com.evil.com' => ['https://uamshealth.com.evil.com'],
    'fakeuamshealth.com' => ['https://fakeuamshealth.com'],
]);

it('defaults to the must-be-UAMS direction when constructed with no argument', function (): void {
    // The constructor default ($mustBeUams = true) means a bare `new FirstPartyUrl`
    // requires a UAMS URL: an external URL fails, a UAMS URL passes.
    expect(uamsUrlFailsDefault('https://example.org'))->toBeTrue();
    expect(uamsUrlFailsDefault('https://www.uams.edu/x'))->toBeFalse();
});

it('matches both an exact apex host and a subdomain independently', function (): void {
    // An exact-apex match (no subdomain) AND a subdomain match must each
    // independently classify as UAMS — proving the host test is an OR of the two,
    // not an AND (which would require both at once and match nothing).
    expect(FirstPartyUrl::isFirstPartyUrl('https://uams.edu'))->toBeTrue();
    expect(FirstPartyUrl::isFirstPartyUrl('https://www.uams.edu'))->toBeTrue();
});

it('proves subdomain matching for the public isFirstPartyUrl classifier', function (string $url, bool $expected): void {
    expect(FirstPartyUrl::isFirstPartyUrl($url))->toBe($expected);
})->with([
    'apex' => ['https://uams.edu', true],
    'www subdomain' => ['https://www.uams.edu', true],
    'deep subdomain' => ['https://a.b.c.uams.edu/path?q=1', true],
    'uamshealth subdomain' => ['https://portal.uamshealth.com', true],
    'look-alike left-label' => ['https://uams.edu.evil.com', false],
    'look-alike suffix' => ['https://notuams.edu', false],
    'unrelated host' => ['https://example.org', false],
    'no host (relative)' => ['/just/a/path', false],
]);

it('skips empty values so an optional field passes', function (mixed $value): void {
    // Empty values defer to the `required` rule; neither direction should fail.
    expect(uamsUrlFails(false, $value))->toBeFalse();
    expect(uamsUrlFails(true, $value))->toBeFalse();
})->with([
    'empty string' => [''],
    'null' => [null],
]);

it('fails a present non-URL with the URL message in both directions', function (string $value): void {
    expect(uamsUrlFails(false, $value))->toBeTrue();
    expect(uamsUrlFails(true, $value))->toBeTrue();
})->with([
    'plain text' => ['not a url'],
    'bare word' => ['uams'],
]);

it('reads the configured UAMS domain set from config/first_party.php (single source)', function (): void {
    // A custom domain added to config is honored without touching the rule.
    config(['first_party.domains' => ['example.test']]);

    expect(FirstPartyUrl::isFirstPartyUrl('https://sub.example.test/x'))->toBeTrue();
    // The default UAMS domains are no longer matched once config is overridden.
    expect(FirstPartyUrl::isFirstPartyUrl('https://uams.edu'))->toBeFalse();
});

it('normalizes configured domains (trims whitespace, strips leading dot, lowercases)', function (string $configured, string $url, bool $expected): void {
    config(['first_party.domains' => [$configured]]);

    expect(FirstPartyUrl::isFirstPartyUrl($url))->toBe($expected);
})->with([
    // A whitespace-padded entry must still match (trim is load-bearing).
    'padded entry matches host' => ['  example.test  ', 'https://example.test', true],
    'padded entry matches subdomain' => ['  example.test  ', 'https://sub.example.test', true],
    // A leading dot is stripped so `.example.test` behaves like `example.test`.
    'leading-dot entry matches host' => ['.example.test', 'https://example.test', true],
    // Uppercase config entry matches a lowercase host (lowercasing is load-bearing).
    'uppercase entry matches host' => ['EXAMPLE.TEST', 'https://example.test', true],
]);

it('coerces a non-string scalar configured domain to a string before matching', function (): void {
    // A numeric-looking config entry (stored as an int) must still match its host,
    // proving the string cast on each config entry is load-bearing.
    config(['first_party.domains' => [123]]);

    expect(FirstPartyUrl::isFirstPartyUrl('https://123/x'))->toBeTrue();
    expect(FirstPartyUrl::isFirstPartyUrl('https://sub.123/x'))->toBeTrue();
});

it('drops empty or whitespace-only configured domains so they never match every host', function (mixed $configured): void {
    // An empty / whitespace-only / non-scalar config entry must be skipped, not
    // turned into a domain that matches every host.
    config(['first_party.domains' => [$configured]]);

    expect(FirstPartyUrl::isFirstPartyUrl('https://example.org'))->toBeFalse();
    expect(FirstPartyUrl::isFirstPartyUrl('https://anything.com'))->toBeFalse();
})->with([
    'empty string' => [''],
    'whitespace only' => ['   '],
    'dot only' => ['.'],
]);

it('short-circuits a non-URL with the URL message, never a UAMS-direction message', function (): void {
    // The early return after the URL precheck means a non-URL fails with exactly
    // the URL message and is never reclassified by direction.
    $messages = [];

    (new FirstPartyUrl(false))->validate('external_website_url', 'not a url', function (string $message) use (&$messages): void {
        $messages[] = $message;
    });

    expect($messages)->toHaveCount(1);
    expect($messages[0])->toBe(__('statamic::validation.url'));
});

it('classifies a trailing-dot FQDN of an owned host as first-party (RFC 1034 absolute form)', function (string $url): void {
    // `uams.edu.` resolves to the same site as `uams.edu`, so it must classify as
    // first-party: passing first_party_url and FAILING non_first_party_url (an owned
    // domain must not slip the external-only guard via the absolute-FQDN form).
    expect(FirstPartyUrl::isFirstPartyUrl($url))->toBeTrue();
    expect(uamsUrlFails(false, $url))->toBeTrue();
    expect(uamsUrlFails(true, $url))->toBeFalse();
})->with([
    'apex trailing dot' => ['https://uams.edu./x'],
    'www trailing dot' => ['https://www.uams.edu.'],
    'uamshealth trailing dot' => ['https://uamshealth.com.'],
]);

it('does not over-strip a trailing-dot look-alike into first-party', function (): void {
    // Stripping the single root dot must not promote a look-alike to first-party.
    expect(FirstPartyUrl::isFirstPartyUrl('https://uams.edu.evil.com.'))->toBeFalse();
    expect(FirstPartyUrl::isFirstPartyUrl('https://notuams.edu.'))->toBeFalse();
});

it('fails each direction with its own message key', function (): void {
    // A UAMS URL fails non_first_party_url with the non-first-party message; an
    // external URL fails first_party_url with the first-party message — so a
    // renamed/deleted directional lang key is caught.
    $nonMessages = [];
    (new FirstPartyUrl(false))->validate('external_website_url', 'https://uams.edu/x', function (string $message) use (&$nonMessages): void {
        $nonMessages[] = $message;
    });
    expect($nonMessages)->toBe([__('statamic::validation.url_non_first_party')]);

    $firstMessages = [];
    (new FirstPartyUrl(true))->validate('external_website_url', 'https://example.org', function (string $message) use (&$firstMessages): void {
        $firstMessages[] = $message;
    });
    expect($firstMessages)->toBe([__('statamic::validation.url_first_party')]);
});
