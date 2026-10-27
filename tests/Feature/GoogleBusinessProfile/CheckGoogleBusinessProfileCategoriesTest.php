<?php

declare(strict_types=1);

use App\Services\GoogleBusinessProfile\Categories\CategoryCheckLog;
use App\Services\GoogleBusinessProfile\Categories\CategoryDiff;
use App\Services\GoogleBusinessProfile\Categories\CategoryList;
use App\Services\GoogleBusinessProfile\Categories\CategorySourceUnavailable;
use App\Services\GoogleBusinessProfile\Categories\FileCategorySource;
use App\Widgets\GoogleBusinessProfileCategoryCheck;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View as IlluminateView;
use Statamic\Auth\File\User as FileUser;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Facades\User;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\Support\StacheIsolatedTestCase;

use function Pest\Laravel\actingAs;

/**
 * Coverage for UAMS-Web/uams-statamic-archive#2615: `gbp:check-categories` reports how a pasted `GCID<TAB>Name` list
 * differs from the Google Business Profile category taxonomy, by GCID, and the dashboard
 * widget shows the last check and flags it overdue after 30 days.
 *
 * Terms are saved into a throwaway Stache. The fixture list holds one unchanged category,
 * one Google has renamed (written with the API's `gcid:` prefix), one no term carries, and
 * four malformed lines; the taxonomy adds a term Google no longer lists and one with no GCID.
 *
 * @command  php artisan test --compact tests/Feature/GoogleBusinessProfile/CheckGoogleBusinessProfileCategoriesTest.php
 */
uses(StacheIsolatedTestCase::class);

const GBP_CHECK_FIXTURE = 'tests/Fixtures/GoogleBusinessProfile/categories.tsv';

/**
 * Save a category term.
 *
 * @param  string  $slug  The term slug.
 * @param  string  $title  The term title.
 * @param  string|null  $gcid  The stored GCID, or null for a term without one.
 */
function gbpCheckTerm(string $slug, string $title, ?string $gcid): void
{
    $term = mustBe(TaxonomyTerm::class, Term::make(), 'the category term');
    $term->taxonomy('google_business_profile_categories');
    $term->slug($slug);
    $term->dataForLocale($term->defaultLocale(), array_filter(['title' => $title, 'gcid' => $gcid], fn (?string $value): bool => $value !== null));
    $term->saveQuietly();
}

/**
 * A hash of every file under a directory, keyed by path.
 *
 * @return array<string, string>
 */
function gbpCheckTreeHashes(string $directory): array
{
    $hashes = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        $file = mustBe(SplFileInfo::class, $file, 'a content file');
        $hashes[$file->getPathname()] = (string) md5_file($file->getPathname());
    }

    ksort($hashes);

    return $hashes;
}

/**
 * An unsaved user, super or with no permissions.
 */
function gbpCheckUser(bool $super): FileUser
{
    $user = mustBe(FileUser::class, User::make(), 'the test user');
    $user->id($super ? 'gbp-check-super' : 'gbp-check-editor');
    $user->email($super ? 'gbp-check-super@example.test' : 'gbp-check-editor@example.test');

    if ($super) {
        $user->makeSuper();
    }

    return $user;
}

/**
 * The widget as rendered for the current user.
 */
function gbpCheckWidgetHtml(): string
{
    $html = (new GoogleBusinessProfileCategoryCheck)->html();

    return $html instanceof IlluminateView ? $html->render() : $html;
}

beforeEach(function (): void {
    Cache::forget(CategoryCheckLog::CACHE_KEY);
    Carbon::setTestNow(Carbon::parse('2026-09-30 12:00:00', 'UTC'));

    $taxonomy = mustBe(Statamic\Taxonomies\Taxonomy::class, Taxonomy::make('google_business_profile_categories'), 'the category taxonomy');
    $taxonomy->save();

    $blueprint = Blueprint::makeFromFields(['title' => ['type' => 'text'], 'gcid' => ['type' => 'text']]);
    $blueprint->setHandle('google_business_profile_category');
    $blueprint->setNamespace('taxonomies.google_business_profile_categories');
    $blueprint->save();

    gbpCheckTerm('acupuncturist', 'Acupuncturist', 'acupuncturist');
    gbpCheckTerm('acupuncture-clinic', 'Acupuncture clinic', 'acupuncture_clinic');
    gbpCheckTerm('discontinued-category', 'Discontinued category', 'discontinued_category');
    gbpCheckTerm('aa-no-gcid-category', 'No GCID category', null);
});

