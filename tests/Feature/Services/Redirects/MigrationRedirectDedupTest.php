<?php

declare(strict_types=1);

// The slug a real duplicate set's dropped post carries (UAMS-Web/uams-statamic-archive#2960)
// cspell:ignore formerstudent

use App\Services\Redirects\MarketingRedirectStore;
use App\Services\Redirects\MigrationRedirectGenerator;
use App\Services\Redirects\MigrationRedirectReport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Globals\Variables;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Coverage for UAMS-Web/uams-statamic-archive#2943: the WXR cleaner's duplicate redirect pairs become one `301` each, from a
 * dropped news duplicate's WordPress path to its kept post's Statamic URL.
 *
 * A dropped duplicate is never imported, so only the kept post has an entry, found through its
 * `original_wordpress_url`. Runs against a hermetic Stache with a routed `dedup` collection, as
 * MigrationRedirectGeneratorTest does for the rest of the generator.
 *
 * @command  php artisan test --compact tests/Feature/Services/Redirects/MigrationRedirectDedupTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
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

    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::make('dedup');
    $collection->sites(['default']);
    $collection->routes('/news/{slug}');
    $collection->save();
});

/**
 * Save a kept news post, imported from the given WordPress URL.
 */
function makeDedupKeptPost(string $slug, string $originalUrl): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('dedup');
    $entry->id("dedup-{$slug}");
    $entry->slug($slug);
    $entry->data(['title' => ucfirst($slug), 'original_wordpress_url' => $originalUrl]);
    $entry->saveQuietly();
}

/**
 * A pair as the cleaner writes it.
 *
 * @return array{source: string, target: string, notes: string|null}
 */
function dedupPair(string $source, string $target, ?string $notes = null): array
{
    return ['source' => $source, 'target' => $target, 'notes' => $notes];
}

/**
 * The rows in the redirects store other than the kept posts' own moves.
 *
 * A kept post was imported, so the generator also writes its ordinary entry move, from its
 * WordPress path to its Statamic URL; that row is UAMS-Web/uams-statamic-archive#1641's, not this ticket's.
 *
 * @return list<array<string, mixed>>
 */
function dedupStoredRows(): array
{
    /** @var list<array<string, mixed>> $rows */
    $rows = app(MarketingRedirectStore::class)->all();

    return array_values(array_filter(
        $rows,
        static fn (array $row): bool => ! (\is_string($row['notes'] ?? null) && str_starts_with($row['notes'], 'Imported entry ')),
    ));
}

/**
 * Write a pair file and return its path.
 */
function dedupPairFile(string $json): string
{
    $path = storage_path('framework/testing/dedup-pairs-'.uniqid('', true).'.json');
    File::put($path, $json);

    return $path;
}

it('sends a dropped duplicate straight to its kept post\'s Statamic URL', function (): void {
    makeDedupKeptPost('mobile-spa', 'https://news.uams.test/2008/10/10/mobile-spa-a-treat/');

    $report = app(MigrationRedirectGenerator::class)->generate(false, [], [
        dedupPair('https://news.uams.test/2008/10/10/mobile-spa-treat/', 'https://news.uams.test/2008/10/10/mobile-spa-a-treat/', 'Duplicate of WordPress post 12 (#798)'),
    ]);

    $rows = dedupStoredRows();

    // One hop: the dropped path to the kept entry's Statamic URL, never to its WordPress URL
    expect($report->dedupMoves)->toHaveCount(1)
        ->and($report->dedupRefused)->toBe([])
        ->and($rows)->toHaveCount(1)
        ->and($rows[0])->toMatchArray([
            'source' => '/2008/10/10/mobile-spa-treat',
            'target_url' => '/news/mobile-spa',
            'response' => '301',
            'notes' => 'Duplicate of WordPress post 12 (#798)',
        ]);
});

it('matches the target regardless of scheme, a leading www. and a trailing slash', function (): void {
    makeDedupKeptPost('kept', 'https://news.uams.test/2010/01/01/kept/');

    $report = app(MigrationRedirectGenerator::class)->generate(false, [], [
        dedupPair('http://www.news.uams.test/2010/01/01/dropped', 'http://www.news.uams.test/2010/01/01/KEPT'),
    ]);

    // With no notes in the file, the row says where the pair came from
    expect($report->dedupMoves)->toHaveCount(1)
        ->and(dedupStoredRows()[0] ?? [])->toMatchArray([
            'source' => '/2010/01/01/dropped',
            'target_url' => '/news/kept',
            'notes' => 'Dropped duplicate of http://www.news.uams.test/2010/01/01/KEPT (#798).',
        ]);
});

