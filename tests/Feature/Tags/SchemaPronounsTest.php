<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for the `schema_pronouns` Antlers tag (App\Tags\SchemaPronouns).
 *
 * Mutation: 100% on App\Tags\SchemaPronouns (75 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Tags/SchemaPronounsTest.php
 */
it('emits property value array for all slots', function () {
    // Parse the tag with every pronoun slot populated.
    $output = (string) Antlers::parse(
        '{{ schema_pronouns name="he/him" description="English third-person singular masculine pronouns" nominative="he" accusative="him" dependent_possessive="his" independent_possessive="his" reflexive="himself" }}',
        [],
        true
    );

    // Decode JSON from the tag output.
    $decoded = json_decode($output, true);

    // Assert top-level JSON is a list: display name plus five PropertyValues.
    expect($decoded)->toBeArray();
    expect($decoded)->not->toHaveKey('@type');
    expect($decoded)->toHaveCount(6);
    expect(data_get($decoded, '0'))->toBe('he/him');

    // Assert first PropertyValue (nominative).
    expect(data_get($decoded, '1.@type'))->toBe('PropertyValue');
    expect(data_get($decoded, '1.name'))->toBe('nominative');
    expect(data_get($decoded, '1.value'))->toBe('he');
    expect(data_get($decoded, '1.propertyID'))->toBe('https://www.wikidata.org/wiki/Q131105');
    expect(data_get($decoded, '1.description'))->toBe('Nominative case (subject form)');

    // Assert accusative description.
    expect(data_get($decoded, '2.description'))->toBe('Accusative case (object form)');

    // Assert dependent possessive has base description only.
    expect(data_get($decoded, '3.description'))->toBe('Dependent possessive / possessive determiner');

    // Assert reflexive slot (last entry).
    expect(data_get($decoded, '5.@type'))->toBe('PropertyValue');
    expect(data_get($decoded, '5.value'))->toBe('himself');
    expect(data_get($decoded, '5.name'))->toBe('reflexive');
    expect(data_get($decoded, '5.propertyID'))->toBe('https://www.wikidata.org/wiki/Q953129');
});

it('returns plain name when only name is set', function () {
    // Parse the tag with only name populated.
    $output = (string) Antlers::parse(
        '{{ schema_pronouns name="he/him" }}',
        [],
        true
    );

    // Assert output is the name string, not JSON.
    expect($output)->toBe('he/him');
    expect(json_decode($output, true))->toBeNull();
});

it('outputs empty string when no pronoun slots are set', function () {
    // Parse the tag with name and description only.
    $output = (string) Antlers::parse(
        '{{ schema_pronouns name="he/him" description="English masculine" }}',
        [],
        true
    );

    // Assert no JSON array is emitted when every slot is empty.
    expect($output)->toBe('');
});

it('includes only non-empty pronoun slots', function () {
    // Parse the tag with a single pronoun slot.
    $output = (string) Antlers::parse(
        '{{ schema_pronouns name="they/them" nominative="they" }}',
        [],
        true
    );

    // Decode JSON from the tag output.
    $decoded = json_decode($output, true);

    // Assert display name then one PropertyValue (nominative).
    expect($decoded)->toHaveCount(2);
    expect(data_get($decoded, '0'))->toBe('they/them');
    expect(data_get($decoded, '1.@type'))->toBe('PropertyValue');
    expect(data_get($decoded, '1.value'))->toBe('they');
    expect(data_get($decoded, '1.name'))->toBe('nominative');
    expect(data_get($decoded, '1.description'))->toBe('Nominative case (subject form)');
});

it('outputs empty string when name is empty', function () {
    // Parse the tag with an empty name.
    $output = (string) Antlers::parse(
        '{{ schema_pronouns name="" description="x" nominative="a" }}',
        [],
        true
    );

    // Assert the template produced nothing.
    expect($output)->toBe('');
});

it('passes surface forms through without extra encoding', function () {
    // Parse the tag with markup in the nominative slot only.
    $output = (string) Antlers::parse(
        '{{ schema_pronouns name="x" nominative="<em>they</em>" }}',
        [],
        true
    );

    // Decode JSON from the tag output.
    $decoded = json_decode($output, true);

    // Assert leading name then PropertyValue value keeps literal markup.
    expect(data_get($decoded, '0'))->toBe('x');
    expect(data_get($decoded, '1.value'))->toBe('<em>they</em>');
});
