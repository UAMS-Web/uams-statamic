<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Term;
use Statamic\Facades\User;
use Statamic\Listeners\UpdateTermReferences;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Stache\Query\TermQueryBuilder;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Posting to the classifieds board from the front end (UAMS-Web/uams-statamic-archive#2519): the sign-in gate, the
 * posting form, validation, and a poster editing and removing only their own listings.
 * Image uploads fake the images disk, so they are in the `serial`
 * `ClassifiedsPostingImagesTest` instead.
 *
 * Posters are real file-driver users, and `users/` is a tracked submodule holding real
 * accounts, so the only users removed are the ones this file creates, by email, never
 * `User::all()` (the FrontEndAccountFlowTest rule).
 *
 * @command  php artisan test --compact tests/Feature/Classifieds/ClassifiedsPostingTest.php
 */
const POSTING_TEST_TITLE_PREFIX = 'ZZ posting test ';

const POSTING_TEST_EMAILS = ['zz-classifieds-poster-a@example.com', 'zz-classifieds-poster-b@example.com'];

const POSTING_TEST_CATEGORY = 'zz-posting-test-furniture';

const MY_LISTINGS = '/inside/classifieds/my-listings';

/** Remove the users, listings and category this file created. */
function tearDownPostingFixtures(): void
{
    foreach (POSTING_TEST_EMAILS as $email) {
        $user = User::findByEmail($email);

        if ($user instanceof Statamic\Auth\User) {
            $user->delete();
        }
    }

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'classifieds');

    /** @var Illuminate\Support\Collection<int, StatamicEntry> $entries */
    $entries = $query->get();

    $entries
        ->filter(fn (StatamicEntry $entry): bool => str_starts_with(postingTitle($entry), POSTING_TEST_TITLE_PREFIX))
        ->each(fn (StatamicEntry $entry) => $entry->delete());

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
            ->filter(fn (LocalizedTerm $term): bool => $term->slug() === POSTING_TEST_CATEGORY)
            ->each(fn (LocalizedTerm $term) => $term->delete());
    } finally {
        UpdateTermReferences::enable();
    }
}

/**
 * A listing's stored title, empty when it has none.
 *
 * @param  StatamicEntry  $entry  The listing.
 */
function postingTitle(StatamicEntry $entry): string
{
    $title = $entry->get('title');

    return is_string($title) ? $title : '';
}

/**
 * Create a signed-in-able poster.
 *
 * @param  string  $email  One of `POSTING_TEST_EMAILS`.
 * @param  string  $name  The poster's name.
 */
function poster(
    string $email,
    string $name
): Statamic\Auth\User {
    /** @var Statamic\Auth\User $user */
    $user = User::make();
    $user->email($email);
    $user->set('name', $name);
    $user->password('a-long-test-password');
    $user->save();

    return $user;
}

/**
 * A user's id, the value a listing's `account` stores.
 *
 * @param  Statamic\Auth\User  $user  The user.
 */
function posterId(Statamic\Auth\User $user): string
{
    $id = $user->getAuthIdentifier();

    return is_string($id) ? $id : '';
}

/**
 * A listing's entry id.
 *
 * @param  StatamicEntry  $entry  The listing.
 */
function listingId(StatamicEntry $entry): string
{
    $id = $entry->id();

    return is_string($id) ? $id : '';
}

/**
 * A listing's public URL.
 *
 * @param  StatamicEntry  $entry  The listing.
 */
function listingUrlOf(StatamicEntry $entry): string
{
    $url = $entry->url();

    return is_string($url) ? $url : '';
}

/**
 * The listings this file created, by title.
 *
 * @return array<string, StatamicEntry>
 */
function postedListings(): array
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'classifieds');

    /** @var Illuminate\Support\Collection<int, StatamicEntry> $entries */
    $entries = $query->get();

    /** @var array<string, StatamicEntry> $byTitle */
    $byTitle = $entries
        ->filter(fn (StatamicEntry $entry): bool => str_starts_with(postingTitle($entry), POSTING_TEST_TITLE_PREFIX))
        ->keyBy(fn (StatamicEntry $entry): string => postingTitle($entry))
        ->all();

    return $byTitle;
}

/**
 * Save a listing directly, bound to the given account.
 *
 * @param  string  $title  The title suffix after the file's prefix.
 * @param  string|null  $account  The owning user id, or null for an imported listing.
 */
