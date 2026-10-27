<?php

declare(strict_types=1);

use App\Tags\Schema\SchemaPropertyValidator;

/**
 * Behavior of SchemaPropertyValidator (app/Tags/Schema/SchemaPropertyValidator.php):
 * resolve the valid Schema.org properties for a type (from the SchemaType enum
 * vocabulary, falling back to reflecting the Spatie schema-org class when the enum has
 * none; empty for unknown types), drop superseded properties, and report a node's
 * invalid property keys.
 *
 * Mutation: 100% on App\Tags\Schema\SchemaPropertyValidator (--covered-only), with five
 * equivalents annotated in the class. One guard is not covered:
 * `$info === null` in the superseded filter, because every SchemaProperty case carries
 * info, so no real property reaches it.
 *
 * @command  php artisan test --compact tests/Unit/Tags/Schema/SchemaPropertyValidatorTest.php
 */
beforeEach(function () {
    // The per-type cache is static; clear it so each case computes from scratch.
    (new ReflectionProperty(SchemaPropertyValidator::class, 'cache'))->setValue(null, []);
});

afterEach(function () {
    // And clear it after, so a seeded entry cannot reach a later file in the process.
    (new ReflectionProperty(SchemaPropertyValidator::class, 'cache'))->setValue(null, []);
});

it('returns the valid Schema.org properties for a type', function (string $type, array $expected) {
    $valid = SchemaPropertyValidator::validPropertiesForType($type);

    expect($valid)->not->toBeEmpty();
    expect($valid)->toContain(...$expected);
})->with([
    'WebPage' => ['WebPage', ['url', 'name', 'description', 'about', 'isPartOf']],
    '3DModel, from the enum' => ['3DModel', ['name', 'encoding', 'encodingFormat']],
    'Credential, from the enum' => ['Credential', ['name', 'credentialCategory', 'recognizedBy']],
]);

it('returns an empty list for an unknown type', function () {
    expect(SchemaPropertyValidator::validPropertiesForType('UnknownTypeThatDoesNotExist'))->toBe([]);
});

it('reports no invalid properties when a node only has valid ones', function () {
    $node = [
        '@type' => 'WebPage',
        'url' => 'https://example.test/',
        'name' => 'Example',
        'description' => 'A page',
        'isPartOf' => ['@id' => 'https://example.test/#website'],
        'about' => ['@id' => 'https://example.test/#organization'],
    ];

    expect(SchemaPropertyValidator::invalidProperties('WebPage', $node))->toBe([]);
});

it('returns the invalid property keys in node order', function () {
    $node = [
        '@type' => 'WebPage',
        'url' => 'https://example.test/',
        'notARealProperty' => 'value',
        'alsoInvalid' => 1,
    ];

    expect(SchemaPropertyValidator::invalidProperties('WebPage', $node))->toBe(['notARealProperty', 'alsoInvalid']);
});

it('falls back to the Spatie class when the enum has no properties for the type', function () {
    // ProductReturnPolicy is a Spatie class the enum vocabulary does not cover. Its
    // setters become the properties, including two the SchemaProperty enum lacks,
    // and none of BaseType's own public methods are among them.
    expect(SchemaPropertyValidator::validPropertiesForType('ProductReturnPolicy'))->toBe([
        'additionalType',
        'alternateName',
        'description',
        'disambiguatingDescription',
        'identifier',
        'image',
        'mainEntityOfPage',
        'name',
        'potentialAction',
        'productReturnDays',
        'productReturnLink',
        'sameAs',
        'subjectOf',
        'url',
    ]);
});

it('strips a leading backslash before looking the Spatie class up', function () {
    expect(SchemaPropertyValidator::validPropertiesForType('\\ProductReturnPolicy'))->toContain('productReturnDays');
});

it('returns an empty list for a Spatie class that has no setters of its own', function () {
    expect(SchemaPropertyValidator::validPropertiesForType('CssSelectorType'))->toBe([]);
});

it('drops superseded properties and keeps their replacements', function () {
    $valid = SchemaPropertyValidator::validPropertiesForType('Movie');

    expect($valid)->not->toContain('actors')
        ->and($valid)->toContain('actor');
});

it('returns the properties sorted', function () {
    $valid = SchemaPropertyValidator::validPropertiesForType('WebPage');
    $sorted = $valid;
    sort($sorted);

    expect($valid)->toBe($sorted);
});

it('returns the same list from its cache on a second call', function () {
    $first = SchemaPropertyValidator::validPropertiesForType('ProductReturnPolicy');

    expect(SchemaPropertyValidator::validPropertiesForType('ProductReturnPolicy'))->toBe($first)
        ->and((new ReflectionProperty(SchemaPropertyValidator::class, 'cache'))->getValue())->toHaveKey('ProductReturnPolicy');
});

it('serves a cached list without recomputing it', function () {
    (new ReflectionProperty(SchemaPropertyValidator::class, 'cache'))->setValue(null, ['WebPage' => ['onlyThis']]);

    expect(SchemaPropertyValidator::validPropertiesForType('WebPage'))->toBe(['onlyThis']);
});

it('always allows the reserved JSON-LD keys', function () {
    $node = ['@context' => 'https://schema.org', '@type' => 'Thing', '@id' => '#x', 'name' => 'X'];

    expect(SchemaPropertyValidator::invalidProperties('UnknownTypeThatDoesNotExist', $node))->toBe(['name']);
});

it('skips Spatie methods that do not take exactly one argument', function () {
    // MultiTypedEntity's getNodes() takes none, and Graph's add(), get() and has() take
    // two, so none of them read as a property. What does survive is pinned as-is:
    // these are helper classes rather than Schema.org types.
    $multiTyped = SchemaPropertyValidator::validPropertiesForType('MultiTypedEntity');
    $graph = SchemaPropertyValidator::validPropertiesForType('Graph');

    // MultiTypedEntity declares __call first and lists its setters out of order, so
    // this exact list also pins that an inherited method is skipped rather than ending
    // the scan, and that the result is sorted.
    expect($multiTyped)->toBe(['add', 'get', 'getOrCreate', 'has', 'set'])
        ->and($graph)->not->toContain('add', 'get', 'has', 'getNodes')
        ->and($graph)->toBe(['__construct']);
});

it('keeps scanning past an inherited method declared first', function () {
    // ThreeDimensionalModel declares getType(), a BaseType name, before its setters.
    expect(SchemaPropertyValidator::validPropertiesForType('ThreeDimensionalModel'))->toHaveCount(141);
});
