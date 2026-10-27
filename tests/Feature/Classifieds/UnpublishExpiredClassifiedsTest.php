<?php

declare(strict_types=1);

use App\Jobs\UnpublishExpiredClassifieds;
use App\Mail\Classifieds\ClassifiedListingExpired;
use App\Scopes\LiveClassifieds;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Statamic\Auth\File\User as FileStatamicUser;
use Statamic\Events\EntrySaved;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Stache\Query\EntryQueryBuilder;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

/**
 * The scheduled job that unpublishes expired classified listings (UAMS-Web/uams-statamic-archive#2520).
 *
 * Fixture listings are saved into the real `classifieds` collection under this
 * file's own slug prefix and deleted by it before and after each test, as
 * ClassifiedsBoardTest does. The collection config lives in the `content`
 * submodule, so these tests self-skip where it is absent.
 *
 * Serial, not parallel: the job unpublishes EVERY expired listing in the
 * collection, not only this file's. Run beside ClassifiedsBoardTest in a
 * parallel worker, it would unpublish that file's expired fixtures mid-test.
 *
 * @command  php artisan test --compact tests/Feature/Classifieds/UnpublishExpiredClassifiedsTest.php
 */
uses(TestCase::class)->group('serial');

const EXPIRY_TEST_PREFIX = 'zz-classifieds-expiry-test-';

const EXPIRY_TEST_SITE = 'inside_classifieds';

/** The address of the account a notified fixture listing belongs to. */
const EXPIRY_TEST_ACCOUNT_EMAIL = 'zz-classifieds-expiry-test@example.com';

/** An account address the users repository stores but Symfony refuses to send to. */
const EXPIRY_TEST_MALFORMED_EMAIL = 'zz-classifieds-expiry-test malformed';

/** Remove every fixture listing this file saved. */
function tearDownExpiryFixtures(): void
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'classifieds');

    /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $entries */
    $entries = $query->get();

    $entries
        ->filter(fn (Statamic\Entries\Entry $entry): bool => str_starts_with(expiryFixtureSlug($entry), EXPIRY_TEST_PREFIX))
        ->each(fn (Statamic\Entries\Entry $entry) => $entry->delete());
}

/**
 * An entry's slug as a string, empty when it has none.
 *
 * @param  Statamic\Entries\Entry  $entry  The listing.
 */
function expiryFixtureSlug(Statamic\Entries\Entry $entry): string
{
    $slug = $entry->slug();

    return is_string($slug) ? $slug : '';
}

/** Remove the fixture accounts, found by their addresses. */
function tearDownExpiryAccount(): void
{
    foreach ([EXPIRY_TEST_ACCOUNT_EMAIL, EXPIRY_TEST_MALFORMED_EMAIL] as $email) {
        $user = User::findByEmail($email);

        if ($user instanceof FileStatamicUser) {
            $user->delete();
        }
    }
}

/**
 * Save a fixture account and return its id.
 *
 * @param  string  $email  The account's address.
 */
function makeExpiryAccount(string $email = EXPIRY_TEST_ACCOUNT_EMAIL): string
{
    $id = (string) Str::uuid();

    $user = User::make();

    // Narrow the repository user to the file-backed implementation
    expect($user)->toBeInstanceOf(FileStatamicUser::class);

    /** @var FileStatamicUser $user */
    $user->id($id);
    $user->email($email);
    $user->save();

    return $id;
}

/**
 * Save a fixture listing on the classifieds site.
 *
 * @param  string  $slugSuffix  Appended to the file's slug prefix.
 * @param  string|null  $expirationDate  The stored `expiration_date`, as `Y-m-d`.
 * @param  bool  $published  Whether the listing is published.
 * @param  string|null  $account  The id of the account that owns the listing, if any.
 */
function makeExpiryFixture(
    string $slugSuffix,
    ?string $expirationDate,
    bool $published = true,
    ?string $account = null,
): Statamic\Entries\Entry {
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('classifieds');
    $entry->locale(EXPIRY_TEST_SITE);
    $entry->slug(EXPIRY_TEST_PREFIX.$slugSuffix);
    $entry->published($published);
    $entry->date(Carbon::now()->subMonth());
    $entry->data([
        'title' => 'Listing '.$slugSuffix,
        'price' => 25,
        'contact_name' => 'Hikaru Sulu',
        'contact_email' => 'sulu@example.com',
        'contact_phone' => '501-555-0100',
        'expiration_date' => $expirationDate,
    ]);

    if ($account !== null) {
        $entry->set('account', $account);
    }

    $entry->save();

    return $entry;
}

