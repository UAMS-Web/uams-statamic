<?php

declare(strict_types=1);

use App\Http\Middleware\ProtectEntry;
use Illuminate\Support\Facades\Queue;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry as EntryFacade;
use Tests\Support\StacheIsolatedTestCase;

/**
 * End-to-end coverage that the cache-skip headers {@see ProtectEntry} sets reach
 * the response Statamic's static cache inspects, through the frontend route and,
 * for the refused private entry, through Laravel's exception rendering.
 *
 * The mock-based ProtectEntryTest reads the headers off the exception the
 * middleware throws; this drives a real request at a real entry so a handler that
 * dropped `abort()`'s headers, or a middleware order that rendered the 404 before
 * the gate, would show here.
 *
 * @covers \App\Http\Middleware\ProtectEntry
 *
 * @command  php artisan test --compact tests/Feature/Http/Middleware/ProtectEntryStaticCacheHeadersTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    Queue::fake();

    // The 404 template queries these collections; empty ones let it render in the
    // isolated Stache, as the page-builder render guard seeds them.
    foreach (['pages', 'posts', 'people', 'knowledge_bases'] as $handle) {
        Collection::make($handle)->save();
    }

    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::make('cache_probe');
    $collection->sites(['default']);
    $collection->routes('{slug}');
    $collection->save();
});

/**
 * Persist a published entry at `/{slug}` with the given gate fields.
 *
 * @param  array<string, mixed>  $data
 */
function cacheProbeEntry(string $slug, array $data): void
{
    /** @var Entry $entry */
    $entry = EntryFacade::make();
    $entry->collection('cache_probe');
    $entry->id('cache-probe-'.$slug);
    $entry->slug($slug);
    $entry->locale('default');
    $entry->published(true);
    $entry->data(['title' => 'Probe '.$slug] + $data);
    $entry->saveQuietly();
}

it('sends the private header with the 404 a guest gets for a private entry', function (): void {
    cacheProbeEntry('private-probe', ['status_and_visibility' => 'private']);

    $response = $this->get('/private-probe');

    $response->assertNotFound();
    $response->assertHeader('X-Statamic-Private', 'true');
});

it('sends the protected header with the password prompt', function (): void {
    cacheProbeEntry('locked-probe', ['password_protected' => true]);

    $response = $this->get('/locked-probe');

    $response->assertOk();
    $response->assertHeader('X-Statamic-Protected', 'true');
    $response->assertSee('Password Required');
});
