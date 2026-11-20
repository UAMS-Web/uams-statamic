<?php

declare(strict_types=1);

use App\Services\Classifieds\ClassifiedListingWriter;
use App\Support\Classifieds\ClassifiedExpiry;
use App\Support\Redirects\DeletedEntryRedirectCapture;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Events\AssetDeleted;
use Statamic\Events\EntrySaving;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;
use Statamic\Listeners\UpdateAssetReferences;
use Statamic\Stache\Stores\Store;
use Tests\Support\ContentSubmodule;
use Tests\Support\EntriesAndTermsIsolatedTestCase;
use Tests\Support\NullDeletedEntryRedirectCapture;

/**
 * Drives `ClassifiedListingWriter` directly (UAMS-Web/uams-statamic#309), pinning what
 * the posting tests reach only through the controller: the slug and stored file names,
 * how each form field is narrowed, the Bard round trip of the description, the
 * configured container and folder, and the clean-up of stored files when a save fails
 * or a listing is removed.
 *
 * Random strings are fixed with `Str::createRandomStringsUsing()`, so a slug and a
 * stored file name are asserted exactly. The `images` disk is faked, as the posting
 * tests fake it, and the entries this file saves land in this process's own copy of the
 * content (`EntriesAndTermsIsolatedTestCase`).
 *
 * @command  php artisan test --compact tests/Feature/Services/Classifieds/ClassifiedListingWriterTest.php
 */
uses(EntriesAndTermsIsolatedTestCase::class);

beforeEach(function (): void {
    if (! Collection::findByHandle('classifieds')) {
        ContentSubmodule::reportMissing('classifieds collection');
    }

    // Deleting a listing records a redirect in the redirects global, which stays on the shared mirror (#558)
    app()->instance(DeletedEntryRedirectCapture::class, new NullDeletedEntryRedirectCapture);

    Carbon::setTestNow(Carbon::parse('2026-06-15 17:00:00', 'UTC'));
    Storage::fake('images');
    Str::createRandomStringsUsing(fn (int $length): string => str_repeat('Q', $length));
});

afterEach(function (): void {
    Str::createRandomStringsNormally();
    Carbon::setTestNow();

    // The faked disk's assets were indexed into the `assets` store; drop them so a later
    // test never sees files that only existed on the fake
    /** @var Store $assets */
    $assets = Stache::store('assets');
    $assets->clear();
});

/** The writer, resolved as the controller resolves it. */
function listingWriter(): ClassifiedListingWriter
{
    return app(ClassifiedListingWriter::class);
}

/**
 * The fields a valid post carries, with the given overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function listingWriterFields(array $overrides = []): array
{
    return [
        'title' => 'Camera For Sale',
        'contact_name' => 'Ada Poster',
        'contact_email' => 'ada@example.com',
        ...$overrides,
    ];
}

/**
 * A saved listing whose stored data is exactly what the given fields say, for the
 * reading and deleting cases.
 *
 * @param  array<string, mixed>  $data
 */
function listingWriterStoredListing(array $data): StatamicEntry
{
    /** @var StatamicEntry $entry */
    $entry = Entry::make();
    $entry->collection('classifieds');
    $entry->locale('inside_classifieds');
    $entry->slug('stored-listing');
    $entry->data(['title' => 'Stored listing', ...$data]);
    $entry->saveQuietly();

    return $entry;
}

/** Put a file on the faked `images` disk at the given path. */
function listingWriterImageOnDisk(string $path): void
{
    Storage::disk('images')->put($path, 'image bytes');
}

/** Make every entry save throw, as a failing write would. */
function listingWriterFailEverySave(): void
{
    Event::listen(EntrySaving::class, function (): never {
        throw new RuntimeException('the save failed');
    });
}

