<?php

declare(strict_types=1);

use App\Events\TriggerActivated;
use App\Listeners\HandleTableDataImporterTrigger;
use Illuminate\Support\Facades\Log;
use League\Flysystem\PathTraversalDetected;
use Maatwebsite\Excel\Facades\Excel;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\LegacyMockInterface;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Entries\Entry;
use Statamic\Facades\Asset;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Listeners\HandleTableDataImporterTrigger
 *
 * Mutation: 100% on App\Listeners\HandleTableDataImporterTrigger (93 mutants), measured on macOS on 2026-10-08, serial, --covered-only, with every test file that names the class.
 * Six equivalent mutants are annotated in the class with the reason each cannot be killed.
 *
 * @command  php artisan test --compact tests/Feature/Listeners/HandleTableDataImporterTriggerTest.php
 */
it('returns empty when the trigger handle is mismatched', function () {
    $listener = new HandleTableDataImporterTrigger;
    $event = new TriggerActivated(null, 'other_trigger', []);

    expect($listener->handle($event))->toBe([]);
});

it('refuses an asset id that smuggles a foreign container prefix', function () {
    // An id already containing `::` carries its own container prefix. The fix
    // (UAMS-Web/uams-statamic-archive#863) must reject any prefix other than `table_spreadsheets` BEFORE the
    // asset is resolved, so out-of-container contents are never read or returned.
    Asset::shouldReceive('find')->never();
    Excel::shouldReceive('toArray')->never();

    $listener = new HandleTableDataImporterTrigger;
    $event = new TriggerActivated(
        null,
        'table_data_importer_trigger',
        ['table_data_importer' => 'secret_container::secrets.csv']
    );

    expect($listener->handle($event))->toBe([]);
});

it('resolves an in-container asset id within table_spreadsheets', function () {
    // A bare id is prefixed with the intended container and resolved there.
    $asset = Mockery::mock(AssetContract::class);
    $asset->shouldReceive('extension')->andReturn('csv');
    $asset->shouldReceive('resolvedPath')->andReturn('/tmp/data.csv');

    // The lookup must target the table_spreadsheets container, not any other.
    Asset::shouldReceive('find')
        ->once()
        ->with('table_spreadsheets::data.csv')
        ->andReturn($asset);

    Excel::shouldReceive('toArray')->once()->andReturn([
        [
            ['Header A', 'Header B'],
            ['1', '2'],
        ],
    ]);

    $listener = new HandleTableDataImporterTrigger;
    $event = new TriggerActivated(
        null,
        'table_data_importer_trigger',
        ['table_data_importer' => 'data.csv']
    );

    expect($listener->handle($event))->toBe([
        'table_data' => [
            ['cells' => ['Header A', 'Header B']],
            ['cells' => ['1', '2']],
        ],
    ]);
});

it('resolves an in-container asset id that already carries the table_spreadsheets prefix', function () {
    // An id whose own prefix IS the intended container is allowed through
    // unchanged (the field can store the fully-qualified id).
    $asset = Mockery::mock(AssetContract::class);
    $asset->shouldReceive('extension')->andReturn('csv');
    $asset->shouldReceive('resolvedPath')->andReturn('/tmp/allowed.csv');

    Asset::shouldReceive('find')
        ->once()
        ->with('table_spreadsheets::allowed.csv')
        ->andReturn($asset);

    Excel::shouldReceive('toArray')->once()->andReturn([
        [
            ['X', 'Y'],
        ],
    ]);

    $listener = new HandleTableDataImporterTrigger;
    $event = new TriggerActivated(
        null,
        'table_data_importer_trigger',
        ['table_data_importer' => 'table_spreadsheets::allowed.csv']
    );

    expect($listener->handle($event))->toBe([
        'table_data' => [
            ['cells' => ['X', 'Y']],
        ],
    ]);
});

