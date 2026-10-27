<?php

declare(strict_types=1);

use App\Modifiers\FormatPhone;
use Statamic\Contracts\Entries\Collection as CollectionContract;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Term;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

// Returned to the parallel band on UAMS-Web/uams-statamic-archive#2503. This file was gated by UAMS-Web/uams-statamic-archive#1447 because the
// `people`-dependent assertions flaked under `--parallel` while passing in isolation (UAMS-Web/uams-statamic-archive#1443).
// The gate attributed that to "the shared, paratest-non-isolated compiled-view cache", which
// was already per worker under Laravel's `TestViews` when the flake was observed, so the cause
// was never established; the UAMS-Web/uams-statamic-archive#1300 Stache warm-up race UAMS-Web/uams-statamic-archive#1590 closed is the likeliest closer,
// and that remains a hypothesis. As an observed flake it came off only on the README's ten-run
// bar: ten consecutive `--parallel --exclude-group=serial` bands on a no-snapshot checkout
// with this file ungated produced zero failures (macOS, 2026-09-25). If it recurs, restore the
// gate with the observed failure as its reason. tests/README.md carries the audit row.
uses(TestCase::class);

/**
 * Asserts the `posts/components/media_contacts` Antlers partial resolves
 * `media_contact` → term `people` and emits expected markup.
 *
 * The expected contact details are read from the first published person on the
 * `general-1` media contact group, not written out, so the file names no real
 * person, address or telephone number (UAMS-Web/uams-statamic-archive#2747).
 *
 * No mutable PHP surface: the system under test is the Antlers partial
 * `resources/views/posts/components/_media_contacts.antlers.html` (template-only,
 * no backing App\ class), so there is no Infection target.
 *
 * @command  php artisan test --compact tests/Feature/PostsMediaContactsPartialTest.php
 */

/**
 * Resolve a collection that lives in the `content/` submodule, skipping the test
 * when it is absent (the Stache returns `null` without the submodule).
 *
 * Inlines `Tests\Concerns\RequiresContentSubmodule::requireContentCollection()`:
 * the canonical environments (`ci.yml` phpunit job, `local-ci.mjs`, a normal
 * local checkout) all initialize `content/`, so this guard never trips there; a
 * bare worktree reports a clear `SKIP` instead of the failure a missing
 * collection would cause.
 *
 * @param  string  $handle  The collection handle (e.g. `people`, `posts`)
 * @return CollectionContract The resolved collection
 */
function requireMediaContactsContentCollection(string $handle): CollectionContract
{
    $collection = Collection::find($handle);

    if (! $collection) {
        ContentSubmodule::reportMissing("{$handle} collection");
    }

    return $collection;
}

/**
 * The person the partial lists first for `general-1`: the first published entry
 * in the term's `people`, in stored order.
 *
 * Skips when the `content/` submodule is absent, and fails when it is present but
 * the term or a published person cannot be resolved, so a renamed term cannot
 * pass as a skip.
 *
 * @return array{name: string, telephone: string, telephone_display: string, email: string}
 */
function firstMediaContactPerson(): array
{
    // Resolve the term the synthetic news release points at
    $found = Term::find('media_contact_groups::general-1');

    if ($found === null) {
        ContentSubmodule::reportMissing('media_contact_groups::general-1 term');
    }

    $term = mustBe(LocalizedTerm::class, $found, 'the general-1 media contact group');
    $ids = mustBeArray($term->get('people'), 'general-1 people');

    expect($ids)->not->toBeEmpty();

    foreach ($ids as $id) {
        $person = Entry::find(mustBeString($id, 'a general-1 person id'));

        if ($person instanceof StatamicEntry && $person->published()) {
            $telephone = mustBeString($person->get('telephone'), 'the first contact telephone');
            $values = [
                'name' => mustBeString($person->get('title'), 'the first contact name'),
                'telephone' => $telephone,
                'telephone_display' => (new FormatPhone)->index($telephone, [], []),
                'email' => mustBeString($person->get('email'), 'the first contact email'),
            ];

            // Each value must be present, and the display form must differ from the stored E.164,
            // or the assertions that use them pass vacuously
            expect($values['name'])->not->toBeEmpty()
                ->and($values['telephone'])->not->toBeEmpty()
                ->and($values['email'])->not->toBeEmpty()
                ->and($values['telephone_display'])->not->toBe($telephone);

            return $values;
        }
    }

    throw new RuntimeException('general-1 lists no published person');
}

/**
 * Synthetic in-memory news release with the same `media_contact` wiring as the
 * former `news-release-test` fixture (term `general-1`).
 *
 * @return StatamicEntry Entry used only for augmentation context
 */
