<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use Illuminate\Http\Request;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Tests\Support\SchemaFixtureStacheTestCase;

/**
 * An `entries` field in a schema template's Antlers context loads its entries only
 * when the template references it (UAMS-Web/uams-statamic-archive#2637).
 *
 * Augmentation leaves an `entries` field as a query builder, and a pair over a
 * builder in the schema pipeline iterates zero times. The pipeline now replaces
 * the builder with the field's entries, for the handles a template names and no
 * others, so the cost of loading them is paid only where something reads them.
 *
 * Each case parses one Antlers row against Kirk in {@see SchemaFixtureStacheTestCase}'s
 * Star-Trek graph, whose Stache is a throwaway copy. The loaded collections are
 * read from the request memo that holds them, which is the one place a load leaves
 * a trace.
 *
 * @command  php artisan test --compact tests/Feature/SchemaJsonLdEntriesFieldHydrationTest.php
 */
uses(SchemaFixtureStacheTestCase::class);

const ENTRIES_HYDRATION_KIRK_ID = 'aaaa1111-0000-4000-8000-000000000001';

const ENTRIES_HYDRATION_MEMO = '_app_schema_output_entries_field_hydration_memo';

beforeEach(function (): void {
    /** @var Statamic\Entries\Collection $specializations */
    $specializations = Collection::make('clinical_specializations');
    $specializations->sites(['default']);
    $specializations->save();

    /** @var EntryInstance $specialization */
    $specialization = Entry::make();
    $specialization->collection('clinical_specializations');
    $specialization->blueprint('clinical_specialization');
    $specialization->slug('208g00000x');
    $specialization->published(true);
    $specialization->data(['title' => 'Thoracic Surgery', 'code' => '208G00000X']);
    $specialization->saveQuietly();

    $this->specializationId = mustBeString($specialization->id(), 'the specialization id');

    /** @var EntryInstance $kirk */
    $kirk = Entry::find(ENTRIES_HYDRATION_KIRK_ID);
    $kirk->set('clinical_specializations', [$this->specializationId]);
    $kirk->saveQuietly();

    // Start each case with an empty request memo
    app()->instance('request', Request::create('http://uams-statamic.test/people/james-kirk', 'GET'));
});

/**
 * Parse one Antlers row against Kirk.
 *
 * @param  string  $code  The Antlers snippet
 * @return mixed The row's output, or null when it renders nothing
 */
function entriesHydrationRender(string $code): mixed
{
    /** @var EntryInstance $kirk */
    $kirk = Entry::find(ENTRIES_HYDRATION_KIRK_ID);

    $emittedEntryIds = [];
    $emittedNodeIds = [];
    $materializedFullNodeAtIds = [];

    return (new ReflectionClass(SchemaOutputService::class))
        ->getMethod('resolveSingleReplicatorRowToValue')
        ->invokeArgs(app(SchemaOutputService::class), [
            ['type' => 'antlers', 'value' => ['code' => $code, 'mode' => 'html'], 'enabled' => true],
            $kirk,
            [],
            [],
            &$emittedEntryIds,
            &$emittedNodeIds,
            &$materializedFullNodeAtIds,
            [],
        ]);
}

/**
 * How many entries fields the pipeline has loaded in this request.
 */
function entriesHydrationLoadCount(): int
{
    $memo = request()->attributes->get(ENTRIES_HYDRATION_MEMO, []);

    return \is_array($memo) ? \count($memo) : 0;
}

it('iterates an entries field pair over its entries', function (): void {
    expect(entriesHydrationRender('{{ knows_about_entries }}{{ id }}|{{ /knows_about_entries }}'))
        ->toBe($this->specializationId.'|');
});

it('iterates a stored entries field pair as well as a computed one', function (): void {
    expect(entriesHydrationRender('{{ clinical_specializations }}{{ title }}{{ /clinical_specializations }}'))
        ->toBe('Thoracic Surgery');
});

it('loads no entries field when the template references none', function (): void {
    // The row rendered, so the empty memo does not mean the pipeline never ran
    expect(entriesHydrationRender('{{ title }}'))->toBe('James T. Kirk')
        ->and(entriesHydrationLoadCount())->toBe(0);
});

it('loads only the entries fields the template references', function (): void {
    entriesHydrationRender('{{ knows_about_entries }}{{ id }}{{ /knows_about_entries }}');

    expect(entriesHydrationLoadCount())->toBe(1);

    entriesHydrationRender('{{ knows_about_entries }}{{ id }}{{ /knows_about_entries }}{{ clinical_specializations }}{{ id }}{{ /clinical_specializations }}');

    expect(entriesHydrationLoadCount())->toBe(2);
});

it('leaves a referenced entries field with no value unloaded', function (): void {
    // Kirk has no clinical provider entry, so the field holds no query builder
    expect(entriesHydrationRender('{{ title }}{{ if clinical_provider_entry }}!{{ /if }}'))->toBe('James T. Kirk')
        ->and(entriesHydrationLoadCount())->toBe(0);
});