it('does not match a target on another host', function (): void {
    makeDedupKeptPost('kept', 'https://news.uams.test/2010/01/01/kept/');

    $report = app(MigrationRedirectGenerator::class)->generate(false, [], [
        dedupPair('https://other.uams.test/2010/01/01/dropped/', 'https://other.uams.test/2010/01/01/kept/'),
    ]);

    expect($report->dedupMoves)->toBe([])
        ->and($report->dedupRefused)->toHaveCount(1);
});

it('goes on to the next pair after one it refuses, and passes over entries that were not imported', function (): void {
    makeDedupKeptPost('kept', 'https://news.uams.test/kept/');

    // A natively authored entry carries no original_wordpress_url and has nothing to match
    /** @var Statamic\Entries\Entry $native */
    $native = Entry::make();
    $native->collection('dedup');
    $native->id('dedup-native');
    $native->slug('native');
    $native->data(['title' => 'Native']);
    $native->saveQuietly();

    $report = app(MigrationRedirectGenerator::class)->generate(false, [], [
        dedupPair('https://news.uams.test/orphan/', 'https://news.uams.test/missing/'),
        dedupPair('https://news.uams.test/dropped/', 'https://news.uams.test/kept/'),
    ]);

    expect(array_column($report->dedupRefused, 'source'))->toBe(['https://news.uams.test/orphan/'])
        ->and(array_column(dedupStoredRows(), 'target_url', 'source'))->toBe(['/dropped' => '/news/kept']);
});

it('reports and does not write a pair whose target no entry carries', function (): void {
    makeDedupKeptPost('kept', 'https://news.uams.test/2010/01/01/kept/');

    $report = app(MigrationRedirectGenerator::class)->generate(false, [], [
        dedupPair('https://news.uams.test/2010/01/01/dropped/', 'https://news.uams.test/2010/01/01/never-imported/'),
    ]);

    expect($report->dedupMoves)->toBe([])
        ->and($report->dedupRefused)->toBe([[
            'source' => 'https://news.uams.test/2010/01/01/dropped/',
            'target' => 'https://news.uams.test/2010/01/01/never-imported/',
            'reason' => 'no entry has the target as its original_wordpress_url',
        ]])
        ->and(dedupStoredRows())->toBe([]);
});

it('reports a pair whose target is itself dropped, and writes the pair it points through', function (): void {
    makeDedupKeptPost('kept', 'https://news.uams.test/c/');

    // a was dropped in favor of b, but b was itself dropped in favor of c
    $report = app(MigrationRedirectGenerator::class)->generate(false, [], [
        dedupPair('https://news.uams.test/a/', 'https://news.uams.test/b/'),
        dedupPair('https://news.uams.test/b/', 'https://news.uams.test/c/'),
    ]);

    expect(array_column($report->dedupRefused, 'source'))->toBe(['https://news.uams.test/a/'])
        ->and($report->dedupRefused[0]['reason'] ?? null)->toBe('the target is itself a dropped duplicate')
        ->and(array_column(dedupStoredRows(), 'source'))->toBe(['/b']);
});

it('reports both pairs of a cycle and writes neither', function (): void {
    makeDedupKeptPost('a', 'https://news.uams.test/a/');
    makeDedupKeptPost('b', 'https://news.uams.test/b/');

    $report = app(MigrationRedirectGenerator::class)->generate(false, [], [
        dedupPair('https://news.uams.test/a/', 'https://news.uams.test/b/'),
        dedupPair('https://news.uams.test/b/', 'https://news.uams.test/a/'),
    ]);

    expect($report->dedupMoves)->toBe([])
        ->and(array_column($report->dedupRefused, 'reason'))->toBe([
            'the target is itself a dropped duplicate',
            'the target is itself a dropped duplicate',
        ])
        ->and(dedupStoredRows())->toBe([]);
});