it('posts a listing under a slug from its title and a fixed six-character suffix', function (): void {
    $entry = listingWriter()->create('user-1', listingWriterFields(), []);

    expect($entry->slug())->toBe('camera-for-sale-qqqqqq')
        ->and($entry->published())->toBeTrue()
        ->and($entry->locale())->toBe('inside_classifieds')
        ->and($entry->get('account'))->toBe('user-1')
        ->and(mustBe(Carbon::class, $entry->date(), 'the listing date')->toIso8601String())->toBe('2026-06-15T17:00:00+00:00')
        ->and($entry->get('expiration_date'))->toBe(app(ClassifiedExpiry::class)->defaultExpirationDate())
        ->and(Entry::find(mustBeString($entry->id(), 'the listing id')))->not->toBeNull();
});

it('gives a title with no letters or digits a slug of the suffix alone', function (): void {
    $entry = listingWriter()->create('user-1', listingWriterFields(['title' => '!!!']), []);

    expect($entry->slug())->toBe('qqqqqq');
});

it('stores each field trimmed and narrowed to its type', function (): void {
    $entry = listingWriter()->create('user-1', listingWriterFields([
        'title' => '  Camera For Sale  ',
        'contact_name' => '  Ada Poster  ',
        'contact_email' => ' ada@example.com ',
        'contact_phone' => ' 501-555-0100 ',
        'location' => ' Little Rock ',
        'price' => '12.50',
        'classified_categories' => ['electronics', 7, null, 'cameras'],
    ]), []);

    expect($entry->get('title'))->toBe('Camera For Sale')
        ->and($entry->get('contact_name'))->toBe('Ada Poster')
        ->and($entry->get('contact_email'))->toBe('ada@example.com')
        ->and($entry->get('contact_phone'))->toBe('501-555-0100')
        ->and($entry->get('location'))->toBe('Little Rock')
        ->and($entry->get('price'))->toBe(12.5)
        ->and($entry->get('classified_categories'))->toBe(['electronics', 'cameras']);
});

it('stores an absent, blank or non-string field as empty, and an optional one as null', function (): void {
    $entry = listingWriter()->create('user-1', [
        'title' => 'Camera',
        'contact_name' => 42,
        'contact_phone' => '   ',
        'price' => 'free',
        'classified_categories' => 'cameras',
    ], []);

    expect($entry->get('contact_name'))->toBe('')
        ->and($entry->get('contact_email'))->toBe('')
        ->and($entry->get('contact_phone'))->toBeNull()
        ->and($entry->get('location'))->toBeNull()
        ->and($entry->get('price'))->toBeNull()
        ->and($entry->get('classified_categories'))->toBe([])
        ->and($entry->get('description'))->toBeNull();
});

it('builds the description as Bard paragraphs and hard breaks, whatever the line endings', function (): void {
    $entry = listingWriter()->create('user-1', listingWriterFields([
        'description' => "  First line\r\nsecond line\r\rNext paragraph\rwith a break  ",
    ]), []);

    expect($entry->get('description'))->toBe([
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'First line'],
            ['type' => 'hardBreak'],
            ['type' => 'text', 'text' => 'second line'],
        ]],
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Next paragraph'],
            ['type' => 'hardBreak'],
            ['type' => 'text', 'text' => 'with a break'],
        ]],
    ]);
});

it('stores a description of only whitespace as no description', function (): void {
    $entry = listingWriter()->create('user-1', listingWriterFields(['description' => " \r\n \n "]), []);

    expect($entry->get('description'))->toBeNull();
});

it('reads a description back as the text it was built from', function (): void {
    $text = "First line\nsecond line\n\nNext paragraph";
    $entry = listingWriter()->create('user-1', listingWriterFields(['description' => $text]), []);

    expect(listingWriter()->descriptionAsText($entry))->toBe($text);
});

it('reads a missing or malformed description as empty text', function (mixed $stored): void {
    expect(listingWriter()->descriptionAsText(listingWriterStoredListing(['description' => $stored])))->toBe('');
})->with([
    'no description' => [null],
    'a string' => ['plain text'],
]);

