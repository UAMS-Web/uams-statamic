<?php

declare(strict_types=1);

use App\Http\Controllers\ClinicalExpertiseSectionController;
use App\Services\ClinicalExpertise\ClinicalExpertiseSectionResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Http\Controllers\FrontendController;
use Statamic\Sites\Site as SiteContract;
use Statamic\View\View;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * The security-critical publish/visibility gate for UAMS-Web/uams-statamic-archive#1058's section route: the
 * custom route bypasses Statamic's DataResponse + the app's ProtectEntry (UAMS-Web/uams-statamic-archive#223),
 * so it must render only published, publicly-visible AoE entries (everything else
 * delegates to the normal front end). These pin that gate with a mocked entry.
 *
 * @covers \App\Http\Controllers\ClinicalExpertiseSectionController
 *
 * @command  php artisan test --compact tests/Unit/Http/Controllers/ClinicalExpertiseSectionControllerTest.php
 */

/**
 * @param  array<string, mixed>  $values
 */
function gateEntry(string $status, array $values = []): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('status')->andReturn($status);
    $entry->shouldReceive('value')->andReturnUsing(fn (string $handle): mixed => $values[$handle] ?? null);

    return $entry;
}

function sectionController(): ClinicalExpertiseSectionController
{
    return new ClinicalExpertiseSectionController(Mockery::mock(ClinicalExpertiseSectionResolver::class));
}

it('renders a published, publicly-visible AoE entry', function () {
    expect(sectionController()->isPubliclyRenderable(gateEntry('published')))->toBeTrue();
});

it('refuses a draft AoE entry (no draft-content disclosure)', function () {
    expect(sectionController()->isPubliclyRenderable(gateEntry('draft')))->toBeFalse();
});

it('refuses a scheduled (future-dated) AoE entry', function () {
    expect(sectionController()->isPubliclyRenderable(gateEntry('scheduled')))->toBeFalse();
});

it('refuses a private AoE entry even when published', function () {
    expect(sectionController()->isPubliclyRenderable(gateEntry('published', ['status_and_visibility' => 'private'])))->toBeFalse();
});

it('refuses a password-protected AoE entry even when published', function () {
    expect(sectionController()->isPubliclyRenderable(gateEntry('published', ['password_protected' => true])))->toBeFalse();
});

/**
 * Point `Site::current()` at a site with the given handle.
 */
function sectionControllerSite(mixed $handle): void
{
    $site = Mockery::mock(SiteContract::class);
    $site->shouldReceive('handle')->andReturn($handle);
    Site::shouldReceive('current')->andReturn($site);
}

/**
 * Stand in for Statamic's front-end controller, which the section route falls back to.
 */
function sectionControllerFrontEnd(int $times): void
{
    $frontEnd = Mockery::mock(FrontendController::class);
    $frontEnd->shouldReceive('index')->times($times)->andReturn('the front end');
    app()->instance(FrontendController::class, $frontEnd);
}

/**
 * A controller whose resolver answers `resolve()` with the given payload, or is never asked.
 *
 * @param  array<string, mixed>|null  $payload
 */
function sectionControllerResolving(?array $payload, bool $asked = true): ClinicalExpertiseSectionController
{
    $resolver = Mockery::mock(ClinicalExpertiseSectionResolver::class);
    $resolver->shouldReceive('resolve')->times($asked ? 1 : 0)->with(Mockery::type(Entry::class), 'providers')->andReturn($payload);

    return new ClinicalExpertiseSectionController($resolver);
}

it('renders a resolved section of a renderable entry, in the section template under the site layout', function (string $uri): void {
    config(['statamic.system.layout' => 'probe_layout']);
    sectionControllerSite('default');
    sectionControllerFrontEnd(0);

    $aoe = gateEntry('published');
    $load = Mockery::mock(Entry::class);
    EntryFacade::shouldReceive('findByUri')->once()->with('/heart/care', 'default')->andReturn($aoe);
    EntryFacade::shouldReceive('find')->once()->with('load-id')->andReturn($load);

    $payload = ['load' => 'load-id', 'section' => 'providers'];
    $view = Mockery::mock(View::class);
    $view->shouldReceive('template')->once()->with('clinical_expertise.section');
    $view->shouldReceive('layout')->once()->with('probe_layout');
    $view->shouldReceive('with')->once()->with($payload);
    $view->shouldReceive('cascadeContent')->once()->with($load);
    $view->shouldReceive('render')->once()->andReturn('<p>section</p>');
    app()->instance(View::class, $view);

    $response = mustBe(Response::class, sectionControllerResolving($payload)(Request::create('/heart/care/providers'), $uri, 'providers'), 'the section response');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('<p>section</p>')
        ->and($response->headers->get('Content-Type'))->toBe('text/html; charset=UTF-8');
})->with([
    'a URI without a leading slash' => ['heart/care'],
    'a URI with one' => ['/heart/care'],
]);

it('cascades no entry when the payload names none to load', function (mixed $load): void {
    sectionControllerSite('default');
    sectionControllerFrontEnd(0);
    EntryFacade::shouldReceive('findByUri')->once()->andReturn(gateEntry('published'));
    EntryFacade::shouldReceive('find')->never();

    $view = Mockery::mock(View::class);
    $view->shouldReceive('template', 'layout', 'with');
    $view->shouldReceive('cascadeContent')->once()->with(null);
    $view->shouldReceive('render')->andReturn('rendered');
    app()->instance(View::class, $view);

    $response = sectionControllerResolving(['load' => $load])(Request::create('/heart/care/providers'), 'heart/care', 'providers');

    expect($response)->toBeInstanceOf(Response::class);
})->with([
    'no load key' => [null],
    'a load that is not an id' => [42],
]);

it('falls back to the front end when the section does not resolve', function (): void {
    sectionControllerSite('default');
    sectionControllerFrontEnd(1);
    EntryFacade::shouldReceive('findByUri')->once()->andReturn(gateEntry('published'));

    $result = sectionControllerResolving(null)(Request::create('/heart/care/providers'), 'heart/care', 'providers');

    expect($result)->toBe('the front end');
});

it('falls back to the front end without resolving when the entry is not renderable', function (string $status): void {
    sectionControllerSite('default');
    sectionControllerFrontEnd(1);
    EntryFacade::shouldReceive('findByUri')->once()->andReturn(gateEntry($status));

    $result = sectionControllerResolving(['load' => null], asked: false)(Request::create('/heart/care/providers'), 'heart/care', 'providers');

    expect($result)->toBe('the front end');
})->with(['draft', 'scheduled']);

it('falls back to the front end when the URI finds no entry, or one that is not a Statamic entry', function (mixed $found): void {
    sectionControllerSite('default');
    sectionControllerFrontEnd(1);
    EntryFacade::shouldReceive('findByUri')->once()->andReturn($found);

    $result = sectionControllerResolving(['load' => null], asked: false)(Request::create('/about/providers'), 'about', 'providers');

    expect($result)->toBe('the front end');
})->with([
    'nothing' => [null],
    'another kind of entry' => [fn (): Statamic\Contracts\Entries\Entry => Mockery::mock(Statamic\Contracts\Entries\Entry::class)],
]);

it('falls back to the front end without looking the URI up when the site has no string handle', function (): void {
    sectionControllerSite(null);
    sectionControllerFrontEnd(1);
    EntryFacade::shouldReceive('findByUri')->never();

    $result = sectionControllerResolving(['load' => null], asked: false)(Request::create('/heart/care/providers'), 'heart/care', 'providers');

    expect($result)->toBe('the front end');
});
