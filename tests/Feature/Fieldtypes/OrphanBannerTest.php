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

/**
 * OrphanBanner (UAMS-Web/uams-statamic-archive#1429) surfaces the universal orphaned-ancestor state on the CP
 * publish form. Its preload() computes the banner meta from the entry being
 * edited via OrphanedAncestorResolver. Mocked service + parent entry — no
 * content/ submodule required.
 *
 * @command php artisan test --compact tests/Feature/Fieldtypes/OrphanBannerTest.php
 */
function orphanBannerFieldtype(?Entry $parent): OrphanBanner
{
    $fieldtype = new OrphanBanner;
    $field = new Field('orphan_banner', ['type' => 'orphan_banner']);

    if ($parent instanceof Entry) {
        $field->setParent($parent);
    }

    $fieldtype->setField($field);

    return $fieldtype;
}

it('flags an orphaned entry with its redirect destination and ancestor name', function (): void {
    $entry = Mockery::mock(Entry::class);

    $resolver = Mockery::mock(OrphanedAncestorResolver::class);
    $resolver->shouldReceive('resolve')->once()->with($entry)
        ->andReturn(new OrphanedAncestorResult('/health/cardiology', 'Cardiology'));
    app()->instance(OrphanedAncestorResolver::class, $resolver);

    expect(orphanBannerFieldtype($entry)->preload())->toBe([
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

    expect(orphanBannerFieldtype($entry)->preload())->toBe([
        'orphaned' => false,
        'redirect_url' => null,
        'ancestor_title' => null,
    ]);
});

it('shows no banner and never resolves without an entry parent', function (): void {
    $resolver = Mockery::mock(OrphanedAncestorResolver::class);
    $resolver->shouldNotReceive('resolve');
    app()->instance(OrphanedAncestorResolver::class, $resolver);

    expect(orphanBannerFieldtype(null)->preload())->toBe([
        'orphaned' => false,
        'redirect_url' => null,
        'ancestor_title' => null,
    ]);
});

it('renders no banner and never dereferences when no field is bound', function (): void {
    // The null-safe field() access must survive a fieldtype with no bound field.
    try {
        $outcome = (new OrphanBanner)->preload();
    } catch (Throwable $e) {
        $outcome = ['threw' => $e->getMessage()];
    }

    expect($outcome)->toBe([
        'orphaned' => false,
        'redirect_url' => null,
        'ancestor_title' => null,
    ]);
});
