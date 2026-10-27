<?php

declare(strict_types=1);

use App\Fieldtypes\Terms;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Fields\Field;
use Statamic\Query\Builder;
use Tests\Support\TermsMutateProbeScope;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(Terms::class);

/**
 * Unit tests for `App\Fieldtypes\Terms` index query-scope merging.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/TermsTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    TermsMutateProbeScope::reset();
    app('statamic.scopes')->put(
        'terms_mutate_probe',
        TermsMutateProbeScope::class
    );

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
 * Terms fieldtype with the given query_scopes config.
 *
 * @param  list<string>  $queryScopes
 */
function termsFieldtype(array $queryScopes = ['from_config']): Terms
{
    /** @var Terms $fieldtype */
    $fieldtype = (new Terms)->setField(
        new Field(
            'tags',
            [
                'type' => 'terms',
                'query_scopes' => $queryScopes,
            ]
        )
    );

    return $fieldtype;
}

/**
 * Invoke a non-public Terms method.
 *
 * @param  array<int, mixed>  $args
 */
function invokeTermsMethod(Terms $fieldtype, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod(
        Terms::class,
        $method
    );
    $reflection->setAccessible(true);

    return $reflection->invoke(
        $fieldtype,
        ...$args
    );
}

it('prefers request query scopes over the field config', function (): void {
    $result = invokeTermsMethod(
        termsFieldtype(),
        'mergedIndexQueryScopeHandles',
        [[
            'queryScopes' => ['from_request'],
        ]],
    );

    expect($result)->toBe(['from_request']);
});

it('falls back to the field config when params omit query scopes', function (): void {
    $result = invokeTermsMethod(
        termsFieldtype(),
        'mergedIndexQueryScopeHandles',
        [[]],
    );

    expect($result)->toBe(['from_config']);
});

it('uses the config when the request query scopes list is empty', function (): void {
    $result = invokeTermsMethod(
        termsFieldtype(),
        'mergedIndexQueryScopeHandles',
        [[
            'queryScopes' => [],
        ]],
    );

    expect($result)->toBe(['from_config']);
});

it('wraps a bare request queryScopes string into a list', function (): void {
    $result = invokeTermsMethod(
        termsFieldtype(),
        'mergedIndexQueryScopeHandles',
        [[
            'queryScopes' => 'from_request',
        ]],
    );

    expect($result)->toBe(['from_request']);
});

it('delegates to the parent when the query is not a Builder', function (): void {
    // InstanceOfToFalse would take this parent path for a real Builder and run
    // only field-config scopes (missing a request-only probe).
    invokeTermsMethod(
        termsFieldtype(['terms_mutate_probe']),
        'applyIndexQueryScopes',
        [new stdClass, ['probe' => 'early']],
    );

    expect(TermsMutateProbeScope::$calls)->toHaveCount(1)
        ->and(TermsMutateProbeScope::$calls[0]['values']['probe'] ?? null)->toBe('early');
});

it('runs request-preferred scopes for a Builder index query', function (): void {
    // InstanceOfToFalse / RemoveNot would fall through to parent and run config only.
    $query = Mockery::mock(Builder::class);

    invokeTermsMethod(
        termsFieldtype(['from_config_only']),
        'applyIndexQueryScopes',
        [
            $query,
            [
                'probe' => 'keep',
                'queryScopes' => ['terms_mutate_probe'],
            ],
        ],
    );

    expect(TermsMutateProbeScope::$calls)->toHaveCount(1)
        ->and(TermsMutateProbeScope::$calls[0]['query'])->toBe($query)
        ->and(TermsMutateProbeScope::$calls[0]['values']['probe'] ?? null)->toBe('keep')
        ->and(TermsMutateProbeScope::$calls[0]['values']['queryScopes'] ?? null)
        ->toBe(['terms_mutate_probe']);
});

it('writes merged config scopes into params when the request list is empty', function (): void {
    // RemoveArrayItem on ``queryScopes`` would leave the empty request list and
    // fail this assertion (request-preferred cases keep the same value either way).
    $query = Mockery::mock(Builder::class);

    invokeTermsMethod(
        termsFieldtype(['terms_mutate_probe']),
        'applyIndexQueryScopes',
        [
            $query,
            [
                'probe' => 'config-fallback',
                'queryScopes' => [],
            ],
        ],
    );

    expect(TermsMutateProbeScope::$calls)->toHaveCount(1)
        ->and(TermsMutateProbeScope::$calls[0]['values']['queryScopes'] ?? null)
        ->toBe(['terms_mutate_probe']);
});
