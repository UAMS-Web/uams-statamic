<?php

declare(strict_types=1);

use App\Services\GoogleBusinessProfile\Categories\ApiCategorySource;
use App\Services\GoogleBusinessProfile\Categories\CategoryCheckLog;
use App\Services\GoogleBusinessProfile\Categories\CategorySourceUnavailable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Coverage for UAMS-Web/uams-statamic-archive#2617: `gbp:check-categories --api` reads the category list from the Google
 * Business Information API, a page at a time, and reports it the way a file run does.
 *
 * The API is faked from a recorded response in the API's shape:
 * `tests/Fixtures/GoogleBusinessProfile/api/categories-page-{1,2}.json`, two pages joined by
 * a `nextPageToken`. They are written for the test rather than saved from a live call: the
 * Business Profile APIs storage policy quoted in UAMS-Web/uams-statamic-archive#2091 rules out keeping fetched category
 * data, and a live call needs the stored credentials. Their categories match the file
 * fixture, `categories.tsv`, so both sources report the same differences against the same
 * terms; the second page's last record has no `gcid:` prefix and is reported as malformed.
 *
 * Stray requests are refused, so no test can reach Google, and the credentials are
 * placeholders that every test checks never reach the output or the log.
 *
 * @command  php artisan test --compact tests/Feature/GoogleBusinessProfile/CheckGoogleBusinessProfileCategoriesApiTest.php
 */
uses(StacheIsolatedTestCase::class);

const GBP_API_FIXTURES = 'tests/Fixtures/GoogleBusinessProfile/api';

/**
 * Placeholder credentials, distinctive enough to find if one ever leaks.
 *
 * @var array{client_id: string, client_secret: string, refresh_token: string}
 */
const GBP_API_CREDENTIALS = [
    'client_id' => 'placeholder-client-id-2617',
    'client_secret' => 'placeholder-client-secret-2617',
    'refresh_token' => 'placeholder-refresh-token-2617',
];

/**
 * Save a category term.
 */
function gbpApiTerm(string $slug, string $title, ?string $gcid): void
{
    $term = mustBe(TaxonomyTerm::class, Term::make(), 'the category term');
    $term->taxonomy('google_business_profile_categories');
    $term->slug($slug);
    $term->dataForLocale($term->defaultLocale(), array_filter(['title' => $title, 'gcid' => $gcid], fn (?string $value): bool => $value !== null));
    $term->saveQuietly();
}

/**
 * One recorded page of the `categories.list` response.
 *
 * @return array<string, mixed>
 */
function gbpApiPage(int $page): array
{
    $path = base_path(GBP_API_FIXTURES.'/categories-page-'.$page.'.json');

    /** @var array<string, mixed> */
    return mustBeArray(json_decode((string) file_get_contents($path), true), 'the recorded page '.$page);
}

/**
 * Fake Google: the token endpoint, then each page by its page token.
 *
 * @param  array<string, mixed>|null  $tokenResponse  The token endpoint's JSON, or null for a working token.
 * @param  int  $tokenStatus  The token endpoint's status.
 * @param  (Closure(Request): mixed)|null  $pages  The categories endpoint, or null for the recorded pages.
 */
function gbpApiFake(?array $tokenResponse = null, int $tokenStatus = 200, ?Closure $pages = null): void
{
    Http::preventStrayRequests();

    Http::fake([
        ApiCategorySource::TOKEN_URL => Http::response($tokenResponse ?? ['access_token' => 'placeholder-access-token', 'expires_in' => 3599, 'token_type' => 'Bearer'], $tokenStatus),
        ApiCategorySource::CATEGORIES_URL.'*' => $pages ?? static function (Request $request): mixed {
            $token = $request->data()['pageToken'] ?? null;

            return Http::response(gbpApiPage($token === 'fixture-page-2' ? 2 : 1));
        },
    ]);
}

/**
 * Everything a run could have shown or logged, for the credential checks.
 *
 * @param  list<string>  $logged  Log messages and their context, encoded.
 * @return list<string> The names of any credentials found.
 */