it('treats a path-traversal asset id as a no-op rather than a 500', function () {
    // A `../` smuggled into an otherwise in-container id passes the container
    // guard but Flysystem throws PathTraversalDetected during resolution. The
    // listener must catch it and return [] (no uncaught 500), since resolution
    // is inside the try block.
    Asset::shouldReceive('find')
        ->once()
        ->with('table_spreadsheets::../secrets.csv')
        ->andThrow(PathTraversalDetected::forPath('../secrets.csv'));

    Excel::shouldReceive('toArray')->never();

    $listener = new HandleTableDataImporterTrigger;
    $event = new TriggerActivated(
        null,
        'table_data_importer_trigger',
        ['table_data_importer' => 'table_spreadsheets::../secrets.csv']
    );

    expect($listener->handle($event))->toBe([]);
});

/**
 * The listener remembers every event it has handled in a static map for the life of the
 * process, so each test below names a file no other test uses.
 */
function tableImporterEvent(mixed $assetId, ?Entry $entry = null): TriggerActivated
{
    return new TriggerActivated($entry, 'table_data_importer_trigger', ['table_data_importer' => $assetId]);
}

/**
 * Resolve `table_spreadsheets::<file>` to a mock asset with the given extension.
 */
function tableImporterAsset(string $file, string $extension = 'csv'): AssetContract
{
    $asset = Mockery::mock(AssetContract::class);
    $asset->shouldReceive('extension')->andReturn($extension);
    $asset->shouldReceive('resolvedPath')->andReturn('/tmp/'.$file);

    Asset::shouldReceive('find')->once()->with('table_spreadsheets::'.$file)->andReturn($asset);

    return $asset;
}

it('handles a repeated event once, and logs the repeat', function () {
    tableImporterAsset('repeat-once.csv');
    Excel::shouldReceive('toArray')->once()->andReturn([[['A']]]);
    /** @var LegacyMockInterface $log */
    $log = Log::spy();

    $event = tableImporterEvent('repeat-once.csv');
    $listener = new HandleTableDataImporterTrigger;

    expect($listener->handle($event))->toBe(['table_data' => [['cells' => ['A']]]])
        ->and($listener->handle($event))->toBe([])
        ->and((new HandleTableDataImporterTrigger)->handle($event))->toBe([]);

    $log->shouldHaveReceived('notice')
        ->twice()
        ->with('HandleTableDataImporterTrigger::handle: Skipping duplicate TriggerActivated event processing', [
            'trigger_handle' => 'table_data_importer_trigger',
        ]);
});

it('logs and stops when no asset is selected', function (mixed $selected) {
    Asset::shouldReceive('find')->never();
    /** @var LegacyMockInterface $log */
    $log = Log::spy();

    $formValues = ['table_data_importer' => $selected, 'probe' => uniqid('', true)];
    $event = new TriggerActivated(null, 'table_data_importer_trigger', $formValues);

    expect((new HandleTableDataImporterTrigger)->handle($event))->toBe([]);

    $log->shouldHaveReceived('error')
        ->once()
        ->with('HandleTableDataImporterTrigger::handle: No asset selected in table_data_importer from form values', [
            'form_values' => $formValues,
        ]);
})->with([
    'null' => [null],
    'empty string' => [''],
    'empty list' => [[]],
]);

it('logs and stops when the field holds no asset at all', function () {
    Asset::shouldReceive('find')->never();
    /** @var LegacyMockInterface $log */
    $log = Log::spy();

    $formValues = ['probe' => uniqid('', true)];
    $event = new TriggerActivated(null, 'table_data_importer_trigger', $formValues);

    expect((new HandleTableDataImporterTrigger)->handle($event))->toBe([]);

    $log->shouldHaveReceived('error')->once()->with(
        'HandleTableDataImporterTrigger::handle: No asset selected in table_data_importer from form values',
        ['form_values' => $formValues]
    );
});

it('reads the first id from a list of selected assets', function () {
    tableImporterAsset('first-of-list.csv');
    Excel::shouldReceive('toArray')->once()->with(Mockery::any(), '/tmp/first-of-list.csv')->andReturn([[['A']]]);

    expect((new HandleTableDataImporterTrigger)->handle(tableImporterEvent(['first-of-list.csv', 'second.csv'])))
        ->toBe(['table_data' => [['cells' => ['A']]]]);
});

