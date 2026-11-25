<?php

declare(strict_types=1);

use App\Tags\GlobalOrigin;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Data\Localization;
use Statamic\Contracts\Globals\GlobalSet as GlobalSetContract;
use Statamic\Contracts\Globals\Variables as GlobalVariablesContract;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteObject;
use Tests\TestCase;
use UAMSWeb\Aether\Forms\Settings\Support\FormRequestContext;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Tags\GlobalOrigin
 *
 * Mutation: no survivor on App\Tags\GlobalOrigin, without --covered-only.
 *
 * @command  php artisan test --compact tests/Feature/Tags/GlobalOriginTagTest.php
 */
it('index returns empty string when handle missing', function () {
    // Nothing is looked up without a handle
    GlobalSet::shouldReceive('find')->never();

    $tag = new GlobalOrigin;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->index())->toBe('');
});

it('index returns empty when global localization missing', function () {
    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')
        ->once()
        ->with('default')
        ->andReturn(null);

    GlobalSet::shouldReceive('find')
        ->once()
        ->with('uams_multisite_settings')
        ->andReturn($set);

    Site::shouldReceive('current')
        ->once()
        ->andReturn(
            new class
            {
                public function handle(): string
                {
                    return 'default';
                }
            }
        );

    $tag = new GlobalOrigin;
    $tag->setContext([]);
    $tag->setParameters([
        'handle' => 'uams_multisite_settings',
    ]);

    expect($tag->index())->toBe('');
});

it('index recursive returns empty array when subject is root', function () {
    $vars = Mockery::mock(GlobalVariablesContract::class);
    $vars->shouldReceive('get')
        ->with('subject')
        ->andReturn('root');

    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')
        ->once()
        ->with('default')
        ->andReturn($vars);

    GlobalSet::shouldReceive('find')
        ->once()
        ->with('uams_multisite_settings')
        ->andReturn($set);

    Site::shouldReceive('current')
        ->once()
        ->andReturn(
            new class
            {
                public function handle(): string
                {
                    return 'default';
                }
            }
        );

    $tag = new GlobalOrigin;
    $tag->setContext([]);
    $tag->setParameters([
        'handle' => 'uams_multisite_settings',
        'recursive' => true,
    ]);

    expect($tag->index())->toBe([]);
});

it('index recursive includes subject for each ancestor', function () {
    // The current site's origin chain leads to a root-level brand parent.
    $origin = Mockery::mock(Localization::class);
    $origin->shouldReceive('locale')
        ->once()
        ->andReturn('rootsite');

    $childVars = globalOriginVariables('major_entity', $origin);

    $rootVars = Mockery::mock(GlobalVariablesContract::class);
    $rootVars->shouldReceive('get')
        ->with('subject')
        ->andReturn('root');

    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')
        ->with('default')
        ->andReturn($childVars);
    $set->shouldReceive('in')
        ->with('rootsite')
        ->andReturn($rootVars);

    GlobalSet::shouldReceive('find')
        ->with('uams_multisite_settings')
        ->andReturn($set);

    Site::shouldReceive('current')
        ->once()
        ->andReturn(
            new class
            {
                public function handle(): string
                {
                    return 'default';
                }
            }
        );

    Site::shouldReceive('get')
        ->with('rootsite')
        ->andReturn(
            new class
            {
                public function name(): string
                {
                    return 'University of Arkansas for Medical Sciences';
                }

                public function absoluteUrl(): string
                {
                    return 'http://uams-statamic.test';
                }
            }
        );

    $tag = new GlobalOrigin;
    $tag->setContext([]);
    $tag->setParameters([
        'handle' => 'uams_multisite_settings',
        'recursive' => true,
    ]);

    $result = $tag->index();

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    expect(data_get($result, '0.title'))->toBe('University of Arkansas for Medical Sciences');
    expect(data_get($result, '0.url'))->toBe('http://uams-statamic.test');
    expect(data_get($result, '0.subject'))->toBe('root');
});

