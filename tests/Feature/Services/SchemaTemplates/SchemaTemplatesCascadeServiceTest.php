<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaBlueprintDefaultsRepository;
use App\Services\SchemaTemplates\SchemaSectionDefaultsRepository;
use App\Services\SchemaTemplates\SchemaSiteDefaultsRepository;
use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\GlobalSet;
use Statamic\Fields\Blueprint;
use Statamic\Globals\GlobalSet as GlobalSetModel;
use Statamic\Globals\Variables;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * `SchemaTemplatesCascadeService::resolve()` over stub defaults repositories: the
 * entity shapes it reads (entry, term, neither), the tri-state `enabled` cascade,
 * the single-entry-id normalization, and the multisite global overrides when the
 * global set or its localization is missing. The webpage/main-content precedence
 * itself is pinned in tests/Unit/Services/SchemaTemplates/SchemaTemplatesCascadePrecedenceTest.php.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaTemplatesCascadeServiceTest.php
 */

/**
 * A service over repositories that answer with the given site, section and
 * blueprint defaults, expecting the handles an entity resolves to.
 *
 * @param  array<array-key, mixed>  $site
 * @param  array<array-key, mixed>  $section
 * @param  array<array-key, mixed>  $blueprint
 */
function cascadeServiceWith(array $site, array $section = [], array $blueprint = [], string $siteHandle = 'default', string $type = 'collection'): SchemaTemplatesCascadeService
{
    $siteRepo = Mockery::mock(SchemaSiteDefaultsRepository::class);
    $siteRepo->shouldReceive('get')->with($siteHandle)->andReturn($site);
    $sectionRepo = Mockery::mock(SchemaSectionDefaultsRepository::class);
    $sectionRepo->shouldReceive('get')->with('pages', $type)->andReturn($section);
    $blueprintRepo = Mockery::mock(SchemaBlueprintDefaultsRepository::class);
    $blueprintRepo->shouldReceive('get')->with('pages', 'page', $type)->andReturn($blueprint);

    return new SchemaTemplatesCascadeService($siteRepo, $sectionRepo, $blueprintRepo);
}

/**
 * A service whose repositories must never be read, for entities that stop early.
 */
function cascadeServiceUnread(): SchemaTemplatesCascadeService
{
    $siteRepo = Mockery::mock(SchemaSiteDefaultsRepository::class);
    $siteRepo->shouldNotReceive('get');
    $sectionRepo = Mockery::mock(SchemaSectionDefaultsRepository::class);
    $sectionRepo->shouldNotReceive('get');
    $blueprintRepo = Mockery::mock(SchemaBlueprintDefaultsRepository::class);
    $blueprintRepo->shouldNotReceive('get');

    return new SchemaTemplatesCascadeService($siteRepo, $sectionRepo, $blueprintRepo);
}

/**
 * An entry in the `pages` collection on the given site, with the given blueprint
 * handle (or none) and `schema_templates` value.
 */
function cascadeEntry(mixed $schemaTemplates, ?string $blueprintHandle = 'page', mixed $locale = 'default'): EntryModel
{
    $collection = Mockery::mock(Collection::class);
    $collection->shouldReceive('handle')->andReturn('pages');

    $blueprint = null;
    if ($blueprintHandle !== null) {
        $blueprint = Mockery::mock(Blueprint::class);
        $blueprint->shouldReceive('handle')->andReturn($blueprintHandle);
    }

    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('locale')->andReturn($locale);
    $entry->shouldReceive('collection')->andReturn($collection);
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('get')->with('schema_templates')->andReturn($schemaTemplates);
    $entry->shouldReceive('get')->with('schema_medicalorganization_subtype')->andReturnNull();

    return $entry;
}

/**
 * Stub the multisite global set lookup: no set at all, or a set with no
 * localization for the requested site.
 */
function cascadeMultisiteGlobal(bool $setExists): void
{
    if (! $setExists) {
        GlobalSet::shouldReceive('findByHandle')->with('uams_multisite_settings')->andReturnNull();

        return;
    }

    $set = Mockery::mock(GlobalSetModel::class);
    $set->shouldReceive('in')->andReturnNull();
    GlobalSet::shouldReceive('findByHandle')->with('uams_multisite_settings')->andReturn($set);
}

const CASCADE_EMPTY = [
    'enabled' => false,
    'subject_entry' => null,
    'website_entry' => null,
    'webpage_entry' => null,
    'main_content_entry' => null,
];

it('resolves nothing without an entity', function () {
    expect(cascadeServiceUnread()->resolve(null))->toBe(CASCADE_EMPTY);
});

it('resolves nothing for an entity that is neither an entry nor a term', function () {
    expect(cascadeServiceUnread()->resolve(Mockery::mock(EntryContract::class)))->toBe(CASCADE_EMPTY);
});

