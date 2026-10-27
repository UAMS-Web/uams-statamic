<?php

declare(strict_types=1);

use App\Support\Redirects\MigrationRedirect;

covers(MigrationRedirect::class);

/**
 * Unit tests for the MigrationRedirect value object (UAMS-Web/uams-statamic-archive#1641).
 *
 * Pins the two dispositions (a `301` move and a `410` gone) and the `redirect`
 * replicator row they serialize to — the exact keys FindRedirectRule reads, with
 * a grandfathered-approved `status` so a generated migration rule serves without
 * human review. Also pins the pattern variants (UAMS-Web/uams-statamic-archive#1657), whose source is a PCRE
 * pattern that must survive storage and keying *un*-canonicalized.
 *
 * Mutation: tracked under App\Support\Redirects\MigrationRedirect (--covered-only).
 *
 * @command  php artisan test --compact tests/Unit/Support/Redirects/MigrationRedirectTest.php
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
it('builds a 301 move with a canonicalized source and explicit target', function (): void {
    $redirect = MigrationRedirect::moved('/old-path/', 'https://example.test/new-path', 'note');

    expect($redirect->source)->toBe('/old-path')
        ->and($redirect->target)->toBe('https://example.test/new-path')
        ->and($redirect->response)->toBe('301')
        ->and($redirect->notes)->toBe('note');
});

it('builds a 410 gone with no target', function (): void {
    $redirect = MigrationRedirect::gone('/retired/', 'gone note');

    expect($redirect->source)->toBe('/retired')
        ->and($redirect->target)->toBeNull()
        ->and($redirect->response)->toBe('410')
        ->and($redirect->notes)->toBe('gone note');
});

it('serializes a 301 to an approved, enabled url-target redirect row', function (): void {
    $row = MigrationRedirect::moved('/old', '/new')->toRow('fixed-id');

    expect($row)->toMatchArray([
        'id' => 'fixed-id',
        'type' => 'redirect',
        'enabled' => true,
        'status' => 'approved',
        'source' => '/old',
        'use_regex' => false,
        'target_type' => 'url',
        'target_url' => '/new',
        'response' => '301',
        'order' => MigrationRedirect::DEFAULT_ORDER,
    ]);
});

it('serializes a 410 to an approved row whose target is null', function (): void {
    $row = MigrationRedirect::gone('/retired')->toRow('gone-id');

    expect($row['response'])->toBe('410')
        ->and($row['target_url'])->toBeNull()
        ->and($row['status'])->toBe('approved')
        ->and($row['enabled'])->toBeTrue();
});

it('carries the provenance note onto the stored row', function (): void {
    $row = MigrationRedirect::moved('/old', '/new', 'why it moved')->toRow('id');

    expect($row['notes'])->toBe('why it moved');
});

it('mints a fresh id when none is supplied', function (): void {
    $first = MigrationRedirect::moved('/a', '/b')->toRow();
    $second = MigrationRedirect::moved('/a', '/b')->toRow();

    expect($first['id'])->not->toBe('');
    expect($first['id'])->not->toBe($second['id']);
});

it('derives a case-insensitive collision key from the source', function (): void {
    expect(MigrationRedirect::moved('/Old-Path', '/new')->collisionKey())->toBe('/old-path');
});

it('stores a pattern source verbatim rather than canonicalizing it (#1657)', function (): void {
    // Canonicalizing would strip the `#` delimiters and graft on a leading slash,
    // leaving a source that is no longer a valid PCRE.
    $pattern = '#^/(?:.+/)?page/[0-9]+/?$#i';
    $row = MigrationRedirect::movedPattern($pattern, '/news', 'pagination')->toRow('pattern-id');

    expect($row['source'])->toBe($pattern)
        ->and($row['use_regex'])->toBeTrue()
        ->and($row['target_url'])->toBe('/news')
        ->and($row['response'])->toBe('301')
        ->and(preg_match($row['source'], '/news/page/2'))->toBe(1);
});

it('builds a 410 pattern with no target', function (): void {
    $redirect = MigrationRedirect::gonePattern('#^/x$#', 'gone pattern');

    expect($redirect->source)->toBe('#^/x$#')
        ->and($redirect->target)->toBeNull()
        ->and($redirect->response)->toBe('410')
        ->and($redirect->useRegex)->toBeTrue()
        ->and($redirect->toRow('id')['use_regex'])->toBeTrue();
});

it('keys a pattern under its own namespace so it never collides with a path', function (): void {
    // A pattern and a literal are different rules even when they read alike; the
    // generator keys STORED rows through the same method, which is what keeps a
    // re-run from re-adding every pattern rule.
    $pattern = MigrationRedirect::movedPattern('#^/Page/[0-9]+$#', '/news');

    expect($pattern->collisionKey())->toBe('regex:#^/page/[0-9]+$#')
        ->and(MigrationRedirect::keyFor('#^/Page/[0-9]+$#', true))->toBe($pattern->collisionKey())
        ->and(MigrationRedirect::keyFor('/Old-Path', false))->toBe('/old-path');
});