it('reports new, renamed and removed GCIDs with counts', function (): void {
    expect(Artisan::call('gbp:check-categories', ['file' => base_path(GBP_CHECK_FIXTURE)]))->toBe(0);

    $output = Artisan::output();
    $report = app(CategoryCheckLog::class)->latest()['report'] ?? [];

    expect($output)->toContain('3 categories read: 1 new, 1 renamed, 1 removed, 0 listed but unreadable, 1 terms with no GCID, 4 malformed lines.')
        ->and($report['new'] ?? null)->toBe(['addiction_treatment_center' => 'Addiction treatment center'])
        ->and($report['renamed'] ?? null)->toBe([[
            'gcid' => 'acupuncture_clinic',
            'slug' => 'acupuncture-clinic',
            'title' => 'Acupuncture clinic',
            'name' => 'Acupuncture Clinic',
        ]])
        ->and($report['removed'] ?? null)->toBe([['gcid' => 'discontinued_category', 'slug' => 'discontinued-category', 'title' => 'Discontinued category']])
        ->and($report['without_gcid'] ?? null)->toBe([['slug' => 'aa-no-gcid-category', 'title' => 'No GCID category']]);
});

it('reports each malformed line with its number and keeps reading', function (): void {
    Artisan::call('gbp:check-categories', ['file' => base_path(GBP_CHECK_FIXTURE)]);

    $output = Artisan::output();
    $malformed = mustBeArray(app(CategoryCheckLog::class)->latest()['report']['malformed'] ?? [], 'the malformed lines');

    // The blank line 4 is skipped, not reported
    expect(array_column($malformed, 'line'))->toBe([5, 6, 7, 8])
        ->and(array_column($malformed, 'reason'))->toBe([
            'no tab between the GCID and the name',
            'the GCID is not lowercase letters, digits and underscores',
            'the GCID appears on an earlier line',
            'no name after the tab',
        ])
        ->and(mustBeArray($malformed[0] ?? [], 'the first malformed line')['content'] ?? null)->toBe('no tab on this line')
        ->and($output)->toContain('Line 5 skipped: no tab between the GCID and the name.')
        // The line after a malformed one is still read
        ->and(app(CategoryCheckLog::class)->latest()['counts']['new'] ?? null)->toBe(1);
});

it('changes no file in the content it reads', function (): void {
    $before = gbpCheckTreeHashes($this->fakeStacheDirectory);

    Artisan::call('gbp:check-categories', ['file' => base_path(GBP_CHECK_FIXTURE)]);

    expect(gbpCheckTreeHashes($this->fakeStacheDirectory))->toBe($before);

    // Control: the same comparison sees a term save
    gbpCheckTerm('allergist', 'Allergist', 'allergist');
    expect(gbpCheckTreeHashes($this->fakeStacheDirectory))->not->toBe($before);
});

it('fails without recording a check when the file cannot be read', function (): void {
    expect(Artisan::call('gbp:check-categories', ['file' => base_path('tests/Fixtures/GoogleBusinessProfile/missing.tsv')]))->toBe(1)
        ->and(Artisan::output())->toContain('Cannot read the category file')
        ->and(app(CategoryCheckLog::class)->latest())->toBeNull();
});