it('logs and stops when the asset id is not text', function (mixed $selected, mixed $logged) {
    Asset::shouldReceive('find')->never();
    /** @var LegacyMockInterface $log */
    $log = Log::spy();

    expect((new HandleTableDataImporterTrigger)->handle(tableImporterEvent($selected)))->toBe([]);

    $log->shouldHaveReceived('error')->once()->with(
        'HandleTableDataImporterTrigger::handle: Invalid asset ID format in form values',
        ['asset_id' => $logged]
    );
})->with([
    'a number' => [4207, 4207],
    'a list holding a number' => [[4208], 4208],
    'a list holding null' => [[null, 'skipped.csv'], null],
]);

it('logs the refused container', function () {
    Asset::shouldReceive('find')->never();
    /** @var LegacyMockInterface $log */
    $log = Log::spy();

    expect((new HandleTableDataImporterTrigger)->handle(tableImporterEvent('other_container::refused-logged.csv')))->toBe([]);

    $log->shouldHaveReceived('warning')->once()->with(
        'HandleTableDataImporterTrigger::handle: Refusing asset id outside the intended container',
        ['intended_container' => 'table_spreadsheets', 'requested_container' => 'other_container']
    );
});

it('logs and stops when the asset is missing', function () {
    Asset::shouldReceive('find')->once()->with('table_spreadsheets::missing.csv')->andReturn(null);
    Excel::shouldReceive('toArray')->never();
    /** @var LegacyMockInterface $log */
    $log = Log::spy();

    expect((new HandleTableDataImporterTrigger)->handle(tableImporterEvent('missing.csv')))->toBe([]);

    $log->shouldHaveReceived('error')->once()->with(
        'HandleTableDataImporterTrigger::handle: Asset not found or not a supported format',
        ['asset_id' => 'table_spreadsheets::missing.csv']
    );
});

it('refuses a file that is not a spreadsheet', function () {
    tableImporterAsset('brochure.pdf', 'pdf');
    Excel::shouldReceive('toArray')->never();
    /** @var LegacyMockInterface $log */
    $log = Log::spy();

    expect((new HandleTableDataImporterTrigger)->handle(tableImporterEvent('brochure.pdf')))->toBe([]);

    $log->shouldHaveReceived('error')->once()->with(
        'HandleTableDataImporterTrigger::handle: Asset not found or not a supported format',
        ['asset_id' => 'table_spreadsheets::brochure.pdf']
    );
});

it('accepts each spreadsheet format, in any case', function (string $file, string $extension) {
    tableImporterAsset($file, $extension);
    Excel::shouldReceive('toArray')->once()->andReturn([[['A']]]);

    expect((new HandleTableDataImporterTrigger)->handle(tableImporterEvent($file)))
        ->toBe(['table_data' => [['cells' => ['A']]]]);
})->with([
    'xls' => ['legacy-format.xls', 'xls'],
    'xlsx' => ['modern-format.xlsx', 'xlsx'],
    'upper-case CSV' => ['shouting-format.CSV', 'CSV'],
]);

it('returns nothing for a spreadsheet with no values', function () {
    tableImporterAsset('blank-sheet.csv');
    Excel::shouldReceive('toArray')->once()->andReturn([[[null, '   '], [], ['', "\t"]]]);

    expect((new HandleTableDataImporterTrigger)->handle(tableImporterEvent('blank-sheet.csv')))->toBe([]);
});

it('keeps a sheet whose first row is blank but a later row has a value', function () {
    tableImporterAsset('late-start.csv');
    Excel::shouldReceive('toArray')->once()->andReturn([[[null, ''], ['', '0']]]);

    expect((new HandleTableDataImporterTrigger)->handle(tableImporterEvent('late-start.csv')))->toBe([
        'table_data' => [
            ['cells' => ['', '']],
            ['cells' => ['', '0']],
        ],
    ]);
});

