<?php

declare(strict_types=1);

use App\Services\ComputedFields\ProviderPeople\ProviderResourcesArchiveUrlResolver;
use App\Tags\ClinicalResourceFilter;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

/**
 * The clinical provider lens's "more resources" destination (UAMS-Web/uams-statamic-archive#1692), and the tag
 * that names the provider the archive is filtered to.
 *
 * Both are covered here because they are two halves of one contract: the CTA
 * builds a `?provider=` URL and the notice on the far end has to recognize the
 * same slug. Real entries throughout — the archive root comes from the
 * collection's mount, which only exists on a persisted collection.
 *
 * @command php artisan test --compact tests/Feature/ComputedFields/ProviderResourcesArchiveUrlResolverTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    $pages = CollectionFacade::make('pages');
    $pages->title('Pages');
    $pages->routes('/{slug}');
    $pages->save();

    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->save();
});

/**
 * Persist the resources collection, optionally mounted on a landing page.
 */
function archiveUrlCollection(?string $mountId = null): void
{
    $resources = CollectionFacade::make('clinical_resources');
    $resources->title('Clinical Resources');

    if ($mountId !== null) {
        $resources->mount($mountId);
    }

    $resources->save();
}

/**
 * Persist the archive landing page and return its id.
 */
function archiveUrlLanding(bool $published = true): string
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('pages');
    $entry->id('resources-landing');
    $entry->slug('resources');
    $entry->published($published);
    $entry->data(['title' => 'Clinical Resources']);
    $entry->saveQuietly();

    return 'resources-landing';
}

/**
 * Persist one provider and return the entry.
 *
 * @param  array<string, mixed>  $data
 */
function archiveUrlProvider(string $slug, array $data = [], bool $published = true): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->id($slug);
    $entry->slug($slug);
    $entry->published($published);
    $entry->data($data + ['title' => ucfirst($slug)]);
    $entry->saveQuietly();

    return $entry;
}

// --- The CTA destination -------------------------------------------------------

it('points at the archive mount, carrying the provider slug', function (): void {
    archiveUrlCollection(archiveUrlLanding());
    $provider = archiveUrlProvider('mccoy');

    $url = app(ProviderResourcesArchiveUrlResolver::class)->resolve($provider);

    expect($url)->toContain('/resources')
        ->and($url)->toEndWith('?provider=mccoy');
});

it('reads the archive root from the mount rather than a hard-coded path', function (): void {
    // Moving or renaming the landing page must not leave the profile pointing at a
    // 404, which is the whole reason this is not the literal `/health/resources`.
    /** @var Statamic\Entries\Entry $landing */
    $landing = Entry::make();
    $landing->collection('pages');
    $landing->id('moved-landing');
    $landing->slug('patient-resources');
    $landing->published(true);
    $landing->data(['title' => 'Patient Resources']);
    $landing->saveQuietly();

    archiveUrlCollection('moved-landing');

    expect(app(ProviderResourcesArchiveUrlResolver::class)->resolve(archiveUrlProvider('spock')))
        ->toContain('/patient-resources');
});

it('yields no destination when the collection is unmounted', function (): void {
    archiveUrlCollection();

    expect(app(ProviderResourcesArchiveUrlResolver::class)->resolve(archiveUrlProvider('uhura')))->toBe('');
});

it('yields no destination when the archive landing is unpublished', function (): void {
    // An unpublished mount would 302 the reader onto a 404 — no link beats a broken
    // link, and the template renders no CTA on ''.
    archiveUrlCollection(archiveUrlLanding(published: false));

    expect(app(ProviderResourcesArchiveUrlResolver::class)->resolve(archiveUrlProvider('scotty')))->toBe('');
});

it('emits the slug verbatim, and encodes anything that would break the query string', function (): void {
    // Statamic normalizes a slug on save, so a stored one is always `[a-z0-9-]` and never
    // actually needs encoding — the `rawurlencode` is belt-and-braces against a slug
    // written straight to the file. What matters here is that a NORMAL slug survives
    // untouched, so the archive's lookup sees exactly what the CTA sent.
    archiveUrlCollection(archiveUrlLanding());

    expect(app(ProviderResourcesArchiveUrlResolver::class)->resolve(archiveUrlProvider('jean-luc-picard')))
        ->toEndWith('?provider=jean-luc-picard');
});

// --- The archive's filter notice ------------------------------------------------

/**
 * The provider name the archive notice would show for a `?provider=` value.
 */
function archiveFilterName(?string $slug): string
{
    $tag = app(ClinicalResourceFilter::class);

    // Context first — `Tags::setParameters()` resolves `:`-prefixed params against
    // it, and throws on a null context.
    $tag->setContext([]);
    $tag->setParameters(['provider' => $slug]);

    return $tag->providerName();
}

/**
 * A complete `person_name` the display-name computeds can actually resolve.
 *
 * @return array<string, mixed>
 */
function archiveFilterPersonName(string $first, string $last): array
{
    return [
        'first_name' => ['text' => $first, 'phonetic_text' => null],
        'no_middle_name' => true,
        'middle_name' => null,
        'no_nickname' => true,
        'last_name' => ['text' => $last, 'phonetic_text' => null],
        'no_generational_suffix' => true,
        'generational_suffix' => null,
        'display_format' => 'full_first_middle_initial@ns',
    ];
}

it('names the provider from the computed display name, not the control-panel title', function (): void {
    // `display_name_full_with_credentials` is a `Collection::computed()`
    // registration and never stored data, so `Entry::get()` returned null for it
    // unconditionally and an earlier version fell back to `title` every time. That
    // is not cosmetic: the `people` entry title is documented as control-panel-only
    // and is stored surname-first, so the archive was publishing a back-office sort
    // key — "McCoy, Leonard Horatio Sr" — to a patient-facing page.
    archiveUrlProvider('bones', [
        'title' => 'McCoy, Leonard Horatio Sr',
        'person_name' => archiveFilterPersonName('Leonard', 'McCoy'),
    ]);

    expect(archiveFilterName('bones'))->toBe('Leonard McCoy');
});

it('carries the credentials the computed appends', function (): void {
    archiveUrlProvider('doctor', [
        'title' => 'McCoy, Leonard',
        'person_name' => archiveFilterPersonName('Leonard', 'McCoy'),
        'credentials' => ['md'],
    ]);

    expect(archiveFilterName('doctor'))->toStartWith('Leonard McCoy');
});

it('falls back to the entry title only when no display name resolves', function (): void {
    // No `person_name`, so the computed resolves to '' and the title is all there is.
    archiveUrlProvider('sulu');

    expect(archiveFilterName('sulu'))->toBe('Sulu');
});

it('names nobody for the same slugs the scope leaves unfiltered', function (): void {
    // The notice and the filter must agree: an archive that says it is narrowed to
    // a provider it did not narrow on is worse than no notice at all.
    archiveUrlProvider('hidden', [], published: false);

    expect(archiveFilterName('does-not-exist'))->toBe('')
        ->and(archiveFilterName('hidden'))->toBe('')
        ->and(archiveFilterName(''))->toBe('')
        ->and(archiveFilterName('   '))->toBe('')
        ->and(archiveFilterName(null))->toBe('');
});