it('reads each block of an edited description onto its own line, skipping what is not a node', function (): void {
    $entry = listingWriterStoredListing(['description' => [
        ['type' => 'paragraph', 'content' => [
            'not a node',
            ['type' => 'text', 'text' => 'Intro'],
            ['type' => 'hardBreak'],
            ['type' => 'text', 'text' => 'continued'],
            ['type' => 'text', 'text' => ['not', 'a', 'string']],
        ]],
        'not a block',
        ['type' => 'bulletList', 'content' => [
            ['type' => 'listItem', 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'One']]],
            ]],
            ['type' => 'listItem', 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Two']]],
            ]],
        ]],
    ]]);

    expect(listingWriter()->descriptionAsText($entry))->toBe("Intro\ncontinued\n\nOne\nTwo");
});

it('stores uploads under random names in the configured container and folder, featured first', function (): void {
    $entry = listingWriter()->create('user-1', listingWriterFields(), [
        UploadedFile::fake()->image('front.jpeg'),
        UploadedFile::fake()->image('side.png'),
    ]);

    $stem = str_repeat('Q', 24);

    expect($entry->get('featured_image'))->toBe("classifieds/{$stem}.jpg")
        ->and($entry->get('gallery'))->toBe(["classifieds/{$stem}.png"])
        ->and(Storage::disk('images')->exists("classifieds/{$stem}.jpg"))->toBeTrue()
        ->and(AssetContainer::findOrFail('images')->asset("classifieds/{$stem}.png")->get('alt'))->toBe('Camera For Sale');
});

it('reads the container and folder from config, falling back when either is not a string', function (): void {
    // `files` is a container the content already holds, so nothing is saved: a container
    // saved here would land in the shared content mirror, where a sibling worker's Stache
    // warm reads it while its disk is configured only in this process (#1139)
    Storage::fake('files');

    config(['classifieds.images.container' => 'files', 'classifieds.images.folder' => '/zz-listings/']);

    $entry = listingWriter()->create('user-1', listingWriterFields(), [UploadedFile::fake()->image('front.png')]);
    $path = 'zz-listings/'.str_repeat('Q', 24).'.png';

    expect($entry->get('featured_image'))->toBe($path)
        ->and(Storage::disk('files')->exists($path))->toBeTrue()
        ->and(Storage::disk('images')->allFiles())->toBe([]);

    config(['classifieds.images.container' => 5, 'classifieds.images.folder' => ['x']]);
    $fallback = listingWriter()->create('user-1', listingWriterFields(), [UploadedFile::fake()->image('front.png')]);

    expect($fallback->get('featured_image'))->toBe('classifieds/'.str_repeat('Q', 24).'.png')
        ->and(Storage::disk('images')->exists('classifieds/'.str_repeat('Q', 24).'.png'))->toBeTrue();
});

it('deletes the files it stored when the new listing cannot be saved', function (): void {
    listingWriterFailEverySave();

    expect(fn () => listingWriter()->create('user-1', listingWriterFields(), [UploadedFile::fake()->image('front.png')]))
        ->toThrow(RuntimeException::class, 'the save failed')
        ->and(Storage::disk('images')->allFiles('classifieds'))->toBe([]);
});

it('deletes the new files and keeps the old ones when an edit cannot be saved', function (): void {
    listingWriterImageOnDisk('classifieds/old.jpg');
    $entry = listingWriterStoredListing(['featured_image' => 'classifieds/old.jpg', 'gallery' => []]);

    listingWriterFailEverySave();

    expect(fn () => listingWriter()->update($entry, listingWriterFields(), [UploadedFile::fake()->image('new.png')]))
        ->toThrow(RuntimeException::class, 'the save failed')
        ->and(Storage::disk('images')->allFiles('classifieds'))->toBe(['classifieds/old.jpg']);
});

