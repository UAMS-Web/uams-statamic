<?php

declare(strict_types=1);

use App\Support\Redirects\DeletedEntryRedirectCapture;
use Illuminate\Support\Facades\Facade;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Globals\GlobalSet as GlobalSetContract;
use Statamic\Entries\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Globals\Variables;
use Statamic\Sites\Site as SiteObject;

/**
 * Pure unit tests for DeletedEntryRedirectCapture.
 *
 * Covers every guard, site-resolution fallback, duplicate check, persistence call,
 * and replicator row field without booting the Statamic application.
 *
 * @command  vendor/bin/pest tests/Unit/Support/Redirects/DeletedEntryRedirectCaptureTest.php
 */
uses(MockeryPHPUnitIntegration::class);

covers(DeletedEntryRedirectCapture::class);

afterEach(function (): void {
    Facade::clearResolvedInstances();
});

/**
 * Swap the GlobalSet facade with a mock repository returning the given global set.
 */
function swapRedirectsGlobal(?GlobalSetContract $global): void
{
    $repo = Mockery::mock();
    $repo->shouldReceive('findByHandle')
        ->once()
        ->with('redirects')
        ->andReturn($global);

    GlobalSet::swap($repo);
}

/**
 * Swap the Site facade with a mock returning the given default site.
 */
function swapDefaultSite(mixed $defaultSite): void
{
    $sites = Mockery::mock();
    $sites->shouldReceive('default')
        ->once()
        ->andReturn($defaultSite);

    Site::swap($sites);
}

/**
 * Build a mock Entry with the given URI, locale, and ID.
 */
function makeMockDeletedEntry(mixed $uri, mixed $locale = 'default', mixed $id = 'entry-123'): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('uri')->andReturn($uri);
    $entry->shouldReceive('locale')->andReturn($locale);
    $entry->shouldReceive('id')->andReturn($id);

    return $entry;
}

it('returns zero when the redirects global set does not exist', function (): void {
    swapRedirectsGlobal(null);

    $entry = Mockery::mock(Entry::class);

    expect((new DeletedEntryRedirectCapture)->captureFor($entry))->toBe(0);
});

it('returns zero when the deleted entry has no usable URI', function (mixed $uri): void {
    $global = Mockery::mock(GlobalSetContract::class);
    swapRedirectsGlobal($global);

    $entry = makeMockDeletedEntry($uri);

    expect((new DeletedEntryRedirectCapture)->captureFor($entry))->toBe(0);
})->with([
    'null' => [null],
    'empty string' => [''],
    'non-string' => [123],
]);

it('returns zero when the target site has no localization on the redirects global', function (): void {
    $global = Mockery::mock(GlobalSetContract::class);
    $global->shouldReceive('in')
        ->once()
        ->with('second')
        ->andReturn(null);

    swapRedirectsGlobal($global);
    swapDefaultSite(null);

    $entry = makeMockDeletedEntry('/services/cardiology', 'second');

    expect((new DeletedEntryRedirectCapture)->captureFor($entry))->toBe(0);
});

it('writes a complete disabled redirect row and saves the global variables', function (): void {
    $variables = Mockery::mock(Variables::class);
    $variables->shouldReceive('get')
        ->once()
        ->with('redirects', [])
        ->andReturn([]);

    $capturedRows = null;
    $variables->shouldReceive('set')
        ->once()
        ->withArgs(function (string $key, array $rows) use (&$capturedRows): bool {
            $capturedRows = $rows;

            return $key === 'redirects';
        });
    $variables->shouldReceive('save')->once();

    $global = Mockery::mock(GlobalSetContract::class);
    $global->shouldReceive('in')
        ->once()
        ->with('second')
        ->andReturn($variables);

    $defaultSite = Mockery::mock(SiteObject::class);
    $defaultSite->shouldReceive('handle')->andReturn('main');

    swapRedirectsGlobal($global);
    swapDefaultSite($defaultSite);

    $entry = makeMockDeletedEntry('/services/cardiology', 'second', 'entry-42');

    expect((new DeletedEntryRedirectCapture)->captureFor($entry))->toBe(1)
        ->and($capturedRows)->toBe([
            [
                'type' => 'redirect',
                'enabled' => false,
                'source' => '/services/cardiology',
                'use_regex' => false,
                'target_type' => 'url',
                'target_url' => '',
                'notes' => 'Captured automatically when entry entry-42 was deleted. Set a target and enable this redirect.',
            ],
        ]);
});

it('falls back to the default site handle when the entry locale is empty or non-string', function (mixed $locale): void {
    $variables = Mockery::mock(Variables::class);
    $variables->shouldReceive('get')->once()->with('redirects', [])->andReturn([]);
    $variables->shouldReceive('set')->once();
    $variables->shouldReceive('save')->once();

    $global = Mockery::mock(GlobalSetContract::class);
    $global->shouldReceive('in')
        ->once()
        ->with('main')
        ->andReturn($variables);

    $defaultSite = Mockery::mock(SiteObject::class);
    $defaultSite->shouldReceive('handle')->once()->andReturn('main');

    swapRedirectsGlobal($global);
    swapDefaultSite($defaultSite);

    $entry = makeMockDeletedEntry('/about', $locale);

    expect((new DeletedEntryRedirectCapture)->captureFor($entry))->toBe(1);
})->with([
    'empty string' => [''],
    'null' => [null],
]);

