<?php

declare(strict_types=1);

use App\Providers\PeopleCollectionServiceProvider;
use App\Services\Locations\LocationInheritedAddressService;
use App\Services\PubMed\PubMedEsummaryService;
use App\Services\Seo\DocumentTitle;
use App\Support\Collections\CollectionExtensions;
use App\Support\Multisite\DepartmentHubPeopleRoster;
use App\Support\References\EntryReferenceRepointer;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Tests\TestCase;
use UAMSWeb\Aether\People\Contracts\DepartmentHubRoster;
use UAMSWeb\Aether\People\Contracts\EntryReferenceRepointer as EntryReferenceRepointerContract;
use UAMSWeb\Aether\People\Contracts\LocationRegions;
use UAMSWeb\Aether\People\Contracts\MergeRedirectWriter;
use UAMSWeb\Aether\People\Contracts\PublicationCitations;
use UAMSWeb\Aether\People\Merge\RedirectsGlobalWriter;

uses(TestCase::class);

covers(PeopleCollectionServiceProvider::class);

/**
 * `PeopleCollectionServiceProvider` binds the contracts `uams-web/aether-people`
 * declares to the site's implementations, and registers the `people` sitemap rule
 * and document title through {@see CollectionExtensions} (UAMS-Web/uams-statamic#872).
 *
 * The entry class and scheduled publishing it also registers are pinned in
 * `CollectionExtensionsTest`.
 *
 * @command  php artisan test --compact tests/Feature/Providers/PeopleCollectionServiceProviderTest.php
 */

/**
 * The `people` rule the provider registered, failing the test when there is none.
 */
function peopleProviderRule(string $kind): Closure
{
    $extensions = app(CollectionExtensions::class);
    $rules = $kind === 'sitemap' ? $extensions->sitemapInclusionRules() : $extensions->documentTitles();

    return $rules['people'] ?? throw new RuntimeException('No '.$kind.' rule is registered for people');
}

/**
 * A fresh document title service, built without the container's binding.
 */
function peopleProviderDocumentTitle(): DocumentTitle
{
    return app()->build(DocumentTitle::class);
}

/**
 * A `people` entry whose computed values come from `$values` and whose own
 * `profile_page_query` is `$profilePage`.
 *
 * @param  array<array-key, mixed>  $values
 */
function peopleProviderEntry(array $values, mixed $profilePage): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('value')->andReturnUsing(static fn (string $key): mixed => $values[$key] ?? null);
    $entry->shouldReceive('get')->with('profile_page_query')->andReturn($profilePage);

    return $entry;
}

it('names each add-on contract and the class bound to it', function (): void {
    expect(PeopleCollectionServiceProvider::CONTRACT_BINDINGS)->toBe([
        EntryReferenceRepointerContract::class => EntryReferenceRepointer::class,
        MergeRedirectWriter::class => RedirectsGlobalWriter::class,
        LocationRegions::class => LocationInheritedAddressService::class,
        PublicationCitations::class => PubMedEsummaryService::class,
        DepartmentHubRoster::class => DepartmentHubPeopleRoster::class,
    ]);
});

it('binds each contract to its implementation, unshared', function (string $contract, string $implementation): void {
    $resolved = app($contract);

    expect(app()->bound($contract))->toBeTrue()
        ->and(app()->isShared($contract))->toBeFalse()
        ->and(\is_object($resolved) ? $resolved::class : null)->toBe($implementation);
})->with([
    'the reference repointer' => [EntryReferenceRepointerContract::class, EntryReferenceRepointer::class],
    'the merge redirect writer' => [MergeRedirectWriter::class, RedirectsGlobalWriter::class],
    'the location regions' => [LocationRegions::class, LocationInheritedAddressService::class],
    'the publication citations' => [PublicationCitations::class, PubMedEsummaryService::class],
    'the department hub roster' => [DepartmentHubRoster::class, DepartmentHubPeopleRoster::class],
]);

it('keeps a person in the sitemap only when the shared gate and the profile page allow it', function (array $values, mixed $profilePage, bool $expected): void {
    expect(peopleProviderRule('sitemap')(peopleProviderEntry($values, $profilePage), null))->toBe($expected);
})->with([
    'a public person with a profile page' => [[], null, true],
    'a person opted out of a profile page' => [[], false, false],
    'a private person' => [['status_and_visibility' => 'private'], null, false],
    'a password-protected person' => [['password_protected' => true], null, false],
    'a person the editor left out' => [['include_in_xml_sitemap' => false], null, false],
    // The profile-page toggle is read from the entry's own data, as PersonEntry::route() reads it
    'an opt-out only in the computed value' => [['profile_page_query' => false], null, true],
]);

it('titles a person through the document title service, built once on first use', function (): void {
    $builds = 0;
    app()->bind(DocumentTitle::class, function () use (&$builds): DocumentTitle {
        $builds++;

        return peopleProviderDocumentTitle();
    });

    $person = EntryFacade::make();
    if (! $person instanceof Entry) {
        throw new RuntimeException('Entry::make() did not make an entry');
    }

    $person->collection('people');
    $person->slug('jim-kirk');
    $person->data(['title' => 'James T. Kirk']);

    $title = peopleProviderRule('title');

    $expected = peopleProviderDocumentTitle()->forPeople($person);

    expect($expected)->toContain('James T. Kirk')
        ->and($builds)->toBe(0)
        ->and($title($person))->toBe($expected)
        ->and($title($person))->toBe($expected)
        ->and($builds)->toBe(1);
});
