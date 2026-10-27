<?php

declare(strict_types=1);

use App\Listeners\DeriveSpotlightTitleAndSlug;
use App\Services\ComputedFields\ProviderSpotlight\ProviderSpotlightComputedValues;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\TestCase;

/**
 * The Provider Spotlight title/slug derivation listener (UAMS-Web/uams-statamic-archive#1629): on save, a
 * spotlight's title is set to "Get to Know {provider name}" (tracking the
 * provider on every save), and its slug is derived from the prefix/degree-free
 * provider name while the entry is a draft, then frozen once published so a
 * live URL is never rewritten. The other clinical-resource types are untouched,
 * and a WordPress console import preserves the imported title/slug.
 *
 * @command php artisan test --compact tests/Feature/DeriveSpotlightTitleAndSlugTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * A `people` provider fixture whose `person_name` resolves to a real display
 * name ("Dr. Leonard H. McCoy"), so the derived title/slug are deterministic.
 *
 * @param  array<string, mixed>  $overrides
 */
function spotlightProvider(string $id, array $overrides = []): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->locale('default');
    $entry->id($id);
    $entry->slug($id);
    $entry->data([
        'credentials' => ['md'],
        'person_name' => [
            'first_name' => ['text' => 'Leonard', 'phonetic_text' => null],
            'no_middle_name' => false,
            'middle_name' => ['text' => 'Horatio', 'phonetic_text' => null],
            'no_nickname' => true,
            'last_name' => ['text' => 'McCoy', 'phonetic_text' => null],
            'no_generational_suffix' => true,
            'generational_suffix' => null,
            'display_format' => 'full_first_middle_initial@ns',
        ],
    ] + $overrides);

    return $entry;
}

/**
 * Build an in-memory spotlight, run the derivation listener over it, and return it.
 *
 * @param  array<string, mixed>  $data
 */
function deriveSpotlight(string $blueprint, array $data, bool $published = false): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('clinical_resources');
    $entry->blueprint($blueprint);
    $entry->locale('default');
    $entry->id('spotlight-derive');
    $entry->published($published);

    if (\array_key_exists('slug', $data)) {
        $entry->slug($data['slug']);
        unset($data['slug']);
    }

    $entry->data($data);

    (new DeriveSpotlightTitleAndSlug(app(ProviderSpotlightComputedValues::class)))
        ->handle(new EntrySaving($entry));

    return $entry;
}

it('sets the title to "Get to Know {provider name}" from the featured provider', function (): void {
    Entry::partialMock()->shouldReceive('find')->with('prov-mccoy')->andReturn(spotlightProvider('prov-mccoy'));

    $entry = deriveSpotlight('provider_spotlight', [
        'featured_provider' => ['prov-mccoy'],
        'title' => 'A stale editor title',
    ]);

    expect($entry->get('title'))->toBe('Get to Know Dr. Leonard H. McCoy');
});

it('derives the slug from the prefix/degree-free provider name while a draft', function (): void {
    Entry::partialMock()->shouldReceive('find')->with('prov-mccoy')->andReturn(spotlightProvider('prov-mccoy'));

    $entry = deriveSpotlight('provider_spotlight', [
        'featured_provider' => ['prov-mccoy'],
        'slug' => 'old-slug',
    ], published: false);

    expect($entry->slug())->toBe('get-to-know-leonard-h-mccoy');
});

it('freezes a published entry\'s slug so a live URL is never rewritten', function (): void {
    // A published spotlight whose provider name would derive a different slug:
    // the live slug is kept, while the title still tracks the provider.
    Entry::partialMock()->shouldReceive('find')->with('prov-mccoy')->andReturn(spotlightProvider('prov-mccoy'));

    $entry = deriveSpotlight('provider_spotlight', [
        'featured_provider' => ['prov-mccoy'],
        'slug' => 'get-to-know-someone-else',
    ], published: true);

    expect($entry->slug())->toBe('get-to-know-someone-else');
    expect($entry->get('title'))->toBe('Get to Know Dr. Leonard H. McCoy');
});

