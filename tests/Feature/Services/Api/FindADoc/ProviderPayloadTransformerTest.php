<?php

declare(strict_types=1);

use App\Services\Api\FindADoc\ProviderPayloadTransformer;
use App\Services\ComputedFields\ProviderPeople\ClinicalTitleResolver;
use App\Services\ComputedFields\ProviderPeople\ProviderPeoplePersonNameComputedValues;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Pins the provider payload `ProviderPayloadTransformer` builds for the Find-a-Doc read
 * API (UAMS-Web/uams-statamic-archive#1043), field by field, including how each stored
 * value is narrowed.
 *
 * `ProviderApiTest` covers the endpoints and the allow-list end to end; this file drives
 * the transformer directly so every narrowing rule is asserted on its own: empty and
 * non-string values become null, a stored list keeps only its non-empty strings, a
 * specialization id outside `clinical_specializations` is dropped, and a stored
 * `true_false` value becomes a strict bool. The name computeds are stubbed, so a name
 * pair the real service never produces (a non-string `text`) can be fed in; the
 * clinical title runs through the real resolver.
 *
 * @command php artisan test --compact tests/Feature/Services/Api/FindADoc/ProviderPayloadTransformerTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    $this->flushIsolatedCache();

    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->routes('/people/{slug}');
    $people->save();

    $specializations = CollectionFacade::make('clinical_specializations');
    $specializations->title('Clinical Specializations');
    $specializations->sites(['default']);
    $specializations->save();

    $other = CollectionFacade::make('payload_transformer_other');
    $other->title('Other');
    $other->save();
});

/**
 * Persist an entry in the given collection and return its id.
 *
 * @param  array<string, mixed>  $data
 */
function payloadTransformerEntry(string $collection, string $slug, array $data = []): string
{
    /** @var EntryInstance $entry */
    $entry = Entry::make();
    $entry->collection($collection);
    $entry->locale('default');
    $entry->slug($slug);
    $entry->published(true);
    $entry->data(['title' => ucfirst($slug), ...$data]);
    $entry->saveQuietly();

    return mustBeString($entry->id(), "the {$slug} id");
}

/**
 * A saved `people` entry carrying the given fields.
 *
 * @param  array<string, mixed>  $fields
 */