it('reports a pair whose kept entry already lives at a dropped duplicate\'s path', function (): void {
    // The kept post's Statamic URL is /news/kept, which another pair drops
    makeDedupKeptPost('kept', 'https://news.uams.test/2010/kept/');
    makeDedupKeptPost('other', 'https://news.uams.test/2010/other/');

    $report = app(MigrationRedirectGenerator::class)->generate(false, [], [
        dedupPair('https://news.uams.test/2010/dropped/', 'https://news.uams.test/2010/kept/'),
        dedupPair('https://news.uams.test/news/kept/', 'https://news.uams.test/2010/other/'),
    ]);

    // Writing /2010/dropped -> /news/kept beside /news/kept -> /news/other would chain into a
    // dropped URL, so the first is refused; the second is a plain move
    expect(array_column($report->dedupRefused, 'source'))->toBe(['https://news.uams.test/2010/dropped/'])
        ->and($report->dedupRefused[0]['reason'] ?? null)->toBe("the target entry's URL is a dropped duplicate's path, so the redirect would loop")
        ->and(array_column(dedupStoredRows(), 'source'))->toBe(['/news/kept']);
});

it('reports every pair of a source listed with two different targets', function (): void {
    makeDedupKeptPost('one', 'https://news.uams.test/one/');
    makeDedupKeptPost('two', 'https://news.uams.test/two/');

    $report = app(MigrationRedirectGenerator::class)->generate(false, [], [
        dedupPair('https://news.uams.test/dropped/', 'https://news.uams.test/one/'),
        dedupPair('https://news.uams.test/dropped/', 'https://news.uams.test/two/'),
    ]);

    expect($report->dedupMoves)->toBe([])
        ->and(array_column($report->dedupRefused, 'reason'))->toBe([
            'the source is listed with more than one target',
            'the source is listed with more than one target',
        ]);
});

it('adds nothing on a re-run, and leaves a human-authored rule for the same source alone', function (): void {
    makeDedupKeptPost('kept', 'https://news.uams.test/kept/');
    $pairs = [
        dedupPair('https://news.uams.test/dropped/', 'https://news.uams.test/kept/'),
        dedupPair('https://news.uams.test/hand-made/', 'https://news.uams.test/kept/'),
    ];

    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::findByHandle('redirects');

    /** @var Variables $vars */
    $vars = $set->in(mustBe(Statamic\Sites\Site::class, Site::default(), 'the default site')->handle());
    $vars->set('redirects', [[
        'id' => 'human', 'type' => 'redirect', 'source' => '/hand-made', 'target_type' => 'url',
        'target_url' => '/human-chosen', 'response' => '301', 'enabled' => true,
    ]]);
    $vars->save();

    $generator = app(MigrationRedirectGenerator::class);
    $first = $generator->generate(false, [], $pairs);
    $second = $generator->generate(false, [], $pairs);

    $rows = dedupStoredRows();

    expect(array_map(static fn ($move): string => $move->source, $first->dedupMoves))->toBe(['/dropped'])
        ->and($second->dedupMoves)->toBe([])
        // The duplicate, the hand-made rule, and the kept post's own entry move
        ->and($second->skippedExisting)->toBe(3)
        ->and($rows)->toHaveCount(2)
        ->and(array_column($rows, 'target_url', 'source'))->toMatchArray(['/hand-made' => '/human-chosen', '/dropped' => '/news/kept']);
});

it('reports the duplicates on a dry run without writing them, and names every pair it refused', function (): void {
    makeDedupKeptPost('kept', 'https://news.uams.test/kept/');
    $path = dedupPairFile((string) json_encode([
        ['source' => 'https://news.uams.test/dropped/', 'target' => 'https://news.uams.test/kept/', 'response' => '301', 'notes' => 'Duplicate (#798)'],
        ['source' => 'https://news.uams.test/orphan/', 'target' => 'https://news.uams.test/missing/', 'response' => '301'],
        ['source' => 'https://news.uams.test/no-target/'],
    ]));

    try {
        expect(Artisan::call('content:generate-migration-redirects', ['--dry-run' => true, '--dedup-redirects' => $path]))->toBe(0);
        $output = Artisan::output();

        expect($output)->toContain('Duplicates (301):      1')
            ->and($output)->toContain('Duplicate pair not written (row 3 has no source or no target): https://news.uams.test/no-target/ -> ')
            ->and($output)->toContain('Duplicate pair not written (no entry has the target as its original_wordpress_url): https://news.uams.test/orphan/ -> https://news.uams.test/missing/')
            // The duplicate, and the kept post's own entry move
            ->and($output)->toContain('Would write 2 migration redirects.')
            ->and(dedupStoredRows())->toBe([]);

        // The same file, written for real
        expect(Artisan::call('content:generate-migration-redirects', ['--dedup-redirects' => $path]))->toBe(0)
            ->and(array_column(dedupStoredRows(), 'target_url', 'source'))->toBe(['/dropped' => '/news/kept']);
    } finally {
        File::delete($path);
    }
});

