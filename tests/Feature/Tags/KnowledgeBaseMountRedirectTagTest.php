<?php

declare(strict_types=1);

use App\Services\KnowledgeBase\KnowledgeBaseMountRedirector;
use App\Tags\KnowledgeBaseMountRedirect;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Pin the pair tag's redirect rows and its use of the current request.
 *
 * Linux, PHP 8.4.27, PCOV: 5/5 covered mutants killed with no survivors.
 *
 * @command  php artisan test --compact tests/Feature/Tags/KnowledgeBaseMountRedirectTagTest.php
 */
uses(TestCase::class);

covers(KnowledgeBaseMountRedirect::class);

it('yields the mounted target as one pair-tag row for the current request', function (): void {
    // Distinguish the active request from any fabricated or stale request.
    $request = Request::create('https://example.test/old-kb/topic?view=all');
    app()->instance('request', $request);
    $redirector = Mockery::mock(KnowledgeBaseMountRedirector::class);
    $redirector->shouldReceive('target')->andReturnUsing(
        static fn (Request $candidate): ?string => $candidate === $request ? '/mounted/kb/topic' : null
    );

    // Assert the public pair-tag shape, not the resolver's implementation.
    expect(new KnowledgeBaseMountRedirect($redirector)->index())
        ->toBe([['target' => '/mounted/kb/topic']]);
});

it('emits no rows when the request has no mounted target', function (): void {
    // Leave unmatched requests available to the surrounding 404 template.
    $redirector = Mockery::mock(KnowledgeBaseMountRedirector::class);
    $redirector->shouldReceive('target')->andReturnNull();

    expect(new KnowledgeBaseMountRedirect($redirector)->index())->toBe([]);
});
