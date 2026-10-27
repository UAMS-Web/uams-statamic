<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\ClinicalExpertiseStructureComputedsRegistrar;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Structures\Page;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(ClinicalExpertiseStructureComputedsRegistrar::class);

/**
 * Narrow mutate coverage for ClinicalExpertiseStructureComputedsRegistrar.
 *
 * @command  php artisan test --compact tests/Unit/Services/ComputedFields/Registration/ClinicalExpertiseStructureComputedsRegistrarMutationTest.php
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
 * Register and capture the clinical_expertise structure computeds.
 *
 * @return array{parent_expertise_name: callable, parent_expertise_url: callable}
 */
function clinicalExpertiseStructureCallbacks(): array
{
    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (string $collection, string $handle, callable $callback) use (&$captured): void {
            expect($collection)->toBe('clinical_expertise');
            $captured[$handle] = $callback;
        }
    );

    (new ClinicalExpertiseStructureComputedsRegistrar)->register();

    expect($captured)->toHaveKeys([
        'parent_expertise_name',
        'parent_expertise_url',
    ]);

    /** @var array{parent_expertise_name: callable, parent_expertise_url: callable} $captured */
    return $captured;
}

it('registers both parent expertise computeds on clinical_expertise', function (): void {
    clinicalExpertiseStructureCallbacks();
});

it('resolves parent name and url from a direct Entry parent', function (): void {
    $callbacks = clinicalExpertiseStructureCallbacks();

    $parent = Mockery::mock(Entry::class);
    $parent->shouldReceive('get')->with('title')->andReturn('Cardiology');
    $parent->shouldReceive('url')->andReturn('/expertise/cardiology');

    $child = Mockery::mock(Entry::class);
    $child->shouldReceive('parent')->andReturn($parent);

    expect(($callbacks['parent_expertise_name'])($child, null))->toBe('Cardiology')
        ->and(($callbacks['parent_expertise_url'])($child, null))->toBe('/expertise/cardiology');
});

it('unwraps a Page parent before reading name and url', function (): void {
    $callbacks = clinicalExpertiseStructureCallbacks();

    $parent = Mockery::mock(Entry::class);
    $parent->shouldReceive('get')->with('title')->andReturn('Oncology');
    $parent->shouldReceive('url')->andReturn('/expertise/oncology');

    $page = Mockery::mock(Page::class);
    $page->shouldReceive('entry')->andReturn($parent);

    $child = Mockery::mock(Entry::class);
    $child->shouldReceive('parent')->andReturn($page);

    expect(($callbacks['parent_expertise_name'])($child, null))->toBe('Oncology')
        ->and(($callbacks['parent_expertise_url'])($child, null))->toBe('/expertise/oncology');
});

it('returns null when there is no parent entry', function (): void {
    $callbacks = clinicalExpertiseStructureCallbacks();

    $child = Mockery::mock(Entry::class);
    $child->shouldReceive('parent')->andReturn(null);

    expect(($callbacks['parent_expertise_name'])($child, null))->toBeNull()
        ->and(($callbacks['parent_expertise_url'])($child, null))->toBeNull();
});

it('returns null when a Page parent has no entry', function (): void {
    $callbacks = clinicalExpertiseStructureCallbacks();

    $page = Mockery::mock(Page::class);
    $page->shouldReceive('entry')->andReturn(null);

    $child = Mockery::mock(Entry::class);
    $child->shouldReceive('parent')->andReturn($page);

    expect(($callbacks['parent_expertise_name'])($child, null))->toBeNull()
        ->and(($callbacks['parent_expertise_url'])($child, null))->toBeNull();
});

it('returns null when parent title or url is not a string', function (): void {
    $callbacks = clinicalExpertiseStructureCallbacks();

    $parent = Mockery::mock(Entry::class);
    $parent->shouldReceive('get')->with('title')->andReturn(123);
    $parent->shouldReceive('url')->andReturn(['not', 'a', 'url']);

    $child = Mockery::mock(Entry::class);
    $child->shouldReceive('parent')->andReturn($parent);

    expect(($callbacks['parent_expertise_name'])($child, null))->toBeNull()
        ->and(($callbacks['parent_expertise_url'])($child, null))->toBeNull();
});

it('ignores a tree parent that is neither a Page nor an Entry', function (): void {
    // InstanceOfToTrue on parentEntry()'s final check would return this object.
    $callbacks = clinicalExpertiseStructureCallbacks();

    $bogus = Mockery::mock();
    $bogus->shouldReceive('url')->andReturn('/leaked-parent');
    $bogus->shouldReceive('get')->with('title')->andReturn('Leaked');

    $child = Mockery::mock(Entry::class);
    $child->shouldReceive('parent')->andReturn($bogus);

    expect(($callbacks['parent_expertise_name'])($child, null))->toBeNull()
        ->and(($callbacks['parent_expertise_url'])($child, null))->toBeNull();
});
