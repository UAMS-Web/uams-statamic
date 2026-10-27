<?php

declare(strict_types=1);

use App\Services\NewsRelease\NewsReleasePdfGenerator;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Vite as LaravelVite;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Assert;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Statamic\Assets\Asset;
use Statamic\Assets\AssetContainer;
use Statamic\Assets\UploadedReplacementFile;
use Statamic\Contracts\Globals\GlobalSet as GlobalSetContract;
use Statamic\Entries\Entry;
use Statamic\Facades\Antlers;
use Statamic\Facades\AssetContainer as AssetContainerFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Fields\Blueprint;
use Statamic\Globals\GlobalCollection;
use Statamic\Globals\Variables;
use Statamic\Sites\Site as SiteModel;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for {@see NewsReleasePdfGenerator}.
 *
 * Uses a mocked {@see Entry} so tests do not depend on a news release living
 * in `content/`. Multisite globals (`uams_multisite_settings`, etc.) still
 * load from Statamic globals where configured. Full HTML render may skip
 * when logo augmentation raises `assets_meta` without asset metadata DB.
 *
 * Mutation: 100% on App\Services\NewsRelease\NewsReleasePdfGenerator (7 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Services/NewsRelease/NewsReleasePdfGeneratorTest.php
 */

/**
 * Seed the `uams_multisite_settings` global into the cascade for the test.
 *
 * The repo stores actual global content in the `content/` git submodule.
 * CI checks out without initializing submodules, so `GlobalSet::all()`
 * resolves empty and the cascade does not expose `uams_multisite_settings`
 * to the PDF Antlers context. This helper builds an in-memory GlobalSet
 * with the fields these tests assert against, then stubs
 * `GlobalSet::all()` so {@see Cascade::hydrateGlobals()} iterates over it.
 */
function newsReleasePdfGeneratorStubMultisiteSettingsGlobal(): void
{
    // Resolve the default site for the localization the cascade will request
    /** @var SiteModel $site */
    $site = Site::default();
    $siteHandle = $site->handle();

    // Build a real GlobalSet (uses the live facade before any stubbing)
    $parent = GlobalSet::make('uams_multisite_settings');

    // Build a real Variables instance with the data the assertions rely on
    // (the setters mutate and return $this but are untyped, so call them as
    // statements rather than chaining to stay PHPStan-clean at max).
    $variables = new Variables;
    $variables->globalSet($parent);
    $variables->locale($siteHandle);
    $variables->data([
        'copyright_holder' => 'University of Arkansas for Medical Sciences',
        // The site-default boilerplate is now a `boilerplates`-taxonomy picker
        // (UAMS-Web/uams-statamic-archive#1184, terms_search_index, max_items 1): the flat `boilerplate_heading`
        // is retired, and the PDF template reads `uams_multisite_settings:boilerplate:heading`
        // / `:boilerplate:boilerplate` off the resolved term. Seed the field with the
        // seed `about-uams` term's slug so the picker augments to that term (heading
        // "About UAMS") from content/, keeping the "About UAMS" partial rendering.
        'boilerplate' => ['about-uams'],
    ]);

    // Stub the contract so cascade->in($siteHandle) returns the variables
    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')->andReturn($variables);

    // Stub only GlobalSet::all() — cascade reads globals through this call
    GlobalSet::shouldReceive('all')->andReturn(GlobalCollection::make([$set]));
}

/**
 * Swap the container's Vite manager for a no-op so the PDF template's
 * `{{ vite }}` / `{{ vite_asset }}` tags do not read `manifest.json`
 * (mirrors `InteractsWithContainer::withoutVite()`, which is unreachable
 * here because `$this` is a Pest TestCall under static analysis).
 */
function newsReleasePdfGeneratorDisableVite(): void
{
    // Only the terminal output methods read manifest.json; the fluent setters
    // just mutate and return $this, so leaving them to the parent is harmless.
    $fake = new class extends LaravelVite
    {
        public function toHtml(): string
        {
            return '';
        }

        public function asset($asset, $buildDirectory = null): string
        {
            return '';
        }

        public function content($asset, $buildDirectory = null): string
        {
            return '';
        }
    };

    app()->instance(LaravelVite::class, $fake);
}

/**
 * Build a mock news release entry for PDF context and rendering tests.
 *
 * Satisfies {@see NewsReleasePdfGenerator} calls to {@see Entry::site()},
 * {@see Entry::toDeferredAugmentedArray()}, {@see Entry::toAugmentedArray()},
 * and {@see Entry::get()} with stable field values.
 *
 * @return Entry&MockInterface
 */
function newsReleasePdfGeneratorMakeEntryMock(bool $siteResolves = true): Entry
{
    // Define minimal entry data shaped like augmented PDF template vars
    $entryData = [
        'title' => 'Mock News Release Title for PDF Test',
        'date' => Carbon::parse('2026-04-06 12:00:00'),
        'excerpt' => '',
        // Empty Bard list: head `if content` skips scope="block" asset detection
        'content' => [],
        'dateline' => 'LITTLE ROCK — ',
        'boilerplate_query' => true,
        'page_builder' => null,
        'template' => 'news_release',
    ];

    // Resolve the default site for cascade and URL origin logic
    $site = Site::default();

    // Build a strict mock of the concrete entry class
    $entry = Mockery::mock(Entry::class);

    // Provide site for cascade construction and current-site switching
    $entry->shouldReceive('site')->andReturn($siteResolves ? $site : null);

    // Feed cascade hydrateContent the same shape as deferred augmentation
    $entry->shouldReceive('toDeferredAugmentedArray')->andReturn($entryData);

    // Merge augmented entry fields after cascade data in buildPdfAntlersContext
    $entry->shouldReceive('toAugmentedArray')->andReturn($entryData);

    // Support direct field reads in assertions (signature matches Entry::get)
    $entry->shouldReceive('get')->andReturnUsing(
        fn (string $key, mixed $default = null): mixed => array_key_exists($key, $entryData)
            ? $entryData[$key]
            : $default
    );

    return $entry;
}

it('includes the multisite global key in the pdf antlers context', function () {
    // Seed the multisite global so the cascade exposes its handle on CI
    newsReleasePdfGeneratorStubMultisiteSettingsGlobal();

    // Build a mocked entry independent of repository content
    $entry = newsReleasePdfGeneratorMakeEntryMock();

    // Build the context
    $context = (new NewsReleasePdfGenerator)->buildPdfAntlersContext($entry);

    // Pass if the context contains the multisite global key
    expect($context)->toHaveKey('uams_multisite_settings');
});

it('resolves the global copyright holder from the pdf context via antlers', function () {
    // Seed the multisite global with the expected copyright holder fixture
    newsReleasePdfGeneratorStubMultisiteSettingsGlobal();

    // Build a mocked entry independent of repository content
    $entry = newsReleasePdfGeneratorMakeEntryMock();

    // Build the context
    $context = (new NewsReleasePdfGenerator)->buildPdfAntlersContext($entry);

    // Parse the copyright holder
    $html = trim((string) Antlers::parse(
        '{{ uams_multisite_settings:copyright_holder }}',
        $context,
        true
    ));

    // Pass if the copyright holder parses to the fixture value
    expect($html)->toBe('University of Arkansas for Medical Sciences');
});

it('renders press release html containing the core pdf template markup', function () {
    // Swap the Vite manager so `{{ vite_asset }}` does not read manifest.json
    newsReleasePdfGeneratorDisableVite();

    // Seed the multisite global so the cascade is populated for rendering
    newsReleasePdfGeneratorStubMultisiteSettingsGlobal();

    // Build a mocked entry independent of repository content
    $entry = newsReleasePdfGeneratorMakeEntryMock();

    // Attempt to render the press release HTML
    try {
        // Render the press release HTML
        $html = (new NewsReleasePdfGenerator)->renderPressReleaseHtml($entry);
    } catch (Throwable $e) {
        // Skip when the content/ submodule is absent: the full PDF template
        // augments global logo assets (needs the `logos` asset container) and
        // asset metadata (needs the asset_metadata DB). Both ship in content/,
        // so skip locally and let CI/local-ci run it where content is present.
        if (
            str_contains($e->getMessage(), 'assets_meta')
            || str_contains($e->getMessage(), 'Asset Container')
        ) {
            Assert::markTestSkipped(
                'Full PDF render needs the logos asset container / asset_metadata DB from the content/ submodule.'
            );
        }

        // Fail if the augmentation fails with an unexpected exception
        throw $e;
    }

    // Pass if the HTML contains the release line
    expect($html)->toContain('FOR IMMEDIATE RELEASE:');

    // Pass if the HTML contains the entry title
    expect($html)->toContain('Mock News Release Title for PDF Test');

    // Pass if the footer end marker is present (media contacts come from
    // `posts/components/media_contacts` when the entry has `media_contact`)
    expect($html)->toContain('class="end-marker"');
    expect($html)->toContain('###');

    // Pass if the HTML contains the about UAMS text
    expect($html)->toContain('About UAMS');

    // Mock entry has no `media_contact`; assert placeholder names are gone
    expect($html)->not->toContain('Yavonda Chase');
});

/**
 * A news release entry for the sync and removal paths, which read only its id, publish
 * date and slug.
 */
function newsReleaseSyncEntry(?CarbonInterface $date = null, ?string $slug = 'kept-slug', string|int|null $id = 'release-1'): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('date')->andReturn($date ?? Carbon::parse('2026-04-06 15:00:00', 'UTC'));
    $entry->shouldReceive('slug')->andReturn($slug);

    return $entry;
}

