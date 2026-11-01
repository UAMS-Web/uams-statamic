<?php

declare(strict_types=1);

use App\Scopes\LiveClassifieds;
use App\Support\Classifieds\ClassifiedExpiry;
use Illuminate\Support\Carbon;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Fields\Field;
use Statamic\Fields\Value;
use Statamic\Listeners\UpdateTermReferences;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Stache\Query\TermQueryBuilder;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class)->group('serial');

/**
 * The classifieds board that replaces WP Adverts on Inside UAMS (UAMS-Web/uams-statamic-archive#2096): the
 * `classifieds` collection, its `classified` blueprint, the `classified_categories`
 * taxonomy that receives WP Adverts' `advert_category`, the `live_classifieds`
 * scope behind the board index, and the index and listing templates.
 *
 * The collection and taxonomy configs live in the `content` submodule, so these
 * tests self-skip where it is absent. Fixture listings are saved into the real
 * collection with a slug prefix and deleted by that prefix before and after
 * each test (the PostsInPostCategoryBySlugScopeTest pattern).
 *
 * Serial, not parallel: this file asserts on every live listing, and
 * ClassifiedsPostingTest and ClassifiedsPostingImagesTest save live listings into the
 * content mirror every worker shares. A worker that builds its Stache cold reads such
 * a listing and keeps it in its index after the file is deleted, so the board shows a
 * listing this file never made (#473).
 *
 * @command  php artisan test --compact tests/Feature/Classifieds/ClassifiedsBoardTest.php
 */
const CLASSIFIEDS_TEST_PREFIX = 'zz-classifieds-test-';

const CLASSIFIEDS_SITE = 'inside_classifieds';

/** Remove every fixture listing and category term this file saved. */
function tearDownClassifiedFixtures(): void
{
    /** @var TermQueryBuilder $termQuery */
    $termQuery = Term::query();
    $termQuery->where('taxonomy', 'classified_categories');

    /** @var Illuminate\Support\Collection<int, LocalizedTerm> $terms */
    $terms = $termQuery->get();

    // Deleting a term runs Statamic's term reference sweep over every entry, term and
    // global, about six seconds each time (the UAMS-Web/uams-statamic-archive#2504 asset-sweep shape). Nothing
    // references these fixture terms except this file's own listings.
    UpdateTermReferences::disable();

    try {
        $terms
            ->filter(fn (LocalizedTerm $term): bool => is_string($term->slug()) && str_starts_with($term->slug(), CLASSIFIEDS_TEST_PREFIX))
            ->each(fn (LocalizedTerm $term) => $term->delete());
    } finally {
        UpdateTermReferences::enable();
    }

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'classifieds');

    /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $entries */
    $entries = $query->get();

    $entries
        ->filter(fn (Statamic\Entries\Entry $entry): bool => str_starts_with(classifiedSlug($entry), CLASSIFIEDS_TEST_PREFIX))
        ->each(fn (Statamic\Entries\Entry $entry) => $entry->delete());
}

/**
 * An entry's slug as a string, empty when it has none.
 *
 * @param  Statamic\Entries\Entry  $entry  The listing.
 */
function classifiedSlug(Statamic\Entries\Entry $entry): string
{
    $slug = $entry->slug();

    return is_string($slug) ? $slug : '';
}

/**
 * Save a fixture listing on the classifieds site.
 *
 * @param  string  $slugSuffix  Appended to the file's slug prefix.
 * @param  array<string, mixed>  $data  Field values, merged over a complete listing.
 * @param  bool  $published  Whether the listing is published.
 */
function makeClassified(
    string $slugSuffix,
    array $data = [],
    bool $published = true,
): Statamic\Entries\Entry {
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('classifieds');
    $entry->locale(CLASSIFIEDS_SITE);
    $entry->slug(CLASSIFIEDS_TEST_PREFIX.$slugSuffix);
    $entry->published($published);
    $entry->date(Carbon::now()->subDay());
    $entry->data(array_merge([
        'title' => 'Listing '.$slugSuffix,
        'price' => 25,
        'contact_name' => 'Pat Poster',
        'contact_email' => 'pat@example.com',
        'contact_phone' => '501-555-0100',
        'expiration_date' => Carbon::today()->addWeek()->toDateString(),
    ], $data));

    $entry->save();

    return $entry;
}

/**
 * Whether a listing's page treats it as expired, read through its `is_expired` computed.
 *
 * @param  Statamic\Entries\Entry  $entry  The listing.
 */
