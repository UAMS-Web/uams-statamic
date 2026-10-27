<?php

declare(strict_types=1);

use App\Services\Locations\CountyRegionMap;
use Statamic\Facades\Term;
use Statamic\Stache\Query\TermQueryBuilder;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\TermCollection;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

/**
 * Coverage for the county -> region inversion of the `regions` taxonomy
 * (App\Services\Locations\CountyRegionMap, UAMS-Web/uams-statamic-archive#962). Asserts the hand-checked spot
 * samples the acceptance criteria require: representative Arkansas counties
 * resolve to the region their `counties_covered` membership defines.
 *
 * Reads the real `regions` taxonomy, so it is env-gated on the content submodule
 * (initialized in CI's full suite); it skips when content is absent rather than
 * asserting against an empty taxonomy.
 *
 * @command  php artisan test --compact tests/Feature/Services/Locations/CountyRegionMapTest.php
 */
uses(TestCase::class);

beforeEach(function (): void {
    /** @var TermQueryBuilder $query */
    $query = Term::query();
    /** @var TermQueryBuilder $query */
    $query = $query->where('taxonomy', 'regions');

    if ($query->count() === 0) {
        ContentSubmodule::reportMissing('regions taxonomy');
    }
});

it('maps representative Arkansas counties to their region term (hand-checked samples)', function (): void {
    $map = new CountyRegionMap;

    expect($map->regionSlugForCountyName('Pulaski County'))->toBe('central-arkansas')
        ->and($map->regionSlugForCountyName('Clay County'))->toBe('northeast-arkansas')
        ->and($map->regionSlugForCountyName('Baxter County'))->toBe('northwest-arkansas')
        ->and($map->regionSlugForCountyName('Arkansas County'))->toBe('southeast-arkansas')
        ->and($map->regionSlugForCountyName('Calhoun County'))->toBe('southwest-arkansas');
});

it('matches county names case- and whitespace-insensitively', function (): void {
    expect((new CountyRegionMap)->regionSlugForCountyName('  pulaski   COUNTY '))->toBe('central-arkansas');
});

it('returns null for a county not covered by any region', function (): void {
    expect((new CountyRegionMap)->regionSlugForCountyName('Nowhere County'))->toBeNull();
});

/**
 * A region term double with the given slug and covered counties.
 */
function countyRegionTerm(mixed $slug, mixed $counties): LocalizedTerm
{
    $term = Mockery::mock(LocalizedTerm::class);
    $term->shouldReceive('slug')->andReturn($slug);
    $term->shouldReceive('get')->with('counties_covered')->andReturn($counties);

    return $term;
}

it('builds the map only from region terms with a slug and a county list', function (): void {
    Term::shouldReceive('whereTaxonomy')->once()->with('regions')->andReturn(TermCollection::make([
        'not a term',
        countyRegionTerm(42, ['Pulaski']),
        countyRegionTerm('', ['Saline']),
        countyRegionTerm('no-counties', 'Faulkner'),
        countyRegionTerm('central', ['  Lonoke  ', '', '   ', 7, 'Perry']),
    ]));

    $map = new CountyRegionMap;

    expect($map->regionSlugForCountyName('Lonoke'))->toBe('central')
        ->and($map->regionSlugForCountyName('perry'))->toBe('central')
        ->and($map->regionSlugForCountyName('Pulaski'))->toBeNull()
        ->and($map->regionSlugForCountyName('Saline'))->toBeNull()
        ->and($map->regionSlugForCountyName('Faulkner'))->toBeNull()
        // A blank county is skipped, so a blank lookup finds nothing.
        ->and($map->regionSlugForCountyName(''))->toBeNull()
        ->and($map->regionSlugForCountyName('   '))->toBeNull();
});
