<?php

declare(strict_types=1);

use App\Listeners\StripProviderPeopleVirtualFieldsOnEntrySaving;
use App\Services\ComputedFields\Registration\QuantitativeValueDurationComputedsRegistrar;
use App\Services\ComputedFields\Registration\RecognitionListTaxonomyAugmentedRegistrar;
use App\Services\ComputedFields\Registration\RoleTitleMirroredComputedsRegistrar;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaving;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Registration edge cases for computed-field registrars and the virtual-field
 * stripping listener.
 *
 * @covers \App\Services\ComputedFields\Registration\RoleTitleMirroredComputedsRegistrar
 * @covers \App\Services\ComputedFields\Registration\QuantitativeValueDurationComputedsRegistrar
 * @covers \App\Services\ComputedFields\Registration\RecognitionListTaxonomyAugmentedRegistrar
 *
 * The three registrar cases here only check that registering does not throw. They
 * reach none of the computed callbacks, so they make no mutation claim for those
 * classes: RoleTitleMirroredComputedsRegistrar and
 * QuantitativeValueDurationComputedsRegistrar are covered by their own tests under
 * tests/Feature/Services/ComputedFields/Registration/.
 *
 * Mutation: 100% on App\Listeners\StripProviderPeopleVirtualFieldsOnEntrySaving
 * (23 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Providers/ComputedPropertiesProviderRegistrationTest.php
 */
it('registers role title computed value fields without error when the derivations list is empty', function () {
    // Registering from the empty configuration list is the whole contract, so
    // "nothing was thrown" is the assertion. throwableFrom() rather than
    // `->not->toThrow(…)`: that idiom passes on any throwable outside the class it
    // names, and covering the tree with two of them would register twice (UAMS-Web/uams-statamic-archive#1759).
    expect(throwableFrom(fn () => (new RoleTitleMirroredComputedsRegistrar)->register()))->toBeNull();
});

it('registers quantitative value duration total years without error when the field list is empty', function () {
    // Registering from the empty configuration list is the whole contract, so
    // "nothing was thrown" is the assertion.
    expect(throwableFrom(fn () => (new QuantitativeValueDurationComputedsRegistrar)->register()))->toBeNull();
});

it('registers recognition list taxonomy augmented terms without error', function () {
    // Wiring up the LocalizedTerm augmented hook pipeline is the whole contract, so
    // "nothing was thrown" is the assertion.
    expect(throwableFrom(fn () => (new RecognitionListTaxonomyAugmentedRegistrar)->register()))->toBeNull();
});

it('strips pubmed_information from each publication select set on provider entry saving', function () {
    // Mock a clinical_providers collection entry carrying virtual PubMed fields
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collectionHandle')
        ->andReturn('clinical_providers');
    $entry->shouldReceive('get')
        ->with(
            'research_publication_select',
            []
        )
        ->andReturn([
            [
                'id' => 'a',
                'pubmed_information' => 'virtual',
            ],
        ]);
    $entry->shouldReceive('set')
        ->once()
        ->with(
            'research_publication_select',
            [
                [
                    'id' => 'a',
                ],
            ]
        );

    // Dispatch the entry saving listener to strip virtual publication fields
    $listener = new StripProviderPeopleVirtualFieldsOnEntrySaving;
    $listener->handle(new EntrySaving($entry));
});

it('strips virtual activity computeds from people certification sets on entry saving', function () {
    // Mock a people collection entry carrying virtual certification computeds
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collectionHandle')
        ->andReturn('people');
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                return match ($key) {
                    'research_publication_select' => [],
                    'certifications' => [
                        [
                            'board' => 'x',
                            'activity_valid_for' => 'v',
                            'activity_active_query' => 'v2',
                        ],
                    ],
                    default => $default,
                };
            }
        );
    $entry->shouldReceive('set')
        ->once()
        ->with(
            'certifications',
            [
                [
                    'board' => 'x',
                ],
            ]
        );

    // Dispatch the entry saving listener to strip virtual certification fields
    $listener = new StripProviderPeopleVirtualFieldsOnEntrySaving;
    $listener->handle(new EntrySaving($entry));
});
