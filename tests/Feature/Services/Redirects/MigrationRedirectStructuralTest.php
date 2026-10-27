<?php

declare(strict_types=1);

use App\Services\Redirects\MarketingRedirectStore;
use App\Services\Redirects\MigrationRedirectGenerator;
use App\Services\Redirects\StructuralRedirectSynthesizer;
use App\Support\Redirects\MigrationRedirect;
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
 * Feature coverage for the synthesized structural WordPress redirects (UAMS-Web/uams-statamic-archive#1657).
 *
 * Where {@see StructuralRedirectSynthesizerTest} proves the URL-shape rules in
 * isolation, this proves the wiring: that {@see MigrationRedirectGenerator} reads
 * the importer-stamped metadata off real Statamic entities
 * (`wordpress_post_id`, `wordpress_author_login`,
 * `wordpress_attachment_page_url`), resolves the archive and feed destinations
 * from the app's own routing config, and folds every synthesized rule through the
 * same idempotent, collision-safe append as the deterministic core.
 *
 * Runs against a hermetic Stache ({@see StacheIsolatedTestCase}) — a dated `posts`
 * collection stands in for the imported WordPress blog, so the generator iterates
 * only these fixtures.
 *
 * @command  php artisan test --compact tests/Feature/Services/Redirects/MigrationRedirectStructuralTest.php
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

    // A dated, routed `posts` collection standing in for the imported WordPress
    // blog — dated so its entries carry the date the archives derive from, and
    // handled `posts` because that is the collection the feed and archive
    // destinations are resolved from.
    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::make('posts');
    $collection->sites(['default']);
    $collection->dated(true);
    $collection->routes('/news/{slug}');
    $collection->save();
});

/**
 * Create an imported post carrying whichever importer-stamped keys the case needs.
 *
 * @param  array<string, mixed>  $stamped  Extra loose data keys the importer stamps
 */
function makeImportedPost(string $slug, string $date, array $stamped = []): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('posts');
    $entry->id("posts-{$slug}");
    $entry->slug($slug);
    $entry->date($date);
    $entry->data([
        'title' => ucfirst($slug),
        // The import marker every structural synthesizer keys off.
        'original_wordpress_url' => "https://old.uams.test/{$slug}/",
        ...$stamped,
    ]);
    // saveQuietly: the generator reads persisted content, not the save events, so
    // skip the inline search-reindex cascade (UAMS-Web/uams-statamic-archive#1633).
    $entry->saveQuietly();
}

/**
 * The structural rules a generate() run planned, keyed by source.
 *
 * @return array<string, MigrationRedirect>
 */
function structuralBySource(MigrationRedirectGenerator $generator): array
{
    $bySource = [];

    foreach ($generator->generate()->structural as $plan) {
        $bySource[$plan->source] = $plan;
    }

    return $bySource;
}

it('synthesizes an ugly permalink from the stamped WordPress post id', function (): void {
    makeImportedPost('a-post', '2019-05-06', ['wordpress_post_id' => 4207]);

    $plans = structuralBySource(app(MigrationRedirectGenerator::class));

    expect($plans)->toHaveKey('/?p=4207')
        ->and($plans['/?p=4207']->target)->toBe('/news/a-post')
        ->and($plans['/?p=4207']->response)->toBe('301');
});

it('accepts a stamped post id that was re-serialized as a string', function (): void {
    makeImportedPost('a-post', '2019-05-06', ['wordpress_post_id' => '4207']);

    expect(structuralBySource(app(MigrationRedirectGenerator::class)))->toHaveKey('/?p=4207');
});

it('synthesizes no ugly permalink for an entry with no stamped post id', function (): void {
    makeImportedPost('a-post', '2019-05-06');

    $sources = array_keys(structuralBySource(app(MigrationRedirectGenerator::class)));

    expect(collect($sources)->filter(fn (string $s): bool => str_starts_with($s, '/?p=')))->toBeEmpty();
});