it('index non recursive returns origin locale string', function () {
    $origin = Mockery::mock(Localization::class);
    $origin->shouldReceive('locale')
        ->once()
        ->andReturn('parent-site');

    $vars = Mockery::mock(GlobalVariablesContract::class);
    $vars->shouldReceive('origin')
        ->once()
        ->andReturn($origin);

    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')
        ->once()
        ->with('default')
        ->andReturn($vars);

    GlobalSet::shouldReceive('find')
        ->once()
        ->with('uams_multisite_settings')
        ->andReturn($set);

    Site::shouldReceive('current')
        ->once()
        ->andReturn(
            new class
            {
                public function handle(): string
                {
                    return 'default';
                }
            }
        );

    $tag = new GlobalOrigin;
    $tag->setContext([]);
    $tag->setParameters([
        'handle' => 'uams_multisite_settings',
    ]);

    expect($tag->index())->toBe('parent-site');
});

it('index non recursive returns empty string when origin missing', function () {
    // A root brand's global set has no origin, so origin() returns null.
    $vars = Mockery::mock(GlobalVariablesContract::class);
    $vars->shouldReceive('origin')
        ->once()
        ->andReturn(null);

    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')
        ->once()
        ->with('default')
        ->andReturn($vars);

    GlobalSet::shouldReceive('find')
        ->once()
        ->with('uams_multisite_settings')
        ->andReturn($set);

    Site::shouldReceive('current')
        ->once()
        ->andReturn(
            new class
            {
                public function handle(): string
                {
                    return 'default';
                }
            }
        );

    $tag = new GlobalOrigin;
    $tag->setContext([]);
    $tag->setParameters([
        'handle' => 'uams_multisite_settings',
    ]);

    // Must be an empty string, not null, to satisfy the string|array return type.
    expect($tag->index())->toBe('');
});

it('index prefers the captured form-request site over the live current site', function () {
    // Simulates a queued form-email render (UAMS-Web/uams-statamic-archive#199/UAMS-Web/uams-statamic-archive#830): a FormRequestContext is active
    // carrying the submitter's site ('health') while the worker's live Site::current() is
    // 'default'. The tag must resolve the global set against the CAPTURED site, so its
    // default origin reflects the submitter, not the worker. The `->in('health')`
    // expectation is the proof: a regression that read Site::current() would query
    // `->in('default')`, which this mock never allows, and the assertion would fail.
    $origin = Mockery::mock(Localization::class);
    $origin->shouldReceive('locale')
        ->once()
        ->andReturn('parent-site');

    $vars = Mockery::mock(GlobalVariablesContract::class);
    $vars->shouldReceive('origin')
        ->once()
        ->andReturn($origin);

    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')
        ->once()
        ->with('health')
        ->andReturn($vars);

    GlobalSet::shouldReceive('find')
        ->once()
        ->with('uams_multisite_settings')
        ->andReturn($set);

    Site::shouldReceive('current')
        ->andReturn(
            new class
            {
                public function handle(): string
                {
                    return 'default';
                }
            }
        );

    $tag = new GlobalOrigin;
    $tag->setContext([]);
    $tag->setParameters([
        'handle' => 'uams_multisite_settings',
    ]);

    // Activate the captured submitter context around the render, exactly as the queued
    // SendFormNotification job does via FormRequestContext::during().
    $captured = new FormRequestContext(ip: '', referrer: '', query: [], siteHandle: 'health');

    expect($captured->during(fn () => $tag->index()))->toBe('parent-site');
});

it('falls back to the live current site when the captured form-request site is empty', function () {
    // An active FormRequestContext whose siteHandle is empty must NOT override the live
    // site — the tag falls back to Site::current() ('default'). This guards the
    // `siteHandle !== ''` condition so it cannot be mutated away.
    $origin = Mockery::mock(Localization::class);
    $origin->shouldReceive('locale')
        ->once()
        ->andReturn('parent-site');

    $vars = Mockery::mock(GlobalVariablesContract::class);
    $vars->shouldReceive('origin')
        ->once()
        ->andReturn($origin);

    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')
        ->once()
        ->with('default')
        ->andReturn($vars);

    GlobalSet::shouldReceive('find')
        ->once()
        ->with('uams_multisite_settings')
        ->andReturn($set);

    Site::shouldReceive('current')
        ->andReturn(
            new class
            {
                public function handle(): string
                {
                    return 'default';
                }
            }
        );

    $tag = new GlobalOrigin;
    $tag->setContext([]);
    $tag->setParameters([
        'handle' => 'uams_multisite_settings',
    ]);

    $captured = new FormRequestContext(ip: '', referrer: '', query: [], siteHandle: '');

    expect($captured->during(fn () => $tag->index()))->toBe('parent-site');
});

