<?php

declare(strict_types=1);

use App\Console\Commands\SchemaTemplatesWarmJsonLdCache;
use App\Services\SchemaTemplates\SchemaJsonLdOutputCache;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Contracts\Globals\GlobalSet as GlobalSetContract;
use Statamic\Contracts\Globals\Variables as GlobalVariablesContract;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * The `schema-templates:warm-json-ld-cache` command: collect each site's warm URLs, merge them,
 * GET each once, and store the run's summary for the Control Panel (UAMS-Web/uams-statamic#314).
 *
 * Every test runs on the array cache store, so the stored summary never reaches the file cache
 * that other test processes share, and on a fixed clock, so the summary's `at` can be pinned.
 * The sites are replaced with a small fixed set, and the warm config slice has no global
 * references, so the collector reads only each site's homepage.
 *
 * @command  php artisan test --compact tests/Feature/Console/SchemaTemplatesWarmJsonLdCacheCommandTest.php
 */
beforeEach(function (): void {
    Cache::setDefaultDriver('array');
    CarbonImmutable::setTestNow('2026-11-14 08:00:00');
    $this->travelTo(CarbonImmutable::now());

    config(['schema_templates.json_ld_cache.warm' => [
        'global_fallback_site_handle' => null,
        'global_entry_fields_by_collection' => [],
        'global_taxonomy_entry_traversals' => [],
        'homepage_collection_handle' => 'pages',
        'homepage_uri_candidates' => ['/'],
    ]]);
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/**
 * The warm config snapshot the base config above produces.
 *
 * @return array<string, mixed>
 */
function warmJsonLdBaseSnapshot(): array
{
    return [
        'homepage_collection_handle' => 'pages',
        'homepage_uri_candidates' => ['/'],
        'global_fallback_site_handle' => null,
        'global_entry_field_reference_total_count' => 0,
        'global_taxonomy_traversal_rule_count' => 0,
    ];
}

/**
 * A `pages` home entry with the given published state and absolute URL.
 *
 * @return EntryModel&MockInterface
 */
function warmJsonLdHomeEntry(bool $published, string $absoluteUrl): EntryModel
{
    $collection = Mockery::mock();
    $collection->shouldReceive('handle')->andReturn('pages');

    /** @var EntryModel&MockInterface $entry */
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('published')->andReturn($published);
    $entry->shouldReceive('collection')->andReturn($collection);
    $entry->shouldReceive('absoluteUrl')->andReturn($absoluteUrl);

    return $entry;
}

/**
 * Replace the sites with the given handles, in order.
 *
 * @param  list<string>  $handles
 */
function warmJsonLdSites(array $handles): void
{
    $sites = [];

    foreach ($handles as $handle) {
        $sites[$handle] = ['name' => ucfirst($handle), 'url' => '/'.$handle.'/', 'locale' => 'en_US'];
    }

    Site::setSites($sites);
}

/**
 * The summary the last run stored for the Control Panel.
 *
 * @return array<string, mixed>|null
 */
function warmJsonLdStoredSummary(): ?array
{
    return app(SchemaJsonLdOutputCache::class)->lastWarmSummary();
}

/**
 * The completion lines the command picks from, copied so a change to the catalog is seen.
 *
 * @return list<string>
 */
function warmJsonLdCompletionMessages(): array
{
    return [
        'The JSON-LD cache has been wrapped in a warm blanket of relevance and tucked in with @context. Sweet dreams, little triples.',
        "You fed the cache a hearty bowl of linked data and gave it a good polish. It's warm, satisfied, and slightly cocky.",
        'Cache warmed. Ego inflated. Your JSON-LD is now ready to flex on unstructured peasants.',
        'The JSON-LD has been spa-treated: exfoliated, massaged, and warmed. It feels prettier than your frontend.',
        "The JSON-LD cache has been meticulously pre-warmed and buffed to perfection. It's now radiating structured confidence.",
        'Schemas gently toasted, cache properly primed. Warm, crisp, and ready to dominate the SERPs.',
        'Semantic polish applied. Buffing complete. The JSON-LD Stache is gleaming, warm, and quietly judging your other caches.',
        "You gave the cache a hearty serving of linked data and a thorough polish. It's warm, satisfied, and mildly arrogant.",
        'You have gently heated the JSON-LD oven and pulled out a perfect batch. Golden brown, fully cached, and ready for action.',
    ];
}

it('stores an empty summary and warns when no site matches the --site handle', function (): void {
    warmJsonLdSites(['alpha']);
    Http::fake();

    expect(Artisan::call('schema-templates:warm-json-ld-cache', ['--site' => 'nope']))->toBe(0)
        ->and(Artisan::output())->toContain('No Statamic sites matched the warm run (check --site handle).');

    Http::assertNothingSent();

    expect(warmJsonLdStoredSummary())->toBe([
        'at' => '2026-11-14T08:00:00+00:00',
        'mode' => 'multisite',
        'requested' => 0,
        'skipped_no_url' => 0,
        'failures' => 0,
        'duplicate_urls_skipped' => 0,
        'urls_merged_count' => 0,
        'per_site_url_counts' => [],
        'slot_schema_object_ids' => [],
        'people_entry_ids' => [],
        'warnings' => ['No sites to process.'],
        'sites' => [],
        'site' => 'nope',
        'warm_config_snapshot' => warmJsonLdBaseSnapshot(),
    ]);
});

it('merges every site\'s URLs, requests each once, and counts duplicates and failures', function (array $options): void {
    warmJsonLdSites(['alpha', 'beta', 'gamma', 'delta', 'epsilon']);

    $homes = [
        'alpha' => warmJsonLdHomeEntry(true, 'https://alpha.test/'),
        // The same URL as alpha's, so the merge skips it as a duplicate
        'beta' => warmJsonLdHomeEntry(true, 'https://alpha.test/'),
        // Unpublished, so the collector warns and the site contributes no URL
        'gamma' => warmJsonLdHomeEntry(false, 'https://gamma.test/'),
        'delta' => warmJsonLdHomeEntry(true, 'https://delta.test/'),
        'epsilon' => warmJsonLdHomeEntry(true, 'https://epsilon.test/'),
    ];

    EntryFacade::shouldReceive('findByUri')
        ->andReturnUsing(fn (string $uri, string $site): ?EntryModel => $uri === '/' ? ($homes[$site] ?? null) : null);

    Http::fake([
        'alpha.test/*' => Http::response('server error', 500),
        'delta.test/*' => Http::response('ok', 200),
        'epsilon.test/*' => Http::failedConnection(),
    ]);

    expect(Artisan::call('schema-templates:warm-json-ld-cache', $options))->toBe(0);

    // Artisan::call has no TTY, so neither the collecting spinner nor the progress bar is drawn
    expect(Artisan::output())->not->toContain('Collecting JSON-LD warm URLs')
        ->not->toContain('Requesting warm URLs');

    // Each merged URL is requested exactly once, in merge order, with the warmer's User-Agent
    $sent = Http::recorded()->map(fn (array $pair): string => $pair[0]->url())->all();
    expect($sent)->toBe(['https://alpha.test/', 'https://delta.test/', 'https://epsilon.test/']);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('User-Agent', 'UAMS-SchemaJsonLdWarmer/1.0'));
    Http::assertNotSent(fn (Request $request): bool => ! $request->hasHeader('User-Agent', 'UAMS-SchemaJsonLdWarmer/1.0'));

    expect(warmJsonLdStoredSummary())->toBe([
        'at' => '2026-11-14T08:00:00+00:00',
        'mode' => 'multisite',
        'requested' => 3,
        'skipped_no_url' => 0,
        // alpha answers 500 and epsilon cannot connect; delta succeeds
        'failures' => 2,
        'duplicate_urls_skipped' => 1,
        'urls_merged_count' => 3,
        'per_site_url_counts' => ['alpha' => 1, 'beta' => 1, 'gamma' => 0, 'delta' => 1, 'epsilon' => 1],
        'globals_site_handles_used' => ['alpha' => null, 'beta' => null, 'gamma' => null, 'delta' => null, 'epsilon' => null],
        'slot_schema_object_ids' => [],
        'people_entry_ids' => [],
        'warnings' => [
            '[gamma] Home candidate uri "\/" resolves to an unpublished entry for site gamma.',
            '[gamma] No published homepage found for site gamma using collection pages and uri candidates ["\/"].',
        ],
        'sites' => ['alpha', 'beta', 'gamma', 'delta', 'epsilon'],
        'site' => null,
        'warm_config_snapshot' => warmJsonLdBaseSnapshot(),
    ]);
})->with([
    'no --site option' => [[]],
    'an empty --site option' => [['--site' => '']],
]);

it('keeps merging the URLs of a site past a duplicate, and gathers each person and schema object once', function (): void {
    warmJsonLdSites(['alpha', 'beta']);

    config(['schema_templates.json_ld_cache.warm.global_entry_fields_by_collection' => [
        'pages' => ['settings.pages'],
        'people' => ['settings.team'],
        'schema_objects' => ['settings.object'],
    ]]);

    $homes = [
        'alpha' => warmJsonLdHomeEntry(true, 'https://alpha.test/'),
        'beta' => warmJsonLdHomeEntry(true, 'https://beta.test/'),
    ];
    $pages = [
        'page-shared' => warmJsonLdHomeEntry(true, 'https://shared.test/'),
        'page-beta' => warmJsonLdHomeEntry(true, 'https://beta-extra.test/'),
    ];

    EntryFacade::shouldReceive('findByUri')
        ->andReturnUsing(fn (string $uri, string $site): ?EntryModel => $uri === '/' ? ($homes[$site] ?? null) : null);
    EntryFacade::shouldReceive('find')->andReturnUsing(fn (string $id): ?EntryModel => $pages[$id] ?? null);

    // beta's shared page duplicates alpha's and comes before a page only beta has, so the
    // merge must skip the one and still reach the next
    $variables = [
        'alpha' => ['pages' => ['page-shared'], 'team' => ['kirk', 'spock'], 'object' => 'object-enterprise'],
        'beta' => ['pages' => ['page-shared', 'page-beta'], 'team' => ['spock', 'mccoy'], 'object' => 'object-galileo'],
    ];

    GlobalSet::shouldReceive('findByHandle')->andReturnUsing(function (string $handle) use ($variables): GlobalSetContract {
        $set = Mockery::mock(GlobalSetContract::class);
        $set->shouldReceive('in')->andReturnUsing(function (string $site) use ($variables): GlobalVariablesContract {
            $siteVariables = Mockery::mock(GlobalVariablesContract::class);
            $siteVariables->shouldReceive('get')->andReturnUsing(fn (string $field): mixed => $variables[$site][$field] ?? null);

            return $siteVariables;
        });

        return $set;
    });

    Http::fake(['*' => Http::response('ok', 200)]);

    expect(Artisan::call('schema-templates:warm-json-ld-cache'))->toBe(0);

    $sent = Http::recorded()->map(fn (array $pair): string => $pair[0]->url())->all();
    expect($sent)->toBe(['https://alpha.test/', 'https://shared.test/', 'https://beta.test/', 'https://beta-extra.test/']);

    $summary = warmJsonLdStoredSummary();
    expect($summary['duplicate_urls_skipped'] ?? null)->toBe(1)
        ->and($summary['urls_merged_count'] ?? null)->toBe(4)
        ->and($summary['slot_schema_object_ids'] ?? null)->toBe(['object-enterprise', 'object-galileo'])
        ->and($summary['people_entry_ids'] ?? null)->toBe(['kirk', 'spock', 'mccoy']);
});

it('skips anything in the site list that is not a Statamic site', function (): void {
    warmJsonLdSites(['alpha']);

    $alpha = Site::get('alpha');
    Site::partialMock()->shouldReceive('all')->andReturn(collect(['not-a-site', $alpha]));

    EntryFacade::shouldReceive('findByUri')
        ->andReturnUsing(fn (string $uri, string $site): EntryModel => warmJsonLdHomeEntry(true, 'https://'.$site.'.test/'));

    Http::fake(['*' => Http::response('ok', 200)]);

    expect(Artisan::call('schema-templates:warm-json-ld-cache'))->toBe(0);

    $sent = Http::recorded()->map(fn (array $pair): string => $pair[0]->url())->all();

    expect($sent)->toBe(['https://alpha.test/'])
        ->and(warmJsonLdStoredSummary()['sites'] ?? null)->toBe(['alpha']);
});

it('warms only the site --site names', function (): void {
    warmJsonLdSites(['alpha', 'beta']);

    EntryFacade::shouldReceive('findByUri')
        ->andReturnUsing(fn (string $uri, string $site): EntryModel => warmJsonLdHomeEntry(true, 'https://'.$site.'.test/'));

    Http::fake(['*' => Http::response('ok', 200)]);

    expect(Artisan::call('schema-templates:warm-json-ld-cache', ['--site' => 'beta']))->toBe(0);

    $sent = Http::recorded()->map(fn (array $pair): string => $pair[0]->url())->all();
    $summary = warmJsonLdStoredSummary();

    expect($sent)->toBe(['https://beta.test/'])
        ->and($summary['sites'] ?? null)->toBe(['beta'])
        ->and($summary['site'] ?? null)->toBe('beta')
        ->and($summary['requested'] ?? null)->toBe(1)
        ->and($summary['failures'] ?? null)->toBe(0);
});

it('requests each URL with a 120 s timeout and a 15 s connect timeout', function (): void {
    warmJsonLdSites(['alpha']);

    EntryFacade::shouldReceive('findByUri')
        ->andReturnUsing(fn (string $uri, string $site): EntryModel => warmJsonLdHomeEntry(true, 'https://'.$site.'.test/'));

    $timeouts = [];

    Http::fake(function (Request $request, array $options) use (&$timeouts) {
        $timeouts[] = [$options['timeout'] ?? null, $options['connect_timeout'] ?? null];

        return Http::response('ok', 200);
    });

    expect(Artisan::call('schema-templates:warm-json-ld-cache'))->toBe(0)
        ->and($timeouts)->toBe([[120, 15]]);
});

it('snapshots the warm config, defaulting what is absent or malformed', function (mixed $warm, array $snapshot): void {
    warmJsonLdSites(['alpha']);
    config(['schema_templates.json_ld_cache.warm' => $warm]);

    // An unmatched --site stores the snapshot without running the collector
    Artisan::call('schema-templates:warm-json-ld-cache', ['--site' => 'nope']);

    expect(warmJsonLdStoredSummary()['warm_config_snapshot'] ?? null)->toBe($snapshot);
})->with([
    'not an array' => ['nonsense', warmJsonLdBaseSnapshot()],
    'counted references, rules and a legacy fallback key' => [
        [
            'global_entry_fields_by_collection' => [
                // Only the non-empty strings count
                'schema_objects' => ['globals.one', '', 5, 'globals.two'],
                // A non-array value contributes nothing
                'people' => 'globals.three',
                'locations' => ['globals.four'],
            ],
            'global_taxonomy_entry_traversals' => [['rule' => 1], ['rule' => 2]],
            'multisite_global_fallback_site_handle' => 'legacy',
            'homepage_collection_handle' => 'home_pages',
            // Empty strings are kept, non-strings dropped
            'homepage_uri_candidates' => ['', '/home', 7],
        ],
        [
            'homepage_collection_handle' => 'home_pages',
            'homepage_uri_candidates' => ['', '/home'],
            'global_fallback_site_handle' => 'legacy',
            'global_entry_field_reference_total_count' => 3,
            'global_taxonomy_traversal_rule_count' => 2,
        ],
    ],
    'malformed values fall back to the defaults' => [
        [
            'global_entry_fields_by_collection' => 'not a list',
            'global_taxonomy_entry_traversals' => 'not a list',
            'global_fallback_site_handle' => '',
            'multisite_global_fallback_site_handle' => 'ignored',
            'homepage_collection_handle' => 5,
            'homepage_uri_candidates' => 'not a list',
        ],
        warmJsonLdBaseSnapshot(),
    ],
    'a candidate list with no strings becomes the root' => [
        ['homepage_uri_candidates' => [7, null]],
        warmJsonLdBaseSnapshot(),
    ],
    'the current fallback key wins over the legacy one' => [
        ['global_fallback_site_handle' => 'current', 'multisite_global_fallback_site_handle' => 'legacy'],
        [...warmJsonLdBaseSnapshot(), 'global_fallback_site_handle' => 'current'],
    ],
]);

it('prints one completion line, and none when quiet', function (): void {
    warmJsonLdSites(['alpha']);
    EntryFacade::shouldReceive('findByUri')->andReturn(null);
    Http::fake();

    Artisan::call('schema-templates:warm-json-ld-cache');
    $output = Artisan::output();
    $printed = array_values(array_filter(warmJsonLdCompletionMessages(), fn (string $line): bool => str_contains($output, $line)));

    expect($printed)->toHaveCount(1);

    Artisan::call('schema-templates:warm-json-ld-cache', ['--quiet' => true]);

    expect(trim(Artisan::output()))->toBe('');
});

it('draws every completion line from the whole catalog', function (): void {
    $command = new SchemaTemplatesWarmJsonLdCache;
    $pick = (new ReflectionMethod($command, 'randomJsonLdWarmCompletionMessage'))->getClosure($command);

    $seen = [];

    // 500 draws from 9 lines miss any one line with probability (8/9)^500, about 3e-26
    for ($draw = 0; $draw < 500; $draw++) {
        $line = $pick();
        expect($line)->toBeString();
        /** @var string $line */
        $seen[$line] = true;
    }

    expect(array_keys($seen))->toEqualCanonicalizing(warmJsonLdCompletionMessages());
});
