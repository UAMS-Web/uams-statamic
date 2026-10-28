<?php

declare(strict_types=1);

use App\Services\ComputedFields\ProviderPeople\ProviderPeopleListingComputedValues;
use App\Services\ComputedFields\Registration\PeopleOnlyCollectionComputedsRegistrar;
use App\Support\Collections\PeopleCollection;
use App\Support\People\CertificationActivityValidity;
use App\Support\People\PersonKnowsAboutComputer;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

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
 * @covers \App\Services\ComputedFields\Registration\PeopleOnlyCollectionComputedsRegistrar
 *
 * @command php artisan test --compact tests/Feature/Services/ComputedFields/Registration/PeopleOnlyCollectionComputedsRegistrarTest.php
 */
it('registers the five people computeds, each bound to its own service', function (): void {
    $validity = app(CertificationActivityValidity::class);
    $listing = Mockery::mock(ProviderPeopleListingComputedValues::class);
    $knowsAbout = app(PersonKnowsAboutComputer::class);

    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collection, string $handle, callable $callback) use (&$captured): void {
            $captured[$handle] = [$collection, $callback];
        }
    );

    (new PeopleOnlyCollectionComputedsRegistrar($validity, $listing, $knowsAbout))->register();

    $people = PeopleCollection::handle();

    expect(array_keys($captured))->toBe([
        'certifications',
        'clinical_provider_profile_eligible_query',
        'knows_about_terms',
        'knows_about_entries',
        'knows_about_strings',
    ])->and(array_column($captured, 0))->toBe(array_fill(0, 5, $people));

    // Certifications pass the stored rows through the merge: the output gains
    // the computed keys, so it is neither the stored value nor null.
    $stored = [['issued' => '2000-01-01', 'expires' => '2999-01-01']];
    $merged = ($captured['certifications'][1])(Mockery::mock(Entry::class), $stored);

    expect($merged)->toBe($validity->mergeIntoCertificationsRows($stored))
        ->and($merged)->not->toBe($stored)
        ->and($merged)->not->toBeNull();

    // The other four are first-class callables on the injected instances.
    $bound = static function (callable $callback): array {
        $function = new ReflectionFunction(Closure::fromCallable($callback));

        return [$function->getName(), $function->getClosureThis()];
    };

    expect($bound($captured['clinical_provider_profile_eligible_query'][1]))
        ->toBe(['computeClinicalProviderProfileEligibleQuery', $listing])
        ->and($bound($captured['knows_about_terms'][1]))->toBe(['computeTerms', $knowsAbout])
        ->and($bound($captured['knows_about_entries'][1]))->toBe(['computeEntries', $knowsAbout])
        ->and($bound($captured['knows_about_strings'][1]))->toBe(['computeStrings', $knowsAbout]);
});
