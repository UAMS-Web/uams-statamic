<?php

declare(strict_types=1);

use App\Fieldtypes\OrphanBanner;
use App\Services\OrphanedAncestors\OrphanedAncestorResolver;
use App\Services\OrphanedAncestors\OrphanedAncestorResult;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(OrphanBanner::class);

/**
 * Narrow mutate coverage for OrphanBanner.
 *
 * @command  php artisan test --compact tests/Unit/Fieldtypes/OrphanBannerMutationTest.php
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

function orphanBannerMutFieldtype(?Entry $parent): OrphanBanner
{
    $fieldtype = new OrphanBanner;
    $field = new Field('orphan_banner', ['type' => 'orphan_banner']);

    if ($parent instanceof Entry) {
        $field->setParent($parent);
    }

    $fieldtype->setField($field);

    return $fieldtype;
}

it('exposes the warning icon and an empty config field list', function (): void {
    $fieldtype = new OrphanBanner;
    $config = new ReflectionMethod(OrphanBanner::class, 'configFieldItems');

    expect($fieldtype->icon())->toBe('warning')
        ->and($config->invoke($fieldtype))->toBe([]);
});

it('flags an orphaned entry with its redirect destination and ancestor name', function (): void {
    $entry = Mockery::mock(Entry::class);

    $resolver = Mockery::mock(OrphanedAncestorResolver::class);
    $resolver->shouldReceive('resolve')->once()->with($entry)
        ->andReturn(new OrphanedAncestorResult('/health/cardiology', 'Cardiology'));
    app()->instance(OrphanedAncestorResolver::class, $resolver);

    expect(orphanBannerMutFieldtype($entry)->preload())->toBe([
        'orphaned' => true,
        'redirect_url' => '/health/cardiology',
        'ancestor_title' => 'Cardiology',
    ]);
});

it('shows no banner when the entry has no unpublished ancestor', function (): void {
    $entry = Mockery::mock(Entry::class);

    $resolver = Mockery::mock(OrphanedAncestorResolver::class);
    $resolver->shouldReceive('resolve')->once()->with($entry)->andReturnNull();
    app()->instance(OrphanedAncestorResolver::class, $resolver);

    expect(orphanBannerMutFieldtype($entry)->preload())->toBe([
        'orphaned' => false,
        'redirect_url' => null,
        'ancestor_title' => null,
    ]);
});

it('shows no banner and never resolves without an entry parent', function (): void {
    $resolver = Mockery::mock(OrphanedAncestorResolver::class);
    $resolver->shouldNotReceive('resolve');
    app()->instance(OrphanedAncestorResolver::class, $resolver);

    expect(orphanBannerMutFieldtype(null)->preload())->toBe([
        'orphaned' => false,
        'redirect_url' => null,
        'ancestor_title' => null,
    ]);
});

it('renders no banner when no field is bound', function (): void {
    expect((new OrphanBanner)->preload())->toBe([
        'orphaned' => false,
        'redirect_url' => null,
        'ancestor_title' => null,
    ]);
});