it('synthesizes the date archives its entries actually have', function (): void {
    makeImportedPost('a-post', '2019-05-06');
    makeImportedPost('b-post', '2020-11-02');

    $plans = structuralBySource(app(MigrationRedirectGenerator::class));

    expect($plans)->toHaveKeys(['/2019', '/2019/05', '/2020', '/2020/11'])
        // With no posts index mounted, an archive with no surviving ancestor is
        // declared gone rather than funnelled to the home page.
        ->and($plans['/2019/05']->response)->toBe('410')
        ->and($plans['/2019/05']->target)->toBeNull();
});

it('synthesizes an author archive from the stamped author login', function (): void {
    // `dc:creator` carries the WordPress user_login; the archive uses its slug.
    makeImportedPost('a-post', '2019-05-06', ['wordpress_author_login' => 'Nyota Uhura']);

    expect(structuralBySource(app(MigrationRedirectGenerator::class)))
        ->toHaveKey('/author/nyota-uhura');
});

it('synthesizes no WordPress archives for a natively authored entry', function (): void {
    // No original_wordpress_url: this entry never had WordPress URLs to redirect.
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('posts');
    $entry->id('posts-native');
    $entry->slug('native');
    $entry->date('2019-05-06');
    $entry->data(['title' => 'Native', 'wordpress_author_login' => 'Should Not Count']);
    $entry->saveQuietly();

    $plans = structuralBySource(app(MigrationRedirectGenerator::class));

    expect($plans)->not->toHaveKey('/2019')
        ->and($plans)->not->toHaveKey('/author/should-not-count');
});

it('301s the site feeds and every nested feed to the site RSS feed', function (): void {
    makeImportedPost('a-post', '2019-05-06');

    $plans = structuralBySource(app(MigrationRedirectGenerator::class));

    expect($plans)->toHaveKeys(['/feed', '/comments/feed'])
        ->and($plans['/feed']->target)->toBe('/feed.xml')
        ->and($plans)->toHaveKey(StructuralRedirectSynthesizer::NESTED_FEED_PATTERN)
        ->and($plans[StructuralRedirectSynthesizer::NESTED_FEED_PATTERN]->useRegex)->toBeTrue()
        ->and($plans[StructuralRedirectSynthesizer::NESTED_FEED_PATTERN]->target)->toBe('/feed.xml');
});

it('410s pagination when no posts index is mounted', function (): void {
    makeImportedPost('a-post', '2019-05-06');

    $plans = structuralBySource(app(MigrationRedirectGenerator::class));

    expect($plans)->toHaveKey(StructuralRedirectSynthesizer::PAGINATION_PATTERN)
        ->and($plans[StructuralRedirectSynthesizer::PAGINATION_PATTERN]->response)->toBe('410');
});

it('301s archives and pagination to the posts index once the collection is mounted', function (): void {
    // A mounted index is the app's own record of where the posts archive lives.
    /** @var Statamic\Entries\Collection $pages */
    $pages = Collection::make('pages');
    $pages->sites(['default']);
    $pages->routes('/{slug}');
    $pages->save();

    /** @var Statamic\Entries\Entry $index */
    $index = Entry::make();
    $index->collection('pages');
    $index->id('pages-news');
    $index->slug('news');
    $index->data(['title' => 'News']);
    $index->saveQuietly();

    /** @var Statamic\Entries\Collection $posts */
    $posts = Collection::findByHandle('posts');
    $posts->mount('pages-news');
    $posts->save();

    makeImportedPost('a-post', '2019-05-06', ['wordpress_author_login' => 'Nyota Uhura']);

    $plans = structuralBySource(app(MigrationRedirectGenerator::class));

    expect($plans['/2019']->response)->toBe('301')
        ->and($plans['/2019']->target)->toBe('/news')
        ->and($plans['/author/nyota-uhura']->target)->toBe('/news')
        ->and($plans[StructuralRedirectSynthesizer::PAGINATION_PATTERN]->response)->toBe('301')
        ->and($plans[StructuralRedirectSynthesizer::PAGINATION_PATTERN]->target)->toBe('/news');
});