/**
 * The generator with its HTML render stubbed, so a sync never needs the full template.
 */
function newsReleaseSyncGenerator(): NewsReleasePdfGenerator
{
    $generator = Mockery::mock(NewsReleasePdfGenerator::class)->makePartial();
    $generator->shouldReceive('renderPressReleaseHtml')->andReturn('<p>Release</p>');

    return $generator;
}

/**
 * A news_releases asset tagged with the given owning entry.
 */
function newsReleasePdfStoredAsset(string $id, ?string $owner, string $path = 'other.pdf'): Asset&MockInterface
{
    $asset = Mockery::mock(Asset::class);
    $asset->shouldReceive('id')->andReturn($id);
    $asset->shouldReceive('path')->andReturn($path);
    $asset->shouldReceive('get')->with('news_release_entry')->andReturn($owner);

    return $asset;
}

/**
 * Collect every warning logged while the callback runs.
 *
 * @return list<array{0: string, 1: array<array-key, mixed>}>
 */
function newsReleaseWarnings(Closure $callback): array
{
    $warnings = [];
    Event::listen(MessageLogged::class, function (MessageLogged $logged) use (&$warnings): void {
        if ($logged->level === 'warning') {
            $warnings[] = [$logged->message, $logged->context];
        }
    });

    $callback();

    return $warnings;
}

