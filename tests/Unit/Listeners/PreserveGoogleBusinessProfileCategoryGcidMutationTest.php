<?php

declare(strict_types=1);

use App\Listeners\PreserveGoogleBusinessProfileCategoryGcid;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Events\TermSaving;
use Statamic\Taxonomies\Term;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(PreserveGoogleBusinessProfileCategoryGcid::class);

/**
 * Narrow mutate coverage for PreserveGoogleBusinessProfileCategoryGcid.
 *
 * @command  php artisan test --compact tests/Unit/Listeners/PreserveGoogleBusinessProfileCategoryGcidMutationTest.php
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

it('restores a non-empty stored gcid on the category taxonomy', function (): void {
    $term = Mockery::mock(Term::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('google_business_profile_categories');
    $term->shouldReceive('getOriginal')->once()->with('gcid')->andReturn('allergist');
    $term->shouldReceive('set')->once()->with('gcid', 'allergist');

    (new PreserveGoogleBusinessProfileCategoryGcid)->handle(new TermSaving($term));
});

it('ignores terms outside the category taxonomy', function (): void {
    $term = Mockery::mock(Term::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('audio_series');
    $term->shouldReceive('getOriginal')->never();
    $term->shouldReceive('set')->never();

    (new PreserveGoogleBusinessProfileCategoryGcid)->handle(new TermSaving($term));
});

it('allows a first gcid when nothing usable is stored', function (mixed $stored): void {
    $term = Mockery::mock(Term::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('google_business_profile_categories');
    $term->shouldReceive('getOriginal')->once()->with('gcid')->andReturn($stored);
    $term->shouldReceive('set')->never();

    (new PreserveGoogleBusinessProfileCategoryGcid)->handle(new TermSaving($term));
})->with([
    'null' => [null],
    'empty string' => [''],
    'non-string' => [12],
]);
