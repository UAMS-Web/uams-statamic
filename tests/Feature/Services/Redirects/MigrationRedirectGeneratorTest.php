<?php

declare(strict_types=1);

use App\Services\Redirects\MarketingRedirectStore;
use App\Services\Redirects\MigrationRedirectGenerator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Statamic\Assets\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Globals\Variables;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Feature coverage for {@see MigrationRedirectGenerator} (UAMS-Web/uams-statamic-archive#1641).
 *
 * Runs the generator against a hermetic Stache ({@see StacheIsolatedTestCase}):
 * a routed `probe` collection and a faked asset container stand in for imported
 * content, so the generator iterates only these fixtures. Proves the deterministic
 * core — a `301` for every entity whose URL changed, the retired-permalink
 * disposition (`301`-to-ancestor / `410`), idempotent re-runs, and that a
 * human-authored redirect for the same source is never clobbered.
 *
 * @command  php artisan test --compact tests/Feature/Services/Redirects/MigrationRedirectGeneratorTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    // Provide the shared redirects global the generator reads and writes.
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::make('redirects');
    $set->title('Redirects');
    $set->save();

    /** @var Statamic\Sites\Site $defaultSite */
    $defaultSite = Site::default();
    /** @var Variables $vars */
    $vars = $set->makeLocalization($defaultSite->handle());
    $vars->data(['redirects' => []]);
    $vars->save();

    // A routed collection so its entries have predictable Statamic URLs.
    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::make('probe');
    $collection->sites(['default']);
    $collection->routes('/probe/{slug}');
    $collection->save();
});

/**
 * Create a probe entry carrying an optional original WordPress URL.
 */
function makeProbeEntry(string $slug, ?string $originalUrl): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('probe');
    $entry->id("probe-{$slug}");
    $entry->slug($slug);

    $data = ['title' => ucfirst($slug)];

    if ($originalUrl !== null) {
        $data['original_wordpress_url'] = $originalUrl;
    }

    $entry->data($data);
    // saveQuietly: the generator reads persisted content, not the save events, so
    // skip the inline search-reindex cascade (UAMS-Web/uams-statamic-archive#1633).
    $entry->saveQuietly();
}

/**
 * Seed a raw redirect row onto the shared default-site list.
 *
 * @param  array<string, mixed>  $row
 */
function seedRedirectRow(array $row): void
{
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::findByHandle('redirects');
    /** @var Statamic\Sites\Site $site */
    $site = Site::default();
    /** @var Variables $vars */
    $vars = $set->in($site->handle());
    /** @var list<array<string, mixed>> $existing */
    $existing = $vars->get('redirects', []);
    $vars->set('redirects', [...$existing, $row]);
    $vars->save();
}

it('writes an approved 301 for an entry whose URL changed', function (): void {
    makeProbeEntry('moved-post', 'https://old.uams.test/2019/legacy-slug/');

    $report = app(MigrationRedirectGenerator::class)->generate();

    /** @var list<array<string, mixed>> $rows */
    $rows = app(MarketingRedirectStore::class)->all();

    expect($report->entryMoves)->toHaveCount(1)
        ->and($report->written)->toBeTrue()
        ->and($rows)->toHaveCount(1)
        ->and($rows[0])->toMatchArray([
            'source' => '/2019/legacy-slug',
            'target_url' => '/probe/moved-post',
            'response' => '301',
            'status' => 'approved',
            'enabled' => true,
            'type' => 'redirect',
        ]);
});

it('skips an entry whose URL did not change', function (): void {
    // The original path already equals the current Statamic URL — a self-redirect.
    makeProbeEntry('unchanged', 'https://old.uams.test/probe/unchanged/');

    $report = app(MigrationRedirectGenerator::class)->generate();

    expect($report->entryMoves)->toHaveCount(0)
        ->and(app(MarketingRedirectStore::class)->all())->toHaveCount(0);
});

it('never redirects the bare site root (the imported WordPress front page)', function (): void {
    // The WordPress front page's original_wordpress_url is the base blog URL, whose
    // path reduces to the bare root `/` — not a URL that moved.
    makeProbeEntry('home', 'https://old.uams.test/');

    $report = app(MigrationRedirectGenerator::class)->generate();

    expect($report->entryMoves)->toHaveCount(0)
        ->and(app(MarketingRedirectStore::class)->all())->toHaveCount(0);
});

it('skips an entry that carries no original WordPress URL', function (): void {
    makeProbeEntry('native', null);

    $report = app(MigrationRedirectGenerator::class)->generate();

    expect($report->total())->toBe(0)
        ->and(app(MarketingRedirectStore::class)->all())->toHaveCount(0);
});

