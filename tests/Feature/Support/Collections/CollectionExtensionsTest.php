<?php

declare(strict_types=1);

use App\Entries\KnowledgeBaseEntry;
use App\Entries\PersonEntry;
use App\Jobs\ApplyScheduledPublishing;
use App\Services\ComputedFields\Registration\DocumentTitleComputedsRegistrar;
use App\Services\ComputedFields\Registration\SitemapInclusionComputedsRegistrar;
use App\Support\Collections\CollectionExtensions;
use App\Support\Collections\PeopleCollection;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests the registration point through which a package adds collection behavior to site core (UAMS-Web/uams-statamic-archive#3180).
 *
 * The `people` collection registers its entry class, sitemap inclusion rule, document title and
 * scheduled publishing through it, and each consumer reads whatever is registered rather than a
 * named collection. A collection registered here as `widgets` stands in for one a package adds.
 *
 * @covers \App\Support\Collections\CollectionExtensions
 * @covers \App\Support\Collections\PeopleCollection
 * @covers \App\Providers\PeopleCollectionServiceProvider
 *
 * @command  php artisan test --compact tests/Feature/Support/Collections/CollectionExtensionsTest.php
 */
it('reads the people collection handle from config', function (): void {
    expect(PeopleCollection::handle())->toBe('people');

    config()->set('people.collection', 'staff');

    expect(PeopleCollection::handle())->toBe('staff');
});

it('resolves one registry for the whole application', function (): void {
    expect(app(CollectionExtensions::class))->toBe(app(CollectionExtensions::class));
});

it('registers the people behavior through the extension point', function (): void {
    $extensions = app(CollectionExtensions::class);

    expect($extensions->entryClassFor('people'))->toBe(PersonEntry::class)
        ->and($extensions->entryClasses())->toBe([PersonEntry::class])
        ->and(array_keys($extensions->sitemapInclusionRules()))->toBe(['people'])
        ->and(array_keys($extensions->documentTitles()))->toBe(['people'])
        ->and($extensions->scheduledPublishingCollections())->toBe(['people']);
});

it('lets the cache unserialize a registered entry class', function (): void {
    expect(config('cache.serializable_classes'))->toContain(PersonEntry::class);
})->skip(
    fn (): bool => ! \is_array(config('cache.serializable_classes')),
    'cache.serializable_classes allows every class here, so there is no list to extend',
);

it('builds a registered collection\'s entries as its class, and any other collection\'s as the stock entry', function (): void {
    app(CollectionExtensions::class)->entryClass('widgets', PersonEntry::class);

    expect(app(EntryContract::class, ['collectionHandle' => 'widgets']))->toBeInstanceOf(PersonEntry::class)
        ->and(app(EntryContract::class, ['collectionHandle' => 'people']))->toBeInstanceOf(PersonEntry::class)
        ->and(app(EntryContract::class, ['collectionHandle' => 'knowledge_bases']))->toBeInstanceOf(KnowledgeBaseEntry::class)
        ->and(app(EntryContract::class, ['collectionHandle' => 'pages'])::class)->toBe(Entry::class)
        ->and(app(EntryContract::class)::class)->toBe(Entry::class);
});

it('registers a registered sitemap rule and document title on that collection', function (): void {
    $extensions = app(CollectionExtensions::class);
    $rule = fn (Entry $entry, mixed $_stored): bool => false;
    $title = fn (Entry $entry): string => 'Widget';
    $extensions->sitemapInclusionRule('widgets', $rule);
    $extensions->documentTitle('widgets', $title);

    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (string $collection, string $handle, Closure $callback) use (&$captured): void {
            $captured[$collection.'.'.$handle] = $callback;
        }
    );

    app(SitemapInclusionComputedsRegistrar::class)->register();
    app(DocumentTitleComputedsRegistrar::class)->register();

    expect($captured['widgets.'.SitemapInclusionComputedsRegistrar::INCLUDE_HANDLE])->toBe($rule)
        ->and($captured['widgets.document_title'])->toBe($title)
        ->and($captured)->toHaveKeys([
            'people.'.SitemapInclusionComputedsRegistrar::INCLUDE_HANDLE,
            'people.document_title',
            'clinical_locations.document_title',
        ]);
});

it('schedules the registered collections ahead of the site\'s own, once each', function (): void {
    $extensions = app(CollectionExtensions::class);
    $extensions->scheduledPublishing('widgets');
    $extensions->scheduledPublishing('widgets');
    $extensions->scheduledPublishing('pages');

    expect(ApplyScheduledPublishing::collections())->toBe(['people', 'widgets', 'pages']);
});