it('applies to a posts entry on the news_release blueprint, and to nothing else', function (string $collection, ?string $blueprint, bool $expected) {
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collectionHandle')->andReturn($collection);

    if ($blueprint === null) {
        $entry->shouldReceive('blueprint')->andReturn(null);
    } else {
        $resolved = Mockery::mock(Blueprint::class);
        $resolved->shouldReceive('handle')->andReturn($blueprint);
        $entry->shouldReceive('blueprint')->andReturn($resolved);
    }

    expect((new NewsReleasePdfGenerator)->appliesTo($entry))->toBe($expected);
})->with([
    'a news release' => ['posts', 'news_release', true],
    'another posts blueprint' => ['posts', 'article', false],
    'no blueprint' => ['posts', null, false],
    'another collection' => ['pages', 'news_release', false],
]);

it('saves a letter PDF with zero margins, CSS page size and a letter-width window', function () {
    $pdf = Pdf::fake();
    AssetContainerFacade::shouldReceive('find')->with('news_releases')->andReturnNull();

    newsReleaseSyncGenerator()->syncPdfForEntry(newsReleaseSyncEntry());

    $pdf->assertSaved(function (PdfBuilder $saved, string $path): bool {
        $browsershot = Mockery::mock(Browsershot::class);
        $browsershot->shouldReceive('setOption')->once()->with('preferCSSPageSize', true);
        $browsershot->shouldReceive('windowSize')->once()->with(624, 1200);
        $customize = $saved->getCustomizeBrowsershotCallback();
        expect($customize)->not->toBeNull();
        $customize?->__invoke($browsershot);

        expect($saved->html)->toBe('<p>Release</p>')
            ->and((new ReflectionProperty($saved, 'format'))->getValue($saved))->toBe('letter')
            ->and((new ReflectionProperty($saved, 'margins'))->getValue($saved))->toBe(['top' => 0.0, 'right' => 0.0, 'bottom' => 0.0, 'left' => 0.0, 'unit' => 'mm'])
            ->and($path)->toEndWith('.pdf')
            // Written beside the allocated temporary file, never as a bare name in the working directory
            ->and(dirname($path))->not->toBe('.')
            // The temporary file is removed once the sync is done
            ->and(file_exists($path))->toBeFalse();

        return true;
    });
});