it('is idempotent — a re-run writes nothing new and never duplicates', function (): void {
    makeProbeEntry('moved-post', 'https://old.uams.test/old/');

    $generator = app(MigrationRedirectGenerator::class);
    $first = $generator->generate();
    $second = $generator->generate();

    expect($first->total())->toBe(1)
        ->and($second->total())->toBe(0)
        ->and($second->skippedExisting)->toBe(1)
        ->and(app(MarketingRedirectStore::class)->all())->toHaveCount(1);
});

it('never clobbers a human-authored redirect for the same source', function (): void {
    // A marketing redirect already covers /old with a human-chosen destination.
    seedRedirectRow([
        'id' => 'human',
        'type' => 'redirect',
        'source' => '/old',
        'target_type' => 'url',
        'target_url' => '/human-chosen',
        'response' => '301',
        'enabled' => true,
    ]);

    makeProbeEntry('moved-post', 'https://old.uams.test/old/');

    $report = app(MigrationRedirectGenerator::class)->generate();

    /** @var list<array<string, mixed>> $rows */
    $rows = app(MarketingRedirectStore::class)->all();

    expect($report->entryMoves)->toHaveCount(0)
        ->and($report->skippedExisting)->toBe(1)
        ->and($rows)->toHaveCount(1)
        ->and($rows[0]['target_url'])->toBe('/human-chosen');
});

it('301s a retired permalink to its nearest surviving ancestor and 410s an orphan', function (): void {
    // /probe/survivor survives; nothing at the top level does.
    makeProbeEntry('survivor', null);

    $report = app(MigrationRedirectGenerator::class)->generate(false, [
        '/probe/survivor/retired-child',
        '/orphan-top-level',
    ]);

    expect($report->ancestorMoves)->toHaveCount(1)
        ->and($report->ancestorMoves[0]->source)->toBe('/probe/survivor/retired-child')
        ->and($report->ancestorMoves[0]->target)->toBe('/probe/survivor')
        ->and($report->gone)->toHaveCount(1)
        ->and($report->gone[0]->source)->toBe('/orphan-top-level');

    /** @var list<array<string, mixed>> $rows */
    $rows = app(MarketingRedirectStore::class)->all();
    $byResponse = collect($rows)->keyBy('response');

    expect($byResponse->get('410'))->toMatchArray(['source' => '/orphan-top-level', 'target_url' => null]);
});

it('plans without writing on a dry run', function (): void {
    makeProbeEntry('moved-post', 'https://old.uams.test/old/');

    $report = app(MigrationRedirectGenerator::class)->generate(dryRun: true);

    expect($report->entryMoves)->toHaveCount(1)
        ->and($report->written)->toBeFalse()
        ->and(app(MarketingRedirectStore::class)->all())->toHaveCount(0);
});

it('writes a 301 for an asset whose URL changed', function (): void {
    // A real temp disk with a public URL so the container resolves an asset URL to
    // move to (Storage::fake drops the `url` config, leaving the asset URL null).
    $root = storage_path('framework/testing/probe-assets-'.uniqid('', true));
    File::deleteDirectory($root);
    config()->set('filesystems.disks.probe_assets_disk', [
        'driver' => 'local',
        'root' => $root,
        'url' => 'http://localhost/probe-assets',
        'visibility' => 'public',
    ]);

    /** @var Statamic\Assets\AssetContainer $container */
    $container = AssetContainer::make('probe_assets');
    $container->disk('probe_assets_disk');
    $container->title('Probe Assets');
    $container->save();

    Storage::disk('probe_assets_disk')->put('2019/doc.pdf', 'probe');

    /** @var Statamic\Assets\AssetContainer $storedContainer */
    $storedContainer = AssetContainer::find('probe_assets');
    /** @var Asset $asset */
    $asset = $storedContainer->makeAsset('2019/doc.pdf');
    $asset->data(['original_wordpress_url' => 'https://old.uams.test/wp-content/uploads/2019/doc.pdf']);
    $asset->saveQuietly();

    try {
        $report = app(MigrationRedirectGenerator::class)->generate();

        expect($report->assetMoves)->toHaveCount(1)
            ->and($report->assetMoves[0]->source)->toBe('/wp-content/uploads/2019/doc.pdf')
            ->and($report->assetMoves[0]->response)->toBe('301');
    } finally {
        File::deleteDirectory($root);
    }
});
