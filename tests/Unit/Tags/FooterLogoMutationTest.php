<?php

declare(strict_types=1);

use App\Support\Footer\FooterLogoResolver;
use App\Tags\FooterLogo;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteObject;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(FooterLogo::class);

/**
 * Narrow mutate coverage for App\Tags\FooterLogo.
 *
 * @command  php artisan test --compact tests/Unit/Tags/FooterLogoMutationTest.php
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

it('exposes the resolved owner site handle, link, and text', function (): void {
    $current = Mockery::mock(SiteObject::class);
    $current->shouldReceive('handle')->once()->andReturn('child');

    $owner = Mockery::mock(SiteObject::class);
    $owner->shouldReceive('absoluteUrl')->once()->andReturn('https://example.test/');
    $owner->shouldReceive('name')->once()->andReturn('Owner Site');

    Site::shouldReceive('current')->once()->andReturn($current);
    Site::shouldReceive('get')->once()->with('owner')->andReturn($owner);

    $resolver = Mockery::mock(FooterLogoResolver::class);
    $resolver->shouldReceive('resolveOwnerHandle')->once()->with('child')->andReturn('owner');
    app()->instance(FooterLogoResolver::class, $resolver);

    expect((new FooterLogo)->index())->toBe([
        'handle' => 'owner',
        'link' => 'https://example.test/',
        'text' => 'Owner Site',
    ]);
});

it('falls back to the current site when the owner handle is missing', function (): void {
    $current = Mockery::mock(SiteObject::class);
    $current->shouldReceive('handle')->once()->andReturn('child');
    $current->shouldReceive('absoluteUrl')->once()->andReturn('https://child.test/');
    $current->shouldReceive('name')->once()->andReturn('Child');

    Site::shouldReceive('current')->once()->andReturn($current);
    Site::shouldReceive('get')->once()->with('gone')->andReturn(null);

    $resolver = Mockery::mock(FooterLogoResolver::class);
    $resolver->shouldReceive('resolveOwnerHandle')->once()->with('child')->andReturn('gone');
    app()->instance(FooterLogoResolver::class, $resolver);

    expect((new FooterLogo)->index())->toBe([
        'handle' => 'child',
        'link' => 'https://child.test/',
        'text' => 'Child',
    ]);
});

it('stringifies scalar site accessors and blanks non-scalars', function (): void {
    $current = Mockery::mock(SiteObject::class);
    $current->shouldReceive('handle')->once()->andReturn(42);

    $owner = Mockery::mock(SiteObject::class);
    $owner->shouldReceive('absoluteUrl')->once()->andReturn(['not', 'a', 'string']);
    $owner->shouldReceive('name')->once()->andReturn(null);

    Site::shouldReceive('current')->once()->andReturn($current);
    Site::shouldReceive('get')->once()->with('')->andReturn($owner);

    $resolver = Mockery::mock(FooterLogoResolver::class);
    $resolver->shouldReceive('resolveOwnerHandle')->once()->with('42')->andReturn('');
    app()->instance(FooterLogoResolver::class, $resolver);

    expect((new FooterLogo)->index())->toBe([
        'handle' => '',
        'link' => '',
        'text' => '',
    ]);
});