function gbpApiLeaks(string $output, array $logged): array
{
    $leaks = [];

    foreach (GBP_API_CREDENTIALS as $name => $value) {
        if (str_contains($output, $value) || str_contains(implode("\n", $logged), $value)) {
            $leaks[] = $name;
        }
    }

    return $leaks;
}

beforeEach(function (): void {
    Cache::forget(CategoryCheckLog::CACHE_KEY);
    Carbon::setTestNow(Carbon::parse('2026-10-15 06:00:00', 'UTC'));
    config()->set('services.google_business_profile', GBP_API_CREDENTIALS);

    $taxonomy = mustBe(Statamic\Taxonomies\Taxonomy::class, Taxonomy::make('google_business_profile_categories'), 'the category taxonomy');
    $taxonomy->save();

    $blueprint = Blueprint::makeFromFields(['title' => ['type' => 'text'], 'gcid' => ['type' => 'text']]);
    $blueprint->setHandle('google_business_profile_category');
    $blueprint->setNamespace('taxonomies.google_business_profile_categories');
    $blueprint->save();

    gbpApiTerm('acupuncturist', 'Acupuncturist', 'acupuncturist');
    gbpApiTerm('acupuncture-clinic', 'Acupuncture clinic', 'acupuncture_clinic');
    gbpApiTerm('discontinued-category', 'Discontinued category', 'discontinued_category');
    gbpApiTerm('aa-no-gcid-category', 'No GCID category', null);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('reads every page and reports new, renamed and removed GCIDs', function (): void {
    gbpApiFake();

    expect(Artisan::call('gbp:check-categories', ['--api' => true]))->toBe(0);

    $output = Artisan::output();
    $latest = app(CategoryCheckLog::class)->latest();
    $report = $latest['report'] ?? [];

    expect($output)->toContain('3 categories read: 1 new, 1 renamed, 1 removed, 0 listed but unreadable, 1 terms with no GCID, 1 malformed lines.')
        // The new category is on the second page, so it is there only if the page token was followed
        ->and($report['new'] ?? null)->toBe(['addiction_treatment_center' => 'Addiction treatment center'])
        ->and($report['renamed'] ?? null)->toBe([[
            'gcid' => 'acupuncture_clinic',
            'slug' => 'acupuncture-clinic',
            'title' => 'Acupuncture clinic',
            'name' => 'Acupuncture Clinic',
        ]])
        ->and($report['removed'] ?? null)->toBe([['gcid' => 'discontinued_category', 'slug' => 'discontinued-category', 'title' => 'Discontinued category']])
        ->and($report['malformed'] ?? null)->toBe([[
            'line' => 4,
            'content' => '{"name":"acupuncturist","displayName":"A record without the gcid: prefix"}',
            'reason' => 'the name is not a gcid: identifier',
        ]])
        ->and($latest['source'] ?? null)->toBe('Google Business Information API (US, en)')
        ->and($latest['checked_at'] ?? null)->toBe('2026-10-15T06:00:00+00:00');
});

it('asks for US English categories in the basic view, a hundred a page, with a minted token', function (): void {
    gbpApiFake();

    Artisan::call('gbp:check-categories', ['--api' => true]);

    // One token exchange, from the refresh token, as a form post
    Http::assertSent(static fn (Request $request): bool => $request->url() === ApiCategorySource::TOKEN_URL
        && $request->isForm()
        && $request->data() === [
            'grant_type' => 'refresh_token',
            'client_id' => GBP_API_CREDENTIALS['client_id'],
            'client_secret' => GBP_API_CREDENTIALS['client_secret'],
            'refresh_token' => GBP_API_CREDENTIALS['refresh_token'],
        ]);

    /** @var list<array<string, mixed>> $pages */
    $pages = [];
    Http::assertSent(static function (Request $request) use (&$pages): bool {
        if (str_starts_with($request->url(), ApiCategorySource::CATEGORIES_URL)) {
            expect($request->hasHeader('Authorization', 'Bearer placeholder-access-token'))->toBeTrue();
            $pages[] = $request->data();
        }

        return true;
    });

    expect($pages)->toBe([
        ['regionCode' => 'US', 'languageCode' => 'en', 'view' => 'BASIC', 'pageSize' => 100],
        ['regionCode' => 'US', 'languageCode' => 'en', 'view' => 'BASIC', 'pageSize' => 100, 'pageToken' => 'fixture-page-2'],
    ]);
    Http::assertSentCount(3);
});

it('records nothing, logs the failure and keeps the last good check when the fetch fails', function (Closure $fake, string $reason): void {
    // A good check from the pasted file, as the widget would be showing it
    expect(Artisan::call('gbp:check-categories', ['file' => base_path('tests/Fixtures/GoogleBusinessProfile/categories.tsv')]))->toBe(0);
    $before = app(CategoryCheckLog::class)->latest();
    expect($before['source'] ?? null)->toStartWith('file: ');

    $logged = [];
    Log::listen(static function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event->level.' '.$event->message.' '.json_encode($event->context);
    });

    $fake();
    Carbon::setTestNow(Carbon::parse('2026-11-01 06:00:00', 'UTC'));

    expect(Artisan::call('gbp:check-categories', ['--api' => true]))->toBe(1);
    $output = Artisan::output();

    // The failure is reported and logged, and names its cause
    expect($output)->toContain($reason)
        ->and(implode("\n", $logged))->toContain('error gbp:check-categories could not read its source')
        ->and(implode("\n", $logged))->toContain($reason)
        ->and(implode("\n", $logged))->toContain('Google Business Information API (US, en)')
        // No changes are reported, and the last good check is untouched
        ->and($output)->not->toContain('categories read')
        ->and(app(CategoryCheckLog::class)->latest())->toBe($before)
        // No credential reaches the output or the log
        ->and(gbpApiLeaks($output, $logged))->toBe([]);
})->with([
    'a refused refresh token' => [
        static fn () => gbpApiFake(['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.'], 400),
        'Google refused an access token (HTTP 400, invalid_grant).',
    ],
    'a token response with an empty access token' => [
        static fn () => gbpApiFake(['access_token' => '', 'token_type' => 'Bearer'], 200),
        'Google refused an access token (HTTP 200).',
    ],
    'a failed page with no error status' => [
        static fn () => gbpApiFake(pages: static fn (): mixed => Http::response(['error' => ['code' => 500, 'status' => '']], 500)),
        'categories.list failed on page 1 (HTTP 500).',
    ],
    'a token response with no access token' => [
        static fn () => gbpApiFake(['token_type' => 'Bearer'], 200),
        'Google refused an access token (HTTP 200).',
    ],
    'the quota exhausted on the second page' => [
        static fn () => gbpApiFake(pages: static function (Request $request): mixed {
            return ($request->data()['pageToken'] ?? null) === 'fixture-page-2'
                ? Http::response(['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'Quota exceeded.']], 429)
                : Http::response(gbpApiPage(1));
        }),
        'categories.list failed on page 2 (HTTP 429, RESOURCE_EXHAUSTED).',
    ],
    'the network down' => [
        static fn () => gbpApiFake(pages: static fn (): never => throw new ConnectionException('cURL error 6: Could not resolve host')),
        'Could not reach categories.list for page 1: cURL error 6',
    ],
    'a first page with no categories' => [
        static fn () => gbpApiFake(pages: static fn (): mixed => Http::response(['categories' => []])),
        'categories.list page 1 held no categories.',
    ],
    // The gate's two shapes on UAMS-Web/uams-statamic-archive#2930: a last page with an empty list, or with no list at all,
    // ended the read as though it were complete, and the categories after page 1 read as removed
    'a second page with an empty list and no token' => [
        static fn () => gbpApiFake(pages: static function (Request $request): mixed {
            return ($request->data()['pageToken'] ?? null) === 'fixture-page-2'
                ? Http::response(['categories' => []])
                : Http::response(gbpApiPage(1));
        }),
        'categories.list page 2 held no categories.',
    ],
    'a second page with no list and no token' => [
        static fn () => gbpApiFake(pages: static function (Request $request): mixed {
            return ($request->data()['pageToken'] ?? null) === 'fixture-page-2'
                ? Http::response([])
                : Http::response(gbpApiPage(1));
        }),
        'categories.list page 2 did not hold a list of categories.',
    ],
    // UAMS-Web/uams-statamic-archive#2930: a token that is present but not a string read as the end of the list, so the
    // categories on page 2 read as removed
    'a first page whose nextPageToken is a number' => [
        static fn () => gbpApiFake(pages: static fn (): mixed => Http::response([...gbpApiPage(1), 'nextPageToken' => 123])),
        'categories.list page 1 had a nextPageToken that is not a string.',
    ],
    'a first page whose nextPageToken is an object' => [
        static fn () => gbpApiFake(pages: static fn (): mixed => Http::response([...gbpApiPage(1), 'nextPageToken' => ['a' => 'b']])),
        'categories.list page 1 had a nextPageToken that is not a string.',
    ],
    'a first page whose nextPageToken is a boolean' => [
        static fn () => gbpApiFake(pages: static fn (): mixed => Http::response([...gbpApiPage(1), 'nextPageToken' => true])),
        'categories.list page 1 had a nextPageToken that is not a string.',
    ],
    'a list whose every record is unreadable' => [
        static fn () => gbpApiFake(pages: static fn (): mixed => Http::response(['categories' => [['name' => 'dentist', 'displayName' => 'Dentist']]])),
        'categories.list returned no categories.',
    ],
    'a second page that is not JSON' => [
        static fn () => gbpApiFake(pages: static function (Request $request): mixed {
            return ($request->data()['pageToken'] ?? null) === 'fixture-page-2'
                ? Http::response('<html><body>Service temporarily unavailable</body></html>', 200, ['Content-Type' => 'text/html'])
                : Http::response(gbpApiPage(1));
        }),
        'categories.list page 2 was not a JSON object.',
    ],
    'a page that is not a list' => [
        static fn () => gbpApiFake(pages: static fn (): mixed => Http::response(['categories' => ['gcid' => 'dentist']])),
        'categories.list page 1 did not hold a list of categories.',
    ],
    'a page token that never ends' => [
        static fn () => gbpApiFake(pages: static fn (): mixed => Http::response(gbpApiPage(1))),
        'categories.list was still returning a next page after 200 pages.',
    ],
]);

it('refuses to call Google when any one credential is missing', function (string $missing, ?string $value): void {
    gbpApiFake();
    config()->set('services.google_business_profile.'.$missing, $value);

    expect(Artisan::call('gbp:check-categories', ['--api' => true]))->toBe(1)
        ->and(Artisan::output())->toContain('The Google Business Profile API credentials are not configured.');

    Http::assertNothingSent();
})->with([
    'client id empty' => ['client_id', ''],
    'client id unset' => ['client_id', null],
    'client secret empty' => ['client_secret', ''],
    'client secret unset' => ['client_secret', null],
    'refresh token empty' => ['refresh_token', ''],
    'refresh token unset' => ['refresh_token', null],
]);

it('reads at most 200 pages before giving up on the page token', function (): void {
    gbpApiFake(pages: static fn (): mixed => Http::response(gbpApiPage(1)));

    expect(fn () => ApiCategorySource::fromConfig()->read())
        ->toThrow(CategorySourceUnavailable::class, 'after 200 pages');

    // One token exchange and exactly 200 pages
    Http::assertSentCount(201);
});

it('reports each unreadable record and keeps the first of a repeated GCID', function (): void {
    gbpApiFake(pages: static fn (): mixed => Http::response([
        'categories' => [
            ['name' => 'gcid:dentist', 'displayName' => '  Dentist  '],
            ['name' => 'gcid:dentist', 'displayName' => 'Second dentist'],
            ['name' => 'gcid:no_display_name'],
            ['name' => 'gcid:blank_display_name', 'displayName' => '   '],
            ['name' => 'gcid:Upper_Case', 'displayName' => 'Upper case'],
            'not a record',
            ['name' => 'gcid:orthodontist', 'displayName' => 'Orthodontist'],
            // The v1 resource-name shape, read the same as the bare one
            ['name' => 'categories/gcid:periodontist', 'displayName' => 'Periodontist'],
            ['name' => 'categories/periodontist', 'displayName' => 'No gcid: after the resource prefix'],
        ],
        // An empty token ends the list, as a missing one does
        'nextPageToken' => '',
    ]));

    $list = ApiCategorySource::fromConfig()->read();

    // The display name is trimmed, and the first record for a GCID wins
    expect($list->categories)->toBe(['dentist' => 'Dentist', 'orthodontist' => 'Orthodontist', 'periodontist' => 'Periodontist'])
        ->and(array_column($list->malformed, 'line'))->toBe([2, 3, 4, 5, 6, 9])
        ->and(array_column($list->malformed, 'reason'))->toBe([
            'the GCID appears in an earlier record',
            'no display name',
            'no display name',
            'the GCID is not lowercase letters, digits and underscores',
            'the name is not a gcid: identifier',
            'the name is not a gcid: identifier',
        ]);

    // One token exchange and one page
    Http::assertSentCount(2);
});

it('reads the credentials from the environment and commits none', function (): void {
    // The configured values are environment reads; with none set they are null, and the
    // repository holds the names only (.env.example is pinned empty by its own test)
    $config = (string) file_get_contents(config_path('services.php'));

    expect($config)->toContain("'client_id' => env('GOOGLE_BUSINESS_PROFILE_CLIENT_ID')")
        ->and($config)->toContain("'client_secret' => env('GOOGLE_BUSINESS_PROFILE_CLIENT_SECRET')")
        ->and($config)->toContain("'refresh_token' => env('GOOGLE_BUSINESS_PROFILE_REFRESH_TOKEN')");
});

it('runs from the API on the 1st and 15th of each month, without overlapping', function (): void {
    /** @var Collection<int, Event> $events */
    $events = collect(app(Schedule::class)->events())
        ->filter(static fn (Event $event): bool => str_contains((string) $event->command, 'gbp:check-categories'));

    expect($events)->toHaveCount(1);

    /** @var Event $event */
    $event = $events->first();

    // Twice a month keeps the widget's 30-day overdue flag from tripping between runs
    expect((string) $event->command)->toEndWith('gbp:check-categories --api')
        ->and($event->expression)->toBe('0 6 1,15 * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        // A read of 40-odd pages must not hold up the jobs due in the same minute
        ->and($event->runInBackground)->toBeTrue();
});

it('refuses both a file and --api, and neither', function (array $arguments): void {
    gbpApiFake();

    expect(Artisan::call('gbp:check-categories', $arguments))->toBe(1)
        ->and(Artisan::output())->toContain('Name a category file, or pass --api')
        ->and(app(CategoryCheckLog::class)->latest())->toBeNull();

    Http::assertNothingSent();
})->with([
    'both' => [['file' => 'tests/Fixtures/GoogleBusinessProfile/categories.tsv', '--api' => true]],
    'neither' => [[]],
    'an empty file name alone' => [['file' => '']],
]);

it('reads the API when --api comes with an empty file name', function (): void {
    gbpApiFake();

    expect(Artisan::call('gbp:check-categories', ['file' => '', '--api' => true]))->toBe(0)
        ->and(app(CategoryCheckLog::class)->latest()['source'] ?? null)->toBe('Google Business Information API (US, en)');
});

it('reports a term whose GCID Google lists only in an unreadable record as listed but unreadable, not removed', function (array $records, array $removed, array $unreadable, array $reasons): void {
    // UAMS-Web/uams-statamic-archive#2944: a record whose GCID reads but which is rejected still shows Google lists the category
    gbpApiFake(pages: static fn (): mixed => Http::response(['categories' => $records]));

    expect(Artisan::call('gbp:check-categories', ['--api' => true]))->toBe(0);
    $output = Artisan::output();
    $report = app(CategoryCheckLog::class)->latest()['report'] ?? [];
    $slugs = static function (mixed $rows): array {
        $slugs = array_column(mustBeArray($rows, 'the report rows'), 'slug');
        sort($slugs);

        return $slugs;
    };

    expect($slugs($report['removed'] ?? []))->toBe($removed)
        ->and($slugs($report['unreadable'] ?? []))->toBe($unreadable)
        ->and(array_column(mustBeArray($report['malformed'] ?? [], 'the malformed records'), 'reason'))->toBe($reasons)
        ->and($output)->toContain(\sprintf('%d removed, %d listed but unreadable,', \count($removed), \count($unreadable)))
        ->and(str_contains($output, 'Listed but unreadable ('))->toBe($unreadable !== []);
})->with([
    'a clean read listing every term' => [
        [
            ['name' => 'gcid:acupuncturist', 'displayName' => 'Acupuncturist'],
            ['name' => 'gcid:acupuncture_clinic', 'displayName' => 'Acupuncture clinic'],
            ['name' => 'gcid:discontinued_category', 'displayName' => 'Discontinued category'],
        ],
        [],
        [],
        [],
    ],
    'a category Google no longer lists' => [
        [
            ['name' => 'gcid:acupuncturist', 'displayName' => 'Acupuncturist'],
            ['name' => 'gcid:acupuncture_clinic', 'displayName' => 'Acupuncture clinic'],
        ],
        ['discontinued-category'],
        [],
        [],
    ],
    'a GCID with no display name' => [
        [
            ['name' => 'gcid:acupuncturist'],
            ['name' => 'gcid:acupuncture_clinic', 'displayName' => 'Acupuncture clinic'],
        ],
        ['discontinued-category'],
        ['acupuncturist'],
        ['no display name'],
    ],
    'a GCID with an empty display name' => [
        [
            ['name' => 'gcid:acupuncturist', 'displayName' => ''],
            ['name' => 'gcid:acupuncture_clinic', 'displayName' => 'Acupuncture clinic'],
        ],
        ['discontinued-category'],
        ['acupuncturist'],
        ['no display name'],
    ],
    'a repeated GCID whose earlier record was rejected' => [
        [
            ['name' => 'gcid:acupuncturist', 'displayName' => ''],
            ['name' => 'categories/gcid:acupuncturist'],
            ['name' => 'gcid:acupuncture_clinic', 'displayName' => 'Acupuncture clinic'],
        ],
        ['discontinued-category'],
        ['acupuncturist'],
        ['no display name', 'no display name'],
    ],
    'a name that is not a gcid: identifier' => [
        [
            ['name' => 'acupuncturist', 'displayName' => 'Acupuncturist'],
            ['name' => 'gcid:acupuncture_clinic', 'displayName' => 'Acupuncture clinic'],
        ],
        ['acupuncturist', 'discontinued-category'],
        [],
        ['the name is not a gcid: identifier'],
    ],
    'a GCID that is not lowercase' => [
        [
            ['name' => 'gcid:Acupuncturist', 'displayName' => 'Acupuncturist'],
            ['name' => 'gcid:acupuncture_clinic', 'displayName' => 'Acupuncture clinic'],
        ],
        ['acupuncturist', 'discontinued-category'],
        [],
        ['the GCID is not lowercase letters, digits and underscores'],
    ],
]);

it('carries a GCID read from a rejected record into the list, once', function (): void {
    gbpApiFake(pages: static fn (): mixed => Http::response(['categories' => [
        ['name' => 'gcid:allergist'],
        ['name' => 'gcid:allergist', 'displayName' => ' '],
        ['name' => 'gcid:bakery', 'displayName' => 'Bakery'],
        ['name' => 'gcid:bakery', 'displayName' => 'Bakery again'],
        ['name' => 'bakery', 'displayName' => 'No gcid: prefix'],
    ]]));

    $list = ApiCategorySource::fromConfig()->read();

    expect($list->categories)->toBe(['bakery' => 'Bakery'])
        ->and($list->unreadable)->toBe(['allergist', 'bakery']);
});
