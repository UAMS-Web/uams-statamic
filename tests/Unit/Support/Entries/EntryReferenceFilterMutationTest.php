<?php

declare(strict_types=1);

use App\Support\Entries\EntryReferenceFilter;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Query\Builder;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(EntryReferenceFilter::class);

/**
 * Narrow mutate coverage for App\Support\Entries\EntryReferenceFilter.
 *
 * @command  php artisan test --compact tests/Unit/Support/Entries/EntryReferenceFilterMutationTest.php
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

it('applies no constraint when the id list is empty', function (): void {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereJsonContains')->never();
    $query->shouldReceive('whereJsonDoesntContain')->never();
    $query->shouldReceive('where')->never();

    EntryReferenceFilter::apply(
        $query,
        'logo_labels',
        [],
        'all',
    );
});

it('requires every selected id under all logic', function (): void {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereJsonContains')->once()->with('logo_labels', 'a')->andReturnSelf();
    $query->shouldReceive('whereJsonContains')->once()->with('logo_labels', 'b')->andReturnSelf();

    EntryReferenceFilter::apply(
        $query,
        'logo_labels',
        ['a', 'b'],
        'all',
    );
});

it('excludes every selected id under not logic', function (): void {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereJsonDoesntContain')->once()->with('logo_labels', 'x')->andReturnSelf();

    EntryReferenceFilter::apply(
        $query,
        'logo_labels',
        ['x'],
        'not',
    );
});

it('ORs selected ids under any logic', function (): void {
    $inner = Mockery::mock(Builder::class);
    $inner->shouldReceive('orWhereJsonContains')->once()->with('logo_labels', 'a')->andReturnSelf();
    $inner->shouldReceive('orWhereJsonContains')->once()->with('logo_labels', 'b')->andReturnSelf();

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with(Mockery::on(function (callable $callback) use ($inner): bool {
            $callback($inner);

            return true;
        }))
        ->andReturnSelf();

    EntryReferenceFilter::apply(
        $query,
        'logo_labels',
        ['a', 'b'],
        'any',
    );
});

it('resolves display logic from strings, Stringables, and junk', function (mixed $logic, string $expected): void {
    expect(EntryReferenceFilter::logic($logic))->toBe($expected);
})->with([
    'all string' => ['all', 'all'],
    'not string' => ['not', 'not'],
    'any string' => ['any', 'any'],
    'unknown string' => ['maybe', 'any'],
    'stringable all' => [new class implements Stringable
    {
        public function __toString(): string
        {
            return 'all';
        }
    }, 'all'],
    'stringable not' => [new class implements Stringable
    {
        public function __toString(): string
        {
            return 'not';
        }
    }, 'not'],
    'null defaults' => [null, 'any'],
    'int defaults' => [12, 'any'],
    // Non-Stringable object must take the default arm: InstanceOfToTrue
    // would cast it to string and fatal instead.
    'object defaults' => [new stdClass, 'any'],
]);