/**
 * This file's fixture slugs, filtered by a predicate and sorted.
 *
 * @param  callable(Statamic\Entries\Entry): bool  $keep  Which fixtures to return.
 * @return list<string>
 */
function expiryFixtureSlugs(callable $keep): array
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'classifieds');

    /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $entries */
    $entries = $query->get();

    /** @var list<string> $slugs */
    $slugs = $entries
        ->filter(fn (Statamic\Entries\Entry $entry): bool => str_starts_with(expiryFixtureSlug($entry), EXPIRY_TEST_PREFIX))
        ->filter($keep)
        ->map(fn (Statamic\Entries\Entry $entry): string => expiryFixtureSlug($entry))
        ->sort()
        ->values()
        ->all();

    return $slugs;
}

/**
 * This file's fixtures that are still published, read back from the Stache.
 *
 * @return list<string>
 */
function publishedExpiryFixtures(): array
{
    return expiryFixtureSlugs(fn (Statamic\Entries\Entry $entry): bool => $entry->published() === true);
}

/**
 * This file's fixtures that the board index keeps, through the real live scope.
 *
 * @return list<string>
 */
function liveExpiryFixtures(): array
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'classifieds');

    app(LiveClassifieds::class)->apply($query, []);

    /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $entries */
    $entries = $query->get();

    /** @var list<string> $slugs */
    $slugs = $entries
        ->map(fn (Statamic\Entries\Entry $entry): string => expiryFixtureSlug($entry))
        ->filter(fn (string $slug): bool => str_starts_with($slug, EXPIRY_TEST_PREFIX))
        ->sort()
        ->values()
        ->all();

    return $slugs;
}

beforeEach(function (): void {
    // Noon in Little Rock, and the same calendar day in UTC.
    Carbon::setTestNow(Carbon::parse('2026-06-15 17:00:00', 'UTC'));

    if (! Collection::findByHandle('classifieds')) {
        ContentSubmodule::reportMissing('classifieds collection');
    }

    Site::setCurrent(EXPIRY_TEST_SITE);
    tearDownExpiryFixtures();
    tearDownExpiryAccount();
});

afterEach(function (): void {
    tearDownExpiryFixtures();
    tearDownExpiryAccount();
    Carbon::setTestNow();
});

// First in the file on purpose: `--mutate --bail` stops a child at its first failure, and
// the mutant this kills makes every test slower, so a later position let the Windows
// child exceed its timeout before reaching it, and the survivor read as a timeout (UAMS-Web/uams-statamic-archive#2550).
it('leaves an entry in another collection published, whatever its expiration date', function (): void {
    if (Collection::find('posts') === null) {
        ContentSubmodule::reportMissing('posts collection config');
    }

    // A post on the same site carrying an `expiration_date` the shared definition calls
    // expired. Only the job's `collection` filter keeps it out: without that filter the
    // job reads every published entry on the site and unpublishes this one too (UAMS-Web/uams-statamic-archive#2550).
    /** @var Statamic\Entries\Entry $post */
    $post = Entry::make();
    $post->collection('posts');
    $post->locale(EXPIRY_TEST_SITE);
    $post->slug(EXPIRY_TEST_PREFIX.'post');
    $post->published(true);
    $post->date(Carbon::now()->subMonth());
    $post->data(['title' => 'Not a Listing', 'expiration_date' => '2026-06-14']);
    $post->saveQuietly();

    $expired = makeExpiryFixture('expired', '2026-06-14');

    try {
        app()->call([new UnpublishExpiredClassifieds, 'handle']);

        /** @var Statamic\Entries\Entry $reloadedPost */
        $reloadedPost = Entry::find($post->id());
        /** @var Statamic\Entries\Entry $reloadedListing */
        $reloadedListing = Entry::find($expired->id());

        // The expired listing is the positive control: the job did run and unpublish.
        expect($reloadedListing->published())->toBeFalse()
            ->and($reloadedPost->published())->toBeTrue();
    } finally {
        $post->deleteQuietly();
    }
});