function ownedListing(
    string $title,
    ?string $account
): StatamicEntry {
    /** @var StatamicEntry $entry */
    $entry = Entry::make();
    $entry->collection('classifieds');
    $entry->locale('inside_classifieds');
    $entry->slug('zz-posting-test-'.Str::slug($title));
    $entry->published(true);
    $entry->date(Carbon::now()->subDay());
    $entry->data([
        'title' => POSTING_TEST_TITLE_PREFIX.$title,
        'contact_name' => 'Owner',
        'contact_email' => 'owner@example.com',
        'expiration_date' => '2026-09-01',
        'account' => $account,
    ]);
    $entry->save();

    return $entry;
}

/**
 * A complete, valid form submission, with overrides.
 *
 * @param  array<array-key, mixed>  $overrides  Fields to replace.
 * @return array<array-key, mixed>
 */
function listingForm(array $overrides = []): array
{
    return array_merge([
        'title' => POSTING_TEST_TITLE_PREFIX.'Oak dresser',
        'description' => "Solid oak, six drawers.\nMinor scratches.\n\nPick up only.",
        'price' => '120',
        'classified_categories' => [POSTING_TEST_CATEGORY],
        'contact_name' => 'Ada Poster',
        'contact_email' => 'ada@example.com',
        'contact_phone' => '501-555-0100',
        'location' => 'Little Rock',
    ], $overrides);
}

beforeEach(function (): void {
    if (! Collection::findByHandle('classifieds')) {
        ContentSubmodule::reportMissing('classifieds collection');
    }

    // Noon in Little Rock on a fixed day, so the 90-day expiry is exact.
    Carbon::setTestNow(Carbon::parse('2026-06-15 17:00:00', 'UTC'));
    Site::setCurrent('inside_classifieds');
    tearDownPostingFixtures();

    /** @var Statamic\Taxonomies\Term $term */
    $term = Term::make();
    $term->taxonomy('classified_categories');
    $term->slug(POSTING_TEST_CATEGORY);
    $term->dataForLocale('inside_classifieds', ['title' => 'Furniture']);
    $term->save();
});

afterEach(function (): void {
    tearDownPostingFixtures();
    Carbon::setTestNow();
});

it('sends a visitor who is not signed in to the login page', function (string $method, string $path): void {
    /** @var TestCase $test */
    $test = $this;

    $response = $test->call($method, $path, $method === 'GET' ? [] : listingForm());

    $response->assertRedirect('/login');

    // The page asked for is kept as the intended URL, which Statamic's login returns to.
    if ($method === 'GET') {
        expect(session('url.intended'))->toBe(url($path));
    }
    expect(postedListings())->toBe([]);
})->with([
    'my listings' => ['GET', MY_LISTINGS],
    'the posting form' => ['GET', MY_LISTINGS.'/new'],
    'a submission' => ['POST', MY_LISTINGS],
]);

it('returns a visitor to the posting form once they sign in', function (): void {
    poster(POSTING_TEST_EMAILS[0], 'Ada Poster');

    /** @var TestCase $test */
    $test = $this;
    $test->get(MY_LISTINGS.'/new')->assertRedirect('/login');

    $test->post(route('statamic.login'), ['email' => POSTING_TEST_EMAILS[0], 'password' => 'a-long-test-password'])
        ->assertRedirect(url(MY_LISTINGS.'/new'));
});

it('keeps a poster\'s pages out of the static cache, so they are never served to anyone else', function (string $suffix): void {
    $user = poster(POSTING_TEST_EMAILS[0], 'Ada Poster');
    $listing = ownedListing('Cached lamp', posterId($user));

    /** @var TestCase $test */
    $test = $this->withoutVite();
    $test->actingAs($user)
        ->get(MY_LISTINGS.str_replace('{id}', listingId($listing), $suffix))
        ->assertOk()
        ->assertHeader('X-Statamic-Uncacheable', 'true');
})->with([
    'my listings' => [''],
    'the posting form' => ['/new'],
    'the edit form' => ['/{id}/edit'],
]);

