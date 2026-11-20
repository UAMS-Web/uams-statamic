<?php

declare(strict_types=1);

use App\Tags\Dateline;
use Carbon\Carbon;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Fields\Value;
use Statamic\Query\OrderedQueryBuilder;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Dateline tag: unit cases (mocked / hand-built context) and augmentation via
 * `Entry::make()` with the real `geographic_locations` entries (no committed posts fixture).
 *
 * Mutation: 100% on App\Tags\Dateline (128 mutants, without --covered-only, on Linux), measured
 * for UAMS-Web/uams-statamic#802. Four equivalent mutants carry `@pest-mutate-ignore` with the
 * reason beside each.
 *
 * @command  php artisan test --compact tests/Feature/Tags/DatelineTest.php
 */

/**
 * Render dateline output with a literal append_separator value.
 *
 * @param  string  $appendSeparatorLiteral  The append_separator literal value
 * @return string The rendered dateline output
 */
function datelineRenderWithAppendSeparatorLiteral(string $appendSeparatorLiteral): string
{
    // Parse the dateline tag with a literal append_separator parameter
    $output = Antlers::parse(
        "{{ dateline append_separator={$appendSeparatorLiteral} }}",
        [
            'geographic_location' => [
                ['dateline' => 'Little Rock'],
            ],
            'date' => '2026-01-02',
        ],
        true
    );

    // Return the rendered output as a string
    return (string) $output;
}

/**
 * Render dateline output with append_separator intentionally undefined.
 *
 * @return string The rendered dateline output
 */
function datelineRenderWithUndefinedAppendSeparator(): string
{
    // Parse the dateline tag with an undefined append_separator variable
    $output = Antlers::parse(
        '{{ dateline append_separator=undefined }}',
        [
            'geographic_location' => [
                ['dateline' => 'Little Rock'],
            ],
            'date' => '2026-01-02',
        ],
        true
    );

    // Return the rendered output as a string
    return (string) $output;
}

/**
 * Invoke Dateline append-separator normalization with a raw input value.
 *
 * @param  mixed  $value  The value to normalize
 * @return bool The normalized boolean result
 */
function datelineNormalizeAppendSeparatorRawValue(mixed $value): bool
{
    // Create a Dateline tag instance for method invocation
    $dateline = new Dateline;

    // Create a reflection method instance for normalization invocation
    $normalizeMethod = new ReflectionMethod(
        Dateline::class,
        'normalizeBoolValue'
    );

    // Invoke normalization and cast the result to bool
    return (bool) $normalizeMethod->invoke(
        $dateline,
        $value
    );
}

/**
 * The real Little Rock `geographic_locations` entry id from the content submodule.
 *
 * @return string The entry id
 */
function datelineLittleRockId(): string
{
    // Content-dependent: the collection and its entries live in `content/`.
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'geographic_locations');
    $query->where('slug', 'little-rock');
    $entry = $query->first();

    if (! $entry instanceof StatamicEntry) {
        ContentSubmodule::reportMissing('geographic_locations little-rock entry');
    }

    return mustBeString($entry->id(), 'the Little Rock entry id');
}

it('builds dateline from context location and date', function () {
    $output = Antlers::parse(
        '{{ dateline }}',
        [
            'geographic_location' => [
                ['dateline' => 'Little Rock'],
            ],
            'date' => '2026-01-02',
        ],
        true
    );

    expect((string) $output)->toBe('Little Rock (Jan. 2, 2026)');
});

it('builds dateline from context date as carbon instance', function () {
    $output = Antlers::parse(
        '{{ dateline append_separator="true" }}',
        [
            'geographic_location' => [
                ['dateline' => 'LITTLE ROCK, Ark.'],
            ],
            'date' => Carbon::parse('2026-03-26 12:00:00'),
        ],
        true
    );

    expect((string) $output)->toBe("LITTLE ROCK, Ark. (March 26, 2026)\u{00A0}— ");
});

/**
 * `toAugmentedArray()` for `geographic_location` + `date` matches the
 * hand-built context tests: the real Little Rock `geographic_locations` entry's
 * dateline and the entry `Carbon` date flow through Values into `{{ dateline }}`.
 */
