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
 * Mutation: 100% on App\Tags\Dateline (155 mutants, --covered-only) before UAMS-Web/uams-statamic-archive#2597
 * moved it from terms to entries; not re-measured since. The entries paths are
 * pinned by hand-applied negative controls recorded on that pull request.
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
