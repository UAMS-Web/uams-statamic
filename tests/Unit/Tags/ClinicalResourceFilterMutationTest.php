<?php

declare(strict_types=1);

use App\Support\People\ClinicalResourceProviderFilter;
use App\Tags\ClinicalResourceFilter;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteObject;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(ClinicalResourceFilter::class);

/**
 * Narrow mutate coverage for App\Tags\ClinicalResourceFilter.
 *
 * `ClinicalResourceProviderFilter` is final, so the tag is exercised against a
 * real filter with Entry/Site facade stubs.
 *
 * @command  php artisan test --compact tests/Unit/Tags/ClinicalResourceFilterMutationTest.php
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
 * Build the tag with a real shared filter and the given provider param.
 */
function clinicalResourceFilterTag(mixed $provider): ClinicalResourceFilter
{
    $tag = new ClinicalResourceFilter(new ClinicalResourceProviderFilter);
    $tag->setContext([]);
    $tag->setParameters(
        $provider === null ? [] : ['provider' => $provider]
    );

    return $tag;
}

it('returns empty for a missing, blank, or non-scalar provider', function (mixed $provider): void {
    EntryFacade::shouldReceive('query')->never();

    expect(clinicalResourceFilterTag($provider)->providerName())->toBe('');
})->with([
    'absent' => [null],
    'empty' => [''],
    'whitespace' => ['   '],
    'array' => [['spock']],
]);

it('returns empty when the slug resolves to no entry', function (): void {
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->once()->andReturn('default');
    Site::shouldReceive('current')->once()->andReturn($site);

    $peopleQuery = Mockery::mock();
    $peopleQuery->shouldReceive('where')->with('collection', 'people')->once()->andReturnSelf();
    $peopleQuery->shouldReceive('where')->with('site', 'default')->once()->andReturnSelf();
    $peopleQuery->shouldReceive('where')->with('slug', 'nobody')->once()->andReturnSelf();
    $peopleQuery->shouldReceive('get')->once()->andReturn([]);

    EntryFacade::shouldReceive('query')->once()->andReturn($peopleQuery);

    expect(clinicalResourceFilterTag('nobody')->providerName())->toBe('');
});

it('returns empty when find yields a non-Entry row', function (): void {
    // InstanceOfToTrue would treat a stdClass as a hit and call displayName().
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->once()->andReturn('default');
    Site::shouldReceive('current')->once()->andReturn($site);

    $peopleQuery = Mockery::mock();
    $peopleQuery->shouldReceive('where')->with('collection', 'people')->once()->andReturnSelf();
    $peopleQuery->shouldReceive('where')->with('site', 'default')->once()->andReturnSelf();
    $peopleQuery->shouldReceive('where')->with('slug', 'ghost')->once()->andReturnSelf();
    $peopleQuery->shouldReceive('get')->once()->andReturn([new stdClass]);

    EntryFacade::shouldReceive('query')->once()->andReturn($peopleQuery);

    expect(clinicalResourceFilterTag('ghost')->providerName())->toBe('');
});

it('returns the display name for a known public provider', function (): void {
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->once()->andReturn('default');
    Site::shouldReceive('current')->once()->andReturn($site);

    $provider = Mockery::mock(Entry::class);
    $provider->shouldReceive('status')->once()->andReturn('published');
    $provider->shouldReceive('value')->with('status_and_visibility')->once()->andReturn('public');
    $provider->shouldReceive('value')->with('password_protected')->once()->andReturn(false);
    $provider->shouldReceive('value')
        ->with('display_name_full_with_credentials')
        ->once()
        ->andReturn(['text' => 'Leonard McCoy']);

    $peopleQuery = Mockery::mock();
    $peopleQuery->shouldReceive('where')->with('collection', 'people')->once()->andReturnSelf();
    $peopleQuery->shouldReceive('where')->with('site', 'default')->once()->andReturnSelf();
    $peopleQuery->shouldReceive('where')->with('slug', 'mccoy')->once()->andReturnSelf();
    $peopleQuery->shouldReceive('get')->once()->andReturn([$provider]);

    EntryFacade::shouldReceive('query')->once()->andReturn($peopleQuery);

    expect(clinicalResourceFilterTag('  mccoy  ')->providerName())->toBe('Leonard McCoy');
});

it('casts a scalar provider code to string before lookup', function (): void {
    // RemoveStringCast on ``(string) $raw`` would leave an int and fail the slug where.
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->once()->andReturn('default');
    Site::shouldReceive('current')->once()->andReturn($site);

    $provider = Mockery::mock(Entry::class);
    $provider->shouldReceive('status')->once()->andReturn('published');
    $provider->shouldReceive('value')->with('status_and_visibility')->once()->andReturn('public');
    $provider->shouldReceive('value')->with('password_protected')->once()->andReturn(false);
    $provider->shouldReceive('value')
        ->with('display_name_full_with_credentials')
        ->once()
        ->andReturn(null);
    $provider->shouldReceive('get')->with('title')->once()->andReturn('Numeric Slug');

    $peopleQuery = Mockery::mock();
    $peopleQuery->shouldReceive('where')->with('collection', 'people')->once()->andReturnSelf();
    $peopleQuery->shouldReceive('where')->with('site', 'default')->once()->andReturnSelf();
    $peopleQuery->shouldReceive('where')->with('slug', '17')->once()->andReturnSelf();
    $peopleQuery->shouldReceive('get')->once()->andReturn([$provider]);

    EntryFacade::shouldReceive('query')->once()->andReturn($peopleQuery);

    expect(clinicalResourceFilterTag(17)->providerName())->toBe('Numeric Slug');
});
