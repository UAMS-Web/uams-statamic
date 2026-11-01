<?php

declare(strict_types=1);

use App\Fieldtypes\PublishDependencyBanner;
use App\Services\PublishDependencies\PublishDependencyResolver;
use App\Services\PublishDependencies\PublishDependencyResult;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(PublishDependencyBanner::class);

/**
 * PublishDependencyBanner (UAMS-Web/uams-statamic-archive#1593) surfaces the suppressed state on the CP publish
 * form. Its preload() computes the banner meta from the entry being edited via
 * PublishDependencyResolver. Mocked service + parent entry — no content/ submodule
 * required.
 *
 * @command php artisan test --compact tests/Feature/Fieldtypes/PublishDependencyBannerTest.php
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
function publishDependencyBannerFieldtype(?Entry $parent): PublishDependencyBanner
{
    $fieldtype = new PublishDependencyBanner;
    $field = new Field('publish_dependency_banner', ['type' => 'publish_dependency_banner']);

    if ($parent instanceof Entry) {
        $field->setParent($parent);
    }

    $fieldtype->setField($field);

    return $fieldtype;
}

it('flags a suppressed entry with its redirect, sources, and policy', function (): void {
    $entry = Mockery::mock(Entry::class);

    $resolver = Mockery::mock(PublishDependencyResolver::class);
    $resolver->shouldReceive('resolve')->once()->with($entry)
        ->andReturn(new PublishDependencyResult('/clinical-resources', ['Dr. Alice', 'Dr. Bob'], true));
    app()->instance(PublishDependencyResolver::class, $resolver);

    expect(publishDependencyBannerFieldtype($entry)->preload())->toBe([
        'suppressed' => true,
        'redirect_url' => '/clinical-resources',
        'source_titles' => ['Dr. Alice', 'Dr. Bob'],
        'require_all' => true,
    ]);
});

it('shows no banner when the entry is not suppressed', function (): void {
    $entry = Mockery::mock(Entry::class);

    $resolver = Mockery::mock(PublishDependencyResolver::class);
    $resolver->shouldReceive('resolve')->once()->with($entry)->andReturnNull();
    app()->instance(PublishDependencyResolver::class, $resolver);

    expect(publishDependencyBannerFieldtype($entry)->preload())->toBe([
        'suppressed' => false,
        'redirect_url' => null,
        'source_titles' => [],
        'require_all' => true,
    ]);
});

it('shows no banner and never resolves without an entry parent', function (): void {
    $resolver = Mockery::mock(PublishDependencyResolver::class);
    $resolver->shouldNotReceive('resolve');
    app()->instance(PublishDependencyResolver::class, $resolver);

    expect(publishDependencyBannerFieldtype(null)->preload())->toBe([
        'suppressed' => false,
        'redirect_url' => null,
        'source_titles' => [],
        'require_all' => true,
    ]);
});

it('renders no banner and never dereferences when no field is bound', function (): void {
    try {
        $outcome = (new PublishDependencyBanner)->preload();
    } catch (Throwable $e) {
        $outcome = ['threw' => $e->getMessage()];
    }

    expect($outcome)->toBe([
        'suppressed' => false,
        'redirect_url' => null,
        'source_titles' => [],
        'require_all' => true,
    ]);
});