it('resolves nothing for an entry with no blueprint', function () {
    expect(cascadeServiceUnread()->resolve(cascadeEntry([], null)))->toBe(CASCADE_EMPTY);
});

it('resolves a term from its own locale, taxonomy and blueprint', function () {
    cascadeMultisiteGlobal(false);
    $taxonomy = Mockery::mock(Taxonomy::class);
    $taxonomy->shouldReceive('handle')->andReturn('pages');
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('handle')->andReturn('page');

    $term = Mockery::mock(LocalizedTerm::class);
    $term->shouldReceive('locale')->andReturn('subsite');
    $term->shouldReceive('taxonomy')->andReturn($taxonomy);
    $term->shouldReceive('blueprint')->andReturn($blueprint);
    $term->shouldReceive('get')->with('schema_templates')->andReturn(['enabled' => 'true', 'inherit_webpage_entry' => false, 'webpage_entry' => ['term-page']]);

    $service = cascadeServiceWith(['subject_entry' => 'subject-1', 'website_entry' => 'site-1'], [], [], 'subsite', 'taxonomy');

    expect($service->resolve($term))->toBe([
        'enabled' => true,
        'subject_entry' => 'subject-1',
        'website_entry' => 'site-1',
        'webpage_entry' => 'term-page',
        'main_content_entry' => null,
    ]);
});

it('reads a term whose schema templates value is not an array as empty', function () {
    cascadeMultisiteGlobal(false);
    $taxonomy = Mockery::mock(Taxonomy::class);
    $taxonomy->shouldReceive('handle')->andReturn('pages');
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('handle')->andReturn('page');

    $term = Mockery::mock(LocalizedTerm::class);
    $term->shouldReceive('locale')->andReturn(['not', 'scalar']);
    $term->shouldReceive('taxonomy')->andReturn($taxonomy);
    $term->shouldReceive('blueprint')->andReturn($blueprint);
    $term->shouldReceive('get')->with('schema_templates')->andReturn('nope');

    $service = cascadeServiceWith(['enabled' => true, 'webpage_entry' => 'site-page'], [], [], '', 'taxonomy');

    expect($service->resolve($term))->toBe([
        'enabled' => true,
        'subject_entry' => null,
        'website_entry' => null,
        'webpage_entry' => 'site-page',
        'main_content_entry' => null,
    ]);
});

it('cascades enabled through true, false and inherit at each level', function (array $site, array $section, array $blueprint, mixed $entryEnabled, bool $expected) {
    cascadeMultisiteGlobal(false);
    $service = cascadeServiceWith($site, $section, $blueprint);
    $entryData = $entryEnabled === null ? [] : ['enabled' => $entryEnabled];

    expect($service->resolve(cascadeEntry($entryData))['enabled'])->toBe($expected);
})->with([
    'site on, all inherit' => [['enabled' => true], [], [], null, true],
    'site off, all inherit' => [[], [], [], null, false],
    'site on, section "false"' => [['enabled' => true], ['enabled' => 'false'], [], null, false],
    'site on, blueprint false' => [['enabled' => true], [], ['enabled' => false], null, false],
    'site off, entry "true"' => [[], [], [], 'true', true],
    'site off, entry true' => [[], [], [], true, true],
    'site on, entry "false"' => [['enabled' => true], [], [], 'false', false],
    'site on, entry inherit' => [['enabled' => true], ['enabled' => 'inherit'], [], 'inherit', true],
]);

it('normalizes a single entry id from a string, a list, or nothing usable', function (mixed $value, ?string $expected) {
    cascadeMultisiteGlobal(false);
    $service = cascadeServiceWith(['subject_entry' => $value]);

    expect($service->resolve(cascadeEntry([]))['subject_entry'])->toBe($expected);
})->with([
    'a string' => ['subject-1', 'subject-1'],
    'a number' => [42, '42'],
    'a list' => [['subject-2', 'ignored'], 'subject-2'],
    'a list led by a number' => [[7], '7'],
    'null' => [null, null],
    'an empty string' => ['', null],
    'an empty list' => [[], null],
    'a list led by an empty string' => [['', 'later'], null],
    'a list led by false' => [[false], null],
    'a list led by a list' => [[['nested']], null],
    'a non-scalar' => [new stdClass, null],
]);

it('keeps the site subject and website when the multisite global set or its localization is missing', function (bool $setExists) {
    cascadeMultisiteGlobal($setExists);
    $service = cascadeServiceWith(['subject_entry' => 'subject-1', 'website_entry' => 'site-1']);

    $resolved = $service->resolve(cascadeEntry([]));

    expect($resolved['subject_entry'])->toBe('subject-1')
        ->and($resolved['website_entry'])->toBe('site-1');
})->with([
    'no global set' => [false],
    'no localization for the site' => [true],
]);

