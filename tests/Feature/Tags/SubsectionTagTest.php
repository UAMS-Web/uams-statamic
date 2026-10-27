<?php

declare(strict_types=1);

use App\Support\Navigation\SubsectionResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Wiring coverage for the `{{ subsection }}` tag (UAMS-Web/uams-statamic-archive#52): it resolves the page id
 * (from `:id` or context), delegates to App\Support\Navigation\SubsectionResolver,
 * and exposes active/url/uri/title. Resolution logic lives in
 * SubsectionResolverTest.
 *
 * @covers \App\Tags\Subsection
 *
 * @command  php artisan test --compact tests/Feature/Tags/SubsectionTagTest.php
 */
it('exposes the resolved subsection root when active', function (): void {
    $root = Mockery::mock(Entry::class);
    $root->shouldReceive('id')->andReturn('root-id');
    $root->shouldReceive('url')->andReturn('/about');
    $root->shouldReceive('uri')->andReturn('/about');
    $root->shouldReceive('value')->with('title')->andReturn('About UAMS');

    $resolver = Mockery::mock(SubsectionResolver::class);
    $resolver->shouldReceive('resolveRoot')->with('page-id')->andReturn($root);
    app()->instance(SubsectionResolver::class, $resolver);

    $out = (string) Antlers::parse(
        '{{ subsection :id="id" }}{{ if active }}active{{ /if }}|{{ url }}|{{ uri }}|{{ title }}{{ /subsection }}',
        ['id' => 'page-id']
    );

    expect($out)->toBe('active|/about|/about|About UAMS');
});

it('reports inactive when the page is outside any subsection', function (): void {
    $resolver = Mockery::mock(SubsectionResolver::class);
    $resolver->shouldReceive('resolveRoot')->with('page-id')->andReturnNull();
    app()->instance(SubsectionResolver::class, $resolver);

    $out = (string) Antlers::parse(
        '{{ subsection }}{{ if active }}yes{{ else }}no{{ /if }}|{{ url }}{{ /subsection }}',
        ['id' => 'page-id']
    );

    // Falls back to the context `id` (no :id param), resolves to inactive.
    expect($out)->toBe('no|');
});

it('does not resolve when no page id is available', function (): void {
    $resolver = Mockery::mock(SubsectionResolver::class);
    // No :id param and no context id → the resolver is never consulted.
    $resolver->shouldNotReceive('resolveRoot');
    app()->instance(SubsectionResolver::class, $resolver);

    $out = (string) Antlers::parse('{{ subsection }}{{ if active }}yes{{ else }}no{{ /if }}{{ /subsection }}');

    expect($out)->toBe('no');
});