function payloadTransformerPerson(string $slug, array $fields = []): EntryInstance
{
    /** @var EntryInstance $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->slug($slug);
    $entry->published(true);
    $entry->data(['title' => '', ...$fields]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * The transformer with each name computed returning the given `text`.
 *
 * @param  array{full?: mixed, with_credentials?: mixed, short?: mixed, prefix?: mixed}  $texts
 */
function payloadTransformerTransformer(array $texts = []): ProviderPayloadTransformer
{
    $names = Mockery::mock(ProviderPeoplePersonNameComputedValues::class);
    $names->allows('getDisplayNameFull')->andReturn(['text' => $texts['full'] ?? 'Leonard McCoy', 'phonetic_text' => '']);
    $names->allows('getDisplayNameFullWithCredentials')->andReturn(['text' => $texts['with_credentials'] ?? 'Leonard McCoy, MD', 'phonetic_text' => '']);
    $names->allows('getDisplayNameShortWithPrefix')->andReturn(['text' => $texts['short'] ?? 'Dr. McCoy', 'phonetic_text' => '']);
    $names->allows('getPrefixValue')->andReturn(['text' => $texts['prefix'] ?? 'Dr.', 'phonetic_text' => '']);

    return new ProviderPayloadTransformer($names, new ClinicalTitleResolver);
}

it('builds the full allow-listed payload from a filled-in person', function (): void {
    $cardiology = payloadTransformerEntry('clinical_specializations', 'cardiology', ['title_specialization_patient' => 'Heart Doctor']);
    $surgery = payloadTransformerEntry('clinical_specializations', 'thoracic-surgery');

    $person = payloadTransformerPerson('leonard-mccoy', [
        'credentials' => ['md', 'phd'],
        'clinical_specializations' => [$cardiology, $surgery],
        'service_line' => ['heart', 'lungs'],
        'pronouns' => 'he-him',
        'accepting_patients_query' => true,
        'referral_required' => true,
        'resident_query' => true,
        'portrait' => 'portraits/mccoy-3x4.jpg',
        'portrait_wide' => 'portraits/mccoy-16x9.jpg',
        'npi' => '1234567890',
    ]);

    expect(payloadTransformerTransformer()->toArray($person))->toBe([
        'id' => $person->id(),
        'slug' => 'leonard-mccoy',
        'url' => $person->absoluteUrl(),
        'name' => [
            'full' => 'Leonard McCoy',
            'full_with_credentials' => 'Leonard McCoy, MD',
            'short_with_prefix' => 'Dr. McCoy',
            'prefix' => 'Dr.',
        ],
        'credentials' => ['md', 'phd'],
        'clinical_title' => 'Heart Doctor',
        'specialties' => ['cardiology', 'thoracic-surgery'],
        'service_line' => ['heart', 'lungs'],
        'pronouns' => 'he-him',
        'appointments' => [
            'accepting_new_patients' => true,
            'referral_required' => true,
        ],
        'is_resident' => true,
        'portrait' => [
            'headshot' => 'portraits/mccoy-3x4.jpg',
            'wide' => 'portraits/mccoy-16x9.jpg',
        ],
    ])
        ->and($person->absoluteUrl())->toEndWith('/people/leonard-mccoy');
});

it('emits null or empty values for a person with nothing filled in', function (): void {
    $person = payloadTransformerPerson('blank');

    expect(payloadTransformerTransformer(['full' => '', 'with_credentials' => '', 'short' => '', 'prefix' => ''])->toArray($person))->toBe([
        'id' => $person->id(),
        'slug' => 'blank',
        'url' => $person->absoluteUrl(),
        'name' => [
            'full' => null,
            'full_with_credentials' => null,
            'short_with_prefix' => null,
            'prefix' => null,
        ],
        'credentials' => [],
        'clinical_title' => null,
        'specialties' => [],
        'service_line' => [],
        'pronouns' => null,
        'appointments' => [
            'accepting_new_patients' => false,
            'referral_required' => false,
        ],
        'is_resident' => false,
        'portrait' => [
            'headshot' => null,
            'wide' => null,
        ],
    ]);
});

it('emits null for a name whose text is not a string', function (): void {
    $payload = payloadTransformerTransformer(['full' => 42, 'with_credentials' => ['x'], 'short' => true, 'prefix' => 1.5])
        ->toArray(payloadTransformerPerson('odd-names'));

    expect($payload['name'])->toBe([
        'full' => null,
        'full_with_credentials' => null,
        'short_with_prefix' => null,
        'prefix' => null,
    ]);
});

it('emits null for a non-string or empty scalar field', function (mixed $stored): void {
    $payload = payloadTransformerTransformer()->toArray(payloadTransformerPerson('scalar', [
        'pronouns' => $stored,
        'portrait' => $stored,
        'portrait_wide' => $stored,
    ]));

    expect($payload['pronouns'])->toBeNull()
        ->and($payload['portrait'])->toBe(['headshot' => null, 'wide' => null]);
})->with([
    'an empty string' => [''],
    'a list' => [['he-him']],
    'an integer' => [7],
]);

it('narrows a stored list to its non-empty strings, in order', function (mixed $stored, array $expected): void {
    $payload = payloadTransformerTransformer()->toArray(payloadTransformerPerson('lists', [
        'credentials' => $stored,
        'service_line' => $stored,
    ]));

    expect($payload['credentials'])->toBe($expected)
        ->and($payload['service_line'])->toBe($expected);
})->with([
    'a single string' => ['md', ['md']],
    'an empty string' => ['', []],
    'a mixed list' => [['md', '', 7, null, ['x'], 'do'], ['md', 'do']],
    'an integer' => [7, []],
    'a boolean' => [true, []],
]);

it('emits the slug of each stored specialization in the collection, and drops every other id', function (): void {
    $cardiology = payloadTransformerEntry('clinical_specializations', 'cardiology');
    $elsewhere = payloadTransformerEntry('payload_transformer_other', 'oncology');
    $surgery = payloadTransformerEntry('clinical_specializations', 'thoracic-surgery');

    $payload = payloadTransformerTransformer()->toArray(payloadTransformerPerson('specialist', [
        'clinical_specializations' => [$surgery, 'no-such-id', $elsewhere, '', 7, $cardiology],
    ]));

    expect($payload['specialties'])->toBe(['thoracic-surgery', 'cardiology']);
});

it('drops a specialization whose slug is null or empty', function (?string $stored, ?string $read): void {
    // Statamic's slug getter returns null for a falsy slug and `Str::slug()` of anything
    // else, which is '' for a slug made only of punctuation. A saved entry does not keep
    // such a slug, so the lookup is pointed at an unsaved one.
    /** @var EntryInstance $noSlug */
    $noSlug = Entry::make();
    $noSlug->collection('clinical_specializations');
    $noSlug->locale('default');
    $noSlug->id('payload-transformer-no-slug');
    $noSlug->slug($stored);
    $noSlug->data(['title' => 'No Slug']);

    $cardiologyId = payloadTransformerEntry('clinical_specializations', 'cardiology');
    $cardiology = mustBe(EntryInstance::class, Entry::find($cardiologyId), 'the cardiology entry');
    $person = payloadTransformerPerson('no-slug-specialty', [
        'clinical_specializations' => ['payload-transformer-no-slug', $cardiologyId],
    ]);

    Entry::shouldReceive('find')->andReturnUsing(fn (string $id): ?EntryInstance => match ($id) {
        'payload-transformer-no-slug' => $noSlug,
        $cardiologyId => $cardiology,
        default => null,
    });

    expect($noSlug->slug())->toBe($read)
        ->and(payloadTransformerTransformer()->toArray($person)['specialties'])->toBe(['cardiology']);
})->with([
    'no slug' => [null, null],
    'a punctuation-only slug' => ['!!!', ''],
]);

it('reads a single stored specialization id as a one-item list', function (): void {
    $cardiology = payloadTransformerEntry('clinical_specializations', 'cardiology');

    $payload = payloadTransformerTransformer()->toArray(payloadTransformerPerson('one-specialty', [
        'clinical_specializations' => $cardiology,
    ]));

    expect($payload['specialties'])->toBe(['cardiology']);
});

it('reads the stored true_false forms as strict booleans', function (mixed $stored, bool $expected): void {
    $payload = payloadTransformerTransformer()->toArray(payloadTransformerPerson('flags', [
        'accepting_patients_query' => $stored,
        'referral_required' => $stored,
        'resident_query' => $stored,
    ]));

    expect($payload['appointments'])->toBe(['accepting_new_patients' => $expected, 'referral_required' => $expected])
        ->and($payload['is_resident'])->toBe($expected);
})->with([
    'true' => [true, true],
    'the string "1"' => ['1', true],
    'the string "true"' => ['true', true],
    'false' => [false, false],
    'the string "0"' => ['0', false],
    'the string "false"' => ['false', false],
    'null' => [null, false],
]);
