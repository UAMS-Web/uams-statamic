<?php

declare(strict_types=1);

use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

/**
 * The one-shot `content:split-person-names` command (UAMS-Web/uams-statamic-archive#3186): it moves each
 * `people` entry's nickname, pronunciation and display format out of
 * `person_name` and into the fields beside it, leaves an entry already split
 * alone, and saves nothing on a dry run.
 *
 * @command  php artisan test --compact tests/Feature/Console/SplitPersonNamesCommandTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->save();
});

/**
 * Persist a `people` entry with the given data and return its id.
 *
 * @param  string|null  $slug  The slug, or null for an entry with none
 * @param  array<string, mixed>  $data  The entry data
 * @param  string|null  $id  A fixed id, so the order the query returns entries in is known
 */
function splitPersonNamesPerson(?string $slug, array $data, ?string $id = null): string
{
    /** @var EntryInstance $entry */
    $entry = Entry::make();
    $entry->collection('people');

    if ($id !== null) {
        $entry->id($id);
    }

    $entry->slug($slug);
    $entry->data(['title' => ucfirst($slug ?? 'untitled'), ...$data]);
    $entry->saveQuietly();

    return mustBeString($entry->id(), 'the entry id');
}

/**
 * A fresh read of an entry.
 */
function splitPersonNamesFind(string $id): EntryInstance
{
    $entry = Entry::find($id);
    expect($entry)->toBeInstanceOf(EntryInstance::class);
    /** @var EntryInstance $entry */

    return $entry;
}

/**
 * A `person_name` value as stored before UAMS-Web/uams-statamic-archive#3186.
 *
 * @return array<string, mixed>
 */
function splitPersonNamesWholeName(): array
{
    return [
        'first_name' => ['text' => 'Leonard', 'phonetic_text' => 'fu'],
        'no_middle_name' => true,
        'middle_name' => ['text' => '', 'phonetic_text' => ''],
        'no_nickname' => false,
        'nickname' => ['text' => 'Bones', 'phonetic_text' => ''],
        'last_name' => ['text' => 'McCoy', 'phonetic_text' => ''],
        'no_generational_suffix' => true,
        'generational_suffix' => null,
        'display_format' => 'nickname_only@ns',
    ];
}

it('moves the nickname, pronunciation and display format into their own fields', function (): void {
    $id = splitPersonNamesPerson('mccoy', ['person_name' => splitPersonNamesWholeName()]);

    pendingArtisan('content:split-person-names')
        ->expectsOutputToContain('Split 1 entry.')
        ->assertSuccessful();

    $mccoy = splitPersonNamesFind($id);
    /** @var array<string, mixed> $personName */
    $personName = $mccoy->get('person_name');

    expect($mccoy->get('nickname'))->toBe(['text' => 'Bones', 'phonetic_text' => null])
        ->and($mccoy->get('name_pronunciation'))->toBe(['first_name' => 'fu'])
        ->and($mccoy->get('name_display_format'))->toBe('nickname_only@ns')
        ->and($personName['first_name'])->toBe(['text' => 'Leonard', 'phonetic_text' => null])
        ->and($personName['no_nickname'])->toBeTrue()
        ->and($personName['display_format'])->toBeNull();
});

it('saves nothing on a dry run', function (): void {
    $id = splitPersonNamesPerson('mccoy', ['person_name' => splitPersonNamesWholeName()]);

    pendingArtisan('content:split-person-names --dry-run')
        ->expectsOutputToContain('Would split 1 entry.')
        ->assertSuccessful();

    $mccoy = splitPersonNamesFind($id);

    expect($mccoy->has('nickname'))->toBeFalse()
        ->and($mccoy->get('person_name'))->toBe(splitPersonNamesWholeName());
});

it('leaves an entry already split, and one with no name, alone', function (): void {
    $split = splitPersonNamesPerson('mccoy', [
        'person_name' => splitPersonNamesWholeName(),
        'nickname' => ['text' => 'Len', 'phonetic_text' => null],
    ]);
    splitPersonNamesPerson('chapel', []);

    pendingArtisan('content:split-person-names')
        ->expectsOutputToContain('Split 0 entries.')
        ->assertSuccessful();

    expect(splitPersonNamesFind($split)->get('person_name'))->toBe(splitPersonNamesWholeName());
});

it('goes on past an entry with no name and one already split to split the entries after them', function (): void {
    splitPersonNamesPerson('chapel', [], 'split-person-names-a');
    splitPersonNamesPerson('sulu', [
        'person_name' => splitPersonNamesWholeName(),
        'nickname' => ['text' => 'Hikaru', 'phonetic_text' => null],
    ], 'split-person-names-b');
    $kirk = splitPersonNamesPerson('kirk', ['person_name' => splitPersonNamesWholeName()], 'split-person-names-c');

    pendingArtisan('content:split-person-names')
        ->expectsOutput('Split kirk')
        ->expectsOutput('Split 1 entry.')
        ->assertSuccessful();

    expect(splitPersonNamesFind($kirk)->get('name_display_format'))->toBe('nickname_only@ns');
});

it('names every entry a dry run would split', function (): void {
    splitPersonNamesPerson('uhura', ['person_name' => splitPersonNamesWholeName()], 'split-person-names-a');
    splitPersonNamesPerson('scott', ['person_name' => splitPersonNamesWholeName()], 'split-person-names-b');

    pendingArtisan('content:split-person-names --dry-run')
        ->expectsOutput('[dry-run] uhura')
        ->expectsOutput('[dry-run] scott')
        ->expectsOutput('Would split 2 entries.')
        ->doesntExpectOutputToContain('Split ')
        ->assertSuccessful();
});

it('names an entry with no slug by an empty string', function (): void {
    $id = splitPersonNamesPerson(null, ['person_name' => splitPersonNamesWholeName()]);

    pendingArtisan('content:split-person-names')
        ->expectsOutput('Split ')
        ->expectsOutput('Split 1 entry.')
        ->assertSuccessful();

    expect(splitPersonNamesFind($id)->get('name_display_format'))->toBe('nickname_only@ns');
});