function classifiedIsExpired(Statamic\Entries\Entry $entry): bool
{
    /** @var Value $isExpired */
    $isExpired = $entry->augmentedValue('is_expired');

    return $isExpired->value() === true;
}

/**
 * Save a fixture category term and return its slug.
 *
 * @param  string  $slugSuffix  Appended to the file's slug prefix.
 * @param  string  $title  The term title.
 */
function makeClassifiedCategory(
    string $slugSuffix,
    string $title,
): string {
    $slug = CLASSIFIEDS_TEST_PREFIX.$slugSuffix;

    /** @var Statamic\Taxonomies\Term $term */
    $term = Term::make();
    $term->taxonomy('classified_categories');
    $term->slug($slug);
    $term->dataForLocale(CLASSIFIEDS_SITE, ['title' => $title]);
    $term->save();

    return $slug;
}

/**
 * The slugs the live scope keeps from this file's fixtures.
 *
 * @return list<string>
 */
function liveClassifiedSlugs(): array
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'classifieds');

    app(LiveClassifieds::class)->apply($query, []);

    /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $entries */
    $entries = $query->get();

    /** @var list<string> $slugs */
    $slugs = $entries
        ->map(fn (Statamic\Entries\Entry $entry): string => classifiedSlug($entry))
        ->filter(fn (string $slug): bool => str_starts_with($slug, CLASSIFIEDS_TEST_PREFIX))
        ->sort()
        ->values()
        ->all();

    return $slugs;
}

beforeEach(function (): void {
    // Noon in Little Rock, and the same calendar day in UTC, so "today" cannot
    // differ between the two unless a test moves the clock on purpose.
    Carbon::setTestNow(Carbon::parse('2026-06-15 17:00:00', 'UTC'));

    if (! Collection::findByHandle('classifieds')) {
        ContentSubmodule::reportMissing('classifieds collection');
    }

    Site::setCurrent(CLASSIFIEDS_SITE);
    tearDownClassifiedFixtures();
});

afterEach(function (): void {
    tearDownClassifiedFixtures();
    Carbon::setTestNow();
});

it('configures the classifieds collection for the Inside UAMS classifieds site', function (): void {
    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::findByHandle('classifieds');

    /** @var Illuminate\Support\Collection<int, string> $sites */
    $sites = $collection->sites();
    /** @var Illuminate\Support\Collection<int, Statamic\Taxonomies\Taxonomy> $taxonomies */
    $taxonomies = $collection->taxonomies();
    /** @var Illuminate\Support\Collection<int, Statamic\Fields\Blueprint> $blueprints */
    $blueprints = $collection->entryBlueprints();

    expect($sites->all())->toBe([CLASSIFIEDS_SITE])
        // Keeps WP Adverts' `/classifieds/advert/<slug>/` shape, so old links redirect one to one.
        ->and($collection->route(CLASSIFIEDS_SITE))->toBe('/advert/{slug}')
        ->and($collection->template())->toBe('classifieds/show')
        ->and($taxonomies->count())->toBe(1)
        ->and($taxonomies->first()?->handle())->toBe('classified_categories')
        ->and($blueprints->count())->toBe(1)
        ->and($blueprints->first()?->handle())->toBe('classified');
});

it('gives advert_category a destination taxonomy whose terms can carry a description', function (): void {
    /** @var Statamic\Taxonomies\Taxonomy|null $taxonomy */
    $taxonomy = Taxonomy::findByHandle('classified_categories');

    expect($taxonomy)->not->toBeNull();

    /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
    /** @var Illuminate\Support\Collection<int, string> $sites */
    $sites = $taxonomy->sites();
    expect($sites->all())->toBe([CLASSIFIEDS_SITE]);

    /** @var Illuminate\Support\Collection<int, Statamic\Fields\Blueprint> $termBlueprints */
    $termBlueprints = $taxonomy->termBlueprints();
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = $termBlueprints->first();

    // Two of the 23 WordPress terms carry a description (the UAMS property reallocation rules).
    expect($blueprint->handle())->toBe('classified_category')
        ->and($blueprint->hasField('description'))->toBeTrue();
});