it('refuses a pair file that cannot be read or is not a list, and writes nothing', function (?string $json): void {
    // A unique name that is never written, so a parallel worker cannot create it
    $path = $json === null ? storage_path('framework/testing/no-such-dedup-pairs-'.uniqid('', true).'.json') : dedupPairFile($json);

    try {
        expect(Artisan::call('content:generate-migration-redirects', ['--dedup-redirects' => $path]))->toBe(1)
            ->and(Artisan::output())->toContain($json === null ? 'Could not read duplicate redirect pairs' : 'Duplicate redirect pairs are not a JSON list')
            ->and(dedupStoredRows())->toBe([]);
    } finally {
        File::delete($path);
    }
})->with([
    'missing file' => [null],
    'an object, not a list' => ['{"source": "https://news.uams.test/a/"}'],
    'not JSON' => ['source,target'],
]);

it('reads each row it can, after the rows it cannot, trimming URLs and notes', function (): void {
    makeDedupKeptPost('kept', 'https://news.uams.test/kept/');
    makeDedupKeptPost('second', 'https://news.uams.test/second/');
    $path = dedupPairFile((string) json_encode([
        'not an object',
        ['target' => 'https://news.uams.test/kept/'],
        ['source' => '  https://news.uams.test/dropped/  ', 'target' => ' https://news.uams.test/kept/ ', 'notes' => '  Duplicate of post 7 (#798)  '],
        ['source' => 'https://news.uams.test/blank-notes/', 'target' => 'https://news.uams.test/second/', 'notes' => '   '],
    ]));

    try {
        expect(Artisan::call('content:generate-migration-redirects', ['--dedup-redirects' => $path]))->toBe(0);
        $output = Artisan::output();

        $rows = [];

        foreach (dedupStoredRows() as $row) {
            $rows[mustBeString($row['source'] ?? null, 'a stored source')] = $row['notes'] ?? null;
        }

        expect($output)->toContain('Duplicate pair not written (row 1 has no source or no target):  -> ')
            ->and($output)->toContain('Duplicate pair not written (row 2 has no source or no target):  -> https://news.uams.test/kept/')
            // The file's notes are kept, trimmed; blank notes fall back to the pair's provenance
            ->and($rows)->toBe([
                '/dropped' => 'Duplicate of post 7 (#798)',
                '/blank-notes' => 'Dropped duplicate of https://news.uams.test/second/ (#798).',
            ]);
    } finally {
        File::delete($path);
    }
});

it('reads an empty --dedup-redirects as no file', function (): void {
    expect(Artisan::call('content:generate-migration-redirects', ['--dedup-redirects' => '']))->toBe(0)
        ->and(Artisan::output())->toContain('Duplicates (301):      0');
});

it('treats an empty list as no duplicates', function (): void {
    $path = dedupPairFile('[]');

    try {
        expect(Artisan::call('content:generate-migration-redirects', ['--dedup-redirects' => $path]))->toBe(0)
            ->and(Artisan::output())->toContain('Duplicates (301):      0');
    } finally {
        File::delete($path);
    }
});

/**
 * The duplicate moves a report planned, as source path => target URL.
 *
 * A kept post that carries a WordPress post id also gets its `?p=` structural redirect, a
 * different disposition stored beside these, so the report's own list is read instead.
 *
 * @return array<string, string|null>
 */
function dedupMovesBySource(MigrationRedirectReport $report): array
{
    $moves = [];

    foreach ($report->dedupMoves as $move) {
        $moves[$move->source] = $move->target;
    }

    return $moves;
}