it('shows the last check\'s time, source and counts on the widget', function (): void {
    actingAs(gbpCheckUser(super: true));

    expect(gbpCheckWidgetHtml())->toContain('data-gbp-category-check-state="not-run"');

    Artisan::call('gbp:check-categories', ['file' => base_path(GBP_CHECK_FIXTURE)]);
    Carbon::setTestNow(Carbon::parse('2026-10-29 12:00:00', 'UTC'));
    $html = gbpCheckWidgetHtml();

    expect($html)->toContain('data-gbp-category-check-state="current"')
        ->and($html)->not->toContain('data-gbp-category-check-state="overdue"')
        ->and($html)->toContain('1 new, 1 renamed, 1 removed, 0 listed but unreadable, 1 with no GCID, 4 malformed lines.')
        ->and($html)->toContain('Last checked 4 weeks ago from file: '.base_path(GBP_CHECK_FIXTURE))
        ->and($html)->toContain('Acupuncture clinic')
        ->and($html)->toContain('Acupuncture Clinic')
        ->and($html)->not->toContain('data-gbp-category-check-list="unreadable"');
});

it('flags the check overdue after 30 days', function (): void {
    actingAs(gbpCheckUser(super: true));
    Artisan::call('gbp:check-categories', ['file' => base_path(GBP_CHECK_FIXTURE)]);

    Carbon::setTestNow(Carbon::parse('2026-10-30 12:00:00', 'UTC'));
    expect(gbpCheckWidgetHtml())->not->toContain('data-gbp-category-check-state="overdue"');

    Carbon::setTestNow(Carbon::parse('2026-10-30 12:00:01', 'UTC'));
    expect(gbpCheckWidgetHtml())->toContain('data-gbp-category-check-state="overdue"');
});

it('hides the widget from a user who cannot view the taxonomy', function (): void {
    actingAs(gbpCheckUser(super: false));

    expect(gbpCheckWidgetHtml())->toBe('');
});

it('is on the dashboard', function (): void {
    expect(config('statamic.cp.widgets'))->toContain('google_business_profile_category_check');
});

it('reads whitespace, carriage returns and a tab inside a name as a person pastes them', function (): void {
    $path = mustBeString(tempnam(sys_get_temp_dir(), 'gbp'), 'the category file path');
    file_put_contents($path, "   \n  allergist  \t  Allergist  \r\nbakery\tBakery\twith a tab\n");

    $list = (new FileCategorySource($path))->read();
    unlink($path);

    // The whitespace-only line 1 is skipped, not reported
    expect($list->malformed)->toBe([])
        ->and($list->categories)->toBe(['allergist' => 'Allergist', 'bakery' => "Bakery\twith a tab"]);
});

it('refuses a path that is not a readable file', function (string $path): void {
    expect(fn (): CategoryList => (new FileCategorySource(base_path($path)))->read())
        ->toThrow(CategorySourceUnavailable::class, 'Cannot read the category file');
})->with([
    'missing' => ['tests/Fixtures/GoogleBusinessProfile/missing.tsv'],
    'a directory' => ['tests/Fixtures/GoogleBusinessProfile'],
]);

it('lists new GCIDs in order and compares titles as trimmed strings', function (): void {
    // A numeric YAML title arrives as an int, and a trailing space is not a rename
    gbpCheckTerm('bakery', 'Bakery ', 'bakery');
    $term = mustBe(TaxonomyTerm::class, Term::make(), 'the category term');
    $term->taxonomy('google_business_profile_categories');
    $term->slug('n911');
    $term->dataForLocale($term->defaultLocale(), ['title' => 911, 'gcid' => 'n911']);
    $term->saveQuietly();

    $report = app(CategoryDiff::class)->compare(new CategoryList([
        'zoo' => 'Zoo',
        'acupuncturist' => 'Acupuncturist',
        'acupuncture_clinic' => 'Acupuncture clinic',
        'discontinued_category' => 'Discontinued category',
        'bakery' => 'Bakery',
        'n911' => '911',
        'aquarium' => 'Aquarium',
    ]));

    expect(array_keys($report->new))->toBe(['aquarium', 'zoo'])
        ->and($report->renamed)->toBe([])
        ->and($report->removed)->toBe([]);
});