it('dateline matches augmented post entry context', function () {
    // Content-dependent: requires the committed `posts` collection and the real
    // Little Rock `geographic_locations` entry from the `content/` submodule.
    $littleRockId = datelineLittleRockId();

    // Resolve the dated `posts` collection from the Stache
    /** @var Statamic\Entries\Collection|null $collection */
    $collection = Collection::find('posts');

    expect($collection)->not->toBeNull();

    // Build an in-memory news release with the real place entry id
    /** @var StatamicEntry $entry */
    $entry = Entry::make();

    // Resolve the configured application timezone for date parsing
    /** @var DateTimeZone|int|string|null $appTimezone */
    $appTimezone = config('app.timezone');

    // Configure the in-memory entry one setter at a time (each mutates in place)
    $entry->collection($collection);
    $entry->id('88888888-8888-4888-8888-888888888888');
    $entry->slug('dateline-augmented-context-test');
    $entry->blueprint('news_release');
    $entry->published(true);
    $entry->locale('default');
    $entry->data(
        [
            'title' => 'Dateline augmented context test',
            'geographic_location' => [$littleRockId],
            'content' => [],
        ]
    );
    $entry->date(
        Carbon::parse(
            '2026-03-26 12:00:00',
            $appTimezone
        )
    );
    $entry->initialPath(
        '/tmp/2026-03-26-1200.dateline-augmented-context-test.md'
    );

    // Mirror frontend template variables: augmented field Values and entry date
    /** @var array<string, mixed> $context */
    $context = $entry->toAugmentedArray(
        [
            'geographic_location',
            'date',
        ]
    );

    $output = Antlers::parse(
        '{{ dateline append_separator="true" }}',
        $context,
        true
    );

    // Real pipeline: entries field → query → Entry; date is Carbon on the entry
    expect((string) $output)->toBe("LITTLE ROCK, Ark. (March 26, 2026)\u{00A0}— ");
});

it('builds dateline with location only when date missing', function () {
    $output = Antlers::parse(
        '{{ dateline }}',
        [
            'geographic_location' => [
                ['dateline' => 'Fayetteville'],
            ],
        ],
        true
    );

    expect((string) $output)->toBe('Fayetteville');
});

it('builds dateline with date only when location missing', function () {
    $output = Antlers::parse(
        '{{ dateline }}',
        [
            'date' => '2026-01-02',
        ],
        true
    );

    expect((string) $output)->toBe('Jan. 2, 2026');
});

it('resolves dateline from the first geographic location entry id', function () {
    $output = Antlers::parse(
        '{{ dateline }}',
        [
            'geographic_location' => [
                datelineLittleRockId(),
            ],
        ],
        true
    );

    expect((string) $output)->toBe('LITTLE ROCK, Ark.');
});

it('resolves a stored slug within the location collection', function () {
    // Content-dependent: the slug is looked up in `geographic_locations`, not by id.
    datelineLittleRockId();

    $output = Antlers::parse(
        '{{ dateline }}',
        [
            'geographic_location' => [
                'little-rock',
            ],
        ],
        true
    );

    expect((string) $output)->toBe('LITTLE ROCK, Ark.');
});

it('does not resolve a slug from another collection', function () {
    // The same slug in a collection the tag was not pointed at resolves nothing.
    datelineLittleRockId();

    $output = Antlers::parse(
        '{{ dateline location_collection="posts" }}',
        [
            'geographic_location' => [
                'little-rock',
            ],
        ],
        true
    );

    expect((string) $output)->toBe('');
});

it('resolves location with custom field, collection and entry field handles', function () {
    $output = Antlers::parse(
        '{{ dateline location_field="custom_location" location_collection="geographic_locations" location_entry_field="title" }}',
        [
            'custom_location' => [
                datelineLittleRockId(),
            ],
        ],
        true
    );

    expect((string) $output)->toBe('Little Rock');
});

it('reads the dateline field from the entry the stored id finds', function () {
    $entry = Mockery::mock(StatamicEntry::class);
    $entry->shouldReceive('value')
        ->once()
        ->with('dateline')
        ->andReturn('MOCK LITTLE ROCK');

    Entry::shouldReceive('find')
        ->once()
        ->with('place-id')
        ->andReturn($entry);

    $output = Antlers::parse(
        '{{ dateline }}',
        [
            'geographic_location' => [
                'place-id',
            ],
        ],
        true
    );

    expect((string) $output)->toBe('MOCK LITTLE ROCK');
});

