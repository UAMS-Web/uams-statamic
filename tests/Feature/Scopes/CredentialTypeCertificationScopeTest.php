<?php

declare(strict_types=1);

use App\Scopes\CredentialTypeCertification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Term;
use Statamic\Stache\Query\TermQueryBuilder;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for `CredentialTypeCertification` query scope `apply` behavior,
 * including the `credential_type_certification_or_certificate` alias (UAMS-Web/uams-statamic-archive#2972).
 *
 * @command  php artisan test --compact tests/Feature/Scopes/CredentialTypeCertificationScopeTest.php
 */
it('reports the credential type certification handle', function () {
    expect(CredentialTypeCertification::handle())->toBe('credential_type_certification');
});

it('adds taxonomy and id filter when handle present', function () {
    // Build a synthetic Term::query() result: three terms in `credentials`,
    // only one of which has the CTDL shape (Credential + Certification)
    // that the scope filters in. The other two stand in for "wrong CTDL
    // root" and "wrong credential type" — both must be excluded.
    $matchingTerm = Mockery::mock(TaxonomyTerm::class);
    $matchingTerm->shouldReceive('get')
        ->with('ctdl')
        ->andReturn([
            'ceterms_type' => CredentialTypeCertification::CTDL_CETERMS_TYPE_VALUE_CREDENTIAL,
            'ceterms_credential_type' => CredentialTypeCertification::CTDL_CREDENTIAL_TYPE_VALUE_CERTIFICATION,
        ]);
    $matchingTerm->shouldReceive('id')->andReturn('credentials::atc');

    $wrongCredentialType = Mockery::mock(TaxonomyTerm::class);
    $wrongCredentialType->shouldReceive('get')
        ->with('ctdl')
        ->andReturn([
            'ceterms_type' => CredentialTypeCertification::CTDL_CETERMS_TYPE_VALUE_CREDENTIAL,
            'ceterms_credential_type' => 'Degree',
        ]);

    $wrongRoot = Mockery::mock(TaxonomyTerm::class);
    $wrongRoot->shouldReceive('get')
        ->with('ctdl')
        ->andReturn([
            'ceterms_type' => 'LearningOpportunity',
        ]);

    // Stub `Term::query()->whereIn('taxonomies', ['credentials'])->get()`
    // so the scope sees these three synthetic terms instead of resolving
    // anything from `content/taxonomies/credentials/`.
    $termQueryBuilder = Mockery::mock(Statamic\Query\Builder::class);
    $termQueryBuilder->shouldReceive('whereIn')
        ->once()
        ->with('taxonomies', [CredentialTypeCertification::TAXONOMY])
        ->andReturnSelf();
    $termQueryBuilder->shouldReceive('get')
        ->once()
        ->andReturn(new Collection([$matchingTerm, $wrongCredentialType, $wrongRoot]));

    Term::shouldReceive('query')->once()->andReturn($termQueryBuilder);

    // The Builder under test: the scope must whereIn taxonomies and
    // whereIn ids with exactly the matching term's id.
    $query = Mockery::mock(TermQueryBuilder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with(
            'taxonomies',
            [CredentialTypeCertification::TAXONOMY],
        )
        ->andReturnSelf();
    $query->shouldReceive('whereIn')
        ->once()
        ->with('id', ['credentials::atc'])
        ->andReturnSelf();

    (new CredentialTypeCertification)->apply(
        $query,
        [
            'queryScopes' => [
                CredentialTypeCertification::handle(),
            ],
        ]
    );
});

it('does not alter the query when the handle is not present', function () {
    $scope = new CredentialTypeCertification;
    $query = Mockery::mock(
        TermQueryBuilder::class
    );
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
    $scope = new CredentialTypeCertification;
    $query = Mockery::mock(
        Builder::class
    );
    $query->shouldReceive('whereIn')->never();
    $query->shouldReceive('where')->never();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                CredentialTypeCertification::handle(),
            ],
        ]
    );
});

