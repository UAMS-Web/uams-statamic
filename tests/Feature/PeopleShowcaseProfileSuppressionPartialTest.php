<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;
use Statamic\Contracts\Entries\Collection as CollectionContract;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Renders the People Showcase item partial to lock the UAMS-Web/uams-statamic-archive#43 heading-link guard:
 * a person flagged "no profile page" (`profile_page_query === false`) renders
 * an UNLINKED heading even when a `url` is present, while a person with a
 * profile page is linked. The strict `!== false` guard in the partial mirrors
 * `App\Entries\PersonEntry::route()`, so suppression is consistent end to end.
 *
 * Template-only (Antlers partial); content-gated like the sibling
 * `PeopleShowcaseJobTitlePartialTest` — the partial and the `people` collection
 * live in the `content/` submodule, so these self-skip without it and run in CI.
 *
 * @command  php artisan test --compact tests/Feature/PeopleShowcaseProfileSuppressionPartialTest.php
 */

/**
 * Resolve the `people` collection, skipping when the submodule is absent.
 */
function profileSuppressionRequirePeopleCollection(): CollectionContract
{
    $collection = Collection::find('people');

    if (! $collection) {
        ContentSubmodule::reportMissing('people collection');
    }

    return $collection;
}

/**
 * Render the showcase item partial for a person with the given
 * `profile_page_query` value and an injected own-profile `url`.
 *
 * @param  bool  $profilePageQuery  The person's "create a profile page?" flag
 * @return string The rendered heading markup
 */
function profileSuppressionRenderItem(bool $profilePageQuery): string
{
    $collection = profileSuppressionRequirePeopleCollection();

    $entry = Entry::make();
    Assert::assertInstanceOf(StatamicEntry::class, $entry);

    /** @var StatamicEntry $entry */
    $entry->collection($collection);
    $entry->blueprint('person');
    $entry->locale('default');
    $entry->data([
        'title' => 'Jane Doe',
        'profile_page_query' => $profilePageQuery,
    ]);

    /** @var array<string, mixed> $context */
    $context = $entry->toAugmentedArray();

    // The block normally supplies these; the default switch arm links to the
    // person's own `url` when not suppressed.
    $context['url'] = '/people/jane-doe';
    $context['display_detail'] = [['key' => 'name']];
    $context['block'] = [
        'entry_link_query' => true,
        'entry_link_url' => 'entry',
    ];

    return (string) Antlers::parse(
        '{{ partial:page_builder/uamsweb_block_people_item }}',
        $context,
        true
    );
}

it('does not link the heading when profile_page_query is false', function () {
    $html = profileSuppressionRenderItem(false);

    // The name renders as the heading text — `widont` joins the last two words
    // with a non-breaking space — and is NOT wrapped in a profile-page link.
    expect($html)->toContain('<h3 class="uamspeople-box-title">Jane&nbsp;Doe</h3>');
    expect($html)->not->toContain('href="/people/jane-doe"');
});

it('links the heading to the profile page when profile_page_query is true', function () {
    $html = profileSuppressionRenderItem(true);

    // The same name renders linked to the person's own profile page.
    expect($html)->toContain('<h3 class="uamspeople-box-title"><a href="/people/jane-doe">Jane&nbsp;Doe</a></h3>');
    expect($html)->toContain('href="/people/jane-doe"');
});