it('limits how many listings an account posts per hour, separately from its edits', function (): void {
    config(['classifieds.throttle.posts_per_hour' => 2, 'classifieds.throttle.edits_per_hour' => 3]);
    $user = poster(POSTING_TEST_EMAILS[0], 'Ada Poster');
    $listing = ownedListing('Throttled chair', posterId($user));

    /** @var TestCase $test */
    $test = $this;

    // Three edits use up the edit allowance and leave the posting allowance alone.
    for ($i = 0; $i < 3; $i++) {
        $test->actingAs($user)->put(MY_LISTINGS.'/'.listingId($listing), listingForm(['title' => POSTING_TEST_TITLE_PREFIX.'Throttled chair']))->assertRedirect();
    }

    $test->actingAs($user)->put(MY_LISTINGS.'/'.listingId($listing), listingForm(['title' => POSTING_TEST_TITLE_PREFIX.'Throttled chair']))->assertTooManyRequests();

    $test->actingAs($user)->post(MY_LISTINGS, listingForm(['title' => POSTING_TEST_TITLE_PREFIX.'First']))->assertRedirect();
    $test->actingAs($user)->post(MY_LISTINGS, listingForm(['title' => POSTING_TEST_TITLE_PREFIX.'Second']))->assertRedirect();
    $test->actingAs($user)->post(MY_LISTINGS, listingForm(['title' => POSTING_TEST_TITLE_PREFIX.'Third']))->assertTooManyRequests();

    expect(array_keys(postedListings()))->not->toContain(POSTING_TEST_TITLE_PREFIX.'Third');
});

it('keeps an editor\'s formatting when a poster edits a listing without touching its description', function (): void {
    $user = poster(POSTING_TEST_EMAILS[0], 'Ada Poster');
    $listing = ownedListing('Formatted desk', posterId($user));

    $formatted = [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Includes:']]],
        ['type' => 'bulletList', 'content' => [
            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Two drawers']]]]],
            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A lamp']]]]],
        ]],
    ];
    $listing->set('description', $formatted);
    $listing->save();

    /** @var TestCase $test */
    $test = $this->withoutVite();
    /** @var string $form */
    $form = $test->actingAs($user)->get(MY_LISTINGS.'/'.listingId($listing).'/edit')->assertOk()->getContent();

    // The list's text reaches the form rather than being dropped.
    expect($form)->toContain("Includes:\n\nTwo drawers\nA lamp</textarea>");

    $test->actingAs($user)->put(MY_LISTINGS.'/'.listingId($listing), listingForm([
        'title' => POSTING_TEST_TITLE_PREFIX.'Formatted desk',
        'description' => "Includes:\n\nTwo drawers\nA lamp",
        'price' => '75',
    ]))->assertRedirect();

    /** @var StatamicEntry $edited */
    $edited = Entry::find(listingId($listing));

    expect($edited->get('description'))->toBe($formatted)
        ->and($edited->get('price'))->toBe(75.0);
});

it('shows the posting form with the poster\'s name and email filled in', function (): void {
    $user = poster(POSTING_TEST_EMAILS[0], 'Ada Poster');

    /** @var TestCase $test */
    $test = $this->withoutVite();
    /** @var string $html */
    $html = $test->actingAs($user)->get(MY_LISTINGS.'/new')->assertOk()->getContent();

    expect($html)->toContain('value="Ada Poster"')
        ->and($html)->toContain('value="'.POSTING_TEST_EMAILS[0].'"')
        ->and($html)->toContain('value="'.POSTING_TEST_CATEGORY.'"')
        ->and($html)->toContain('action="'.url(MY_LISTINGS).'"');
});

it('posts a published listing bound to the poster, expiring in 90 days', function (): void {
    $user = poster(POSTING_TEST_EMAILS[0], 'Ada Poster');

    /** @var TestCase $test */
    $test = $this;
    $response = $test->actingAs($user)->post(MY_LISTINGS, listingForm());

    $listing = postedListings()[POSTING_TEST_TITLE_PREFIX.'Oak dresser'] ?? null;
    expect($listing)->not->toBeNull();

    /** @var StatamicEntry $listing */
    $response->assertRedirect(listingUrlOf($listing));

    expect($listing->published())->toBeTrue()
        ->and($listing->locale())->toBe('inside_classifieds')
        ->and($listing->get('account'))->toBe(posterId($user))
        ->and($listing->get('expiration_date'))->toBe('2026-09-13')
        ->and($listing->get('price'))->toBe(120.0)
        ->and($listing->get('classified_categories'))->toBe([POSTING_TEST_CATEGORY])
        ->and($listing->get('contact_email'))->toBe('ada@example.com')
        ->and($listing->get('location'))->toBe('Little Rock')
        ->and($listing->get('description'))->toBe([
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Solid oak, six drawers.'],
                ['type' => 'hardBreak'],
                ['type' => 'text', 'text' => 'Minor scratches.'],
            ]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Pick up only.']]],
        ]);
});

