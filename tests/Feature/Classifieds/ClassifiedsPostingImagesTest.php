<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Stache;
use Statamic\Facades\User;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Stache\Stores\Store;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Image uploads on the classifieds posting form (UAMS-Web/uams-statamic-archive#2519): where they land, which become
 * the featured image and the gallery, the type and count limits, and that replacing or
 * removing a listing deletes its images without touching a shared one.
 *
 * Runs in the parallel band, as `ClassifiedsPostingTest` does: the `images` disk it
 * fakes is rooted per worker under `--parallel` (UAMS-Web/uams-statamic-archive#3111), and its listings and poster
 * carry a title prefix and an email no other file uses (UAMS-Web/uams-statamic-archive#3205). Everything else about
 * posting is in `ClassifiedsPostingTest`.
 *
 * @command  php artisan test --compact tests/Feature/Classifieds/ClassifiedsPostingImagesTest.php
 */
const IMAGES_TEST_TITLE_PREFIX = 'ZZ posting images test ';

const IMAGES_TEST_EMAIL = 'zz-classifieds-images-poster@example.com';

const IMAGES_MY_LISTINGS = '/inside/classifieds/my-listings';

/** Remove the poster and listings this file created, and drop cached asset discovery. */
function tearDownImagesFixtures(): void
{
    $user = User::findByEmail(IMAGES_TEST_EMAIL);

    if ($user instanceof Statamic\Auth\User) {
        $user->delete();
    }

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'classifieds');

    /** @var Illuminate\Support\Collection<int, StatamicEntry> $entries */
    $entries = $query->get();

    $entries
        ->filter(fn (StatamicEntry $entry): bool => str_starts_with((string) (is_string($entry->get('title')) ? $entry->get('title') : ''), IMAGES_TEST_TITLE_PREFIX))
        ->each(fn (StatamicEntry $entry) => $entry->delete());

    // The faked disk's assets were indexed into the `assets` store; drop them so a later
    // test never sees files that only existed on the fake.
    /** @var Store $assets */
    $assets = Stache::store('assets');
    $assets->clear();
}

/**
 * A listing's entry id.
 *
 * @param  StatamicEntry  $entry  The listing.
 */
function imagesListingId(StatamicEntry $entry): string
{
    $id = $entry->id();

    return is_string($id) ? $id : '';
}

/**
 * An upload whose type is read from its CONTENT, as a real request's is.
 *
 * `UploadedFile::fake()` reports a MIME type derived from the file NAME, so a fake
 * named `photo.jpg` claims to be a JPEG whatever it holds, and cannot exercise a file
 * whose name and content disagree. A real `UploadedFile` over a temporary file asks
 * `finfo` about the bytes.
 *
 * @param  string  $name  The name the browser would send.
 * @param  string  $content  The file's bytes.
 */
function realUpload(
    string $name,
    string $content
): UploadedFile {
    $path = tempnam(sys_get_temp_dir(), 'classified-upload-');

    if ($path === false) {
        throw new RuntimeException('Could not create a temporary upload file.');
    }

    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, true);
}

/** The poster, created fresh for each test. */
function imagesPoster(): Statamic\Auth\User
{
    /** @var Statamic\Auth\User $user */
    $user = User::make();
    $user->email(IMAGES_TEST_EMAIL);
    $user->password('a-long-test-password');
    $user->save();

    return $user;
}

/**
 * A valid submission carrying the given uploads.
 *
 * @param  array<int, UploadedFile>  $images  The uploads.
 * @param  string  $title  The title suffix after the file's prefix.
 * @return array<string, mixed>
 */
function imagesForm(
    array $images,
    string $title = 'Camera'
): array {
    return [
        'title' => IMAGES_TEST_TITLE_PREFIX.$title,
        'contact_name' => 'Ada Poster',
        'contact_email' => 'ada@example.com',
        'images' => $images,
    ];
}

/**
 * The listing this file posted with the given title suffix.
 *
 * @param  string  $title  The title suffix after the file's prefix.
 */
function imagesListing(string $title = 'Camera'): StatamicEntry
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'classifieds');
    $query->where('title', IMAGES_TEST_TITLE_PREFIX.$title);

    /** @var StatamicEntry|null $entry */
    $entry = $query->first();

    expect($entry)->not->toBeNull();

    /** @var StatamicEntry $entry */
    return $entry;
}

beforeEach(function (): void {
    if (! Collection::findByHandle('classifieds')) {
        ContentSubmodule::reportMissing('classifieds collection');
    }

    Carbon::setTestNow(Carbon::parse('2026-06-15 17:00:00', 'UTC'));
    Site::setCurrent('inside_classifieds');
    Storage::fake('images');
    tearDownImagesFixtures();
});

afterEach(function (): void {
    tearDownImagesFixtures();
    Carbon::setTestNow();
});