/**
 * Save a kept post stamped with its WordPress post id, as the importer stamps one (UAMS-Web/uams-statamic-archive#2960).
 */
function makeDedupKeptPostWithId(string $slug, string $originalUrl, int $postId, string $collection = 'dedup', string $site = 'default'): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection($collection);
    $entry->locale($site);
    $entry->id("dedup-{$collection}-{$site}-{$slug}");
    $entry->slug($slug);
    $entry->data(['title' => ucfirst($slug), 'original_wordpress_url' => $originalUrl, 'wordpress_post_id' => $postId]);
    $entry->saveQuietly();
}

/**
 * A pair as the cleaner writes it for #798, with both WordPress post ids.
 *
 * @return array{source: string, target: string, notes: string|null, source_wordpress_post_id: int|null, target_wordpress_post_id: int|null}
 */
function dedupPairWithIds(string $source, string $target, ?int $sourceId, ?int $targetId): array
{
    return ['source' => $source, 'target' => $target, 'notes' => null, 'source_wordpress_post_id' => $sourceId, 'target_wordpress_post_id' => $targetId];
}

it('resolves a pair by the kept post\'s id, whatever host or link form its original_wordpress_url has (#2960)', function (string $originalUrl): void {
    // The export's <link>: the local export host, pretty or in the ?p= form, never the public URL
    makeDedupKeptPostWithId('kept', $originalUrl, 12240);

    $report = app(MigrationRedirectGenerator::class)->generate(false, [], [
        dedupPairWithIds('https://news.uams.edu/2008/10/06/formerstudent/', 'https://news.uams.edu/2008/10/06/former-student/', 16831, 12240),
    ]);

    expect($report->dedupRefused)->toBe([])
        ->and(dedupMovesBySource($report))->toBe(['/2008/10/06/formerstudent' => '/news/kept']);
})->with([
    'a pretty permalink on the export host' => ['https://uams-news.test/2008/10/06/former-student/'],
    'the ?p= form' => ['https://uams-news.test/?p=12240'],
]);

it('refuses a pair whose target id no entry carries, rather than resolving it by URL (#2960)', function (): void {
    // An entry whose original_wordpress_url IS the target, under another id: a URL lookup
    // would resolve it, which is exactly what an id miss must not fall back to
    makeDedupKeptPostWithId('kept', 'https://news.uams.edu/2010/01/01/kept/', 99);

    $report = app(MigrationRedirectGenerator::class)->generate(false, [], [
        dedupPairWithIds('https://news.uams.edu/2010/01/01/dropped/', 'https://news.uams.edu/2010/01/01/kept/', 98, 12240),
    ]);

    expect($report->dedupMoves)->toBe([])
        ->and($report->dedupRefused)->toBe([[
            'source' => 'https://news.uams.edu/2010/01/01/dropped/',
            'target' => 'https://news.uams.edu/2010/01/01/kept/',
            'reason' => "no entry has the target's WordPress post id 12240",
        ]])
        ->and(dedupMovesBySource($report))->toBe([]);
});

it('resolves a pair without a target id by URL, beside one resolved by id (#2960)', function (): void {
    makeDedupKeptPost('by-url', 'https://news.uams.test/2010/01/01/by-url/');
    makeDedupKeptPostWithId('by-id', 'https://uams-news.test/?p=500', 500);

    $report = app(MigrationRedirectGenerator::class)->generate(false, [], [
        dedupPair('https://news.uams.test/2010/01/01/url-dropped/', 'https://news.uams.test/2010/01/01/by-url/'),
        dedupPairWithIds('https://news.uams.edu/2010/01/01/id-dropped/', 'https://news.uams.edu/2010/01/01/by-id/', 501, 500),
    ]);

    expect($report->dedupRefused)->toBe([])
        ->and(dedupMovesBySource($report))->toBe([
            '/2010/01/01/url-dropped' => '/news/by-url',
            '/2010/01/01/id-dropped' => '/news/by-id',
        ]);
});