it('resolves dateline when location field is wrapped value object', function () {
    $entry = Mockery::mock(StatamicEntry::class);
    $entry->shouldReceive('value')
        ->once()
        ->with('dateline')
        ->andReturn('WRAPPED LITTLE ROCK');

    Entry::shouldReceive('find')
        ->once()
        ->with('place-id')
        ->andReturn($entry);

    // Build a real Value wrapper to avoid strict mock return typing issues.
    $wrappedValue = new Value(['place-id']);

    $resolveMethod = new ReflectionMethod(
        Dateline::class,
        'resolveGeographicLocationDatelineFromContext'
    );

    $resolvedDateline = $resolveMethod->invoke(
        new Dateline,
        ['geographic_location' => $wrappedValue],
        'geographic_location',
        'geographic_locations',
        'dateline'
    );

    expect($resolvedDateline)->toBe('WRAPPED LITTLE ROCK');
});

it('resolves the first entry from an ordered query builder', function () {
    // Production: an entries field augments to a query whose items are entries.
    $entry = Mockery::mock(StatamicEntry::class);
    $entry->shouldReceive('value')
        ->once()
        ->with('dateline')
        ->andReturn('QB ENTRY');

    $queryBuilder = Mockery::mock(OrderedQueryBuilder::class);
    $queryBuilder->shouldReceive('get')
        ->once()
        ->andReturn(collect([$entry]));

    $resolveMethod = new ReflectionMethod(
        Dateline::class,
        'resolveGeographicLocationDatelineFromContext'
    );

    $resolvedDateline = $resolveMethod->invoke(
        new Dateline,
        ['geographic_location' => $queryBuilder],
        'geographic_location',
        'geographic_locations',
        'dateline'
    );

    expect($resolvedDateline)->toBe('QB ENTRY');
});

it('normalize context date unwraps value wrapping carbon', function () {
    $method = new ReflectionMethod(
        Dateline::class,
        'normalizeContextDateForApStyle'
    );

    $resolved = $method->invoke(
        new Dateline,
        new Value(Carbon::parse('2026-06-15 00:00:00'))
    );

    expect($resolved)->toBe('2026-06-15 00:00:00');
});

it('returns empty output when location and date missing', function () {
    $output = Antlers::parse(
        '{{ dateline }}',
        [],
        true
    );

    expect((string) $output)->toBe('');
});

it('prefers explicit parameters over context values', function () {
    $output = Antlers::parse(
        '{{ dateline location="Seattle" date="March 1, 2026" }}',
        [
            'geographic_location' => [
                ['dateline' => 'Ignored Location'],
            ],
            'date' => '2026-01-02',
        ],
        true
    );

    expect((string) $output)->toBe('Seattle (March 1, 2026)');
});

it('appends separator when append separator is true', function () {
    $output = Antlers::parse(
        '{{ dateline append_separator="true" }}',
        [
            'geographic_location' => [
                ['dateline' => 'Little Rock'],
            ],
            'date' => '2026-01-02',
        ],
        true
    );

    expect((string) $output)->toBe("Little Rock (Jan. 2, 2026)\u{00A0}— ");
});

/**
 * `dateline:separator` matches `append_separator="true"` for nested Antlers call sites.
 */
it('separator tag method appends separator like true flag', function () {
    $output = Antlers::parse(
        '{{ dateline:separator }}',
        [
            'geographic_location' => [
                ['dateline' => 'Little Rock'],
            ],
            'date' => '2026-01-02',
        ],
        true
    );

    expect((string) $output)->toBe("Little Rock (Jan. 2, 2026)\u{00A0}— ");
});

it('treats empty string as false for append separator', function () {
    expect(datelineRenderWithAppendSeparatorLiteral('""'))->toBe('Little Rock (Jan. 2, 2026)');
});

it('treats null string as false for append separator', function () {
    expect(datelineRenderWithAppendSeparatorLiteral('"null"'))->toBe('Little Rock (Jan. 2, 2026)');
});

it('treats zero as false for append separator', function () {
    expect(datelineNormalizeAppendSeparatorRawValue(0))->toBeFalse();
});

it('treats false as false for append separator', function () {
    expect(datelineNormalizeAppendSeparatorRawValue(false))->toBeFalse();
});

