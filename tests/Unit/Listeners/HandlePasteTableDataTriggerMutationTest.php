<?php

declare(strict_types=1);

use App\Events\TriggerActivated;
use App\Listeners\HandlePasteTableDataTrigger;
use App\Services\Tables\PastedTableParser;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(HandlePasteTableDataTrigger::class);

/**
 * Narrow mutate coverage for App\Listeners\HandlePasteTableDataTrigger.
 *
 * @command  php artisan test --compact tests/Unit/Listeners/HandlePasteTableDataTriggerMutationTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

it('ignores triggers it does not own', function (): void {
    $parser = Mockery::mock(PastedTableParser::class);
    $parser->shouldReceive('parse')->never();

    $result = (new HandlePasteTableDataTrigger($parser))->handle(
        new TriggerActivated(null, 'table_data_importer_trigger', [
            'table_data_paste' => "a,b\n1,2",
        ]),
    );

    expect($result)->toBe([]);
});

it('returns no change for a non-string or absent paste', function (array $formValues): void {
    // Every dataset row below is keyed by field handle.
    /** @var array<string, mixed> $formValues */
    $parser = Mockery::mock(PastedTableParser::class);
    $parser->shouldReceive('parse')->never();

    $result = (new HandlePasteTableDataTrigger($parser))->handle(
        new TriggerActivated(null, 'table_data_paste_trigger', $formValues),
    );

    expect($result)->toBe([]);
})->with([
    'absent' => [[]],
    'null' => [['table_data_paste' => null]],
    'array' => [['table_data_paste' => ['a']]],
    'int' => [['table_data_paste' => 12]],
]);

it('returns no change when the parser yields no rows', function (): void {
    $parser = Mockery::mock(PastedTableParser::class);
    $parser->shouldReceive('parse')->once()->with('<table></table>')->andReturn([]);

    $result = (new HandlePasteTableDataTrigger($parser))->handle(
        new TriggerActivated(null, 'table_data_paste_trigger', [
            'table_data_paste' => '<table></table>',
        ]),
    );

    expect($result)->toBe([]);
});

it('returns parsed table_data for a usable paste', function (): void {
    $rows = [
        ['cells' => ['a', 'b']],
        ['cells' => ['1', '2']],
    ];

    $parser = Mockery::mock(PastedTableParser::class);
    $parser->shouldReceive('parse')->once()->with("a\tb\n1\t2")->andReturn($rows);

    $result = (new HandlePasteTableDataTrigger($parser))->handle(
        new TriggerActivated(null, 'table_data_paste_trigger', [
            'table_data_paste' => "a\tb\n1\t2",
        ]),
    );

    expect($result)->toBe(['table_data' => $rows]);
});
