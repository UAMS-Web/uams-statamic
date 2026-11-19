<?php

declare(strict_types=1);

use App\Support\People\ClinicalResourceProviderFilter;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Fields\Value;
use Statamic\Sites\Site as SiteObject;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(ClinicalResourceProviderFilter::class);

/**
 * The provider lookup, the related-resource ids and the notice name of
 * {@see ClinicalResourceProviderFilter}, pinned directly rather than through the
 * scope and tag that share it: the public-render gate one condition at a time, an
 * ineligible namesake ahead of an eligible one, the stored id shapes, and every
 * fallback of the display name.
 *
 * Pure Mockery doubles, so no content/ submodule.
 *
 * @command  php artisan test --compact tests/Unit/Support/People/ClinicalResourceProviderFilterTest.php
 */

/**
 * Stub the current site and the `people` query for one slug, returning `$rows`.
 */
function clinicalProviderQuery(string $slug, mixed $rows): void
{
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->once()->andReturn('default');
    Site::shouldReceive('current')->once()->andReturn($site);

    $query = Mockery::mock();
    $query->shouldReceive('where')->with('collection', 'people')->once()->andReturnSelf();
    $query->shouldReceive('where')->with('site', 'default')->once()->andReturnSelf();
    $query->shouldReceive('where')->with('slug', $slug)->once()->andReturnSelf();
    $query->shouldReceive('get')->once()->andReturn($rows);

    EntryFacade::shouldReceive('query')->once()->andReturn($query);
}

/**
 * A person double with the given status and visibility values.
 */
function clinicalProviderPerson(string $status = 'published', mixed $visibility = 'public', mixed $passwordProtected = false): Entry&MockInterface
{
    $person = Mockery::mock(Entry::class);
    $person->shouldReceive('status')->andReturn($status);
    $person->shouldReceive('value')->with('status_and_visibility')->andReturn($visibility);
    $person->shouldReceive('value')->with('password_protected')->andReturn($passwordProtected);

    return $person;
}

/**
 * A person double whose display-name computed and stored title are the given values.
 */
function clinicalProviderNamed(mixed $name, mixed $title): Entry&MockInterface
{
    $person = Mockery::mock(Entry::class);
    $person->shouldReceive('value')->with('display_name_full_with_clinical_credentials')->andReturn($name);
    $person->shouldReceive('get')->with('title')->andReturn($title);

    return $person;
}

it('finds nothing for a blank slug without querying', function (string $slug): void {
    EntryFacade::shouldReceive('query')->never();
    Site::shouldReceive('current')->never();

    expect((new ClinicalResourceProviderFilter)->find($slug))->toBeNull();
})->with([
    'empty' => [''],
    'whitespace' => ["  \t "],
]);

it('queries people on the current site for the trimmed slug and returns the public match', function (): void {
    $person = clinicalProviderPerson();
    clinicalProviderQuery('spock', [$person]);

    expect((new ClinicalResourceProviderFilter)->find('  spock  '))->toBe($person);
});

it('finds nothing when the query returns no iterable rows', function (): void {
    clinicalProviderQuery('spock', null);

    expect((new ClinicalResourceProviderFilter)->find('spock'))->toBeNull();
});

it('rejects a person who is not publicly renderable', function (Entry $person): void {
    clinicalProviderQuery('spock', [$person]);

    expect((new ClinicalResourceProviderFilter)->find('spock'))->toBeNull();
})->with([
    'unpublished' => fn (): Entry => clinicalProviderPerson(status: 'draft'),
    'private' => fn (): Entry => clinicalProviderPerson(visibility: 'private'),
    'password protected' => fn (): Entry => clinicalProviderPerson(passwordProtected: true),
]);

it('treats a non-boolean password flag and a non-private visibility as public', function (): void {
    // The checks are strict: only `'private'` and boolean `true` withhold a person.
    $person = clinicalProviderPerson(visibility: null, passwordProtected: 'true');
    clinicalProviderQuery('spock', [$person]);

    expect((new ClinicalResourceProviderFilter)->find('spock'))->toBe($person);
});

it('skips an ineligible namesake and a non-entry row to reach the public provider', function (): void {
    $public = clinicalProviderPerson();
    $later = clinicalProviderPerson();
    clinicalProviderQuery('spock', [new stdClass, clinicalProviderPerson(status: 'draft'), $public, $later]);

    expect((new ClinicalResourceProviderFilter)->find('spock'))->toBe($public);
});

it('reads the related resource ids in every stored shape', function (mixed $stored, array $expected): void {
    $provider = Mockery::mock(Entry::class);
    $provider->shouldReceive('get')->with('clinical_resources')->once()->andReturn($stored);

    expect((new ClinicalResourceProviderFilter)->resourceIds($provider))->toBe($expected);
})->with([
    'a list' => [['res-1', 'res-2'], ['res-1', 'res-2']],
    'a bare reference' => ['res-1', ['res-1']],
    'an empty reference' => ['', []],
    'nothing stored' => [null, []],
    'a non-string scalar' => [7, []],
    'a list with blanks and non-strings, re-indexed' => [['', 'res-1', 7, null, 'res-2'], ['res-1', 'res-2']],
]);

it('names the provider from the clinical display name, trimmed', function (mixed $name): void {
    expect((new ClinicalResourceProviderFilter)->displayName(clinicalProviderNamed($name, 'McCoy, Leonard')))
        ->toBe('Leonard McCoy, MD');
})->with([
    'a computed value' => fn (): Value => new Value(['text' => '  Leonard McCoy, MD  ']),
    'a raw text shape' => [['text' => 'Leonard McCoy, MD']],
]);

it('falls back to the trimmed title when the display name has no usable text', function (mixed $name): void {
    expect((new ClinicalResourceProviderFilter)->displayName(clinicalProviderNamed($name, '  McCoy, Leonard  ')))
        ->toBe('McCoy, Leonard');
})->with([
    'nothing' => [null],
    'a bare string' => ['Leonard McCoy, MD'],
    'no text key' => [['name' => 'Leonard McCoy, MD']],
    'non-string text' => [['text' => 7]],
    'blank text' => [['text' => '   ']],
    'a computed value with blank text' => fn (): Value => new Value(['text' => '']),
]);

it('names nobody when neither the display name nor the title is usable', function (): void {
    expect((new ClinicalResourceProviderFilter)->displayName(clinicalProviderNamed(null, null)))->toBe('');
});