it('exposes the certification-or-certificate handle only as an alias', function () {
    expect(CredentialTypeCertification::aliases())->toBe([
        'credential_type_certification_or_certificate',
    ]);
});

it('keeps the CTDL credential types each handle allows', function (string $handle, array $expectedIds) {
    // Build one credentials term per CTDL credential type
    $termOfType = function (string $id, string $type): TaxonomyTerm {
        $term = Mockery::mock(TaxonomyTerm::class);
        $term->shouldReceive('get')
            ->with('ctdl')
            ->andReturn([
                'ceterms_type' => CredentialTypeCertification::CTDL_CETERMS_TYPE_VALUE_CREDENTIAL,
                'ceterms_credential_type' => $type,
            ]);
        $term->shouldReceive('id')->andReturn($id);

        return $term;
    };

    $termQueryBuilder = Mockery::mock(Statamic\Query\Builder::class);
    $termQueryBuilder->shouldReceive('whereIn')
        ->once()
        ->with('taxonomies', [CredentialTypeCertification::TAXONOMY])
        ->andReturnSelf();
    $termQueryBuilder->shouldReceive('get')
        ->once()
        ->andReturn(new Collection([
            $termOfType('credentials::certification', 'Certification'),
            $termOfType('credentials::certificate', 'Certificate'),
            $termOfType('credentials::degree', 'Degree'),
        ]));

    Term::shouldReceive('query')->once()->andReturn($termQueryBuilder);

    $query = Mockery::mock(TermQueryBuilder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with('taxonomies', [CredentialTypeCertification::TAXONOMY])
        ->andReturnSelf();
    $query->shouldReceive('whereIn')
        ->once()
        ->with('id', $expectedIds)
        ->andReturnSelf();

    (new CredentialTypeCertification)->apply(
        $query,
        [
            'queryScopes' => [
                'unrelated_scope',
                $handle,
            ],
        ]
    );
})->with([
    'certification handle' => ['credential_type_certification', ['credentials::certification']],
    'certification-or-certificate alias' => ['credential_type_certification_or_certificate', ['credentials::certification', 'credentials::certificate']],
]);

it('keeps only exact CTDL types and coerces term ids to strings', function () {
    // Build credentials terms with a loose-matching type and odd ids
    $termWith = function (mixed $id, mixed $type): TaxonomyTerm {
        $term = Mockery::mock(TaxonomyTerm::class);
        $term->shouldReceive('get')
            ->with('ctdl')
            ->andReturn([
                'ceterms_type' => CredentialTypeCertification::CTDL_CETERMS_TYPE_VALUE_CREDENTIAL,
                'ceterms_credential_type' => $type,
            ]);
        $term->shouldReceive('id')->andReturn($id);

        return $term;
    };

    $termQueryBuilder = Mockery::mock(Statamic\Query\Builder::class);
    $termQueryBuilder->shouldReceive('whereIn')->once()->andReturnSelf();
    $termQueryBuilder->shouldReceive('get')
        ->once()
        ->andReturn(new Collection([
            $termWith('credentials::loose', true),
            $termWith(7, 'Certification'),
            $termWith(null, 'Certification'),
        ]));

    Term::shouldReceive('query')->once()->andReturn($termQueryBuilder);

    $query = Mockery::mock(TermQueryBuilder::class);
    $query->shouldReceive('whereIn')->once()->with('taxonomies', [CredentialTypeCertification::TAXONOMY])->andReturnSelf();
    $query->shouldReceive('whereIn')
        ->once()
        ->with('id', Mockery::on(fn (mixed $ids): bool => $ids === ['7', '']))
        ->andReturnSelf();

    (new CredentialTypeCertification)->apply(
        $query,
        [
            'queryScopes' => [
                CredentialTypeCertification::handle(),
            ],
        ]
    );
});