/**
 * Run the tag in recursive mode for the `default` site over the given global set localizations.
 *
 * @param  array<string, mixed>  $localizations  site handle => the set's variables there, or null
 * @param  array<string, mixed>  $sites  site handle => the site, or null
 * @param  bool  $setStays  Whether the set is still found after the current site's own lookup
 * @return string|array<int, array<string, string|null>>
 */
function globalOriginAncestors(array $localizations, array $sites, bool $setStays = true): string|array
{
    $current = Mockery::mock(SiteObject::class);
    $current->shouldReceive('handle')->andReturn('default');
    Site::shouldReceive('current')->andReturn($current);
    Site::shouldReceive('get')->andReturnUsing(fn (string $handle): mixed => $sites[$handle] ?? null);

    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')->andReturnUsing(fn (string $handle): mixed => $localizations[$handle] ?? null);
    $lookups = 0;
    GlobalSet::shouldReceive('find')
        ->with('uams_multisite_settings')
        ->andReturnUsing(function () use (&$lookups, $set, $setStays): ?GlobalSetContract {
            return ++$lookups === 1 || $setStays ? $set : null;
        });

    $tag = new GlobalOrigin;
    $tag->setContext([]);
    $tag->setParameters(['handle' => 'uams_multisite_settings', 'recursive' => true]);

    return $tag->index();
}

/**
 * An origin() answer that allows one pass of the tag's loop: the loop condition, then the body.
 *
 * A further read throws, so a loop that never leaves a site fails instead of running forever.
 * A Mockery call count would not do: it is checked when the test ends, which is never.
 */
function globalOriginOnePass(?object $origin): Closure
{
    $reads = 0;

    return function () use (&$reads, $origin): ?object {
        throw_if(++$reads > 2, RuntimeException::class, 'The origin chain did not move on.');

        return $origin;
    };
}

/**
 * Global variables with the given subject and origin.
 */
function globalOriginVariables(mixed $subject, ?object $origin): GlobalVariablesContract
{
    $vars = Mockery::mock(GlobalVariablesContract::class);
    $vars->shouldReceive('get')->with('subject')->andReturn($subject);
    $vars->shouldReceive('origin')->andReturnUsing(globalOriginOnePass($origin));

    return $vars;
}

/**
 * An origin at the given locale, whose own origin is the next one up.
 */
function globalOriginLocalization(mixed $locale, ?object $origin = null): Localization
{
    $localization = Mockery::mock(Localization::class);
    $localization->shouldReceive('locale')->andReturn($locale);
    $localization->shouldReceive('origin')->andReturnUsing(globalOriginOnePass($origin));

    return $localization;
}

/**
 * A site double with the given name and URL.
 */
function globalOriginSite(mixed $name, mixed $url): SiteObject
{
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('name')->andReturn($name);
    $site->shouldReceive('absoluteUrl')->andReturn($url);

    return $site;
}

it('returns the empty value of its mode when the global set does not exist', function (bool $recursive, string|array $expected): void {
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->andReturn('default');
    Site::shouldReceive('current')->andReturn($site);
    GlobalSet::shouldReceive('find')->with('missing_settings')->andReturn(null);

    $tag = new GlobalOrigin;
    $tag->setContext([]);
    $tag->setParameters(['handle' => 'missing_settings', 'recursive' => $recursive]);

    expect($tag->index())->toBe($expected);
})->with([
    'immediate origin' => [false, ''],
    'ancestor chain' => [true, []],
]);

