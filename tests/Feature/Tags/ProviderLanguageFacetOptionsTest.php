<?php

declare(strict_types=1);

use Statamic\Entries\Collection;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Coverage for the `{{ provider_language_facet_options }}` tag (UAMS-Web/uams-statamic-archive#570): the distinct
 * BCP-47 languages spoken across the `people` collection, resolved to display names
 * and sorted, feeding the provider directory's Language facet `<select>`.
 *
 * Real `people` entries are built in an isolated Stache so the tag's collection query
 * runs against them, exactly as at the live directory.
 *
 * @command php artisan test --compact tests/Feature/Tags/ProviderLanguageFacetOptionsTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    /** @var Collection $collection */
    $collection = CollectionFacade::make('people');
    $collection->title('People');
    $collection->save();
});

/**
 * Persist a `people` entry with the given data (title plus optional `language` values).
 *
 * @param  array<string, mixed>  $data
 */
function makeLanguageFacetPerson(string $slug, array $data): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection('people');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($slug);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data($data);
    $entry->saveQuietly();
}

/**
 * Render the tag as `[code=label]` rows so a test can assert order and content. The
 * `{{ if value }}` guard mirrors how the directory filter partial consumes the tag —
 * a Statamic tag pair renders its body once over an empty array, so the guard keeps a
 * zero-language directory from emitting a stray empty `<option>`.
 */
function renderLanguageOptions(): string
{
    return (string) Antlers::parse(
        '{{ provider_language_facet_options }}{{ if value }}[{{ value }}={{ label }}]{{ /if }}{{ /provider_language_facet_options }}',
        [],
        true,
    );
}

it('returns the distinct provider languages resolved to display names, sorted by label', function (): void {
    makeLanguageFacetPerson('a', ['title' => 'A', 'language' => ['es', 'en']]);
    makeLanguageFacetPerson('b', ['title' => 'B', 'language' => ['en']]);
    makeLanguageFacetPerson('c', ['title' => 'C', 'language' => ['fr']]);

    // English, French, then "Spanish, Castilian" — de-duplicated and label-sorted.
    expect(renderLanguageOptions())->toBe('[en=English][fr=French][es=Spanish, Castilian]');
});

it('falls back to the raw code when a code is not a known BCP-47 subtag', function (): void {
    makeLanguageFacetPerson('a', ['title' => 'A', 'language' => ['en', 'zz-not-a-code']]);

    // The unknown code survives as its own value/label rather than being dropped.
    expect(renderLanguageOptions())->toContain('[en=English]')
        ->and(renderLanguageOptions())->toContain('[zz-not-a-code=zz-not-a-code]');
});

it('emits nothing when no provider records a language', function (): void {
    makeLanguageFacetPerson('a', ['title' => 'A']);

    expect(renderLanguageOptions())->toBe('');
});

it('trims whitespace-padded codes and drops non-string language values', function (): void {
    // A hand-edited value may carry padding or a non-string element; the tag trims the
    // former (so it resolves to a real subtag) and drops the latter (never a stray option).
    makeLanguageFacetPerson('a', [
        'title' => 'A',
        'language' => ['  fr  ', 'en', 12345, null],
    ]);

    // 'fr'/'en' survive (trimmed, label-sorted); the int and null are dropped entirely.
    expect(renderLanguageOptions())->toBe('[en=English][fr=French]');
});
