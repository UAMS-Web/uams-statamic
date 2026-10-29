<?php

declare(strict_types=1);

use App\Services\OnetWebServices\OnetOccupationOverviewService;
use App\Services\SchemaTemplates\Mergers\OnetSocOccupationCodeMerger;
use Mockery\MockInterface;

covers(OnetSocOccupationCodeMerger::class);

/**
 * Pins OnetSocOccupationCodeMerger's whole fragment for each overview shape.
 *
 * The fixture holds the fragment `main` built at `a969e6227` for the value
 * `VALUE-1` with no overview available. The overview service is mocked, so
 * no request leaves the process: an overview adds `name`, and a description
 * adds `description`, to the CategoryCode, and nothing else changes.
 *
 * @command  php artisan test --compact tests/Unit/Services/SchemaTemplates/Mergers/OnetSocOccupationCodeMergerMutationTest.php
 */

/**
 * Read the fragment pinned for the no-overview case.
 *
 * @return array{sourceKey: string, fragment: array<string, mixed>} Pinned source key and fragment.
 */
function pinnedOnetSocMergerFragment(): array
{
    $path = __DIR__.'/../../../../Fixtures/SchemaTemplates/onet-soc-merger-fragment.json';
    $json = file_get_contents($path);

    if ($json === false) {
        throw new RuntimeException('Cannot read the pinned fixture: '.$path);
    }

    /** @var array{sourceKey: string, fragment: array<string, mixed>} $decoded */
    $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

    return $decoded;
}

/**
 * Build the merger over a mocked overview service that answers once for `VALUE-1`.
 *
 * @param  array{title: string, description?: string}|null  $overview  What the service returns.
 * @return OnetSocOccupationCodeMerger The merger under test.
 */
function onetSocMergerReturning(?array $overview): OnetSocOccupationCodeMerger
{
    /** @var OnetOccupationOverviewService&MockInterface $service */
    $service = Mockery::mock(OnetOccupationOverviewService::class);
    $service->shouldReceive('overview')->once()->with('VALUE-1')->andReturn($overview);

    return new OnetSocOccupationCodeMerger($service);
}

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
 * Verify the mocked overview call counts; no booted test case does it here.
 */
afterEach(function (): void {
    Mockery::close();
});

it('reports the onet_soc_occupation_code source key', function (): void {
    /** @var OnetOccupationOverviewService&MockInterface $service */
    $service = Mockery::mock(OnetOccupationOverviewService::class);
    $service->shouldNotReceive('overview');

    expect((new OnetSocOccupationCodeMerger($service))->sourceKey())
        ->toBe(pinnedOnetSocMergerFragment()['sourceKey']);
});

it('builds the pinned fragment when no overview is available', function (): void {
    expect(onetSocMergerReturning(null)->buildFragment('VALUE-1'))
        ->toBe(pinnedOnetSocMergerFragment()['fragment']);
});

it('adds the overview title as the CategoryCode name', function (): void {
    $expected = pinnedOnetSocMergerFragment()['fragment'];
    /** @var array<string, mixed> $category */
    $category = $expected['occupationalCategory'];
    $category['name'] = 'Civil Engineers';
    $expected['occupationalCategory'] = $category;

    expect(onetSocMergerReturning(['title' => 'Civil Engineers'])->buildFragment('VALUE-1'))
        ->toBe($expected);
});

it('adds the overview description after the name', function (): void {
    $expected = pinnedOnetSocMergerFragment()['fragment'];
    /** @var array<string, mixed> $category */
    $category = $expected['occupationalCategory'];
    $category['name'] = 'Civil Engineers';
    $category['description'] = 'Perform engineering duties.';
    $expected['occupationalCategory'] = $category;

    expect(onetSocMergerReturning([
        'title' => 'Civil Engineers',
        'description' => 'Perform engineering duties.',
    ])->buildFragment('VALUE-1'))->toBe($expected);
});