it('lists ancestors from the outermost in, and stops at the first root-level brand', function (): void {
    // `beyond` is above the root-level brand, so it is never reached
    $root = globalOriginLocalization('root', globalOriginLocalization('beyond'));
    $parent = globalOriginLocalization('parent', $root);

    $ancestors = globalOriginAncestors(
        [
            'default' => globalOriginVariables('sub_entity', $parent),
            'parent' => globalOriginVariables('major_entity', null),
            'root' => globalOriginVariables('root', null),
            'beyond' => globalOriginVariables('root', null),
        ],
        [
            'parent' => globalOriginSite('Parent', 'https://parent.test'),
            'root' => globalOriginSite('Root', 'https://root.test'),
            'beyond' => globalOriginSite('Beyond', 'https://beyond.test'),
        ],
    );

    expect($ancestors)->toBe([
        ['title' => 'Root', 'url' => 'https://root.test', 'subject' => 'root'],
        ['title' => 'Parent', 'url' => 'https://parent.test', 'subject' => 'major_entity'],
    ]);
});

it('leaves the chain at a root-level brand instead of reading the same origin again', function (): void {
    $ancestors = globalOriginAncestors(
        [
            'default' => globalOriginVariables('sub_entity', globalOriginLocalization('root')),
            'root' => globalOriginVariables('root', null),
        ],
        ['root' => globalOriginSite('Root', 'https://root.test')],
    );

    expect($ancestors)->toBe([['title' => 'Root', 'url' => 'https://root.test', 'subject' => 'root']]);
});

it('ends the chain at an origin that is not a localization', function (): void {
    $ancestors = globalOriginAncestors(['default' => globalOriginVariables('sub_entity', new stdClass)], []);

    expect($ancestors)->toBe([]);
});

it('keeps an ancestor with no subject when its settings cannot be read', function (bool $setStays): void {
    // The set has no variables for `parent`
    $ancestors = globalOriginAncestors(
        ['default' => globalOriginVariables('sub_entity', globalOriginLocalization('parent'))],
        ['parent' => globalOriginSite('Parent', 'https://parent.test')],
        $setStays,
    );

    expect($ancestors)->toBe([['title' => 'Parent', 'url' => 'https://parent.test', 'subject' => null]]);
})->with([
    'the set is gone by the second lookup' => [false],
    'the set has no variables for the ancestor' => [true],
]);

it('casts a scalar ancestor title, url and subject to strings', function (): void {
    $ancestors = globalOriginAncestors(
        [
            'default' => globalOriginVariables('sub_entity', globalOriginLocalization('parent')),
            'parent' => globalOriginVariables(3, null),
        ],
        ['parent' => globalOriginSite(1, 2)],
    );

    expect($ancestors)->toBe([['title' => '1', 'url' => '2', 'subject' => '3']]);
});

it('reads an ancestor title and url that are not scalar as empty strings', function (): void {
    $ancestors = globalOriginAncestors(
        [
            'default' => globalOriginVariables('sub_entity', globalOriginLocalization('parent')),
            'parent' => globalOriginVariables(['not', 'scalar'], null),
        ],
        ['parent' => globalOriginSite(null, ['not', 'scalar'])],
    );

    expect($ancestors)->toBe([['title' => '', 'url' => '', 'subject' => null]]);
});

it('returns the immediate origin locale as a string, or empty when it is not scalar', function (mixed $locale, string $expected): void {
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->andReturn('default');
    Site::shouldReceive('current')->andReturn($site);

    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')->with('default')->andReturn(globalOriginVariables('sub_entity', globalOriginLocalization($locale)));
    GlobalSet::shouldReceive('find')->with('uams_multisite_settings')->andReturn($set);

    $tag = new GlobalOrigin;
    $tag->setContext([]);
    $tag->setParameters(['handle' => 'uams_multisite_settings']);

    expect($tag->index())->toBe($expected);
})->with([
    'a scalar that is not a string' => [5, '5'],
    'not scalar' => [['not', 'scalar'], ''],
]);