it('reads an entry locale as a string site handle, or none when it is not scalar', function (mixed $locale, string $siteHandle) {
    cascadeMultisiteGlobal(false);
    $service = cascadeServiceWith(['subject_entry' => 'subject-1'], [], [], $siteHandle);

    expect($service->resolve(cascadeEntry([], 'page', $locale))['subject_entry'])->toBe('subject-1');
})->with([
    'a number' => [5, '5'],
    'a list' => [['default'], ''],
]);

it('reads a numeric term locale as a string site handle', function () {
    cascadeMultisiteGlobal(false);
    $taxonomy = Mockery::mock(Taxonomy::class);
    $taxonomy->shouldReceive('handle')->andReturn('pages');
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('handle')->andReturn('page');

    $term = Mockery::mock(LocalizedTerm::class);
    $term->shouldReceive('locale')->andReturn(7);
    $term->shouldReceive('taxonomy')->andReturn($taxonomy);
    $term->shouldReceive('blueprint')->andReturn($blueprint);
    $term->shouldReceive('get')->with('schema_templates')->andReturn([]);

    expect(cascadeServiceWith(['subject_entry' => 'subject-1'], [], [], '7', 'taxonomy')->resolve($term)['subject_entry'])->toBe('subject-1');
});

it('resolves nothing for a term with no blueprint', function () {
    $taxonomy = Mockery::mock(Taxonomy::class);
    $taxonomy->shouldReceive('handle')->andReturn('pages');

    $term = Mockery::mock(LocalizedTerm::class);
    $term->shouldReceive('locale')->andReturn('default');
    $term->shouldReceive('taxonomy')->andReturn($taxonomy);
    $term->shouldReceive('blueprint')->andReturnNull();
    $term->shouldReceive('get')->with('schema_templates')->andReturn([]);

    expect(cascadeServiceUnread()->resolve($term))->toBe(CASCADE_EMPTY);
});

it('routes an entry to the main content node mapped for its subtype', function () {
    cascadeMultisiteGlobal(false);
    $service = cascadeServiceWith([
        'main_content_entry' => 'flat-node',
        'main_content_entry_by_subtype' => ['MedicalClinic' => 'clinic-node'],
    ]);

    $collection = Mockery::mock(Collection::class);
    $collection->shouldReceive('handle')->andReturn('pages');
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('handle')->andReturn('page');
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('locale')->andReturn('default');
    $entry->shouldReceive('collection')->andReturn($collection);
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('get')->with('schema_templates')->andReturn([]);
    $entry->shouldReceive('get')->with('schema_medicalorganization_subtype')->andReturn('MedicalClinic');

    expect($service->resolve($entry)['main_content_entry'])->toBe('clinic-node');
});

it('takes the subject and website from the multisite global set when it sets them', function () {
    $localized = Mockery::mock(Variables::class);
    $localized->shouldReceive('get')->with('schema_subject')->andReturn(['global-subject']);
    $localized->shouldReceive('get')->with('schema_website')->andReturn('global-site');
    $set = Mockery::mock(GlobalSetModel::class);
    $set->shouldReceive('in')->with('default')->andReturn($localized);
    GlobalSet::shouldReceive('findByHandle')->with('uams_multisite_settings')->andReturn($set);

    $resolved = cascadeServiceWith(['subject_entry' => 'subject-1', 'website_entry' => 'site-1'])->resolve(cascadeEntry([]));

    expect($resolved['subject_entry'])->toBe('global-subject')
        ->and($resolved['website_entry'])->toBe('global-site');
});

it('keeps the site subject when the global set leaves it unset but sets the website', function () {
    $localized = Mockery::mock(Variables::class);
    $localized->shouldReceive('get')->with('schema_subject')->andReturnNull();
    $localized->shouldReceive('get')->with('schema_website')->andReturn('global-site');
    $set = Mockery::mock(GlobalSetModel::class);
    $set->shouldReceive('in')->with('default')->andReturn($localized);
    GlobalSet::shouldReceive('findByHandle')->with('uams_multisite_settings')->andReturn($set);

    $resolved = cascadeServiceWith(['subject_entry' => 'subject-1', 'website_entry' => 'site-1'])->resolve(cascadeEntry([]));

    expect($resolved['subject_entry'])->toBe('subject-1')
        ->and($resolved['website_entry'])->toBe('global-site');
});

it('treats a site enabled value as truthy without needing a bool', function (mixed $value, bool $expected) {
    cascadeMultisiteGlobal(false);

    expect(cascadeServiceWith(['enabled' => $value])->resolve(cascadeEntry([]))['enabled'])->toBe($expected);
})->with([
    'a truthy string' => ['yes', true],
    'one' => [1, true],
    'zero' => [0, false],
    'an empty string' => ['', false],
]);