it('refuses a pair whose target id is another pair\'s dropped post (#2960)', function (): void {
    makeDedupKeptPostWithId('b', 'https://uams-news.test/?p=2', 2);
    makeDedupKeptPostWithId('c', 'https://uams-news.test/?p=3', 3);

    // a was dropped for post 2, but post 2 was itself dropped for post 3; the URLs share
    // nothing, so only the ids show it
    $report = app(MigrationRedirectGenerator::class)->generate(false, [], [
        dedupPairWithIds('https://news.uams.edu/a/', 'https://news.uams.edu/b-public/', 1, 2),
        dedupPairWithIds('https://news.uams.edu/b-other/', 'https://news.uams.edu/c/', 2, 3),
    ]);

    expect(array_column($report->dedupRefused, 'reason', 'source'))->toBe(['https://news.uams.edu/a/' => 'the target is itself a dropped duplicate'])
        ->and(dedupMovesBySource($report))->toBe(['/b-other' => '/news/c']);
});

it('refuses a target id two entries carry, and resolves it among one site\'s entries when a site is named (#2960)', function (): void {
    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::make('dedup_sites');
    $collection->sites(['default', 'news']);
    $collection->routes(['default' => '/default-posts/{slug}', 'news' => '/news-posts/{slug}']);
    $collection->save();

    // Post 40 on two WordPress subsites, imported to two Statamic sites
    makeDedupKeptPostWithId('elsewhere', 'https://uams-other.test/?p=40', 40, 'dedup_sites', 'default');
    makeDedupKeptPostWithId('kept', 'https://uams-news.test/?p=40', 40, 'dedup_sites', 'news');

    $pair = dedupPairWithIds('https://news.uams.edu/dropped/', 'https://news.uams.edu/kept/', 41, 40);
    $generator = app(MigrationRedirectGenerator::class);

    $everySite = $generator->planDedupRedirects([$pair]);
    $newsOnly = $generator->planDedupRedirects([$pair], 'news');

    expect($everySite['moves'])->toBe([])
        ->and(array_column($everySite['refused'], 'reason'))->toBe(["more than one entry has the target's WordPress post id 40"])
        ->and($newsOnly['refused'])->toBe([])
        ->and($newsOnly['moves'])->toHaveCount(1)
        ->and((string) $newsOnly['moves'][0]->target)->toEndWith('/news-posts/kept');
});

it('reads each row\'s WordPress post ids, and reports a row whose id is not a positive integer (#2960)', function (): void {
    makeDedupKeptPostWithId('int-id', 'https://uams-news.test/?p=70', 70);
    makeDedupKeptPostWithId('string-id', 'https://uams-news.test/?p=71', 71);
    $path = dedupPairFile((string) json_encode([
        ['source' => 'https://news.uams.edu/a/', 'target' => 'https://news.uams.edu/int-id/', 'source_wordpress_post_id' => 80, 'target_wordpress_post_id' => 70],
        ['source' => 'https://news.uams.edu/b/', 'target' => 'https://news.uams.edu/string-id/', 'source_wordpress_post_id' => ' 81 ', 'target_wordpress_post_id' => '71'],
        ['source' => 'https://news.uams.edu/c/', 'target' => 'https://news.uams.edu/zero/', 'target_wordpress_post_id' => 0],
        ['source' => 'https://news.uams.edu/d/', 'target' => 'https://news.uams.edu/word/', 'source_wordpress_post_id' => 'twelve'],
        ['source' => 'https://news.uams.edu/e/', 'target' => 'https://news.uams.edu/float/', 'target_wordpress_post_id' => 7.5],
    ]));

    try {
        expect(Artisan::call('content:generate-migration-redirects', ['--dedup-redirects' => $path, '--dedup-site' => 'default', '--dry-run' => true]))->toBe(0);
        $output = Artisan::output();

        expect($output)->toContain('Duplicates (301):      2')
            ->and($output)->toContain('Duplicate pair not written (row 3 has a target_wordpress_post_id that is not a positive integer): https://news.uams.edu/c/ -> https://news.uams.edu/zero/')
            ->and($output)->toContain('Duplicate pair not written (row 4 has a source_wordpress_post_id that is not a positive integer): https://news.uams.edu/d/ -> https://news.uams.edu/word/')
            ->and($output)->toContain('Duplicate pair not written (row 5 has a target_wordpress_post_id that is not a positive integer): https://news.uams.edu/e/ -> https://news.uams.edu/float/');
    } finally {
        File::delete($path);
    }
});