it('treats false string as false for append separator', function () {
    expect(datelineRenderWithAppendSeparatorLiteral('"false"'))->toBe('Little Rock (Jan. 2, 2026)');
});

it('treats null as false for append separator', function () {
    expect(datelineNormalizeAppendSeparatorRawValue(null))->toBeFalse();
});

it('treats undefined as false for append separator', function () {
    expect(datelineRenderWithUndefinedAppendSeparator())->toBe('Little Rock (Jan. 2, 2026)');
});

it('treats undefined string as false for append separator', function () {
    expect(datelineRenderWithAppendSeparatorLiteral('"undefined"'))->toBe('Little Rock (Jan. 2, 2026)');
});

it('treats no string as false for append separator', function () {
    expect(datelineRenderWithAppendSeparatorLiteral('"no"'))->toBe('Little Rock (Jan. 2, 2026)');
});

it('treats true string as true for append separator', function () {
    expect(datelineRenderWithAppendSeparatorLiteral('"true"'))->toBe("Little Rock (Jan. 2, 2026)\u{00A0}— ");
});

it('treats one as true for append separator', function () {
    expect(datelineNormalizeAppendSeparatorRawValue(1))->toBeTrue();
});

it('treats one string as true for append separator', function () {
    expect(datelineRenderWithAppendSeparatorLiteral('"1"'))->toBe("Little Rock (Jan. 2, 2026)\u{00A0}— ");
});

it('treats true as true for append separator', function () {
    expect(datelineNormalizeAppendSeparatorRawValue(true))->toBeTrue();
});

it('treats yes string as true for append separator', function () {
    expect(datelineRenderWithAppendSeparatorLiteral('"yes"'))->toBe("Little Rock (Jan. 2, 2026)\u{00A0}— ");
});

it('treats other truthy values as true for append separator', function () {
    expect(datelineNormalizeAppendSeparatorRawValue([1]))->toBeTrue();
});

it('treats other falsey values as false for append separator', function () {
    expect(datelineNormalizeAppendSeparatorRawValue([]))->toBeFalse();
});

/**
 * Run the dateline tag's `index()` with parameters that Antlers cannot pass, such as
 * non-string handles.
 *
 * @param  array<string, mixed>  $params  The tag parameters
 * @param  array<string, mixed>  $context  The tag context
 * @return string|null The dateline output
 */
function datelineIndexWithParameters(array $params, array $context): ?string
{
    // Build the tag the way Antlers does, with these parameters and this context
    $dateline = new Dateline;
    $dateline->setProperties([
        'parser' => null,
        'content' => '',
        'context' => $context,
        'params' => $params,
        'tag' => 'dateline',
        'tag_method' => 'index',
    ]);

    return $dateline->index();
}

/**
 * Invoke the private geographic location resolver with the default handles.
 *
 * @param  mixed  $geographicLocation  The `geographic_location` context value
 * @return string|null The resolved dateline
 */
function datelineResolveGeographicLocation(mixed $geographicLocation): ?string
{
    $resolveMethod = new ReflectionMethod(
        Dateline::class,
        'resolveGeographicLocationDatelineFromContext'
    );

    /** @var string|null $resolved */
    $resolved = $resolveMethod->invoke(
        new Dateline,
        ['geographic_location' => $geographicLocation],
        'geographic_location',
        'geographic_locations',
        'dateline'
    );

    return $resolved;
}

it('falls back to the default handles when the handle parameters are empty strings', function () {
    // Content-dependent: a slug only resolves within the default collection.
    datelineLittleRockId();

    $output = Antlers::parse(
        '{{ dateline location_field="" location_collection="" location_entry_field="" }}',
        [
            'geographic_location' => [
                'little-rock',
            ],
        ],
        true
    );

    expect((string) $output)->toBe('LITTLE ROCK, Ark.');
});

it('falls back to the default handles when the handle parameters are not strings', function (string $handle) {
    // Content-dependent: a slug only resolves within the default collection.
    datelineLittleRockId();

    $output = datelineIndexWithParameters(
        [$handle => 5],
        [
            'geographic_location' => [
                'little-rock',
            ],
        ]
    );

    expect($output)->toBe('LITTLE ROCK, Ark.');
})->with([
    'location_field',
    'location_collection',
    'location_entry_field',
]);