it('logs and stops when the container, the date or the slug is missing', function (?Closure $entry, bool $container, string $message, array $context) {
    Pdf::fake();
    AssetContainerFacade::shouldReceive('find')->with('news_releases')->andReturn($container ? Mockery::mock(AssetContainer::class) : null);
    EntryFacade::shouldReceive('find')->never();

    $syncEntry = mustBe(Entry::class, $entry instanceof Closure ? $entry() : newsReleaseSyncEntry(), 'the entry');
    $warnings = newsReleaseWarnings(fn () => newsReleaseSyncGenerator()->syncPdfForEntry($syncEntry));

    expect(array_column($warnings, 0))->toBe([$message])
        ->and($warnings[0][1])->toMatchArray(['entry_id' => 'release-1', ...$context]);
})->with([
    'no container' => [null, false, 'NewsReleasePdfGenerator: asset container not found', ['container' => 'news_releases']],
    'no publish date' => [fn (): Entry => newsReleaseSyncEntryWithoutDate(), true, 'NewsReleasePdfGenerator: could not build PDF storage path', []],
    'no slug' => [fn (): Entry => newsReleaseSyncEntry(null, ''), true, 'NewsReleasePdfGenerator: could not build PDF storage path', []],
]);

/**
 * A news release entry with no publish date.
 */
function newsReleaseSyncEntryWithoutDate(): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn('release-1');
    $entry->shouldReceive('date')->andReturnNull();
    $entry->shouldReceive('slug')->andReturn('kept-slug');

    return $entry;
}