it('unpublishes a listing whose expiration date has passed, and leaves one expiring today published', function (): void {
    makeExpiryFixture('expired', '2026-06-14');
    makeExpiryFixture('today', '2026-06-15');
    makeExpiryFixture('future', '2026-06-22');
    makeExpiryFixture('undated', null);

    // The positive control: before the job runs, every fixture is published
    expect(publishedExpiryFixtures())->toHaveCount(4);

    app()->call([new UnpublishExpiredClassifieds, 'handle']);

    expect(publishedExpiryFixtures())->toBe([
        EXPIRY_TEST_PREFIX.'future',
        EXPIRY_TEST_PREFIX.'today',
        EXPIRY_TEST_PREFIX.'undated',
    ]);
});

it('agrees with the board index on which listings are still live', function (string $now): void {
    Carbon::setTestNow(Carbon::parse($now, 'UTC'));

    makeExpiryFixture('long-past', '2026-05-01');
    makeExpiryFixture('yesterday', '2026-06-14');
    makeExpiryFixture('the-day', '2026-06-15');
    makeExpiryFixture('tomorrow', '2026-06-16');
    makeExpiryFixture('undated', null);

    app()->call([new UnpublishExpiredClassifieds, 'handle']);

    // The job and the board index read the same fixtures, and must keep the same set
    expect(publishedExpiryFixtures())->toBe(liveExpiryFixtures());
})->with([
    'noon in Little Rock' => ['2026-06-15 17:00:00'],
    'the evening of the 15th in Little Rock, already the 16th in UTC' => ['2026-06-16 02:00:00'],
    'the morning of the 16th in Little Rock' => ['2026-06-16 12:00:00'],
]);

it('keeps a listing published through the evening of its expiration date in Little Rock', function (): void {
    // 21:00 on the 15th in Little Rock is 02:00 on the 16th in UTC
    Carbon::setTestNow(Carbon::parse('2026-06-16 02:00:00', 'UTC'));

    makeExpiryFixture('evening', '2026-06-15');

    app()->call([new UnpublishExpiredClassifieds, 'handle']);

    expect(publishedExpiryFixtures())->toBe([EXPIRY_TEST_PREFIX.'evening']);
});

it('leaves an already unpublished listing unpublished and unsaved', function (): void {
    $draft = makeExpiryFixture('draft', '2026-06-14', published: false);
    $expired = makeExpiryFixture('expired', '2026-06-14');

    // Count saves by entry id. A modification time would read the real file clock, so a
    // second save inside the same second would pass unnoticed.
    $saved = [];
    Event::listen(EntrySaved::class, function (EntrySaved $event) use (&$saved): void {
        $entry = $event->entry;

        if ($entry instanceof Statamic\Entries\Entry) {
            $saved[] = $entry->id();
        }
    });

    app()->call([new UnpublishExpiredClassifieds, 'handle']);

    /** @var Statamic\Entries\Entry $reloaded */
    $reloaded = Entry::find($draft->id());

    // The published expired listing is the positive control: the listener does see a save
    expect($saved)->toBe([$expired->id()])
        ->and($reloaded->published())->toBeFalse();
});

it('registers the job on the schedule at the configured cadence, in the display timezone', function (): void {
    // Asserted against the real schedule rather than by reading routes/console.php,
    // so a registration that silently fails to take cannot pass.
    $events = collect(app(Schedule::class)->events());

    $expiry = mustExist(
        $events->first(fn ($e): bool => str_contains((string) $e->getSummaryForDisplay(), UnpublishExpiredClassifieds::class)),
        'the scheduled classifieds expiry',
    );

    expect($expiry->expression)->toBe(config('classifieds.expiry.cron'))
        // Fully qualified: importing Statamic\Statamic would re-root every relative
        // `Statamic\...` name in this file onto that class.
        ->and($expiry->timezone)->toBe(Statamic\Statamic::displayTimezone());
});

it('tells an expired listing\'s account holder once, at the account\'s address', function (): void {
    Mail::fake();

    $account = makeExpiryAccount();
    $expired = makeExpiryFixture('owned', '2026-06-14', account: $account);

    app()->call([new UnpublishExpiredClassifieds, 'handle']);

    // One notice, to the account's address and not to the free-text contact_email
    Mail::assertSent(ClassifiedListingExpired::class, 1);
    Mail::assertSent(
        ClassifiedListingExpired::class,
        fn (ClassifiedListingExpired $mail): bool => $mail->hasTo(EXPIRY_TEST_ACCOUNT_EMAIL)
            && ! $mail->hasTo('sulu@example.com')
            && $mail->listingTitle === 'Listing owned',
    );

    /** @var Statamic\Entries\Entry $reloaded */
    $reloaded = Entry::find($expired->id());

    expect($reloaded->published())->toBeFalse()
        ->and($reloaded->get(UnpublishExpiredClassifieds::NOTICE_MARKER))->toBe('2026-06-14');
});