it('writes the pattern rules with use_regex set so the serving tag consults them', function (): void {
    makeImportedPost('a-post', '2019-05-06');

    app(MigrationRedirectGenerator::class)->generate();

    /** @var list<array<string, mixed>> $rows */
    $rows = app(MarketingRedirectStore::class)->all();
    $patterns = array_values(array_filter($rows, static fn (array $r): bool => $r['use_regex'] === true));

    expect($patterns)->toHaveCount(2)
        ->and(collect($patterns)->pluck('source')->all())
        ->toEqualCanonicalizing([
            StructuralRedirectSynthesizer::NESTED_FEED_PATTERN,
            StructuralRedirectSynthesizer::PAGINATION_PATTERN,
        ]);

    // A stored pattern must still be a usable PCRE — a normalized one would not be.
    foreach ($patterns as $pattern) {
        $source = $pattern['source'];

        expect($source)->toBeString()
            ->and(@preg_match(\is_string($source) ? $source : '', '/news/page/2'))->not->toBeFalse();
    }
});

it('is idempotent across a re-run, patterns included', function (): void {
    makeImportedPost('a-post', '2019-05-06', ['wordpress_post_id' => 4207]);

    $generator = app(MigrationRedirectGenerator::class);
    $first = $generator->generate();
    $second = $generator->generate();

    expect($first->structural)->not->toBeEmpty()
        ->and($second->structural)->toBeEmpty()
        ->and($second->total())->toBe(0)
        ->and($second->skippedExisting)->toBe($first->total())
        ->and(app(MarketingRedirectStore::class)->all())->toHaveCount($first->total());
});

it('never clobbers a human-authored rule for a synthesized source', function (): void {
    // An editor already points the 2019 archive somewhere deliberate.
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::findByHandle('redirects');
    /** @var Statamic\Sites\Site $site */
    $site = Site::default();
    /** @var Variables $vars */
    $vars = $set->in($site->handle());
    $vars->set('redirects', [[
        'id' => 'human',
        'type' => 'redirect',
        'source' => '/2019',
        'target_type' => 'url',
        'target_url' => '/human-chosen',
        'response' => '301',
        'enabled' => true,
    ]]);
    $vars->save();

    makeImportedPost('a-post', '2019-05-06');

    $plans = structuralBySource(app(MigrationRedirectGenerator::class));

    expect($plans)->not->toHaveKey('/2019');

    /** @var list<array<string, mixed>> $rows */
    $rows = app(MarketingRedirectStore::class)->all();
    $forSource = collect($rows)->firstWhere('source', '/2019');

    expect($forSource)->not->toBeNull()
        ->and($forSource['target_url'] ?? null)->toBe('/human-chosen');
});

it('synthesizes a 301 from an attachment page to the migrated asset', function (): void {
    // A real temp disk with a public URL so the container resolves an asset URL to
    // move to (Storage::fake drops the `url` config, leaving the asset URL null).
    $root = storage_path('framework/testing/structural-assets-'.uniqid('', true));
    File::deleteDirectory($root);
    config()->set('filesystems.disks.structural_assets_disk', [
        'driver' => 'local',
        'root' => $root,
        'url' => 'http://localhost/structural-assets',
        'visibility' => 'public',
    ]);

    /** @var Statamic\Assets\AssetContainer $container */
    $container = AssetContainer::make('structural_assets');
    $container->disk('structural_assets_disk');
    $container->title('Structural Assets');
    $container->save();

    Storage::disk('structural_assets_disk')->put('2019/brochure.pdf', 'probe');

    /** @var Statamic\Assets\AssetContainer $storedContainer */
    $storedContainer = AssetContainer::find('structural_assets');
    /** @var Asset $asset */
    $asset = $storedContainer->makeAsset('2019/brochure.pdf');
    $asset->data([
        'original_wordpress_url' => 'https://old.uams.test/wp-content/uploads/2019/brochure.pdf',
        'wordpress_attachment_page_url' => 'https://old.uams.test/a-post/brochure/',
    ]);
    $asset->saveQuietly();

    try {
        $plans = structuralBySource(app(MigrationRedirectGenerator::class));

        expect($plans)->toHaveKey('/a-post/brochure')
            ->and($plans['/a-post/brochure']->response)->toBe('301')
            ->and($plans['/a-post/brochure']->target)->toBe('http://localhost/structural-assets/2019/brochure.pdf');
    } finally {
        File::deleteDirectory($root);
    }
});