it('reads no check from a record missing its time or source', function (array $record): void {
    Cache::forever(CategoryCheckLog::CACHE_KEY, $record);

    expect(app(CategoryCheckLog::class)->latest())->toBeNull();
})->with([
    'no time' => [['source' => 'file: x', 'counts' => [], 'report' => []]],
    'no source' => [['checked_at' => '2026-09-30T12:00:00+00:00', 'counts' => [], 'report' => []]],
]);

it('prints each section of the report as a table', function (): void {
    Artisan::call('gbp:check-categories', ['file' => base_path(GBP_CHECK_FIXTURE)]);
    $output = str_replace("\r\n", "\n", Artisan::output());

    // Each row, read back as its cells in column order
    $rows = collect(explode("\n", $output))
        ->map(fn (string $line): array => array_values(array_filter(array_map('trim', explode('|', $line)), fn (string $cell): bool => $cell !== '')))
        ->filter(fn (array $cells): bool => $cells !== [])
        ->values()
        ->all();

    // Each section is its heading after a blank line
    foreach (['New (no term carries the GCID):', "Renamed (the term title differs from Google's name):", 'Removed (Google no longer lists the GCID):', 'Not compared (the term has no GCID):'] as $heading) {
        expect($output)->toContain("\n\n{$heading}\n");
    }

    expect($rows)->toContain(['GCID', 'Google name'])
        ->toContain(['addiction_treatment_center', 'Addiction treatment center'])
        ->toContain(['GCID', 'Term', 'Title', 'Google name'])
        ->toContain(['acupuncture_clinic', 'acupuncture-clinic', 'Acupuncture clinic', 'Acupuncture Clinic'])
        ->toContain(['GCID', 'Term', 'Title'])
        ->toContain(['discontinued_category', 'discontinued-category', 'Discontinued category'])
        ->toContain(['Term', 'Title'])
        ->toContain(['aa-no-gcid-category', 'No GCID category']);
});

it('prints no section for a kind the report does not hold', function (): void {
    $path = mustBeString(tempnam(sys_get_temp_dir(), 'gbp'), 'the category file path');
    // Exactly the three keyed terms, unchanged, so nothing is new, renamed or removed
    file_put_contents($path, "acupuncturist\tAcupuncturist\nacupuncture_clinic\tAcupuncture clinic\ndiscontinued_category\tDiscontinued category\n");

    Artisan::call('gbp:check-categories', ['file' => $path]);
    unlink($path);
    $output = Artisan::output();

    expect($output)->toContain('3 categories read: 0 new, 0 renamed, 0 removed, 0 listed but unreadable, 1 terms with no GCID, 0 malformed lines.')
        ->and($output)->not->toContain('New (no term carries the GCID):')
        ->and($output)->not->toContain('Renamed (')
        ->and($output)->not->toContain('Removed (')
        ->and($output)->not->toContain('Listed but unreadable (')
        ->and($output)->toContain('Not compared (the term has no GCID):');
});