it('uploads a new PDF at the display-timezone date and slug, tags it, removes superseded ones, and attaches it', function () {
    Pdf::fake();
    config(['statamic.system.display_timezone' => 'America/Chicago']);

    // 03:00 UTC is the evening before in Chicago, so the path takes that date
    $entry = newsReleaseSyncEntry(Carbon::parse('2026-04-07 03:00:00', 'UTC'));

    $uploaded = newsReleasePdfStoredAsset('news_releases::2026-04-06_kept-slug.pdf', 'release-1', '2026-04-06_kept-slug.pdf');
    $uploaded->shouldReceive('set')->once()->with('news_release_entry', 'release-1');
    $uploaded->shouldReceive('saveQuietly')->once();

    $made = Mockery::mock(Asset::class);
    // A test-mode upload, so it is valid although PHP did not receive it over HTTP
    $made->shouldReceive('upload')->once()->with(Mockery::on(
        fn (UploadedFile $file): bool => $file->isValid()
            && $file->getClientOriginalName() === '2026-04-06_kept-slug.pdf'
            && $file->getClientMimeType() === 'application/pdf'
    ))->andReturn($uploaded);

    $superseded = newsReleasePdfStoredAsset('news_releases::2025-01-01_kept-slug.pdf', 'release-1');
    $superseded->shouldReceive('deleteQuietly')->once();
    $stuck = newsReleasePdfStoredAsset('news_releases::stuck.pdf', 'release-1');
    $stuck->shouldReceive('deleteQuietly')->once()->andThrow(new RuntimeException('disk is read-only'));
    $others = newsReleasePdfStoredAsset('news_releases::other.pdf', 'release-2');
    $others->shouldReceive('deleteQuietly')->never();

    $container = Mockery::mock(AssetContainer::class);
    $container->shouldReceive('asset')->with('2026-04-06_kept-slug.pdf')->andReturnNull();
    $container->shouldReceive('makeAsset')->once()->with('2026-04-06_kept-slug.pdf')->andReturn($made);
    // Another entry's PDF ahead of this entry's, so the sweep must pass over it and go on
    $container->shouldReceive('assets')->andReturn(collect([$uploaded, $others, $superseded, $stuck]));
    AssetContainerFacade::shouldReceive('find')->with('news_releases')->andReturn($container);

    $persisted = Mockery::mock(Entry::class);
    $persisted->shouldReceive('set')->once()->with('news_release_pdf', ['2026-04-06_kept-slug.pdf']);
    $persisted->shouldReceive('saveQuietly')->once();
    EntryFacade::shouldReceive('find')->once()->with('release-1')->andReturn($persisted);

    $warnings = newsReleaseWarnings(fn () => newsReleaseSyncGenerator()->syncPdfForEntry($entry));

    expect($warnings)->toBe([[
        'NewsReleasePdfGenerator: failed deleting superseded PDF asset',
        ['entry_id' => 'release-1', 'asset_id' => 'news_releases::stuck.pdf', 'message' => 'disk is read-only'],
    ]]);
});

it('re-uploads over an existing PDF at the same path', function () {
    Pdf::fake();
    $existing = newsReleasePdfStoredAsset('news_releases::2026-04-06_kept-slug.pdf', 'release-1', '2026-04-06_kept-slug.pdf');
    $existing->shouldReceive('reupload')->once()->with(Mockery::type(UploadedReplacementFile::class));
    $existing->shouldReceive('set')->once()->with('news_release_entry', 'release-1');
    $existing->shouldReceive('saveQuietly')->once();

    $container = Mockery::mock(AssetContainer::class);
    $container->shouldReceive('asset')->with('2026-04-06_kept-slug.pdf')->andReturn($existing);
    $container->shouldReceive('makeAsset')->never();
    $container->shouldReceive('assets')->andReturn(collect([$existing]));
    AssetContainerFacade::shouldReceive('find')->with('news_releases')->andReturn($container);

    $persisted = Mockery::mock(Entry::class);
    $persisted->shouldReceive('set')->once()->with('news_release_pdf', ['2026-04-06_kept-slug.pdf']);
    $persisted->shouldReceive('saveQuietly')->once();
    EntryFacade::shouldReceive('find')->once()->with('release-1')->andReturn($persisted);

    $warnings = newsReleaseWarnings(fn () => newsReleaseSyncGenerator()->syncPdfForEntry(newsReleaseSyncEntry()));

    expect($warnings)->toBe([]);
});