it('carries a field for every WP Adverts field the decisions retain', function (string $handle, string $type): void {
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::find('collections.classifieds.classified');

    /** @var Field|null $field */
    $field = $blueprint->field($handle);

    expect($field)->not->toBeNull();

    /** @var Field $field */
    expect($field->type())->toBe($type);
})->with([
    'post_title' => ['title', 'text'],
    'post_content' => ['description', 'bard'],
    'advert_category' => ['classified_categories', 'terms'],
    'adverts_price' => ['price', 'float'],
    'adverts_person' => ['contact_name', 'text'],
    'adverts_email' => ['contact_email', 'text'],
    'adverts_phone' => ['contact_phone', 'text'],
    'adverts_location' => ['location', 'text'],
    '_expiration_date' => ['expiration_date', 'date'],
    '_thumbnail_id' => ['featured_image', 'assets'],
    '_adverts_attachments_order' => ['gallery', 'assets'],
    'account binding' => ['account', 'users'],
]);

it('keeps the account optional, because no imported listing has one', function (): void {
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::find('collections.classifieds.classified');

    /** @var Field $account */
    $account = $blueprint->field('account');

    // `_adverts_account` is empty on all 229 WordPress listings.
    expect($account->isRequired())->toBeFalse()
        ->and($account->config()['max_items'] ?? null)->toBe(1);
});

it('agrees between the board index and a listing page on which listings have expired', function (): void {
    makeClassified('agree-future');
    makeClassified('agree-today', ['expiration_date' => '2026-06-15']);
    makeClassified('agree-expired', ['expiration_date' => '2026-06-14']);
    makeClassified('agree-undated', ['expiration_date' => null]);

    $live = liveClassifiedSlugs();

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'classifieds');

    /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $entries */
    $entries = $query->get();

    /** @var list<string> $notExpired */
    $notExpired = $entries
        ->filter(fn (Statamic\Entries\Entry $entry): bool => str_starts_with(classifiedSlug($entry), CLASSIFIEDS_TEST_PREFIX))
        ->reject(fn (Statamic\Entries\Entry $entry): bool => classifiedIsExpired($entry))
        ->map(fn (Statamic\Entries\Entry $entry): string => classifiedSlug($entry))
        ->sort()
        ->values()
        ->all();

    expect($live)->toBe($notExpired)
        ->and($live)->not->toContain(CLASSIFIEDS_TEST_PREFIX.'agree-expired');
});

it('expires a listing the day after its expiration date, by the display timezone', function (mixed $stored, string $now, bool $expired): void {
    Carbon::setTestNow(Carbon::parse($now, 'UTC'));

    expect(app(ClassifiedExpiry::class)->isExpired($stored))->toBe($expired);
})->with([
    'the day before' => ['2026-06-16', '2026-06-15 17:00:00', false],
    'the day itself' => ['2026-06-15', '2026-06-15 17:00:00', false],
    'the evening of the day in Little Rock, the next day in UTC' => ['2026-06-15', '2026-06-16 02:00:00', false],
    'the day after' => ['2026-06-15', '2026-06-16 06:00:00', true],
    'a Carbon at app-timezone midnight' => [Carbon::parse('2026-06-14', 'UTC'), '2026-06-15 17:00:00', true],
    'no date' => [null, '2026-06-15 17:00:00', false],
    'not a date' => ['soon', '2026-06-15 17:00:00', false],
]);

it('keeps unexpired listings, those expiring today, and those with no date on the board', function (): void {
    makeClassified('future');
    makeClassified('today', ['expiration_date' => Carbon::today()->toDateString()]);
    makeClassified('undated', ['expiration_date' => null]);
    makeClassified('expired', ['expiration_date' => Carbon::yesterday()->toDateString()]);

    expect(liveClassifiedSlugs())->toBe([
        CLASSIFIEDS_TEST_PREFIX.'future',
        CLASSIFIEDS_TEST_PREFIX.'today',
        CLASSIFIEDS_TEST_PREFIX.'undated',
    ]);
});

it('keeps a listing live through the evening of its expiration date in Little Rock', function (): void {
    makeClassified('evening', ['expiration_date' => '2026-06-15']);

    // 02:00 UTC on the 16th is 21:00 on the 15th in America/Chicago.
    Carbon::setTestNow(Carbon::parse('2026-06-16 02:00:00', 'UTC'));

    expect(liveClassifiedSlugs())->toBe([CLASSIFIEDS_TEST_PREFIX.'evening']);

    /** @var TestCase $test */
    $test = $this->withoutVite();
    /** @var string $html */
    $html = $test->get('/inside/classifieds/advert/'.CLASSIFIEDS_TEST_PREFIX.'evening')->assertOk()->getContent();

    expect($html)->toContain('mailto:pat@example.com')
        ->and($html)->not->toContain('classified-expired');
});

