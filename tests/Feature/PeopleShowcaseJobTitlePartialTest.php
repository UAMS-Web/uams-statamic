<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;
use Statamic\Contracts\Entries\Collection as CollectionContract;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Regression coverage for UAMS-Web/uams-statamic-archive#842: the People Showcase item partial must render a
 * person's `job_title` replicator (an array of role/title sets) instead of
 * piping the whole array through `widont`, which threw a `TypeError` and `500`d
 * the page once the list actually populated.
 *
 * Template-only: this exercises the `page_builder/uamsweb_block_people_item`
 * Antlers partial; there is no backing App\ PHP class, so there is no mutable
 * PHP surface to target. All three tests are content-dependent and self-skip
 * without the `content/` submodule (the partial and the committed
 * `c-lowry-barnes` person both live there), so they run only in CI / a normal
 * local checkout.
 *
 * @command  php artisan test --compact tests/Feature/PeopleShowcaseJobTitlePartialTest.php
 */

/**
 * Resolve a collection that lives in the `content/` submodule, skipping the
 * test when it is absent (the Stache returns `null` without the submodule).
 *
 * Mirrors `Tests\Concerns\RequiresContentSubmodule::requireContentCollection()`:
 * the canonical environments (`ci.yml` phpunit job, `local-ci.mjs`, a normal
 * local checkout) all initialize `content/`, so this guard never trips there; a
 * bare worktree reports a clear `SKIP` instead of the failure a missing
 * collection would cause.
 *
 * @param  string  $handle  The collection handle (e.g. `people`, `posts`)
 * @return CollectionContract The resolved collection
 */
function peopleShowcaseJobTitleRequireContentCollection(string $handle): CollectionContract
{
    $collection = Collection::find($handle);

    if (! $collection) {
        ContentSubmodule::reportMissing("{$handle} collection");
    }

    return $collection;
}

/**
 * Assert a real committed entry resolved, skipping the test when it did not —
 * the entry is indexed only with the `content/` submodule present.
 *
 * Mirrors `Tests\Concerns\RequiresContentSubmodule::requireContentEntry()`.
 *
 * @param  mixed  $entry  The resolved entry, or `null`
 * @param  string  $description  What the missing entry provides
 * @return EntryContract The non-null entry
 */
function peopleShowcaseJobTitleRequireContentEntry(mixed $entry, string $description): EntryContract
{
    if (! $entry) {
        ContentSubmodule::reportMissing($description);
    }

    /** @var EntryContract $entry */
    return $entry;
}

/**
 * Render the showcase item partial against an augmented person context with
 * the `position` detail enabled.
 *
 * @param  StatamicEntry  $entry  Person entry providing the augmentation scope
 * @return string The rendered partial markup
 */
function peopleShowcaseJobTitleRenderItemPartial(StatamicEntry $entry): string
{
    /** @var array<string, mixed> $context */
    $context = $entry->toAugmentedArray();

    // The block normally supplies `display_detail`; the item partial plucks
    // its `key`s, so seed the `position` detail to exercise the job title.
    $context['display_detail'] = [
        ['key' => 'position'],
    ];

    return (string) Antlers::parse(
        '{{ partial:page_builder/uamsweb_block_people_item }}',
        $context,
        true
    );
}

/**
 * Build an unsaved person carrying a two-role `job_title` replicator: a
 * free-text role (plain string title) and an unstructured `role_title` role
 * (iterable `title.value`). Covers both rendering branches.
 *
 * @return StatamicEntry Person entry used only for augmentation context
 */