it('logs and attaches nothing when the entry cannot be reloaded', function () {
    Pdf::fake();
    $uploaded = newsReleasePdfStoredAsset('news_releases::2026-04-06_kept-slug.pdf', 'release-1', '2026-04-06_kept-slug.pdf');
    $uploaded->shouldReceive('set', 'saveQuietly');
    $made = Mockery::mock(Asset::class);
    $made->shouldReceive('upload')->andReturn($uploaded);

    $container = Mockery::mock(AssetContainer::class);
    $container->shouldReceive('asset')->andReturnNull();
    $container->shouldReceive('makeAsset')->andReturn($made);
    $container->shouldReceive('assets')->andReturn(collect([$uploaded]));
    AssetContainerFacade::shouldReceive('find')->with('news_releases')->andReturn($container);
    EntryFacade::shouldReceive('find')->once()->with('release-1')->andReturnNull();

    $warnings = newsReleaseWarnings(fn () => newsReleaseSyncGenerator()->syncPdfForEntry(newsReleaseSyncEntry()));

    expect($warnings)->toBe([['NewsReleasePdfGenerator: entry not found when attaching PDF', ['entry_id' => 'release-1']]]);
});

it('logs a failure while storing the PDF instead of throwing', function () {
    Pdf::fake();
    AssetContainerFacade::shouldReceive('find')->with('news_releases')->andThrow(new RuntimeException('container store is down'));

    $warnings = newsReleaseWarnings(fn () => newsReleaseSyncGenerator()->syncPdfForEntry(newsReleaseSyncEntry()));

    expect($warnings)->toBe([['NewsReleasePdfGenerator: failed to generate or store PDF', ['entry_id' => 'release-1', 'message' => 'container store is down']]]);
});

it('removes every PDF tagged for the entry, logging one it cannot delete, and leaves the rest', function () {
    $mine = newsReleasePdfStoredAsset('news_releases::mine.pdf', 'release-1');
    $mine->shouldReceive('deleteQuietly')->once();
    $stuck = newsReleasePdfStoredAsset('news_releases::stuck.pdf', 'release-1');
    $stuck->shouldReceive('deleteQuietly')->once()->andThrow(new RuntimeException('locked'));
    $theirs = newsReleasePdfStoredAsset('news_releases::theirs.pdf', 'release-2');
    $theirs->shouldReceive('deleteQuietly')->never();

    $container = Mockery::mock(AssetContainer::class);
    // Another entry's PDF first, so the sweep must pass over it and go on
    $container->shouldReceive('assets')->andReturn(collect([$theirs, $mine, $stuck]));
    AssetContainerFacade::shouldReceive('find')->with('news_releases')->andReturn($container);

    $warnings = newsReleaseWarnings(fn () => (new NewsReleasePdfGenerator)->removePdfForEntry(newsReleaseSyncEntry()));

    expect($warnings)->toBe([['NewsReleasePdfGenerator: failed deleting PDF on removal', ['entry_id' => 'release-1', 'asset_id' => 'news_releases::stuck.pdf', 'message' => 'locked']]]);
});

it('removes nothing when there is no news releases container', function () {
    AssetContainerFacade::shouldReceive('find')->with('news_releases')->andReturnNull();

    $warnings = newsReleaseWarnings(fn () => (new NewsReleasePdfGenerator)->removePdfForEntry(newsReleaseSyncEntry()));

    expect($warnings)->toBe([]);
});

it('builds the context on the default site when the entry\'s site does not resolve', function () {
    newsReleasePdfGeneratorStubMultisiteSettingsGlobal();

    $context = (new NewsReleasePdfGenerator)->buildPdfAntlersContext(newsReleasePdfGeneratorMakeEntryMock(siteResolves: false));

    expect($context)->toHaveKey('uams_multisite_settings')
        ->and($context['title'] ?? null)->toBe('Mock News Release Title for PDF Test');
});