it('shows the stored expiration date without shifting it a day', function (): void {
    makeClassified('shown-date', ['expiration_date' => '2026-06-20']);

    /** @var TestCase $test */
    $test = $this->withoutVite();
    /** @var string $html */
    $html = $test->get('/inside/classifieds/advert/'.CLASSIFIEDS_TEST_PREFIX.'shown-date')->assertOk()->getContent();

    expect($html)->toContain('<time datetime="2026-06-20">June 20, 2026</time>');
});

it('lists live published listings on the board index and leaves out expired and unpublished ones', function (): void {
    makeClassified('live', ['title' => 'Oak dresser']);
    makeClassified('expired', ['title' => 'Old lamp', 'expiration_date' => Carbon::yesterday()->toDateString()]);
    makeClassified('draft', ['title' => 'Unfinished draft'], published: false);

    $html = (string) Antlers::parse('{{ partial:classifieds/listing }}', []);

    expect($html)->toContain('Oak dresser')
        ->and($html)->not->toContain('Old lamp')
        ->and($html)->not->toContain('Unfinished draft');
});

it('escapes listing text on the board index', function (): void {
    $category = makeClassifiedCategory('xss-card', '<b onmouseover=alert(3)>Tools</b>');

    makeClassified('xss', [
        'title' => '<script>alert(1)</script>Bike',
        'classified_categories' => [$category],
    ]);

    $html = (string) Antlers::parse('{{ partial:classifieds/listing }}', []);

    expect($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;Bike')
        ->and($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->toContain('&lt;b onmouseover=alert(3)&gt;Tools&lt;/b&gt;')
        ->and($html)->not->toContain('<b onmouseover');
});

it('shows a listing\'s categories on its card and page', function (): void {
    $furniture = makeClassifiedCategory('furniture', 'Furniture');
    $general = makeClassifiedCategory('general', 'General');

    makeClassified('categorized', ['title' => 'Oak chair', 'classified_categories' => [$furniture, $general]]);

    $card = (string) Antlers::parse('{{ partial:classifieds/listing }}', []);

    /** @var TestCase $test */
    $test = $this->withoutVite();
    /** @var string $page */
    $page = $test->get('/inside/classifieds/advert/'.CLASSIFIEDS_TEST_PREFIX.'categorized')->assertOk()->getContent();

    expect($card)->toMatch('/classified-card-categories[^>]*>\s*<small>\s*Furniture, General\s*<\/small>/')
        ->and($page)->toContain('<dd class="classified-categories">Furniture, General</dd>');
});

it('formats the price as dollars and hides a zero price', function (): void {
    makeClassified('priced', ['title' => 'Camper', 'price' => 465000]);
    makeClassified('free', ['title' => 'Box of books', 'price' => 0]);

    $html = (string) Antlers::parse('{{ partial:classifieds/listing }}', []);

    // WP Adverts stores bare numbers and formats them as currency; 31 of its 229 listings carry `0`.
    expect($html)->toContain('$465,000.00')
        ->and(substr_count($html, 'classified-card-price'))->toBe(1);
});

it('pages the board newest first', function (): void {
    $oldest = makeClassified('oldest', ['title' => 'Oldest listing']);
    $oldest->date(Carbon::now()->subDays(3));
    $oldest->save();
    $middle = makeClassified('middle', ['title' => 'Middle listing']);
    $middle->date(Carbon::now()->subDays(2));
    $middle->save();
    $newest = makeClassified('newest', ['title' => 'Newest listing']);
    $newest->date(Carbon::now()->subDay());
    $newest->save();

    $html = (string) Antlers::parse('{{ partial:classifieds/listing listings_per_page="2" }}', []);

    expect($html)->toContain('Page 1 of 2')
        ->and($html)->not->toContain('Oldest listing')
        ->and(strpos($html, 'Newest listing'))->toBeLessThan((int) strpos($html, 'Middle listing'));
});

it('renders the board index template', function (): void {
    makeClassified('index', ['title' => 'Garden hose']);

    $html = (string) Antlers::parse('{{ partial:classifieds/index }}', []);

    expect($html)->toContain('Garden hose')
        ->and($html)->toContain('classifieds-list');
});

it('does not print a listing with no categories as its own category', function (): void {
    makeClassified('uncategorized', ['title' => 'Uncategorized desk']);

    $html = (string) Antlers::parse('{{ partial:classifieds/listing }}', []);

    // With no categories, the card prints no category line at all rather than an
    // empty one or one carrying the listing's own title (the UAMS-Web/uams-statamic-archive#2026 failure shape).
    expect($html)->toContain('Uncategorized desk')
        ->and($html)->not->toContain('classified-card-categories');
});

it('shows the empty state when no listing is live', function (): void {
    $html = (string) Antlers::parse('{{ partial:classifieds/listing }}', []);

    expect($html)->toContain('There are no listings on the board right now.');
});

it('escapes every listing text value on the listing page', function (): void {
    makeClassified('xss-page', [
        'title' => '<script>alert(1)</script>Bike',
        'contact_name' => '<i onclick=alert(4)>Pat</i>',
        'contact_email' => 'pat@example.com"><script>alert(5)</script>',
        'contact_phone' => '<u onclick=alert(6)>501</u>',
        'location' => '<s onclick=alert(7)>Little Rock</s>',
    ]);

    /** @var TestCase $test */
    $test = $this->withoutVite();
    /** @var string $page */
    $page = $test->get('/inside/classifieds/advert/'.CLASSIFIEDS_TEST_PREFIX.'xss-page')->assertOk()->getContent();

    // Scope to the listing's own markup. The shared primary navigation lists entries
    // and prints their titles unescaped on a site with no manual navigation; that is
    // the site shell, not this template, and is tracked with the posting form.
    $start = strpos($page, '<div class="classified">');
    expect($start)->not->toBeFalse();
    $html = substr($page, (int) $start);

    expect($html)->toContain('<h1 class="classified-title">&lt;script&gt;alert(1)&lt;/script&gt;Bike</h1>')
        ->and($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->not->toContain('<i onclick')
        ->and($html)->not->toContain('<script>alert(5)</script>')
        ->and($html)->not->toContain('"><script>')
        ->and($html)->not->toContain('<u onclick')
        ->and($html)->not->toContain('<s onclick');
});

it('renders a live listing with its details and contact', function (): void {
    makeClassified('show-live', [
        'title' => 'Walnut bookshelf',
        'location' => 'Little Rock',
    ]);

    /** @var TestCase $test */
    $test = $this->withoutVite();
    /** @var string $html */
    $html = $test->get('/inside/classifieds/advert/'.CLASSIFIEDS_TEST_PREFIX.'show-live')->assertOk()->getContent();

    expect($html)->toContain('<h1 class="classified-title">Walnut bookshelf</h1>')
        ->and($html)->toContain('<dd class="classified-price">$25.00</dd>')
        ->and($html)->toContain('Little Rock')
        ->and($html)->toContain('mailto:pat@example.com')
        ->and($html)->toContain('501-555-0100')
        ->and($html)->not->toContain('classified-expired');
});

it('renders the description of a listing', function (): void {
    makeClassified('show-description', [
        'title' => 'Maple table',
        'description' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Seats six, minor scratches.']]],
        ],
    ]);

    /** @var TestCase $test */
    $test = $this->withoutVite();
    /** @var string $html */
    $html = $test->get('/inside/classifieds/advert/'.CLASSIFIEDS_TEST_PREFIX.'show-description')->assertOk()->getContent();

    expect($html)->toContain('Seats six, minor scratches.');
});

it('treats a listing expiring today as still live on its page', function (): void {
    makeClassified('show-today', [
        'title' => 'Desk lamp',
        'expiration_date' => Carbon::today()->toDateString(),
    ]);

    /** @var TestCase $test */
    $test = $this->withoutVite();
    /** @var string $html */
    $html = $test->get('/inside/classifieds/advert/'.CLASSIFIEDS_TEST_PREFIX.'show-today')->assertOk()->getContent();

    // The board index keeps a listing through its expiration date, so its page must too.
    expect($html)->toContain('mailto:pat@example.com')
        ->and($html)->not->toContain('classified-expired');
});

it('marks an expired listing as expired and withholds its contact details', function (): void {
    makeClassified('show-expired', [
        'title' => 'Vintage radio',
        'expiration_date' => Carbon::today()->subDays(3)->toDateString(),
    ]);

    /** @var TestCase $test */
    $test = $this->withoutVite();
    /** @var string $html */
    $html = $test->get('/inside/classifieds/advert/'.CLASSIFIEDS_TEST_PREFIX.'show-expired')->assertOk()->getContent();

    expect($html)->toContain('Vintage radio')
        ->and($html)->toContain('classified-expired')
        ->and($html)->not->toContain('pat@example.com')
        ->and($html)->not->toContain('501-555-0100');
});
