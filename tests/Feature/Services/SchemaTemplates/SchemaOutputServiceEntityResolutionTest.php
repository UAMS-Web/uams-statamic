<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use Illuminate\Routing\Route;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Data;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Structures\Page;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SchemaOutputService::class);

/**
 * Pins two lookups of {@see SchemaOutputService}: `resolveEntity()`, which finds
 * the page's entry or term from the route's `data` parameter or the request URL
 * (a structure page resolving to its entry), and `templateInstanceRowIdentity()`,
 * which names the replicator row that asks for a template instance.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaOutputServiceEntityResolutionTest.php
 */

/**
 * Call a non-public method of the service.
 */
function sosEntityCall(string $method, mixed ...$args): mixed
{
    return (new ReflectionMethod(SchemaOutputService::class, $method))->invoke(app(SchemaOutputService::class), ...$args);
}

/**
 * Route the request with the given `data` parameter, or with no route at all.
 */
function sosEntityRoute(bool $routed, object|string|null $data = null): void
{
    if (! $routed) {
        request()->setRouteResolver(fn (): ?Route => null);

        return;
    }

    $route = new Route('GET', '/page', []);
    $route->bind(request());
    $route->setParameter('data', $data);
    request()->setRouteResolver(fn (): Route => $route);
}

/**
 * An entry double with the given id.
 */
function sosEntityEntry(string $id = 'entry-1'): EntryModel&MockInterface
{
    /** @var EntryModel&MockInterface $entry */
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn($id);

    return $entry;
}

it('resolves the entry or term the route passes as its data', function (Closure $make): void {
    $entity = $make();
    if (! is_object($entity)) {
        throw new LogicException('The dataset makes an entity object.');
    }
    sosEntityRoute(true, $entity);
    Data::partialMock()->shouldReceive('findByRequestUrl')->never();

    expect(sosEntityCall('resolveEntity'))->toBe($entity);
})->with([
    'an entry' => [fn (): EntryContract => sosEntityEntry()],
    'a term' => [fn (): Term => Mockery::mock(Term::class)],
]);

it('resolves the entry or term the request URL finds when the route passes none', function (bool $routed, ?string $routeData, Closure $make): void {
    $entity = $make();
    sosEntityRoute($routed, $routeData);
    Data::partialMock()->shouldReceive('findByRequestUrl')->once()->andReturn($entity);

    expect(sosEntityCall('resolveEntity'))->toBe($entity);
})->with([
    'no route, an entry' => [false, null, fn (): EntryContract => sosEntityEntry()],
    'a route without an entity, a term' => [true, 'slug', fn (): Term => Mockery::mock(Term::class)],
]);

it('resolves a structure page the request URL finds to its entry', function (): void {
    $entry = sosEntityEntry('entry-9');
    /** @var Page&MockInterface $page */
    $page = Mockery::mock(Page::class);
    $page->shouldReceive('id')->andReturn('entry-9');
    sosEntityRoute(false);
    Data::partialMock()->shouldReceive('findByRequestUrl')->once()->andReturn($page);
    EntryFacade::partialMock()->shouldReceive('find')->once()->with('entry-9')->andReturn($entry);

    expect(sosEntityCall('resolveEntity'))->toBe($entry);
});

it('resolves nothing when the request URL finds neither an entry nor a term', function (mixed $found): void {
    sosEntityRoute(false);
    Data::partialMock()->shouldReceive('findByRequestUrl')->once()->andReturn($found);

    expect(sosEntityCall('resolveEntity'))->toBeNull();
})->with([
    'nothing' => [null],
    'another object' => [new stdClass],
]);

it('names a replicator row by the context entity, the requested index and its own id', function (): void {
    expect(sosEntityCall('templateInstanceRowIdentity', sosEntityEntry('entry-1'), 2, ['id' => 'row-7']))
        ->toBe("entry-1\0"."2\0".'row-7');
});

it('names no replicator row without an id of its own', function (array $context): void {
    expect(sosEntityCall('templateInstanceRowIdentity', sosEntityEntry('entry-1'), 2, $context))->toBeNull();
})->with([
    'no id' => [[]],
    'an empty id' => [['id' => '']],
    'a numeric id' => [['id' => 7]],
    'the context entity id' => [['id' => 'entry-1']],
]);