it('stores the first upload as the featured image and the rest as the gallery, in order', function (): void {
    $user = imagesPoster();

    /** @var TestCase $test */
    $test = $this;
    $test->actingAs($user)->post(IMAGES_MY_LISTINGS, imagesForm([
        UploadedFile::fake()->image('front.jpg'),
        UploadedFile::fake()->image('side.png'),
        UploadedFile::fake()->image('back.jpg'),
    ]))->assertRedirect();

    $listing = imagesListing();

    /** @var string $featured */
    $featured = $listing->get('featured_image');
    /** @var list<string> $gallery */
    $gallery = $listing->get('gallery');

    expect($featured)->toStartWith('classifieds/')
        ->and($featured)->toEndWith('.jpg')
        ->and($gallery)->toHaveCount(2)
        ->and($gallery[0])->toEndWith('.png')
        ->and($gallery[1])->toEndWith('.jpg');

    Storage::disk('images')->assertExists([$featured, ...$gallery]);

    // Each image carries the listing title as its alt text.
    expect(AssetContainer::find('images')?->asset($featured)?->get('alt'))->toBe(IMAGES_TEST_TITLE_PREFIX.'Camera');
});

it('refuses an upload the images container would refuse', function (): void {
    $user = imagesPoster();

    /** @var TestCase $test */
    $test = $this;
    $test->actingAs($user)
        ->from(IMAGES_MY_LISTINGS.'/new')
        ->post(IMAGES_MY_LISTINGS, imagesForm([UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf')]))
        ->assertSessionHasErrors('images.0');

    expect(Storage::disk('images')->allFiles())->toBe([]);
});

it('refuses a file named as an image whose content is not one', function (string $name, string $content): void {
    $user = imagesPoster();

    /** @var TestCase $test */
    $test = $this;
    $test->actingAs($user)
        ->from(IMAGES_MY_LISTINGS.'/new')
        ->post(IMAGES_MY_LISTINGS, imagesForm([realUpload($name, $content)]))
        ->assertSessionHasErrors('images.0');

    expect(Storage::disk('images')->allFiles())->toBe([]);
})->with([
    'HTML named .jpg' => ['photo.jpg', '<html><body><script>alert(1)</script></body></html>'],
    'SVG named .png' => ['photo.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(2)</script></svg>'],
]);

it('refuses more images than a listing may carry', function (): void {
    $user = imagesPoster();

    $images = [];

    for ($i = 0; $i < 13; $i++) {
        $images[] = UploadedFile::fake()->image("photo-{$i}.jpg");
    }

    /** @var TestCase $test */
    $test = $this;
    $test->actingAs($user)
        ->from(IMAGES_MY_LISTINGS.'/new')
        ->post(IMAGES_MY_LISTINGS, imagesForm($images))
        ->assertSessionHasErrors('images');

    expect(Storage::disk('images')->allFiles())->toBe([]);
});

it('replaces a listing\'s images when the poster uploads new ones, deleting the old files', function (): void {
    $user = imagesPoster();

    /** @var TestCase $test */
    $test = $this;
    $test->actingAs($user)->post(IMAGES_MY_LISTINGS, imagesForm([UploadedFile::fake()->image('old.jpg')]));

    $listing = imagesListing();

    /** @var string $old */
    $old = $listing->get('featured_image');

    $test->actingAs($user)
        ->put(IMAGES_MY_LISTINGS.'/'.imagesListingId($listing), imagesForm([UploadedFile::fake()->image('new.png')]))
        ->assertRedirect();

    /** @var StatamicEntry $edited */
    $edited = Entry::find(imagesListingId($listing));

    /** @var string $new */
    $new = $edited->get('featured_image');

    expect($new)->not->toBe($old)
        ->and($new)->toEndWith('.png');

    Storage::disk('images')->assertMissing($old);
    Storage::disk('images')->assertExists($new);
});

it('keeps a listing\'s images when an edit uploads none', function (): void {
    $user = imagesPoster();

    /** @var TestCase $test */
    $test = $this;
    $test->actingAs($user)->post(IMAGES_MY_LISTINGS, imagesForm([UploadedFile::fake()->image('keep.jpg')]));

    $listing = imagesListing();

    /** @var string $kept */
    $kept = $listing->get('featured_image');

    $test->actingAs($user)->put(IMAGES_MY_LISTINGS.'/'.imagesListingId($listing), imagesForm([]));

    /** @var StatamicEntry $edited */
    $edited = Entry::find(imagesListingId($listing));

    expect($edited->get('featured_image'))->toBe($kept);
    Storage::disk('images')->assertExists($kept);
});

it('deletes a removed listing\'s images and leaves a shared image alone', function (): void {
    $user = imagesPoster();

    /** @var TestCase $test */
    $test = $this;
    $test->actingAs($user)->post(IMAGES_MY_LISTINGS, imagesForm([UploadedFile::fake()->image('mine.jpg')]));

    $listing = imagesListing();

    /** @var string $mine */
    $mine = $listing->get('featured_image');

    // Point the gallery at an image outside the board's folder, as a Control Panel edit might.
    Storage::disk('images')->put('shared/campus.jpg', 'shared');
    $listing->set('gallery', ['shared/campus.jpg']);
    $listing->save();

    $test->actingAs($user)->delete(IMAGES_MY_LISTINGS.'/'.imagesListingId($listing))->assertRedirect();

    Storage::disk('images')->assertMissing($mine);
    Storage::disk('images')->assertExists('shared/campus.jpg');
});
