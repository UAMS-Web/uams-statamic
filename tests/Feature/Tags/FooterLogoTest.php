<?php

declare(strict_types=1);

use App\Support\Footer\FooterLogoResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Antlers;
use Statamic\Facades\Site;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Wiring coverage for the `{{ footer_logo }}` tag (UAMS-Web/uams-statamic-archive#46): it resolves the owner
 * handle via App\Support\Footer\FooterLogoResolver and exposes the owner site's
 * link + display name. The resolution logic lives in FooterLogoResolverTest;
 * this proves the tag plumbing and the missing-site fallback.
 *
 * @covers \App\Tags\FooterLogo
 *
 * @command  php artisan test --compact tests/Feature/Tags/FooterLogoTest.php
 */
it('exposes the resolved owner site link and text', function (): void {
    /** @var Statamic\Sites\Site $current */
    $current = Site::current();

    $resolver = Mockery::mock(FooterLogoResolver::class);
    $resolver->shouldReceive('resolveOwnerHandle')
        ->with($current->handle())
        ->andReturn($current->handle());
    app()->instance(FooterLogoResolver::class, $resolver);

    $out = (string) Antlers::parse('{{ footer_logo }}{{ handle }}|{{ link }}|{{ text }}{{ /footer_logo }}');

    /** @var string $handle */
    $handle = $current->handle();
    /** @var string $absoluteUrl */
    $absoluteUrl = $current->absoluteUrl();
    /** @var string $name */
    $name = $current->name();
    expect($out)->toBe($handle.'|'.$absoluteUrl.'|'.$name);
});

it('falls back to the current site when the resolved handle is not a site', function (): void {
    /** @var Statamic\Sites\Site $current */
    $current = Site::current();

    $resolver = Mockery::mock(FooterLogoResolver::class);
    $resolver->shouldReceive('resolveOwnerHandle')->andReturn('definitely_not_a_site_xyz');
    app()->instance(FooterLogoResolver::class, $resolver);

    $out = (string) Antlers::parse('{{ footer_logo }}{{ handle }}{{ /footer_logo }}');

    expect($out)->toBe($current->handle());
});
