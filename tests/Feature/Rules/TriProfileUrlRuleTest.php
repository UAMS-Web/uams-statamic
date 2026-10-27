<?php

declare(strict_types=1);

use App\Rules\FirstPartyUrl;
use App\Rules\TriProfileUrl;
use Tests\TestCase;

covers(TriProfileUrl::class);

uses(TestCase::class);

/**
 * Behavior of the {@see TriProfileUrl} validation rule. This rule's UAMS-domain
 * check was centralized onto the shared {@see FirstPartyUrl::isFirstPartyUrl()}
 * source; these cases pin its externally-observed accept/reject behavior so the
 * centralization cannot drift it.
 *
 * Mutation: tracked under App\Rules\TriProfileUrl (--covered-only).
 *
 * @command php artisan test --compact tests/Feature/Rules/TriProfileUrlRuleTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

/**
 * @return array{0: bool, 1: ?string} Failed flag and the fail message when present
 */
function triProfileUrlValidate(string $url): array
{
    $failed = false;
    $message = null;

    (new TriProfileUrl)->validate(
        'tri_profile_url',
        $url,
        function (string $msg) use (&$failed, &$message): void {
            $failed = true;
            $message = $msg;
        },
    );

    return [
        $failed,
        $message,
    ];
}

it('accepts only well-formed TRI profile URLs', function (string $url, bool $shouldPass): void {
    [
        $failed,
    ] = triProfileUrlValidate($url);

    expect($failed)->toBe(! $shouldPass);
})->with([
    'canonical TRI profile URL' => [
        'https://uams-triprofiles.uams.edu/profiles/display/12345',
        true,
    ],
    'single-digit id' => [
        'https://uams-triprofiles.uams.edu/profiles/display/1',
        true,
    ],
    'non-numeric id rejected' => [
        'https://uams-triprofiles.uams.edu/profiles/display/abc',
        false,
    ],
    'trailing path rejected' => [
        'https://uams-triprofiles.uams.edu/profiles/display/12345/extra',
        false,
    ],
    'wrong host rejected' => [
        'https://example.org/profiles/display/12345',
        false,
    ],
    'wrong UAMS subdomain rejected' => [
        'https://www.uams.edu/profiles/display/12345',
        false,
    ],
    'http (not https) rejected' => [
        'http://uams-triprofiles.uams.edu/profiles/display/12345',
        false,
    ],
    'non-URL rejected' => [
        'not a url',
        false,
    ],
]);

it('fails non-URLs with the generic URL message', function (): void {
    // Distinct from url_tri_profile so RemoveEarlyReturn cannot skip past this gate unnoticed.
    [
        $failed,
        $message,
    ] = triProfileUrlValidate('not a url');

    expect($failed)->toBeTrue()
        ->and($message)->toBe(__('statamic::validation.url'));
});

it('fails off-host URLs with the TRI profile message', function (): void {
    [
        $failed,
        $message,
    ] = triProfileUrlValidate('https://example.org/profiles/display/12345');

    expect($failed)->toBeTrue()
        ->and($message)->toBe(__('statamic::validation.url_tri_profile'));
});

it('fails malformed TRI paths with the TRI profile message', function (): void {
    [
        $failed,
        $message,
    ] = triProfileUrlValidate('https://uams-triprofiles.uams.edu/profiles/display/abc');

    expect($failed)->toBeTrue()
        ->and($message)->toBe(__('statamic::validation.url_tri_profile'));
});

it('skips an empty value', function (): void {
    [
        $failed,
    ] = triProfileUrlValidate('');

    expect($failed)->toBeFalse();
});