function peopleShowcaseJobTitleSyntheticPersonWithRoles(): StatamicEntry
{
    $collection = peopleShowcaseJobTitleRequireContentCollection('people');

    $entry = Entry::make();

    Assert::assertInstanceOf(StatamicEntry::class, $entry);

    /** @var StatamicEntry $entry */
    $entry->collection($collection);
    $entry->id('44444444-4444-4444-8444-444444444444');
    $entry->slug('job-title-partial-test');
    $entry->blueprint('person');
    $entry->published(true);
    $entry->locale('default');
    $entry->data(
        [
            'title' => 'Job Title Partial Test',
            'job_title' => [
                [
                    'id' => 'rolefreetext',
                    'type' => 'free_text',
                    'enabled' => true,
                    'title' => 'acting chancellor',
                ],
                [
                    'id' => 'roleunstructured',
                    'type' => 'institutional_leadership_role',
                    'enabled' => true,
                    'title' => [
                        'mode' => 'unstructured',
                        'value' => 'lead director',
                    ],
                ],
            ],
        ]
    );

    return $entry;
}

/**
 * Every enabled role renders as its own schema.org `jobTitle` line; the
 * partial does not throw on the replicator array.
 *
 * @command  php artisan test --compact tests/Feature/PeopleShowcaseJobTitlePartialTest.php --filter=test_renders_all_roles_as_position_lines
 */
it('renders all roles as position lines', function () {
    $html = peopleShowcaseJobTitleRenderItemPartial(peopleShowcaseJobTitleSyntheticPersonWithRoles());

    expect($html)->toContain('uamspeople-single-position');
    expect($html)->toContain('schema.org/jobTitle');

    // Both enabled roles render (free-text title and composed role_title),
    // title-cased like the people entry template — the lowercase fixtures
    // `acting chancellor` / `lead director` come out capitalized.
    expect($html)->toContain('Acting');
    expect($html)->toContain('Chancellor');
    expect($html)->toContain('Lead');
    expect($html)->toContain('Director');
    expect($html)->not->toContain('acting chancellor');

    // Neither synthetic role carries a department, so no separator appears.
    expect($html)->not->toContain('&mdash;');

    // "All roles" decision: one position line per enabled role.
    expect(\substr_count($html, 'class="uamspeople-single-position"'))->toBe(2);
});

/**
 * A role that carries a `department` appends it after an em dash, mirroring
 * the people entry template. Uses the committed `c-lowry-barnes` person,
 * whose academic/faculty roles reference an academic department (the same
 * fixture other people tests rely on); `terms_search_index` resolves only
 * for a real, indexed entry, not a synthetic one.
 *
 * @command  php artisan test --compact tests/Feature/PeopleShowcaseJobTitlePartialTest.php --filter=test_renders_role_department_after_em_dash
 */
it('renders role department after em dash', function () {
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();

    /** @var EntryQueryBuilder $query */
    $query = $query->where('collection', 'people');

    /** @var EntryQueryBuilder $query */
    $query = $query->where('slug', 'c-lowry-barnes');

    $entry = $query->first();

    $entry = peopleShowcaseJobTitleRequireContentEntry(
        $entry,
        'people: the real c-lowry-barnes entry and its department term'
    );

    /** @var StatamicEntry $entry */
    $html = peopleShowcaseJobTitleRenderItemPartial($entry);

    // At least one role renders its department after the em dash separator.
    expect($html)->toContain('uamspeople-single-position');
    expect($html)->toContain('&mdash;');
    expect($html)->toContain('Department of Orthopaedic Surgery');
});

/**
 * A person with no `job_title` omits the position line entirely.
 *
 * @command  php artisan test --compact tests/Feature/PeopleShowcaseJobTitlePartialTest.php --filter=test_no_job_title_omits_position_line
 */
it('omits the position line when there is no job title', function () {
    $collection = peopleShowcaseJobTitleRequireContentCollection('people');

    $entry = Entry::make();

    Assert::assertInstanceOf(StatamicEntry::class, $entry);

    /** @var StatamicEntry $entry */
    $entry->collection($collection);
    $entry->id('44444444-4444-4444-8444-444444444445');
    $entry->slug('job-title-partial-test-empty');
    $entry->blueprint('person');
    $entry->published(true);
    $entry->locale('default');
    $entry->data(
        [
            'title' => 'No Role Person',
        ]
    );

    $html = peopleShowcaseJobTitleRenderItemPartial($entry);

    expect($html)->not->toContain('uamspeople-single-position');
});