it('fills a published entry that has no slug yet, so a spotlight always has a slug', function (): void {
    Entry::partialMock()->shouldReceive('find')->with('prov-mccoy')->andReturn(spotlightProvider('prov-mccoy'));

    $entry = deriveSpotlight('provider_spotlight', [
        'featured_provider' => ['prov-mccoy'],
        'slug' => '',
    ], published: true);

    expect($entry->slug())->toBe('get-to-know-leonard-h-mccoy');
});

it('leaves the title and slug untouched when there is no featured provider', function (): void {
    $entry = deriveSpotlight('provider_spotlight', [
        'title' => 'Editor title',
        'slug' => 'editor-slug',
    ]);

    expect($entry->get('title'))->toBe('Editor title');
    expect($entry->slug())->toBe('editor-slug');
});

it('leaves a pre-set title and slug untouched when the provider has no resolvable name', function (): void {
    // A provider that resolves but whose (absent) person_name yields no display
    // name: the derivation returns null and the stored title/slug survive.
    /** @var Statamic\Entries\Entry $nameless */
    $nameless = Entry::make();
    $nameless->collection('people');
    $nameless->locale('default');
    $nameless->id('prov-nameless');
    $nameless->slug('prov-nameless');
    $nameless->data([]);
    Entry::partialMock()->shouldReceive('find')->with('prov-nameless')->andReturn($nameless);

    $entry = deriveSpotlight('provider_spotlight', [
        'featured_provider' => ['prov-nameless'],
        'title' => 'Kept title',
        'slug' => 'kept-slug',
    ]);

    expect($entry->get('title'))->toBe('Kept title');
    expect($entry->slug())->toBe('kept-slug');
});

it('disambiguates the derived slug when another entry already uses it', function (): void {
    // Another clinical-resources entry already owns the derived slug (a second
    // spotlight for the same provider, or two providers sharing a display name).
    /** @var Statamic\Entries\Entry $existing */
    $existing = Entry::make();
    $existing->collection('clinical_resources');
    $existing->locale('default');
    $existing->id('other-spotlight');
    $existing->slug('get-to-know-leonard-h-mccoy');

    /** @var EntryQueryBuilder&MockInterface $builder */
    $builder = Mockery::mock(EntryQueryBuilder::class);
    $builder->shouldReceive('where')->andReturnSelf();
    $builder->shouldReceive('get')->andReturn(collect([$existing]));

    $mock = Entry::partialMock();
    $mock->shouldReceive('find')->with('prov-mccoy')->andReturn(spotlightProvider('prov-mccoy'));
    $mock->shouldReceive('query')->andReturn($builder);

    $entry = deriveSpotlight('provider_spotlight', [
        'featured_provider' => ['prov-mccoy'],
        'slug' => '',
    ], published: false);

    expect($entry->slug())->toBe('get-to-know-leonard-h-mccoy-2');
});

it('leaves the other clinical-resource types untouched', function (): void {
    Entry::partialMock()->shouldReceive('find')->with('prov-mccoy')->andReturn(spotlightProvider('prov-mccoy'));

    $entry = deriveSpotlight('article', [
        'featured_provider' => ['prov-mccoy'],
        'title' => 'An article title',
        'slug' => 'an-article',
    ]);

    expect($entry->get('title'))->toBe('An article title');
    expect($entry->slug())->toBe('an-article');
});

it('preserves an imported spotlight\'s WordPress title/slug during a console import', function (): void {
    Entry::partialMock()->shouldReceive('find')->with('prov-mccoy')->andReturn(spotlightProvider('prov-mccoy'));

    // Simulate the `php artisan import:wordpress` console context the bypass detects.
    $originalArgv = $_SERVER['argv'] ?? null;
    $_SERVER['argv'] = ['artisan', 'import:wordpress', 'some-file.xml'];

    try {
        $entry = deriveSpotlight('provider_spotlight', [
            'featured_provider' => ['prov-mccoy'],
            'title' => 'Imported WordPress Title',
            'slug' => 'imported-wordpress-slug',
        ]);
    } finally {
        if ($originalArgv === null) {
            unset($_SERVER['argv']);
        } else {
            $_SERVER['argv'] = $originalArgv;
        }
    }

    expect($entry->get('title'))->toBe('Imported WordPress Title');
    expect($entry->slug())->toBe('imported-wordpress-slug');
});