it('renders on the default site when the entry\'s site does not resolve, and restores the current site', function () {
    newsReleasePdfGeneratorDisableVite();
    newsReleasePdfGeneratorStubMultisiteSettingsGlobal();
    $before = mustBe(SiteModel::class, Site::current(), 'the current site')->handle();

    $html = (new NewsReleasePdfGenerator)->renderPressReleaseHtml(newsReleasePdfGeneratorMakeEntryMock(siteResolves: false));

    expect($html)->toContain('Mock News Release Title for PDF Test')
        ->and(mustBe(SiteModel::class, Site::current(), 'the current site')->handle())->toBe($before);
});

it('takes the asset origin from the site URL, falling back to the app URL', function (mixed $siteUrl, string $expected) {
    config(['app.url' => 'https://app.example.test/']);

    $site = Mockery::mock(SiteModel::class);
    $site->shouldReceive('absoluteUrl')->andReturn($siteUrl);
    $origin = new ReflectionMethod(NewsReleasePdfGenerator::class, 'siteOrigin');

    expect($origin->invoke(new NewsReleasePdfGenerator, $site))->toBe($expected);
})->with([
    'scheme and host only' => ['https://news.example.test/press/', 'https://news.example.test'],
    'a non-default port' => ['http://news.example.test:8443/', 'http://news.example.test:8443'],
    'no scheme' => ['news.example.test/press', 'https://app.example.test'],
    'a host and no scheme' => ['//news.example.test/press', 'https://app.example.test'],
    'a scheme and no host' => ['mailto:press@example.test', 'https://app.example.test'],
    'unparseable' => ['http:///press', 'https://app.example.test'],
    'not a string' => [null, 'https://app.example.test'],
    'a number' => [42, 'https://app.example.test'],
]);

it('uses an app URL that is missing or a number as the fallback origin', function (mixed $appUrl, string $expected) {
    config(['app.url' => $appUrl]);

    $site = Mockery::mock(SiteModel::class);
    $site->shouldReceive('absoluteUrl')->andReturn('/relative-only');
    $origin = new ReflectionMethod(NewsReleasePdfGenerator::class, 'siteOrigin');

    expect($origin->invoke(new NewsReleasePdfGenerator, $site))->toBe($expected);
})->with([
    'missing' => [null, ''],
    'a number' => [42, '42'],
]);

it('makes root-relative src attributes absolute and leaves the rest', function () {
    $absolutize = new ReflectionMethod(NewsReleasePdfGenerator::class, 'absolutizeRootRelativeSrcAttributes');

    $html = '<img src="/logo.png"> <img src="https://cdn.test/x.png"> <img data-src="/lazy.png">';

    expect($absolutize->invoke(new NewsReleasePdfGenerator, $html, 'https://news.example.test'))
        ->toBe('<img src="https://news.example.test/logo.png"> <img src="https://cdn.test/x.png"> <img data-src="/lazy.png">');
});

it('lists the stored asset path for the entry field, or an empty path', function (mixed $path, string $expected) {
    $asset = Mockery::mock(Asset::class);
    $asset->shouldReceive('path')->andReturn($path);
    $raw = new ReflectionMethod(NewsReleasePdfGenerator::class, 'rawNewsReleasePdfFieldValue');

    expect($raw->invoke(new NewsReleasePdfGenerator, $asset))->toBe([$expected]);
})->with([
    'a path' => ['2026-04-06_kept-slug.pdf', '2026-04-06_kept-slug.pdf'],
    'a number' => [7, '7'],
    'no path' => [null, ''],
]);