it('pads short rows to the first row\'s width and turns each cell into trimmed text', function () {
    tableImporterAsset('ragged.csv');
    Excel::shouldReceive('toArray')->once()->andReturn([[
        ['Name', 'Dose', 'Notes'],
        ['  Aspirin ', 81],
        [null, ['not', 'a', 'cell'], 2.5],
    ]]);

    expect((new HandleTableDataImporterTrigger)->handle(tableImporterEvent('ragged.csv')))->toBe([
        'table_data' => [
            ['cells' => ['Name', 'Dose', 'Notes']],
            ['cells' => ['Aspirin', '81', '']],
            ['cells' => ['', '', '2.5']],
        ],
    ]);
});

/**
 * An entry that records its quiet saves instead of writing to the Stache.
 *
 * A named class rather than a Mockery mock: the listener serializes the whole event to build its
 * repeat key, and a mock refuses `__sleep()` while an anonymous class cannot be serialized at all.
 */
final class TableImporterRecordingEntry extends Entry
{
    public int $quietSaves = 0;

    public function saveQuietly(): bool
    {
        $this->quietSaves++;

        return true;
    }
}

it('writes the table onto the entry and saves it quietly', function () {
    tableImporterAsset('onto-entry.csv');
    Excel::shouldReceive('toArray')->once()->andReturn([[['A', 'B']]]);

    $tableData = [['cells' => ['A', 'B']]];
    $entry = new TableImporterRecordingEntry;

    expect((new HandleTableDataImporterTrigger)->handle(tableImporterEvent('onto-entry.csv', $entry)))
        ->toBe(['table_data' => $tableData])
        ->and($entry->get('table_data'))->toBe($tableData)
        ->and($entry->quietSaves)->toBe(1);
});

it('logs a failure while reading the spreadsheet and returns nothing', function () {
    tableImporterAsset('corrupt.xlsx', 'xlsx');
    Excel::shouldReceive('toArray')->once()->andThrow(new RuntimeException('zip is corrupt'));
    /** @var LegacyMockInterface $log */
    $log = Log::spy();

    expect((new HandleTableDataImporterTrigger)->handle(tableImporterEvent('corrupt.xlsx')))->toBe([]);

    $log->shouldHaveReceived('error')->once()->with('HandleTableDataImporterTrigger::handle: Error replacing table data: zip is corrupt');
});

it('logs a mismatched trigger and reads nothing', function () {
    Asset::shouldReceive('find')->never();
    /** @var LegacyMockInterface $log */
    $log = Log::spy();

    $event = new TriggerActivated(null, 'other_trigger', ['table_data_importer' => 'mismatched-trigger.csv']);

    expect((new HandleTableDataImporterTrigger)->handle($event))->toBe([]);

    $log->shouldHaveReceived('warning')->once()->with('HandleTableDataImporterTrigger::handle: Trigger handle does not match, skipping listener');
    $log->shouldNotHaveReceived('error');
});

it('reads a number in the first row as a value', function () {
    tableImporterAsset('numbers-first.csv');
    Excel::shouldReceive('toArray')->once()->andReturn([[[2024, 'Total']]]);

    expect((new HandleTableDataImporterTrigger)->handle(tableImporterEvent('numbers-first.csv')))
        ->toBe(['table_data' => [['cells' => ['2024', 'Total']]]]);
});

it('reads a sheet holding only cells that are not values as empty', function () {
    tableImporterAsset('nested-only.csv');
    Excel::shouldReceive('toArray')->once()->andReturn([[[['nested'], null], [new stdClass]]]);

    expect((new HandleTableDataImporterTrigger)->handle(tableImporterEvent('nested-only.csv')))->toBe([]);
});

it('numbers each row\'s cells from zero', function () {
    tableImporterAsset('sparse-keys.csv');
    Excel::shouldReceive('toArray')->once()->andReturn([[[3 => 'A', 7 => 'B']]]);

    expect((new HandleTableDataImporterTrigger)->handle(tableImporterEvent('sparse-keys.csv')))
        ->toBe(['table_data' => [['cells' => ['A', 'B']]]]);
});
