<?php

declare(strict_types=1);

use App\Support\Navigation\SubsectionResolver;
use App\Tags\Subsection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Antlers;
use Statamic\Fields\Value;
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

/**
 * Run the tag directly with the given params and context.
 *
 * @param  array<string, mixed>  $params
 * @param  array<string, mixed>  $context
 * @return array<string, mixed>
 */
function subsectionTag(array $params = [], array $context = []): array
{
    $tag = new Subsection;
    $tag->setContext($context);
    $tag->setParameters($params);

    return $tag->index();
}

/**
 * Bind a resolver that expects one page id and returns the given root.
 */
function subsectionResolverFor(int|string $id, ?Entry $root): void
{
    $resolver = Mockery::mock(SubsectionResolver::class);
    $resolver->shouldReceive('resolveRoot')->once()->with($id)->andReturn($root);
    app()->instance(SubsectionResolver::class, $resolver);
}

/**
 * A root entry whose accessors return the given values.
 */
function subsectionRoot(mixed $id, mixed $url, mixed $uri, mixed $title): Entry
{
    $root = Mockery::mock(Entry::class);
    $root->shouldReceive('id')->andReturn($id);
    $root->shouldReceive('url')->andReturn($url);
    $root->shouldReceive('uri')->andReturn($uri);
    $root->shouldReceive('value')->with('title')->andReturn($title);

    return $root;
}

it('returns the full inactive shape when no root resolves', function (): void {
    subsectionResolverFor('page-id', null);

    expect(subsectionTag([], ['id' => 'page-id']))->toBe([
        'active' => false,
        'id' => null,
        'url' => null,
        'uri' => null,
        'title' => null,
    ]);
});

it('returns every field of an active root, ids as strings', function (): void {
    subsectionResolverFor('page-id', subsectionRoot(7, '/about', '/about', 'About UAMS'));

    expect(subsectionTag(['id' => 'page-id']))->toBe([
        'active' => true,
        'id' => '7',
        'url' => '/about',
        'uri' => '/about',
        'title' => 'About UAMS',
    ]);
});

it('narrows false, empty and non-scalar accessor values to null', function (): void {
    subsectionResolverFor('page-id', subsectionRoot(false, '', ['not', 'a', 'string'], null));

    expect(subsectionTag(['id' => 'page-id']))->toBe([
        'active' => true,
        'id' => null,
        'url' => null,
        'uri' => null,
        'title' => null,
    ]);
});

it('prefers the :id param over the context id', function (): void {
    subsectionResolverFor('param-id', null);

    expect(subsectionTag(['id' => 'param-id'], ['id' => 'context-id'])['active'])->toBeFalse();
});

it('falls back to the context id when the :id param is not a usable id', function (mixed $param): void {
    subsectionResolverFor('context-id', null);

    expect(subsectionTag(['id' => $param], ['id' => 'context-id'])['active'])->toBeFalse();
})->with([
    'an empty string' => [''],
    'an array' => [['page-id']],
]);

it('passes an integer id through unchanged', function (): void {
    subsectionResolverFor(42, null);

    expect(subsectionTag(['id' => 42])['active'])->toBeFalse();
});

it('unwraps a context id held in a Value', function (): void {
    subsectionResolverFor('page-id', null);

    expect(subsectionTag([], ['id' => new Value('page-id')])['active'])->toBeFalse();
});