it('falls back to the context location when the location parameter is empty or not a string', function (mixed $location) {
    $output = datelineIndexWithParameters(
        ['location' => $location],
        [
            'geographic_location' => [
                ['dateline' => 'Little Rock'],
            ],
        ]
    );

    expect($output)->toBe('Little Rock');
})->with([
    'empty string' => [''],
    'integer' => [5],
]);

it('falls back to the context date when the date parameter is empty or not a string', function (mixed $date) {
    $output = datelineIndexWithParameters(
        ['date' => $date],
        [
            'date' => '2026-01-02',
        ]
    );

    expect($output)->toBe('Jan. 2, 2026');
})->with([
    'empty string' => [''],
    'integer' => [5],
]);

it('normalizes a context date to a string only when it is a non-empty scalar', function (mixed $contextDate, ?string $expected) {
    $method = new ReflectionMethod(
        Dateline::class,
        'normalizeContextDateForApStyle'
    );

    expect($method->invoke(new Dateline, $contextDate))->toBe($expected);
})->with([
    'empty string' => ['', null],
    'false' => [false, null],
    'array' => [[], null],
    'integer timestamp' => [1767312000, '1767312000'],
]);

it('reads the dateline from the first augmented item of a collection', function () {
    expect(datelineResolveGeographicLocation(collect([
        ['dateline' => 'COLLECTED'],
        ['dateline' => 'SECOND'],
    ])))->toBe('COLLECTED');
});

it('unwraps a value wrapping the first location item', function () {
    expect(datelineResolveGeographicLocation([
        new Value(['dateline' => 'VALUE ITEM']),
    ]))->toBe('VALUE ITEM');
});

it('unwraps a value wrapping the augmented dateline field', function () {
    expect(datelineResolveGeographicLocation([
        ['dateline' => new Value('WRAPPED FIELD')],
    ]))->toBe('WRAPPED FIELD');
});

it('resolves no dateline from an augmented field that is empty or not a string', function (mixed $field) {
    expect(datelineResolveGeographicLocation([
        ['dateline' => $field],
    ]))->toBeNull();
})->with([
    'empty string' => [''],
    'integer' => [5],
]);

it('resolves no dateline when the entry field is empty or not a string', function (mixed $field) {
    $entry = Mockery::mock(StatamicEntry::class);
    $entry->shouldReceive('value')
        ->with('dateline')
        ->andReturn($field);

    Entry::shouldReceive('find')
        ->with('place-id')
        ->andReturn($entry);

    expect(datelineResolveGeographicLocation(['place-id']))->toBeNull();
})->with([
    'empty string' => [''],
    'integer' => [5],
]);

it('looks up the entry named by the id of augmented data', function (mixed $id) {
    $entry = Mockery::mock(StatamicEntry::class);
    $entry->shouldReceive('value')
        ->with('dateline')
        ->andReturn('ID LITTLE ROCK');

    Entry::shouldReceive('find')
        ->with('place-id')
        ->andReturn($entry);

    expect(datelineResolveGeographicLocation([
        ['id' => $id],
    ]))->toBe('ID LITTLE ROCK');
})->with([
    'string id' => ['place-id'],
    'value-wrapped id' => [new Value('place-id')],
]);

it('trims and lowercases a string append separator before matching it', function (string $value, bool $expected) {
    expect(datelineNormalizeAppendSeparatorRawValue($value))->toBe($expected);
})->with([
    'padded uppercase true' => [' TRUE ', true],
    'mixed-case yes' => ['Yes', true],
    'padded one' => [' 1 ', true],
    'padded uppercase false' => [' FALSE ', false],
    'uppercase no' => ['NO', false],
    'mixed-case null' => ['Null', false],
    'mixed-case undefined' => ['Undefined', false],
    'whitespace only' => ['   ', false],
]);

it('falls back to PHP truthiness for an append separator outside both lists', function (mixed $value, bool $expected) {
    expect(datelineNormalizeAppendSeparatorRawValue($value))->toBe($expected);
})->with([
    'zero string' => ['0', false],
    'other string' => ['always', true],
    'padded zero string' => [' 0 ', true],
    'integer two' => [2, true],
    'float zero' => [0.0, false],
]);