it('builds the context on the entry\'s own site, with its augmented fields over the cascade', function () {
    newsReleasePdfGeneratorStubMultisiteSettingsGlobal();
    $health = mustBe(SiteModel::class, Site::get('health'), 'the health site');

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('site')->andReturn($health);
    $entry->shouldReceive('toDeferredAugmentedArray')->andReturn(['title' => 'Cascade title']);
    $entry->shouldReceive('toAugmentedArray')->andReturn(['title' => 'Augmented title']);

    $context = (new NewsReleasePdfGenerator)->buildPdfAntlersContext($entry);

    expect($context['title'] ?? null)->toBe('Augmented title')
        ->and(mustBe(SiteModel::class, $context['site'] ?? null, 'the context site')->handle())->toBe('health');
});

it('renders trusted Antlers on the entry\'s own site, then restores the current site', function () {
    Site::setCurrent('default');
    $health = mustBe(SiteModel::class, Site::get('health'), 'the health site');
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('site')->andReturn($health);

    $renderedOn = null;
    $generator = Mockery::mock(NewsReleasePdfGenerator::class)->makePartial();
    $generator->shouldReceive('buildPdfAntlersContext')->once()->andReturnUsing(function () use (&$renderedOn): array {
        $renderedOn = mustBe(SiteModel::class, Site::current(), 'the current site')->handle();

        return ['probe' => 'context'];
    });
    Antlers::shouldReceive('parse')->once()
        ->with(file_get_contents(resource_path('views/pdf/news_release.antlers.html')), ['probe' => 'context'], true)
        ->andReturn('<img src="/logo.png">');

    $html = $generator->renderPressReleaseHtml($entry);

    expect($renderedOn)->toBe('health')
        ->and($html)->toBe('<img src="'.rtrim(mustBeString(config('app.url'), 'the app URL'), '/').'/logo.png">')
        ->and(mustBe(SiteModel::class, Site::current(), 'the current site')->handle())->toBe('default');
});

it('tags and attaches the PDF under the entry id as text, empty when the entry has none', function (?int $id, string $owner) {
    Pdf::fake();
    $uploaded = newsReleasePdfStoredAsset('news_releases::2026-04-06_kept-slug.pdf', $owner, '2026-04-06_kept-slug.pdf');
    $uploaded->shouldReceive('set')->once()->with('news_release_entry', $owner);
    $uploaded->shouldReceive('saveQuietly')->once();
    $made = Mockery::mock(Asset::class);
    $made->shouldReceive('upload')->andReturn($uploaded);

    $container = Mockery::mock(AssetContainer::class);
    $container->shouldReceive('asset')->andReturnNull();
    $container->shouldReceive('makeAsset')->andReturn($made);
    $container->shouldReceive('assets')->andReturn(collect([$uploaded]));
    AssetContainerFacade::shouldReceive('find')->with('news_releases')->andReturn($container);
    EntryFacade::shouldReceive('find')->once()->with($id)->andReturnNull();

    $warnings = newsReleaseWarnings(fn () => newsReleaseSyncGenerator()->syncPdfForEntry(newsReleaseSyncEntry(null, 'kept-slug', $id)));

    expect(array_column($warnings, 0))->toBe(['NewsReleasePdfGenerator: entry not found when attaching PDF']);
})->with([
    'a numeric id' => [42, '42'],
    'no id' => [null, ''],
]);

it('removes the PDFs tagged with the entry id as text, empty when the entry has none', function (?int $id, string $owner) {
    $mine = newsReleasePdfStoredAsset('news_releases::mine.pdf', $owner);
    $mine->shouldReceive('deleteQuietly')->once();
    $untagged = newsReleasePdfStoredAsset('news_releases::untagged.pdf', null);
    $untagged->shouldReceive('deleteQuietly')->never();

    $container = Mockery::mock(AssetContainer::class);
    $container->shouldReceive('assets')->andReturn(collect([$untagged, $mine]));
    AssetContainerFacade::shouldReceive('find')->with('news_releases')->andReturn($container);

    (new NewsReleasePdfGenerator)->removePdfForEntry(newsReleaseSyncEntry(null, 'kept-slug', $id));
})->with([
    'a numeric id' => [42, '42'],
    'no id' => [null, ''],
]);