it('deletes a removed listing\'s featured image and gallery, and only files in its own folder', function (): void {
    foreach (['classifieds/front.jpg', 'classifieds/side.jpg', 'classifieds/back.jpg', 'classifieds-shared/logo.jpg'] as $path) {
        listingWriterImageOnDisk($path);
    }

    $entry = listingWriterStoredListing([
        'featured_image' => 'classifieds/front.jpg',
        'gallery' => ['classifieds/side.jpg', 'classifieds-shared/logo.jpg', 'classifieds/missing.jpg', 7, 'classifieds/back.jpg'],
    ]);

    listingWriter()->delete($entry);

    expect(Storage::disk('images')->allFiles())->toBe(['classifieds-shared/logo.jpg'])
        ->and(Entry::find(mustBeString($entry->id(), 'the listing id')))->toBeNull();
});

it('switches the asset reference sweep off while deleting images, and back on after', function (): void {
    listingWriterImageOnDisk('classifieds/front.jpg');
    $entry = listingWriterStoredListing(['featured_image' => 'classifieds/front.jpg']);
    $sweep = [UpdateAssetReferences::class, 'handleDeleted'];
    $sweepRegistered = function () use ($sweep): bool {
        $listeners = Event::getRawListeners()[AssetDeleted::class] ?? [];

        return is_array($listeners) && in_array($sweep, $listeners, true);
    };
    $duringDelete = [];

    expect($sweepRegistered())->toBeTrue();

    Event::listen(AssetDeleted::class, function () use (&$duringDelete, $sweepRegistered): void {
        $duringDelete[] = $sweepRegistered();
    });

    listingWriter()->delete($entry);

    expect($duringDelete)->toBe([false])
        ->and($sweepRegistered())->toBeTrue();
});

it('keeps the stored description when an edit leaves its text unchanged, and replaces it otherwise', function (): void {
    $formatted = [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Bold', 'marks' => [['type' => 'bold']]]]]];
    $entry = listingWriterStoredListing(['description' => $formatted, 'account' => 'user-1', 'expiration_date' => '2026-07-01']);

    listingWriter()->update($entry, listingWriterFields(['title' => 'Edited', 'description' => 'Bold']), []);
    $kept = mustBe(StatamicEntry::class, Entry::find(mustBeString($entry->id(), 'the listing id')), 'the edited listing');

    expect($kept->get('description'))->toBe($formatted)
        ->and($kept->get('title'))->toBe('Edited')
        ->and($kept->get('account'))->toBe('user-1')
        ->and($kept->get('expiration_date'))->toBe('2026-07-01');

    listingWriter()->update($kept, listingWriterFields(['description' => 'Plain now']), []);
    $replaced = mustBe(StatamicEntry::class, Entry::find(mustBeString($entry->id(), 'the listing id')), 'the edited listing');

    expect($replaced->get('description'))->toBe([['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Plain now']]]]);
});

it('keeps a listing\'s images when an edit uploads none, and replaces them when it uploads some', function (): void {
    listingWriterImageOnDisk('classifieds/old-front.jpg');
    listingWriterImageOnDisk('classifieds/old-side.jpg');
    $entry = listingWriterStoredListing(['featured_image' => 'classifieds/old-front.jpg', 'gallery' => ['classifieds/old-side.jpg']]);

    listingWriter()->update($entry, listingWriterFields(), []);

    expect($entry->get('featured_image'))->toBe('classifieds/old-front.jpg')
        ->and($entry->get('gallery'))->toBe(['classifieds/old-side.jpg'])
        ->and(Storage::disk('images')->allFiles('classifieds'))->toBe(['classifieds/old-front.jpg', 'classifieds/old-side.jpg']);

    listingWriter()->update($entry, listingWriterFields(), [UploadedFile::fake()->image('new.png')]);
    $new = 'classifieds/'.str_repeat('Q', 24).'.png';

    expect($entry->get('featured_image'))->toBe($new)
        ->and($entry->get('gallery'))->toBe([])
        ->and(Storage::disk('images')->allFiles('classifieds'))->toBe([$new]);
});
