<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\DocumentTitleComputedsRegistrar;
use App\Services\Seo\DocumentTitle;
use App\Support\Collections\CollectionExtensions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
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
 * @covers \App\Services\ComputedFields\Registration\DocumentTitleComputedsRegistrar
 *
 * @command php artisan test --compact tests/Feature/Services/ComputedFields/Registration/DocumentTitleComputedsRegistrarTest.php
 */
it('registers every extension document title, then the clinical location title', function (): void {
    $kirk = fn (): string => 'Kirk';
    $spock = fn (): string => 'Spock';

    $extensions = new CollectionExtensions;
    $extensions->documentTitle('clinical_providers', $kirk);
    $extensions->documentTitle('people', $spock);

    $title = app(DocumentTitle::class);

    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collection, string $handle, callable $callback) use (&$captured): void {
            $captured[] = [$collection, $handle, $callback];
        }
    );

    (new DocumentTitleComputedsRegistrar($title, $extensions))->register();

    expect($captured)->toHaveCount(3)
        ->and(array_column($captured, 0))->toBe(['clinical_providers', 'people', 'clinical_locations'])
        ->and(array_column($captured, 1))->toBe(['document_title', 'document_title', 'document_title'])
        ->and($captured[0][2])->toBe($kirk)
        ->and($captured[1][2])->toBe($spock);

    // The location title is the service's own method, bound to the injected instance.
    $location = new ReflectionFunction(Closure::fromCallable($captured[2][2]));

    expect($location->getName())->toBe('forClinicalLocation')
        ->and($location->getClosureThis())->toBe($title);
});
