<?php

declare(strict_types=1);

use App\Scopes\ExcludeSelf;
use Illuminate\Http\Request;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Query\Builder;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(ExcludeSelf::class);

/**
 * Narrow mutate coverage for App\Scopes\ExcludeSelf.
 *
 * UAMS-Web/uams-statamic-archive#1759 killed the non-Builder guard mutants. Remaining killable gaps were the
 * ``is_array($values)`` early return (needs a resolvable referrer so removing
 * the guard would call ``where``) and ``BooleanOrToBooleanAnd`` on the null-id
 * check. ``EmptyStringToNotEmpty`` on ``=== ''`` is ignored in source:
 * ``CpRelFieldEditContext::resolve()`` normalizes missing ids to null.
 *
 * @command  php artisan test --compact tests/Feature/Scopes/ExcludeSelfScopeTest.php
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

/**
 * Bind a CP entry-edit referrer so ``CpRelFieldEditContext::resolve()`` yields
 * the given id (EntryFacade::find must return a non-null entry).
 */
function excludeSelfReferrer(string $entryId): void
{
    EntryFacade::shouldReceive('find')
        ->with($entryId)
        ->andReturn(Mockery::mock(Entry::class));

    app()->instance(
        'request',
        Request::create(
            'http://acme.test/current',
            'GET',
            [],
            [],
            [],
            [
                'HTTP_REFERER' => "http://acme.test/cp/collections/pages/entries/{$entryId}/edit",
            ]
        )
    );
}

it('skips when not a statamic builder', function (): void {
    // Give the scope a resolvable edit context so the ``instanceof Builder``
    // guard is the only thing standing between a non-builder and ``where()``.
    excludeSelfReferrer('e1');

    $scope = new ExcludeSelf;

    expect(throwableFrom(fn () => $scope->apply(
        new stdClass,
        []
    )))->toBeNull();
});

it('skips when values are not an array', function (mixed $values): void {
    // Referrer must resolve: RemoveEarlyReturn on the ``is_array`` guard would
    // otherwise fall through to ``where`` and this test would still pass.
    excludeSelfReferrer('e1');

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')->never();

    (new ExcludeSelf)->apply(
        $query,
        $values,
    );
})->with([
    'null' => [null],
    'string' => ['not-an-array'],
    'int' => [12],
]);

it('skips when the referrer yields no current id', function (): void {
    // BooleanOrToBooleanAnd on ``id === null || id === ''`` would require both
    // arms and fall through to ``where`` for a null id.
    app()->instance(
        'request',
        Request::create(
            'http://acme.test/current',
            'GET',
        )
    );

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')->never();

    (new ExcludeSelf)->apply(
        $query,
        [],
    );
});

it('excludes the resolved entry id from the referrer context', function (): void {
    excludeSelfReferrer('e1');

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with(
            'id',
            '!=',
            'e1'
        )
        ->andReturnSelf();

    (new ExcludeSelf)->apply(
        $query,
        [],
    );
});
