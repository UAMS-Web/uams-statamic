<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplateInstanceWatermarkBag;
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
 * Pins the lookups and identities of {@see SchemaOutputService}: `resolveEntity()`,
 * which finds the page's entry or term from the route's `data` parameter or the
 * request URL (a structure page resolving to its entry); `normalizeEntryId()`,
 * which reads a stored slot value as an entry id; `templateInstanceRowIdentity()`,
 * which names the replicator row that asks for a template instance; and
 * `resolveEffectiveTemplateInstanceIndexForWatermark()`, which issues the index.
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

it('normalizes a stored slot value to an entry id', function (mixed $stored, ?string $expected): void {
    expect(sosEntityCall('normalizeEntryId', $stored))->toBe($expected);
})->with([
    'an id' => ['entry-1', 'entry-1'],
    'a number' => [7, '7'],
    'a Stringable' => [new class implements Stringable
    {
        public function __toString(): string
        {
            return 'entry-2';
        }
    }, 'entry-2'],
    'another object' => [new stdClass, null],
    'null' => [null, null],
    'an empty string' => ['', null],
    'a list holding an id' => [['entry-3', 'entry-4'], 'entry-3'],
    'a list holding a number' => [[8], '8'],
    'a list holding a Stringable' => [[new class implements Stringable
    {
        public function __toString(): string
        {
            return 'entry-5';
        }
    }], 'entry-5'],
    'a list holding another object' => [[new stdClass], null],
    'a list holding false' => [[false], null],
    'a list holding null' => [[null], null],
    'a list holding an empty string' => [[''], null],
    'an empty list' => [[], null],
]);

/**
 * Resolve the effective template instance index for one emit of `tpl-1` under `entry-1`.
 *
 * @param  array<string, mixed>  $context
 */
function sosEntityIndex(array $context, ?SchemaTemplateInstanceWatermarkBag $bag, bool $commit = true): mixed
{
    return sosEntityCall('resolveEffectiveTemplateInstanceIndexForWatermark', 'tpl-1', sosEntityEntry('entry-1'), ['s1'], $context, $bag, $commit);
}

/**
 * The watermark allocation key for `tpl-1` under `entry-1`.
 */
function sosEntityIndexKey(): string
{
    return mustBeString(sosEntityCall('templateInstanceWatermarkAllocationKey', 'tpl-1', ['s1'], sosEntityEntry('entry-1')), 'the allocation key');
}

it('resolves no template instance index for an unusable one, even with a watermark bag', function (mixed $index): void {
    expect(sosEntityIndex([SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX => $index], new SchemaTemplateInstanceWatermarkBag))->toBeNull();
})->with([
    'a negative index' => [-1],
    'text' => ['first'],
]);

it('takes the requested template instance index as it is without a watermark bag', function (): void {
    expect(sosEntityIndex([SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX => 2], null))->toBe(2);
});

it('issues each repeated template instance index past the last one committed', function (): void {
    $bag = new SchemaTemplateInstanceWatermarkBag;
    $at = fn (int $index, bool $commit = true): mixed => sosEntityIndex([SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX => $index], $bag, $commit);

    expect([$at(0), $at(0, false), $at(0), $at(5), $at(2)])->toBe([0, 1, 1, 5, 6])
        ->and($bag->lastIssuedByKey)->toBe([sosEntityIndexKey() => 6])
        ->and($bag->issuedByRowIdentity)->toBe([]);
});

it('reissues the index a replicator row already holds, and commits a new row\'s index for it', function (): void {
    $bag = new SchemaTemplateInstanceWatermarkBag;
    $row = fn (string $id, bool $commit = true): mixed => sosEntityIndex([SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX => 0, 'id' => $id], $bag, $commit);

    expect([$row('row-1'), $row('row-2', false), $row('row-1'), $row('row-2')])->toBe([0, 1, 0, 1])
        ->and($bag->issuedByRowIdentity)->toBe([sosEntityIndexKey() => ["entry-1\0"."0\0".'row-1' => 0, "entry-1\0"."0\0".'row-2' => 1]]);
});

it('reissues no index for a row without an id of its own', function (): void {
    $bag = new SchemaTemplateInstanceWatermarkBag;
    $bag->issuedByRowIdentity[sosEntityIndexKey()][''] = 9;

    expect(sosEntityIndex([SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX => 0], $bag))->toBe(0);
});