function syntheticNewsReleaseForMediaContacts(): StatamicEntry
{
    // Resolve the dated `posts` collection from the Stache (skips when the
    // content/ submodule is absent and the collection cannot be found)
    $collection = requireMediaContactsContentCollection('posts');

    // Build an unsaved entry so tests do not depend on committed markdown
    $entry = Entry::make();

    expect($entry)->toBeInstanceOf(StatamicEntry::class);

    /** @var StatamicEntry $entry */
    $entry->collection($collection);
    $entry->id('33333333-3333-4333-8333-333333333333');
    $entry->slug('media-contacts-partial-test');
    $entry->blueprint('news_release');
    $entry->published(true);
    $entry->locale('default');
    $entry->data(
        [
            'title' => 'Automated test news release',
            'media_contact' => 'general-1',
            'media_contact_query' => true,
            'boilerplate_query' => true,
            'sticky_query' => false,
            'content' => [
                [
                    'type' => 'paragraph',
                    'attrs' => [
                        'id' => null,
                    ],
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Fixture paragraph for automated tests.',
                        ],
                    ],
                ],
            ],
        ]
    );

    return $entry;
}

/**
 * `media_contact` general-1 with two people; default heading is plural and
 * phone/email use anchors.
 *
 * @command  php artisan test --compact tests/Feature/PostsMediaContactsPartialTest.php --filter=test_default_renders_plural_heading_and_linked_contacts
 */
it('default renders plural heading and linked contacts', function () {
    // Build augmented context from the synthetic entry
    $entry = syntheticNewsReleaseForMediaContacts();

    /** @var array<string,mixed> $context */
    $context = $entry->toAugmentedArray();

    $html = (string) Antlers::parse(
        '{{ partial:posts/components/media_contacts display_enabled="true" }}',
        $context,
        true
    );

    expect($html)->toContain('<h2>Media Contacts</h2>');
    expect($html)->toContain('<ul class="media-contacts-list">');
    expect($html)->toContain('<li>');
    $person = firstMediaContactPerson();

    expect($html)->toContain('<strong itemprop="name">'.$person['name'].'</strong>');
    expect($html)->toContain('Office:');
    expect($html)->toContain('Wireless:');
    expect($html)->toContain('Email:');
    expect($html)->toContain('<a href="tel:'.$person['telephone'].'">'.$person['telephone_display'].'</a>');
    expect($html)->toContain('<a href="mailto:');
});

/**
 * With `include_links="false"`, contact values stay plain text (no `<a>`).
 *
 * @command  php artisan test --compact tests/Feature/PostsMediaContactsPartialTest.php --filter=test_include_links_false_omits_anchors
 */
it('include links false omits anchors', function () {
    // Build augmented context from the synthetic entry
    $entry = syntheticNewsReleaseForMediaContacts();

    /** @var array<string,mixed> $context */
    $context = $entry->toAugmentedArray();

    $html = (string) Antlers::parse(
        '{{ partial:posts/components/media_contacts display_enabled="true" include_links="false" }}',
        $context,
        true
    );

    $person = firstMediaContactPerson();

    expect($html)->not->toContain('<a href');
    expect($html)->toContain($person['telephone_display']);
    expect($html)->not->toContain($person['telephone']);
    expect($html)->toContain($person['email']);
});

/**
 * Matches `posts/news_release` wiring: bool_string `true` enables the block.
 *
 * @command  php artisan test --compact tests/Feature/PostsMediaContactsPartialTest.php --filter=test_display_enabled_from_bool_string_true_renders
 */
it('display enabled from bool string true renders', function () {
    // Build augmented context from the synthetic entry (no Stache `fresh()`)
    $entry = syntheticNewsReleaseForMediaContacts();

    /** @var array<string,mixed> $context */
    $context = $entry->toAugmentedArray();

    $html = (string) Antlers::parse(
        '{{ partial:posts/components/media_contacts :display_enabled="( media_contact_query | raw | bool_string )" }}',
        $context,
        true
    );

    expect($html)->toContain('<h2>Media Contacts</h2>');
});

/**
 * With `display_enabled="false"`, the partial renders no markup.
 *
 * @command  php artisan test --compact tests/Feature/PostsMediaContactsPartialTest.php --filter=test_display_enabled_false_outputs_nothing
 */
it('display enabled false outputs nothing', function () {
    // Build augmented context from the synthetic entry
    $entry = syntheticNewsReleaseForMediaContacts();

    /** @var array<string,mixed> $context */
    $context = $entry->toAugmentedArray();

    $html = (string) Antlers::parse(
        '{{ partial:posts/components/media_contacts display_enabled="false" }}',
        $context,
        true
    );

    expect($html)->not->toContain('Media Contact');
    expect($html)->not->toContain(firstMediaContactPerson()['name']);
});

/**
 * Heading, level, and wrapper classes pass through to the markup.
 *
 * @command  php artisan test --compact tests/Feature/PostsMediaContactsPartialTest.php --filter=test_heading_level_and_classes
 */
it('heading level and classes', function () {
    // Build augmented context from the synthetic entry
    $entry = syntheticNewsReleaseForMediaContacts();

    /** @var array<string,mixed> $context */
    $context = $entry->toAugmentedArray();

    $html = (string) Antlers::parse(
        '{{ partial:posts/components/media_contacts display_enabled="true" heading="Press Desk" heading_level="3" heading_class="hc" address_class="ac" }}',
        $context,
        true
    );

    expect($html)->toContain('<h3 class="hc">Press Desk</h3>');
    expect($html)->toContain('<address class="ac">');
});
