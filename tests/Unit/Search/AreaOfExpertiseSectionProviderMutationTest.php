<?php

declare(strict_types=1);

use App\Search\AreaOfExpertiseSectionDocument;
use App\Search\AreaOfExpertiseSectionProvider;
use App\Services\ClinicalExpertise\ClinicalExpertiseSectionResolver;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\TestCase;

uses(TestCase::class);

covers(AreaOfExpertiseSectionProvider::class);

/**
 * The branches of {@see AreaOfExpertiseSectionProvider} that the content-backed
 * `AreaOfExpertiseSectionSearchTest` cannot reach: a malformed index key, an Area of
 * Expertise with no usable title, URL or locale, and the collection filter on `provide()`.
 *
 * Each Area of Expertise here is a Mockery `Entry`, and the section resolver is a mock bound
 * in the container, so a case can hand the provider exactly the shape it needs.
 *
 * @command  php artisan test --compact tests/Unit/Search/AreaOfExpertiseSectionProviderMutationTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under `--mutate`.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

/**
 * Bind a resolver that reports `locations` as the only populated section, labeled
 * `Related Clinical Locations`, and return it.
 */
function bindAoeSectionResolver(): ClinicalExpertiseSectionResolver
{
    $resolver = Mockery::mock(ClinicalExpertiseSectionResolver::class);
    $resolver->shouldReceive('populatedSections')->andReturn(['locations']);
    $resolver->shouldReceive('resolve')->andReturnUsing(
        static fn (Entry $entry, string $section): ?array => $section === 'locations'
            ? ['section_label' => 'Related Clinical Locations']
            : null,
    );

    app()->instance(ClinicalExpertiseSectionResolver::class, $resolver);

    return $resolver;
}

/**
 * A published, searchable Area of Expertise entry with the given title, URL and locale.
 */
function mockAoeEntry(mixed $title = 'Cancer Care', mixed $url = '/cancer-care', mixed $locale = 'default'): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('status')->andReturn('published');
    $entry->shouldReceive('get')->with('searchable')->andReturn(null);
    $entry->shouldReceive('get')->with('title')->andReturn($title);
    $entry->shouldReceive('id')->andReturn('aoe-1');
    $entry->shouldReceive('url')->andReturn($url);
    $entry->shouldReceive('locale')->andReturn($locale);

    return $entry;
}

/**
 * The document the provider builds for the `locations` section of an entry.
 */
function aoeLocationsDocument(Entry $entry): AreaOfExpertiseSectionDocument
{
    $document = (new AreaOfExpertiseSectionProvider)->documentFor($entry, 'locations');

    expect($document)->toBeInstanceOf(AreaOfExpertiseSectionDocument::class);

    /** @var AreaOfExpertiseSectionDocument $document */
    return $document;
}

it('claims only its own section documents', function (): void {
    $provider = new AreaOfExpertiseSectionProvider;

    expect($provider->contains(new AreaOfExpertiseSectionDocument('aoe-1', 'locations', 'Title', '/url', 'default')))->toBeTrue()
        ->and($provider->contains(Mockery::mock(Entry::class)))->toBeFalse()
        ->and($provider->contains('clinical_expertise_section::aoe-1::locations'))->toBeFalse();
});

it('builds its index membership from the Areas of Expertise collection alone', function (): void {
    bindAoeSectionResolver();

    $query = Mockery::mock(EntryQueryBuilder::class);
    $query->shouldReceive('where')->once()->with('collection', 'clinical_expertise')->andReturnSelf();
    $query->shouldReceive('get')->once()->andReturn(EntryCollection::make([mockAoeEntry()]));
    EntryFacade::shouldReceive('query')->once()->andReturn($query);

    expect((new AreaOfExpertiseSectionProvider)->provide()->all())
        ->toBe(['clinical_expertise_section::aoe-1::locations']);
});

it('rebuilds nothing from a malformed key, without looking up an entry', function (string $key): void {
    bindAoeSectionResolver();
    EntryFacade::shouldReceive('find')->never();

    expect((new AreaOfExpertiseSectionProvider)->find([$key]))->toBeEmpty();
})->with([
    'no separator' => ['aoe-1'],
    'an empty entry id' => ['::locations'],
    'an empty section' => ['aoe-1::'],
]);

it('keeps everything after the first separator as the section', function (): void {
    bindAoeSectionResolver();
    $entry = mockAoeEntry();
    EntryFacade::shouldReceive('find')->with('aoe-1')->andReturn($entry);

    $provider = new AreaOfExpertiseSectionProvider;

    expect($provider->find(['aoe-1::locations']))->toHaveCount(1)
        ->and($provider->find(['aoe-1::locations::extra']))->toBeEmpty();
});

it('titles a section with its trimmed Area of Expertise title, or the label alone', function (mixed $title, string $expected): void {
    bindAoeSectionResolver();

    expect(aoeLocationsDocument(mockAoeEntry(title: $title))->getSearchValue('title'))->toBe($expected);
})->with([
    'a padded title' => ['  Cancer Care  ', "Cancer Care \u{2014} Related Clinical Locations"],
    'a blank title' => ['   ', 'Related Clinical Locations'],
    'an empty title' => ['', 'Related Clinical Locations'],
    'no title' => [null, 'Related Clinical Locations'],
]);

it('appends the section to the Area of Expertise URL once', function (mixed $url, string $expected): void {
    bindAoeSectionResolver();

    expect(aoeLocationsDocument(mockAoeEntry(url: $url))->getSearchValue('url'))->toBe($expected);
})->with([
    'a plain URL' => ['/cancer-care', '/cancer-care/locations'],
    'a URL with a trailing slash' => ['/cancer-care/', '/cancer-care/locations'],
    'no URL' => [null, '/locations'],
]);

it('takes the site from the entry locale, or the default site without one', function (mixed $locale, string $expected): void {
    bindAoeSectionResolver();

    expect(aoeLocationsDocument(mockAoeEntry(locale: $locale))->getQueryableValue('site'))->toBe($expected);
})->with([
    'a locale' => ['french', 'french'],
    'an empty locale' => ['', 'default'],
    'no locale' => [null, 'default'],
]);

it('resolves its section resolver once and reuses it', function (): void {
    $builds = 0;
    app()->bind(ClinicalExpertiseSectionResolver::class, function () use (&$builds): ClinicalExpertiseSectionResolver {
        $builds++;

        $resolver = Mockery::mock(ClinicalExpertiseSectionResolver::class);
        $resolver->shouldReceive('populatedSections')->andReturn(['locations']);
        $resolver->shouldReceive('resolve')->andReturn(['section_label' => 'Related Clinical Locations']);

        return $resolver;
    });

    $provider = new AreaOfExpertiseSectionProvider;
    $entry = mockAoeEntry();
    $provider->sectionReferencesFor($entry);
    $provider->documentFor($entry, 'locations');

    expect($builds)->toBe(1);
});