it('says the listing has expired and is no longer shown, with no link or renewal path', function (): void {
    $text = (new ClassifiedListingExpired('Couch & Loveseat'))->render();

    // The title is printed as written: a plain-text body has no use for HTML entities
    expect($text)->toContain('"Couch & Loveseat" has expired and is no longer shown on the site.')
        ->and($text)->not->toContain('&amp;')
        ->and($text)->not->toContain('http')
        ->and(strtolower($text))->not->toContain('renew');
});

it('sends nothing for an expired listing with no account, as for every imported one', function (): void {
    Mail::fake();

    $expired = makeExpiryFixture('unowned', '2026-06-14');

    app()->call([new UnpublishExpiredClassifieds, 'handle']);

    /** @var Statamic\Entries\Entry $reloaded */
    $reloaded = Entry::find($expired->id());

    // The unpublish is the positive control: the job did reach this listing
    expect($reloaded->published())->toBeFalse()
        ->and($reloaded->get(UnpublishExpiredClassifieds::NOTICE_MARKER))->toBeNull();
    Mail::assertNothingSent();
});

it('sends no notice before a listing expires', function (): void {
    Mail::fake();

    $account = makeExpiryAccount();
    makeExpiryFixture('due-today', '2026-06-15', account: $account);
    makeExpiryFixture('due-later', '2026-06-22', account: $account);

    app()->call([new UnpublishExpiredClassifieds, 'handle']);

    Mail::assertNothingSent();
});

it('does not notify a listing twice for the same expiration date, and does for a new one', function (): void {
    Mail::fake();

    $account = makeExpiryAccount();
    $listing = makeExpiryFixture('republished', '2026-06-14', account: $account);

    app()->call([new UnpublishExpiredClassifieds, 'handle']);

    // Republished with the same expiration date: the next run unpublishes it again
    /** @var Statamic\Entries\Entry $reloaded */
    $reloaded = Entry::find($listing->id());
    $reloaded->published(true);
    $reloaded->save();

    app()->call([new UnpublishExpiredClassifieds, 'handle']);

    /** @var Statamic\Entries\Entry $again */
    $again = Entry::find($listing->id());

    expect($again->published())->toBeFalse();
    Mail::assertSent(ClassifiedListingExpired::class, 1);

    // Republished with a later date that has also passed: that is a new expiry
    $again->set('expiration_date', '2026-06-15');
    $again->published(true);
    $again->save();
    Carbon::setTestNow(Carbon::parse('2026-06-16 17:00:00', 'UTC'));

    app()->call([new UnpublishExpiredClassifieds, 'handle']);

    Mail::assertSent(ClassifiedListingExpired::class, 2);
});

it('reads an account stored as a one-item list, as the users fieldtype can save it', function (): void {
    Mail::fake();

    $account = makeExpiryAccount();
    $listing = makeExpiryFixture('listed-account', '2026-06-14');
    $listing->set('account', [$account]);
    $listing->save();

    app()->call([new UnpublishExpiredClassifieds, 'handle']);

    Mail::assertSent(ClassifiedListingExpired::class, fn (ClassifiedListingExpired $mail): bool => $mail->hasTo(EXPIRY_TEST_ACCOUNT_EMAIL));
});

it('sends nothing, and does not fail, when the listing\'s account no longer exists', function (): void {
    Mail::fake();

    $expired = makeExpiryFixture('deleted-account', '2026-06-14', account: (string) Str::uuid());

    app()->call([new UnpublishExpiredClassifieds, 'handle']);

    /** @var Statamic\Entries\Entry $reloaded */
    $reloaded = Entry::find($expired->id());

    expect($reloaded->published())->toBeFalse();
    Mail::assertNothingSent();
});

it('names a listing with no title as empty rather than inventing one', function (): void {
    Mail::fake();

    $account = makeExpiryAccount();
    $listing = makeExpiryFixture('untitled', '2026-06-14', account: $account);
    $listing->remove('title');
    $listing->save();

    app()->call([new UnpublishExpiredClassifieds, 'handle']);

    Mail::assertSent(ClassifiedListingExpired::class, fn (ClassifiedListingExpired $mail): bool => $mail->listingTitle === '');
});

