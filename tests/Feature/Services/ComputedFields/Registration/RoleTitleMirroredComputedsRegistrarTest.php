<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\RoleTitleMirroredComputedsRegistrar;
use App\Support\RoleTitleValue;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Services\ComputedFields\Registration\RoleTitleMirroredComputedsRegistrar
 *
 * @command  php artisan test --compact tests/Feature/Services/ComputedFields/Registration/RoleTitleMirroredComputedsRegistrarTest.php
 */
it('registers nothing as the container builds it, because no collection opts in yet', function (): void {
    Collection::shouldReceive('computed')->never();

    app(RoleTitleMirroredComputedsRegistrar::class)->register();
});

it('registers one computed per tuple, on the collection and handle the tuple names', function (): void {
    $registered = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (string $collection, string $handle) use (&$registered): void {
            $registered[] = [$collection, $handle];
        }
    );

    (new RoleTitleMirroredComputedsRegistrar([
        ['people', 'job_title', 'job_title_text'],
        ['clinical_providers', 'leadership_title', 'leadership_title_text'],
    ]))->register();

    expect($registered)->toBe([
        ['people', 'job_title_text'],
        ['clinical_providers', 'leadership_title_text'],
    ]);
});

it('computes the title composed from the source field', function (mixed $stored, string $expected): void {
    $callbacks = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (string $collection, string $handle, Closure $callback) use (&$callbacks): void {
            $callbacks[$handle] = $callback;
        }
    );

    (new RoleTitleMirroredComputedsRegistrar([['people', 'job_title', 'job_title_text']]))->register();

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')->with('job_title')->andReturn($stored);

    expect(($callbacks['job_title_text'])($entry, 'ignored stored value'))->toBe($expected);
})->with([
    'a faculty title' => [
        [
            'configuration' => RoleTitleValue::CONFIGURATION_FACULTY,
            'title_options' => ['visiting' => true, 'distinguished' => false, 'adjunct' => false],
            'honorific' => 'emeritus',
            'scope_qualifier' => null,
            'hierarchical_modifier' => 'associate',
            'role' => 'professor',
        ],
        'visiting associate professor emeritus',
    ],
    'a value that is not a payload' => ['Professor', ''],
]);