it('reports a term whose GCID the file carries only on an unreadable line as listed but unreadable, not removed', function (string $lines, array $removed, array $unreadable, array $reasons): void {
    // UAMS-Web/uams-statamic-archive#2944: a line whose GCID reads but which is rejected still shows the category is listed
    $path = mustBeString(tempnam(sys_get_temp_dir(), 'gbp'), 'the category file path');
    file_put_contents($path, $lines);

    expect(Artisan::call('gbp:check-categories', ['file' => $path]))->toBe(0);
    unlink($path);
    $output = Artisan::output();
    $report = app(CategoryCheckLog::class)->latest()['report'] ?? [];
    $slugs = static function (mixed $rows): array {
        $slugs = array_column(mustBeArray($rows, 'the report rows'), 'slug');
        sort($slugs);

        return $slugs;
    };

    expect($slugs($report['removed'] ?? []))->toBe($removed)
        ->and($slugs($report['unreadable'] ?? []))->toBe($unreadable)
        ->and(array_column(mustBeArray($report['malformed'] ?? [], 'the malformed lines'), 'reason'))->toBe($reasons)
        ->and($output)->toContain(\sprintf('%d removed, %d listed but unreadable,', \count($removed), \count($unreadable)))
        ->and(str_contains($output, 'Listed but unreadable ('))->toBe($unreadable !== []);
})->with([
    'a clean read listing every term' => [
        "acupuncturist\tAcupuncturist\nacupuncture_clinic\tAcupuncture clinic\ndiscontinued_category\tDiscontinued category\n",
        [],
        [],
        [],
    ],
    'a category the list no longer carries' => [
        "acupuncturist\tAcupuncturist\nacupuncture_clinic\tAcupuncture clinic\n",
        ['discontinued-category'],
        [],
        [],
    ],
    'a GCID with no name' => [
        "acupuncturist\t\nacupuncture_clinic\tAcupuncture clinic\n",
        ['discontinued-category'],
        ['acupuncturist'],
        ['no name after the tab'],
    ],
    'a repeated GCID whose earlier line was rejected' => [
        "acupuncturist\t\nacupuncturist\t   \nacupuncture_clinic\tAcupuncture clinic\n",
        ['discontinued-category'],
        ['acupuncturist'],
        ['no name after the tab', 'no name after the tab'],
    ],
    'a line with no tab, so no GCID' => [
        "acupuncturist Acupuncturist\nacupuncture_clinic\tAcupuncture clinic\n",
        ['acupuncturist', 'discontinued-category'],
        [],
        ['no tab between the GCID and the name'],
    ],
    'a GCID that is not lowercase' => [
        "Acupuncturist\tAcupuncturist\nacupuncture_clinic\tAcupuncture clinic\n",
        ['acupuncturist', 'discontinued-category'],
        [],
        ['the GCID is not lowercase letters, digits and underscores'],
    ],
]);

it('carries a GCID read from a rejected line into the list, once', function (): void {
    $path = mustBeString(tempnam(sys_get_temp_dir(), 'gbp'), 'the category file path');
    // A name-less line, a repeat of a GCID already read, and a line with no usable GCID
    file_put_contents($path, "allergist\t\nallergist\t\nbakery\tBakery\nbakery\tBakery again\nBad GCID\tSomething\n");

    $list = (new FileCategorySource($path))->read();
    unlink($path);

    expect($list->categories)->toBe(['bakery' => 'Bakery'])
        ->and($list->unreadable)->toBe(['allergist', 'bakery']);
});

it('lists the terms Google lists only on an unreadable line on the widget', function (): void {
    actingAs(gbpCheckUser(super: true));
    $path = mustBeString(tempnam(sys_get_temp_dir(), 'gbp'), 'the category file path');
    file_put_contents($path, "acupuncturist\t\nacupuncture_clinic\tAcupuncture clinic\ndiscontinued_category\tDiscontinued category\n");

    Artisan::call('gbp:check-categories', ['file' => $path]);
    unlink($path);
    $html = gbpCheckWidgetHtml();

    expect($html)->toContain('0 new, 0 renamed, 0 removed, 1 listed but unreadable, 1 with no GCID, 1 malformed lines.')
        ->and($html)->toContain('Listed but unreadable')
        ->and($html)->toContain('data-gbp-category-check-list="unreadable"')
        ->and($html)->toContain('Acupuncturist <span class="text-gray-600 dark:text-gray-400">(acupuncturist)</span>')
        ->and($html)->not->toContain('data-gbp-category-check-list="removed"');
});

it('shows a check recorded before the unreadable count existed as none unreadable', function (): void {
    actingAs(gbpCheckUser(super: true));
    Cache::forever(CategoryCheckLog::CACHE_KEY, [
        'checked_at' => '2026-09-30T12:00:00+00:00',
        'source' => 'file: an earlier list',
        'counts' => ['new' => 1, 'renamed' => 2, 'removed' => 3, 'without_gcid' => 4, 'malformed' => 5],
        'report' => ['new' => [], 'renamed' => [], 'removed' => [], 'without_gcid' => [], 'malformed' => []],
    ]);

    expect(gbpCheckWidgetHtml())->toContain('1 new, 2 renamed, 3 removed, 0 listed but unreadable, 4 with no GCID, 5 malformed lines.');
});