it('requires --dedup-site when a pair carries a WordPress post id, and writes nothing without it (#2960)', function (array $ids, ?string $site): void {
    makeDedupKeptPostWithId('kept', 'https://uams-news.test/?p=90', 90);
    $path = dedupPairFile((string) json_encode([
        ['source' => 'https://news.uams.edu/plain/', 'target' => 'https://news.uams.edu/kept/'],
        ['source' => 'https://news.uams.edu/dropped/', 'target' => 'https://news.uams.edu/kept/', ...$ids],
    ]));
    $options = ['--dedup-redirects' => $path];

    if ($site !== null) {
        $options['--dedup-site'] = $site;
    }

    try {
        expect(Artisan::call('content:generate-migration-redirects', $options))->toBe(1)
            ->and(Artisan::output())->toContain('pass --dedup-site=<handle> with the site their posts were imported into')
            ->and(dedupStoredRows())->toBe([]);
    } finally {
        File::delete($path);
    }
})->with([
    'a target id, no option' => [['target_wordpress_post_id' => 90], null],
    'a source id only, no option' => [['source_wordpress_post_id' => 91], null],
    'a target id, a blank option' => [['target_wordpress_post_id' => 90], '  '],
]);

it('refuses a --dedup-site that names no site, and writes nothing (#2960)', function (): void {
    makeDedupKeptPostWithId('kept', 'https://uams-news.test/?p=90', 90);
    $path = dedupPairFile((string) json_encode([
        ['source' => 'https://news.uams.edu/dropped/', 'target' => 'https://news.uams.edu/kept/', 'target_wordpress_post_id' => 90],
    ]));

    try {
        expect(Artisan::call('content:generate-migration-redirects', ['--dedup-redirects' => $path, '--dedup-site' => 'no-such-site']))->toBe(1)
            ->and(Artisan::output())->toContain('No site has the handle given to --dedup-site: no-such-site')
            ->and(dedupStoredRows())->toBe([]);
    } finally {
        File::delete($path);
    }
});

it('needs no --dedup-site for a file without post ids, and accepts one there (#2960)', function (?string $site): void {
    makeDedupKeptPost('kept', 'https://news.uams.test/kept/');
    $path = dedupPairFile((string) json_encode([
        ['source' => 'https://news.uams.test/dropped/', 'target' => 'https://news.uams.test/kept/'],
    ]));
    $options = ['--dedup-redirects' => $path];

    if ($site !== null) {
        $options['--dedup-site'] = $site;
    }

    try {
        expect(Artisan::call('content:generate-migration-redirects', $options))->toBe(0)
            ->and(array_column(dedupStoredRows(), 'target_url', 'source'))->toBe(['/dropped' => '/news/kept']);
    } finally {
        File::delete($path);
    }
})->with([
    'no option' => [null],
    'the news site' => ['news'],
]);

it('looks a pair\'s post id up among the --dedup-site site\'s entries only (#2960)', function (string $site): void {
    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::make('dedup_sites');
    $collection->sites(['default', 'news']);
    $collection->routes(['default' => '/default-posts/{slug}', 'news' => '/news-posts/{slug}']);
    $collection->save();

    // Post 40 on two WordPress subsites, imported to two Statamic sites
    makeDedupKeptPostWithId('elsewhere', 'https://uams-other.test/?p=40', 40, 'dedup_sites', 'default');
    makeDedupKeptPostWithId('kept', 'https://uams-news.test/?p=40', 40, 'dedup_sites', 'news');
    $path = dedupPairFile((string) json_encode([
        ['source' => 'https://news.uams.edu/dropped/', 'target' => 'https://news.uams.edu/kept/', 'source_wordpress_post_id' => 41, 'target_wordpress_post_id' => 40],
    ]));

    try {
        expect(Artisan::call('content:generate-migration-redirects', ['--dedup-redirects' => $path, '--dedup-site' => $site]))->toBe(0);

        $targets = array_column(dedupStoredRows(), 'target_url', 'source');

        expect($targets)->toHaveKey('/dropped')
            ->and(mustBeString($targets['/dropped'], 'the dropped post\'s target'))->toEndWith($site === 'news' ? '/news-posts/kept' : '/default-posts/elsewhere');
    } finally {
        File::delete($path);
    }
})->with([
    'the news site' => ['news'],
    'the default site' => ['default'],
]);