it('logs how many listings it unpublished and how many it notified', function (): void {
    Mail::fake();

    $account = makeExpiryAccount();
    makeExpiryFixture('logged-owned', '2026-06-14', account: $account);
    makeExpiryFixture('logged-unowned', '2026-06-14');
    makeExpiryFixture('logged-live', '2026-06-22', account: $account);

    /** @var list<array{message: string, context: array<string, mixed>}> $logged */
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = ['message' => $event->message, 'context' => $event->context];
    });

    app()->call([new UnpublishExpiredClassifieds, 'handle']);

    $summaries = collect($logged)->where('message', 'Unpublished expired classified listings.');
    $summary = $summaries->first();

    // The collection may hold other expired listings, so this file's own two are a floor
    expect($summaries)->toHaveCount(1)
        ->and($summary['context'] ?? null)->toMatchArray(['notified' => 1, 'today' => '2026-06-15'])
        ->and(data_get($summary, 'context.unpublished'))->toBeGreaterThanOrEqual(2)
        ->and(data_get($summary, 'context.checked'))->toBeGreaterThanOrEqual(3);
});

it('logs a failed send, keeps the listing marked, and carries on with the next listing', function (): void {
    Mail::shouldReceive('to')->twice()->andThrow(new TransportException('mail server unavailable'));

    $account = makeExpiryAccount();
    $first = makeExpiryFixture('failed-first', '2026-06-14', account: $account);
    $second = makeExpiryFixture('failed-second', '2026-06-14', account: $account);

    /** @var list<array{message: string, context: array<string, mixed>}> $logged */
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = ['message' => $event->message, 'context' => $event->context];
    });

    app()->call([new UnpublishExpiredClassifieds, 'handle']);

    foreach ([$first, $second] as $listing) {
        /** @var Statamic\Entries\Entry $reloaded */
        $reloaded = Entry::find($listing->id());

        expect($reloaded->published())->toBeFalse()
            ->and($reloaded->get(UnpublishExpiredClassifieds::NOTICE_MARKER))->toBe('2026-06-14');
    }

    // One error per listing, so the first failure did not stop the second attempt
    $errors = collect($logged)->where('message', 'Could not send a classified listing expiry notice.')->pluck('context');

    expect($errors->pluck('listing')->sort()->values()->all())->toBe(collect([$first->id(), $second->id()])->sort()->values()->all())
        ->and($errors->pluck('error')->unique()->all())->toBe(['mail server unavailable'])
        ->and(data_get(collect($logged)->firstWhere('message', 'Unpublished expired classified listings.'), 'context.notified'))->toBe(0);
});

it('logs a malformed account address and still notifies the next listing', function (): void {
    // The array transport builds every message, so Symfony checks each address as a real
    // send would; Mail::fake() never builds one, and could not see this failure
    Config::set('mail.default', 'array');

    $malformed = makeExpiryFixture('malformed-address', '2026-06-14', account: makeExpiryAccount(EXPIRY_TEST_MALFORMED_EMAIL));
    makeExpiryFixture('good-address', '2026-06-14', account: makeExpiryAccount());

    /** @var list<array{message: string, context: array<string, mixed>}> $logged */
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = ['message' => $event->message, 'context' => $event->context];
    });

    app()->call([new UnpublishExpiredClassifieds, 'handle']);

    $transport = app('mailer')->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(ArrayTransport::class);

    /** @var ArrayTransport $transport */
    $recipients = [];

    foreach ($transport->messages() as $message) {
        if ($message instanceof SentMessage) {
            foreach ($message->getEnvelope()->getRecipients() as $address) {
                $recipients[] = $address->getAddress();
            }
        }
    }

    expect($recipients)->toBe([EXPIRY_TEST_ACCOUNT_EMAIL])
        ->and(data_get(collect($logged)->firstWhere('message', 'Could not send a classified listing expiry notice.'), 'context.listing'))->toBe($malformed->id())
        ->and(data_get(collect($logged)->firstWhere('message', 'Unpublished expired classified listings.'), 'context.notified'))->toBe(1);
});

it('queues one run while another is pending, so two runs cannot both notify a listing', function (): void {
    Queue::fake();

    // The second dispatch finds the first run's unique lock still held, and is dropped
    UnpublishExpiredClassifieds::dispatch();
    UnpublishExpiredClassifieds::dispatch();

    Queue::assertPushed(UnpublishExpiredClassifieds::class, 1);
});