it('refuses a title containing markup, so it cannot reach the page header or navigation', function (string $title): void {
    $user = poster(POSTING_TEST_EMAILS[0], 'Ada Poster');

    /** @var TestCase $test */
    $test = $this;
    $test->actingAs($user)
        ->from(MY_LISTINGS.'/new')
        ->post(MY_LISTINGS, listingForm(['title' => $title]))
        ->assertSessionHasErrors('title');

    expect(postedListings())->toBe([]);
})->with([
    'a script tag' => [POSTING_TEST_TITLE_PREFIX.'<script>alert(1)</script>Bike'],
    'an attribute breakout' => [POSTING_TEST_TITLE_PREFIX.'"><img src=x onerror=alert(2)>'],
    'a lone angle bracket' => [POSTING_TEST_TITLE_PREFIX.'Bike < $50'],
]);

it('renders markup in a poster\'s description as text, never as HTML', function (): void {
    $user = poster(POSTING_TEST_EMAILS[0], 'Ada Poster');

    /** @var TestCase $test */
    $test = $this->withoutVite();
    $test->actingAs($user)->post(MY_LISTINGS, listingForm([
        'description' => "<script>alert(9)</script>\n\n<img src=x onerror=alert(10)>",
    ]));

    /** @var StatamicEntry $listing */
    $listing = postedListings()[POSTING_TEST_TITLE_PREFIX.'Oak dresser'];

    /** @var string $page */
    $page = $test->get(listingUrlOf($listing))->assertOk()->getContent();

    // The description is the one listing field a template prints without `| entities`,
    // because it is Bard. It is safe only because the poster's text becomes the text
    // of Bard nodes, which Bard escapes, and never markup.
    expect($page)->toContain('&lt;script&gt;alert(9)&lt;/script&gt;')
        ->and($page)->toContain('&lt;img src=x onerror=alert(10)&gt;')
        ->and($page)->not->toContain('<script>alert(9)</script>')
        ->and($page)->not->toContain('<img src=x onerror=alert(10)>');
});

it('validates the contact email, the price and the categories', function (array $overrides, string $field): void {
    $user = poster(POSTING_TEST_EMAILS[0], 'Ada Poster');

    /** @var TestCase $test */
    $test = $this;
    $test->actingAs($user)
        ->from(MY_LISTINGS.'/new')
        ->post(MY_LISTINGS, listingForm($overrides))
        ->assertSessionHasErrors($field);

    expect(postedListings())->toBe([]);
})->with([
    'no contact email' => [['contact_email' => ''], 'contact_email'],
    'an invalid contact email' => [['contact_email' => 'not-an-email'], 'contact_email'],
    'a negative price' => [['price' => '-5'], 'price'],
    'a price that is not a number' => [['price' => 'best offer'], 'price'],
    'a category that does not exist' => [['classified_categories' => ['no-such-category']], 'classified_categories.0'],
]);

it('sends a failed submission back with its values, escaped', function (): void {
    $user = poster(POSTING_TEST_EMAILS[0], 'Ada Poster');

    /** @var TestCase $test */
    $test = $this->withoutVite();
    $test->actingAs($user)
        ->from(MY_LISTINGS.'/new')
        ->post(MY_LISTINGS, listingForm(['contact_email' => '', 'location' => '"><script>alert(3)</script>']))
        ->assertRedirect(MY_LISTINGS.'/new');

    /** @var string $html */
    $html = $test->actingAs($user)->get(MY_LISTINGS.'/new')->assertOk()->getContent();

    expect($html)->toContain('The contact email field is required.')
        ->and($html)->toContain('&quot;&gt;&lt;script&gt;alert(3)&lt;/script&gt;')
        ->and($html)->not->toContain('<script>alert(3)</script>');
});

