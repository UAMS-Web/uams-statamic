<?php

declare(strict_types=1);

use App\Fieldtypes\Date as AppDateFieldtype;
use Carbon\Carbon;
use PHPUnit\Framework\Assert;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Entries\GetDateFromPath;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests that Date::preProcess prefers the filename date prefix over a stored
 * "now" default, using the same app-timezone parse rules as Stache
 * (CollectionEntriesStore::getDateFromPath) on Statamic 6.11+.
 *
 * @covers \App\Fieldtypes\Date::preProcess
 *
 * Mutation: content-dependent on App\Fieldtypes\Date — the sole test self-skips
 * without the content/ submodule (the dated `posts` collection), so Pest reports
 * no covered mutants locally; coverage runs in CI (--covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/DatedEntryDateFieldPreprocessTest.php
 */
it('prefers the filename prefix over a stored now default when preprocessing the publish date', function () {
    // Requires the dated `posts` collection (+ `news_release` blueprint) from the
    // content/ submodule; skip locally when it is absent.
    if (Collection::find('posts') === null) {
        ContentSubmodule::reportMissing('dated entry fixtures');
    }

    // Resolve the dated `posts` collection from the Stache
    $collection = Collection::find('posts');

    expect($collection)->not->toBeNull();

    // Resolve the app timezone the way Stache parses dated filenames
    /** @var string|null $appTimezone */
    $appTimezone = config('app.timezone');

    // Build an unsaved entry with a synthetic dated `initialPath` (no file)
    /** @var StatamicEntry $entry */
    $entry = Entry::make();

    // Configure the entry one setter at a time so the concrete type is preserved
    // (the fluent setters are untyped and return mixed, which would break a chain)
    $entry->collection($collection);
    $entry->id('66666666-6666-4666-8666-666666666666');
    $entry->slug('dated-preprocess-test');
    $entry->blueprint('news_release');
    $entry->published(true);
    $entry->locale('default');
    $entry->data([
        'title' => 'Dated preprocess test',
    ]);
    $entry->date(
        Carbon::parse(
            '2026-04-04 04:16',
            $appTimezone
        )
    );
    $entry->initialPath(
        '/tmp/2026-04-04-0416.dated-preprocess-test.md'
    );

    // Get the blueprint for the entry
    /** @var Blueprint $blueprint */
    $blueprint = $entry->blueprint();

    // Set the blueprint parent to the entry
    $blueprint->setParent($entry);

    // Resolve the publish date field from the blueprint
    /** @var Field $field */
    $field = $blueprint->field('date');

    // Assert the test uses the app extended Date fieldtype, not stock Statamic only.
    // Asserted on the call rather than the annotated variable — the `@var` below is
    // what would make an instanceof over `$fieldtype` statically redundant.
    expect($field->fieldtype())->toBeInstanceOf(AppDateFieldtype::class);

    // Get the fieldtype instance bound to that field
    /** @var AppDateFieldtype $fieldtype */
    $fieldtype = $field->fieldtype();

    // Simulate blueprint default merged into data — must not win over filename
    $entry->set('date', 'now');

    // Read the path that includes the dated filename prefix
    $path = $entry->path();

    // Assert the repository returned a concrete path string
    expect($path)->toBeString();

    // Narrow the path to a string for the Stache date extractor
    $path = is_string($path) ? $path : '';

    // Extract the dated first segment from the filename the way Stache does
    $dateString = (new GetDateFromPath)($path);

    // Assert the fixture filename carries a Statamic dated prefix
    expect($dateString)->not->toBeNull();

    // Narrow the extracted prefix to a string for length-based parsing
    $dateString = is_string($dateString) ? $dateString : '';

    // Measure prefix length to choose between date-only and datetime formats
    $prefixLength = \strlen($dateString);

    // Assert the prefix uses a length Statamic supports for collection entries
    expect([
        10,
        15,
        17,
    ])->toContain($prefixLength);

    // Map prefix length to the matching Carbon parse format
    $format = match ($prefixLength) {
        10 => 'Y-m-d',
        15 => 'Y-m-d-Hi',
        17 => 'Y-m-d-His',
        default => throw new UnexpectedValueException("Unsupported dated prefix length: {$prefixLength}."),
    };

    // Parse the prefix in the app timezone like CollectionEntriesStore does
    $expectedCarbon = Carbon::createFromFormat(
        $format,
        $dateString,
        $appTimezone
    );

    // Assert the prefix parsed into a concrete Carbon instant for the math below
    if (! $expectedCarbon instanceof Carbon) {
        Assert::fail('Carbon could not parse the dated filename prefix.');
    }

    // Check if the filename carries date-only (no time in the prefix)
    if (\strlen($dateString) === 10) {
        // Normalize to start of day so the expected instant matches Stache
        $expectedCarbon->startOfDay();
    }

    // Build the ISO-8601 Zulu string the CP date field should receive
    $expected = $expectedCarbon->utc()->toIso8601ZuluString('millisecond');

    // Run preprocess — must follow filename, not Carbon::parse('now')
    $iso = $fieldtype->preProcess('now');

    // Assert preProcess ignored the now sentinel and matched the file prefix
    expect($iso)->toBe($expected);
});
