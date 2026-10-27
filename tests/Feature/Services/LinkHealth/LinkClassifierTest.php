<?php

declare(strict_types=1);

use App\Services\LinkHealth\LinkClassifier;
use App\Services\LinkHealth\LinkKind;
use Tests\TestCase;

/**
 * Coverage for {@see LinkClassifier} — the shape-only classification of one stored value (UAMS-Web/uams-statamic-archive#1764).
 *
 * The classifier resolves nothing: every case here is decided from the value plus two pieces of
 * local context (which hosts this install serves, and which entry ids exist), so the whole file
 * runs with the network unavailable.
 *
 * @command  php artisan test --compact tests/Feature/Services/LinkHealth/LinkClassifierTest.php
 */
uses(TestCase::class);

const LC_SERVED = ['www.uams.edu', 'uams.edu'];
const LC_KNOWN_ENTRY = 'e1f2a3b4-0000-4000-8000-000000000001';

function lcClassifier(): LinkClassifier
{
    return new LinkClassifier(LC_SERVED, [LC_KNOWN_ENTRY => true]);
}

it('classifies each kind from the value shape alone', function (string $value, ?LinkKind $expected): void {
    expect(lcClassifier()->classify($value))->toBe($expected);
})->with([
    'external host' => ['https://example.com/thing', LinkKind::External],
    'external with port' => ['http://example.com:8080/x', LinkKind::External],
    'served host is internal' => ['https://www.uams.edu/clinic', LinkKind::Internal],
    'served apex is internal' => ['https://uams.edu/', LinkKind::Internal],
    'root-relative is internal' => ['/clinics/cardiology', LinkKind::Internal],
    'first-party elsewhere' => ['https://uamshealth.com/find-a-doctor', LinkKind::FirstPartyOther],
    'first-party subdomain elsewhere' => ['https://www.uamshealth.com/x', LinkKind::FirstPartyOther],
    'entry reference' => ['statamic://entry::abc-123', LinkKind::EntryReference],
    'bare entry reference' => ['entry::abc-123', LinkKind::EntryReference],
    'asset reference' => ['statamic://asset::main::x.pdf', LinkKind::AssetReference],
    'bare asset reference' => ['asset::main::x.pdf', LinkKind::AssetReference],
    'term reference' => ['statamic://term::topics::cardiology', LinkKind::TermReference],
    'bare term reference' => ['term::topics::cardiology', LinkKind::TermReference],
    'mailto is non-http' => ['mailto:someone@uams.edu', LinkKind::NonHttp],
    'tel is non-http' => ['tel:+15015551234', LinkKind::NonHttp],
    'fragment only' => ['#main-content', LinkKind::FragmentOnly],
    'empty is not a link' => ['', null],
    'whitespace is not a link' => ['   ', null],
    'plain prose is not a link' => ['Cardiology Clinic', null],
    'bare uuid without a relation field is not a link' => [LC_KNOWN_ENTRY, null],
]);

// The acceptance criterion names this case explicitly: ownership and service are different
// questions, and collapsing them would send an editor chasing a third-party outage that is
// actually a UAMS property served from somewhere else.
it('separates a first-party host this install does not serve from an external one', function (): void {
    $classifier = lcClassifier();

    expect($classifier->classify('https://uamshealth.com/x'))->toBe(LinkKind::FirstPartyOther)
        ->and($classifier->classify('https://example.org/x'))->toBe(LinkKind::External)
        ->and($classifier->classify('https://www.uams.edu/x'))->toBe(LinkKind::Internal);
});

// Recognized by whole-string match against known ids, never by UUID shape — this repository
// stores plenty of UUID-shaped values that reference nothing.
it('classifies a bare id as a relation only inside an entries field', function (): void {
    $classifier = lcClassifier();

    expect($classifier->classify(LC_KNOWN_ENTRY, fromRelationField: true))->toBe(LinkKind::EntryRelation)
        ->and($classifier->classify(LC_KNOWN_ENTRY, fromRelationField: false))->toBeNull();
});

it('does not classify an unknown uuid as a relation even inside an entries field', function (): void {
    expect(lcClassifier()->classify('ffffffff-0000-4000-8000-00000000ffff', fromRelationField: true))->toBeNull();
});

// The empty guard is not merely tidy: without it an empty value reaches the relation check, and
// an id map that contains an empty key would classify nothing at all as an entry relation. Pinning
// it here is what makes the guard's purpose visible rather than incidental.
it('never classifies an empty value as a relation, whatever the id map holds', function (): void {
    $classifier = new LinkClassifier([], ['' => true]);

    expect($classifier->classify('', fromRelationField: true))->toBeNull()
        ->and($classifier->classify('   ', fromRelationField: true))->toBeNull();
});

it('trims surrounding whitespace before classifying', function (): void {
    expect(lcClassifier()->classify('  https://example.com/x  '))->toBe(LinkKind::External)
        ->and(lcClassifier()->classify("\n#anchor\t"))->toBe(LinkKind::FragmentOnly);
});

it('treats a scheme-bearing value with no host as not a link', function (): void {
    expect(lcClassifier()->classify('https://'))->toBeNull();
});

// The SCHEME is lower-cased before the http/https test, and dropping that would classify an
// upper-cased scheme as a non-HTTP link — RFC 3986 says the scheme is case-insensitive.
it('matches the scheme case-insensitively', function (): void {
    expect(lcClassifier()->classify('HTTPS://www.uams.edu/x'))->toBe(LinkKind::Internal)
        ->and(lcClassifier()->classify('HtTp://example.com/x'))->toBe(LinkKind::External)
        ->and(lcClassifier()->classify('MAILTO:someone@uams.edu'))->toBe(LinkKind::NonHttp);
});

// An authority-less URL parses to an EMPTY host rather than a missing one, so the guard needs
// both arms: a missing host and a present-but-empty host are different parse results.
it('treats an http url with an empty host as not a link', function (): void {
    expect(lcClassifier()->classify('http:///just-a-path'))->toBeNull()
        ->and(lcClassifier()->classify('https:///x'))->toBeNull();
});

it('matches the host case-insensitively', function (): void {
    expect(lcClassifier()->classify('https://WWW.UAMS.EDU/x'))->toBe(LinkKind::Internal);
});