it('lets a poster edit their own listing without extending or moving it', function (): void {
    $user = poster(POSTING_TEST_EMAILS[0], 'Ada Poster');

    /** @var TestCase $test */
    $test = $this->withoutVite();
    $test->actingAs($user)->post(MY_LISTINGS, listingForm());

    /** @var StatamicEntry $listing */
    $listing = postedListings()[POSTING_TEST_TITLE_PREFIX.'Oak dresser'];

    /** @var string $form */
    $form = $test->actingAs($user)->get(MY_LISTINGS.'/'.listingId($listing).'/edit')->assertOk()->getContent();

    // The description round-trips from Bard back to the text the poster typed.
    expect($form)->toContain("Solid oak, six drawers.\nMinor scratches.\n\nPick up only.</textarea>");

    Carbon::setTestNow(Carbon::parse('2026-07-01 17:00:00', 'UTC'));

    $test->actingAs($user)
        ->put(MY_LISTINGS.'/'.listingId($listing), listingForm([
            'title' => POSTING_TEST_TITLE_PREFIX.'Oak dresser, reduced',
            'price' => '90',
        ]))
        ->assertRedirect();

    /** @var StatamicEntry $edited */
    $edited = Entry::find(listingId($listing));

    expect($edited->get('title'))->toBe(POSTING_TEST_TITLE_PREFIX.'Oak dresser, reduced')
        ->and($edited->get('price'))->toBe(90.0)
        ->and($edited->get('expiration_date'))->toBe('2026-09-13')
        ->and($edited->get('account'))->toBe(posterId($user));
});

it('refuses to let a poster edit or remove a listing that is not theirs', function (string $method, string $suffix, ?string $ownerEmail): void {
    $poster = poster(POSTING_TEST_EMAILS[0], 'Ada Poster');
    $owner = $ownerEmail === null ? null : poster($ownerEmail, 'Grace Owner');

    $listing = ownedListing('Somebody else\'s lamp', ($owner === null ? null : posterId($owner)));

    /** @var TestCase $test */
    $test = $this->withoutVite();
    $test->actingAs($poster)
        ->call($method, MY_LISTINGS.'/'.listingId($listing).$suffix, $method === 'PUT' ? listingForm() : [])
        ->assertForbidden();

    /** @var StatamicEntry $unchanged */
    $unchanged = Entry::find(listingId($listing));

    expect($unchanged)->not->toBeNull()
        ->and($unchanged->get('title'))->toBe(POSTING_TEST_TITLE_PREFIX.'Somebody else\'s lamp');
})->with([
    'the edit form of another poster\'s listing' => ['GET', '/edit', POSTING_TEST_EMAILS[1]],
    'an update to another poster\'s listing' => ['PUT', '', POSTING_TEST_EMAILS[1]],
    'removing another poster\'s listing' => ['DELETE', '', POSTING_TEST_EMAILS[1]],
    'the edit form of an imported listing with no account' => ['GET', '/edit', null],
    'removing an imported listing with no account' => ['DELETE', '', null],
]);

it('answers a listing that does not exist with a 404', function (): void {
    $user = poster(POSTING_TEST_EMAILS[0], 'Ada Poster');

    /** @var TestCase $test */
    $test = $this->withoutVite();
    $test->actingAs($user)->get(MY_LISTINGS.'/no-such-listing/edit')->assertNotFound();
});

it('lets a poster remove their own listing', function (): void {
    $user = poster(POSTING_TEST_EMAILS[0], 'Ada Poster');
    $listing = ownedListing('Garden hose', posterId($user));

    /** @var TestCase $test */
    $test = $this->withoutVite();
    $test->actingAs($user)
        ->delete(MY_LISTINGS.'/'.listingId($listing))
        ->assertRedirect(url(MY_LISTINGS));

    expect(Entry::find(listingId($listing)))->toBeNull();
});

it('lists only the signed-in poster\'s own listings', function (): void {
    $poster = poster(POSTING_TEST_EMAILS[0], 'Ada Poster');
    $other = poster(POSTING_TEST_EMAILS[1], 'Grace Owner');

    ownedListing('Mine', posterId($poster));
    ownedListing('Theirs', posterId($other));

    /** @var TestCase $test */
    $test = $this->withoutVite();
    /** @var string $html */
    $html = $test->actingAs($poster)->get(MY_LISTINGS)->assertOk()->getContent();

    expect($html)->toContain(POSTING_TEST_TITLE_PREFIX.'Mine')
        ->and($html)->not->toContain(POSTING_TEST_TITLE_PREFIX.'Theirs');
});
