<?php

declare(strict_types=1);

use App\Scopes\BoardCertifications;
use Illuminate\Database\Eloquent\Builder;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Stache\Query\TermQueryBuilder;

uses(MockeryPHPUnitIntegration::class);

/**
 * Unit tests for `BoardCertifications` query scope aliases, `apply` behavior,
 * and the shared `isBoardCertification()` definition (UAMS-Web/uams-statamic-archive#2972).
 *
 * @command  php artisan test --compact tests/Unit/Scopes/BoardCertificationsTest.php
 */
it('exposes the inverted handle only as an alias', function () {
    $aliases = BoardCertifications::aliases();

    expect($aliases)->toBe([
        BoardCertifications::ALIAS_OTHER_CERTIFICATIONS,
    ]);
});

it('uses the snake form of the class short name as the handle', function () {
    expect(BoardCertifications::handle())->toBe('board_certifications');
});

it('adds taxonomy, true flag and certification kinds for board certifications', function () {
    $scope = new BoardCertifications;
    $query = Mockery::mock(TermQueryBuilder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with(
            'taxonomies',
            [
                BoardCertifications::CERTIFICATIONS_TAXONOMY,
            ]
        )
        ->andReturnSelf();
    $query->shouldReceive('where')
        ->once()
        ->with(
            BoardCertifications::BOARD_CERTIFICATION_QUERY_FIELD,
            true
        )
        ->andReturnSelf();
    $query->shouldReceive('whereNotIn')
        ->once()
        ->with(
            BoardCertifications::CREDENTIAL_KIND_FIELD,
            [
                'focused_practice_designation',
                'assessment_based_certificate',
            ]
        )
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'board_certifications',
            ],
        ]
    );
});

it('adds taxonomy and a not-true flag or non-board kind for other certifications', function () {
    $scope = new BoardCertifications;
    $nested = Mockery::mock(TermQueryBuilder::class);
    $nested->shouldReceive('where')
        ->once()
        ->with(
            BoardCertifications::BOARD_CERTIFICATION_QUERY_FIELD,
            '!=',
            true
        )
        ->andReturnSelf();
    $nested->shouldReceive('orWhereIn')
        ->once()
        ->with(
            BoardCertifications::CREDENTIAL_KIND_FIELD,
            [
                'focused_practice_designation',
                'assessment_based_certificate',
            ]
        )
        ->andReturnSelf();
    $query = Mockery::mock(TermQueryBuilder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with(
            'taxonomies',
            [
                BoardCertifications::CERTIFICATIONS_TAXONOMY,
            ]
        )
        ->andReturnSelf();
    $query->shouldReceive('where')
        ->once()
        ->with(Mockery::on(function (mixed $callback) use ($nested): bool {
            // Run the grouped condition against the nested builder
            if (! $callback instanceof Closure) {
                return false;
            }

            $callback($nested);

            return true;
        }))
        ->andReturnSelf();
    $query->shouldReceive('whereNotIn')->never();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                BoardCertifications::ALIAS_OTHER_CERTIFICATIONS,
            ],
        ]
    );
});

it('uses the first matching handle when both are present', function () {
    $scope = new BoardCertifications;
    $query = Mockery::mock(TermQueryBuilder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->andReturnSelf();
    $query->shouldReceive('where')
        ->once()
        ->with(
            BoardCertifications::BOARD_CERTIFICATION_QUERY_FIELD,
            true
        )
        ->andReturnSelf();
    $query->shouldReceive('whereNotIn')
        ->once()
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'board_certifications',
                BoardCertifications::ALIAS_OTHER_CERTIFICATIONS,
            ],
        ]
    );
});

it('does not alter the query when no known scope handle is present', function () {
    $scope = new BoardCertifications;
    $query = Mockery::mock(TermQueryBuilder::class);
    $query->shouldReceive('whereIn')->never();
    $query->shouldReceive('where')->never();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'unrelated_scope',
            ],
        ]
    );
});

it('does not alter a non-statamic builder', function () {
    $scope = new BoardCertifications;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')->never();
    $query->shouldReceive('where')->never();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'board_certifications',
            ],
        ]
    );
});

it('treats only a true flag on a kind that can be board certified as a board certification', function (mixed $flag, mixed $kind, bool $expected) {
    expect(BoardCertifications::isBoardCertification($flag, $kind))->toBe($expected);
})->with([
    'certification, flag true' => [true, 'certification', true],
    'no kind, flag true' => [true, null, true],
    'focused practice designation, flag true' => [true, 'focused_practice_designation', false],
    'assessment-based certificate, flag true' => [true, 'assessment_based_certificate', false],
    'certification, flag false' => [false, 'certification', false],
    'certification, flag null' => [null, 'certification', false],
    'certification, flag string true' => ['true', 'certification', false],
    'non-string kind matching loosely, flag true' => [true, true, true],
]);