it('falls back to the literal default handle when no default site handle is available', function (bool $hasSite): void {
    $variables = Mockery::mock(Variables::class);
    $variables->shouldReceive('get')->once()->with('redirects', [])->andReturn([]);
    $variables->shouldReceive('set')->once();
    $variables->shouldReceive('save')->once();

    $global = Mockery::mock(GlobalSetContract::class);
    $global->shouldReceive('in')
        ->once()
        ->with('default')
        ->andReturn($variables);

    $defaultSite = null;
    if ($hasSite) {
        $defaultSite = Mockery::mock(SiteObject::class);
        $defaultSite->shouldReceive('handle')->once()->andReturn(123);
    }

    swapRedirectsGlobal($global);
    swapDefaultSite($defaultSite);

    $entry = makeMockDeletedEntry('/about', '');

    expect((new DeletedEntryRedirectCapture)->captureFor($entry))->toBe(1);
})->with([
    'null default site' => [false],
    'non-string default handle' => [true],
]);

it('treats a non-array redirects value as empty and appends the captured row', function (): void {
    $variables = Mockery::mock(Variables::class);
    $variables->shouldReceive('get')->once()->with('redirects', [])->andReturn('corrupt');

    $capturedRows = null;
    $variables->shouldReceive('set')
        ->once()
        ->withArgs(function (string $key, array $rows) use (&$capturedRows): bool {
            $capturedRows = $rows;

            return $key === 'redirects';
        });
    $variables->shouldReceive('save')->once();

    $global = Mockery::mock(GlobalSetContract::class);
    $global->shouldReceive('in')->once()->with('default')->andReturn($variables);

    swapRedirectsGlobal($global);
    swapDefaultSite(null);

    $entry = makeMockDeletedEntry('/about', 'default', 'entry-9');

    expect((new DeletedEntryRedirectCapture)->captureFor($entry))->toBe(1)
        ->and($capturedRows)->toBe([
            [
                'type' => 'redirect',
                'enabled' => false,
                'source' => '/about',
                'use_regex' => false,
                'target_type' => 'url',
                'target_url' => '',
                'notes' => 'Captured automatically when entry entry-9 was deleted. Set a target and enable this redirect.',
            ],
        ]);
});

it('does not write a duplicate row when the URI already exists in redirects', function (): void {
    $variables = Mockery::mock(Variables::class);
    $variables->shouldReceive('get')
        ->once()
        ->with('redirects', [])
        ->andReturn([
            ['source' => '/other'],
            ['source' => '/about'],
        ]);
    $variables->shouldNotReceive('set');
    $variables->shouldNotReceive('save');

    $global = Mockery::mock(GlobalSetContract::class);
    $global->shouldReceive('in')->once()->with('default')->andReturn($variables);

    swapRedirectsGlobal($global);
    swapDefaultSite(null);

    $entry = makeMockDeletedEntry('/about', 'default');

    expect((new DeletedEntryRedirectCapture)->captureFor($entry))->toBe(0);
});

it('appends a new row alongside non-matching and non-array existing entries', function (): void {
    $variables = Mockery::mock(Variables::class);
    $variables->shouldReceive('get')
        ->once()
        ->with('redirects', [])
        ->andReturn([
            'not-an-array',
            ['source' => '/other'],
        ]);

    $capturedRows = null;
    $variables->shouldReceive('set')
        ->once()
        ->withArgs(function (string $key, array $rows) use (&$capturedRows): bool {
            $capturedRows = $rows;

            return $key === 'redirects';
        });
    $variables->shouldReceive('save')->once();

    $global = Mockery::mock(GlobalSetContract::class);
    $global->shouldReceive('in')->once()->with('default')->andReturn($variables);

    swapRedirectsGlobal($global);
    swapDefaultSite(null);

    $entry = makeMockDeletedEntry('/about', 'default', 'entry-7');

    expect((new DeletedEntryRedirectCapture)->captureFor($entry))->toBe(1)
        ->and($capturedRows)->toBe([
            'not-an-array',
            ['source' => '/other'],
            [
                'type' => 'redirect',
                'enabled' => false,
                'source' => '/about',
                'use_regex' => false,
                'target_type' => 'url',
                'target_url' => '',
                'notes' => 'Captured automatically when entry entry-7 was deleted. Set a target and enable this redirect.',
            ],
        ]);
});

it('records unknown in notes when the deleted entry ID is not scalar', function (): void {
    $variables = Mockery::mock(Variables::class);
    $variables->shouldReceive('get')->once()->with('redirects', [])->andReturn([]);

    $capturedRows = null;
    $variables->shouldReceive('set')
        ->once()
        ->withArgs(function (string $key, array $rows) use (&$capturedRows): bool {
            $capturedRows = $rows;

            return $key === 'redirects';
        });
    $variables->shouldReceive('save')->once();

    $global = Mockery::mock(GlobalSetContract::class);
    $global->shouldReceive('in')->once()->with('default')->andReturn($variables);

    swapRedirectsGlobal($global);
    swapDefaultSite(null);

    $entry = makeMockDeletedEntry('/about', 'default', null);

    expect((new DeletedEntryRedirectCapture)->captureFor($entry))->toBe(1)
        ->and($capturedRows)->toBe([
            [
                'type' => 'redirect',
                'enabled' => false,
                'source' => '/about',
                'use_regex' => false,
                'target_type' => 'url',
                'target_url' => '',
                'notes' => 'Captured automatically when entry unknown was deleted. Set a target and enable this redirect.',
            ],
        ]);
});
