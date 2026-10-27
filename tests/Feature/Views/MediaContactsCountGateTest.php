<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Fields\Field;
use Tests\Support\StacheIsolatedTestCase;

/**
 * The media-contacts section gate (UAMS-Web/uams-statamic-archive#1693), exercised against a REAL augmented
 * `entries` relation rather than a plain fixture array.
 *
 * `PostsMediaContactsPartialTest` already covers this partial's markup, but it
 * feeds the partial content-submodule entries that are all published, so it
 * cannot see the defect this file exists for: a bare `{{ people | count }}`
 * forwards to the wrapped builder and counts the STORED IDS, while the
 * `{{ people }}` loop below it resolves through `StatusQueryBuilder::get()` and
 * emits only the published ones. A term whose contacts are all unpublished
 * therefore rendered a heading over an empty list. Only a published/unpublished
 * mix through the real augmentation asserts the fix.
 *
 * @command php artisan test --compact tests/Feature/Views/MediaContactsCountGateTest.php
 */
uses(StacheIsolatedTestCase::class);

const MEDIA_CONTACTS_GATE_COLLECTION = 'people';

beforeEach(function (): void {
    $collection = CollectionFacade::make(MEDIA_CONTACTS_GATE_COLLECTION);
    $collection->title('People');
    $collection->save();
});

/**
 * Persist one `people` entry and return its id.
 *
 * @param  string  $id  The entry id and slug.
 * @param  string  $title  The person's display name.
 * @param  bool  $published  Whether the entry is published.
 * @return string The persisted entry's id.
 */
function mediaContactsGatePerson(string $id, string $title, bool $published): string
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection(MEDIA_CONTACTS_GATE_COLLECTION);
    $entry->id($id);
    $entry->slug($id);
    $entry->published($published);
    $entry->data([
        'title' => $title,
        'telephone' => '+15016868998',
        'email' => sprintf('%s@uams.edu', $id),
    ]);
    $entry->saveQuietly();

    return $id;
}

/**
 * Augment an `entries` relation over the given ids the way the
 * `media_contact_group` blueprint's `people` field does.
 *
 * @param  list<string>  $ids  Entry ids the relation stores.
 * @return mixed The augmented relation value, as the template sees it.
 */
function mediaContactsGateRelation(array $ids): mixed
{
    $field = Blueprint::makeFromFields([
        'people' => [
            'type' => 'entries',
            'collections' => [MEDIA_CONTACTS_GATE_COLLECTION],
        ],
    ])->field('people');

    // `Blueprint::field()` and `Field::augment()` are untyped upstream, so narrow
    // both rather than asserting a shape PHPStan cannot see through.
    if (! $field instanceof Field) {
        throw new RuntimeException('The fixture blueprint did not yield a `people` field.');
    }

    $field->setValue($ids);
    $augmented = $field->augment();

    if (! $augmented instanceof Field) {
        throw new RuntimeException('The fixture `people` field did not augment.');
    }

    return $augmented->value();
}

/**
 * Render the media-contacts partial with the given relation on the term scope.
 *
 * @param  mixed  $relation  The augmented `people` relation.
 * @return string The rendered markup.
 */
function renderMediaContactsWith(mixed $relation): string
{
    return (string) Antlers::parse(
        '{{ partial:posts/components/media_contacts display_enabled="true" }}',
        ['media_contact' => ['people' => $relation]],
        true
    );
}

it('renders no section when every media contact is unpublished', function (): void {
    mediaContactsGatePerson('embargoed', 'Ensign Chekov', published: false);

    $html = renderMediaContactsWith(mediaContactsGateRelation(['embargoed']));

    expect($html)->not->toContain('media-contacts-list')
        ->and($html)->not->toContain('Media Contact')
        ->and($html)->not->toContain('Ensign Chekov');
});

it('renders only the published contacts on a mixed relation', function (): void {
    mediaContactsGatePerson('uhura', 'Nyota Uhura', published: true);
    mediaContactsGatePerson('embargoed', 'Ensign Chekov', published: false);

    $html = renderMediaContactsWith(mediaContactsGateRelation(['uhura', 'embargoed']));

    expect($html)->toContain('media-contacts-list')
        ->and($html)->toContain('Nyota Uhura')
        ->and($html)->not->toContain('Ensign Chekov');
});

// The count also drives the default heading's pluralization, so an over-count is
// visible in the copy as well as in the empty list: one published contact beside
// an unpublished one must read "Media Contact", not "Media Contacts".
it('pluralizes the default heading from the resolved contacts, not the stored ids', function (): void {
    mediaContactsGatePerson('uhura', 'Nyota Uhura', published: true);
    mediaContactsGatePerson('embargoed', 'Ensign Chekov', published: false);

    $html = renderMediaContactsWith(mediaContactsGateRelation(['uhura', 'embargoed']));

    expect($html)->toContain('<h2>Media Contact</h2>')
        ->and($html)->not->toContain('<h2>Media Contacts</h2>');
});

it('keeps the plural heading when two contacts really are published', function (): void {
    mediaContactsGatePerson('uhura', 'Nyota Uhura', published: true);
    mediaContactsGatePerson('sulu', 'Hikaru Sulu', published: true);

    $html = renderMediaContactsWith(mediaContactsGateRelation(['uhura', 'sulu']));

    expect($html)->toContain('<h2>Media Contacts</h2>')
        ->and($html)->toContain('Nyota Uhura')
        ->and($html)->toContain('Hikaru Sulu');
});
