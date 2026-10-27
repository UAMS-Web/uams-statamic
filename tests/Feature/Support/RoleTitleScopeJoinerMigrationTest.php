<?php

declare(strict_types=1);

use App\Fieldtypes\RoleTitle;
use App\Rules\RoleTitleScopeJoinerRule;
use App\Services\People\RoleTitleScopeJoinerMigrator;
use App\Support\RoleTitleDefinition;
use App\Support\RoleTitleValue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Assert;
use Statamic\Contracts\Entries\Collection as CollectionContract;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Field;
use Statamic\Stache\Repositories\EntryRepository as StacheEntryRepository;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The scope joiner stores a preposition, and the article is separate (UAMS-Web/uams-statamic-archive#2546).
 *
 * A linked unit always takes `the`; a typed scope carries its own `scope_article`.
 * `role-title:collapse-scope-joiners` rewrites the five legacy combined joiners
 * without changing a composed title, and reports the one way a title can still
 * move -- through the object, which only a linked unit now suppresses.
 *
 * @command  php artisan test --compact tests/Feature/Support/RoleTitleScopeJoinerMigrationTest.php
 */

/**
 * An institutional leadership title payload with the given scope and object.
 *
 * @param  array<string, mixed>  $scope
 * @return array<string, mixed>
 */
function collapseTitle(array $scope, ?string $object = null): array
{
    return [
        'configuration' => RoleTitleValue::CONFIGURATION_ACADEMIC_LEADERSHIP,
        'title_options' => [],
        'hierarchical_modifier' => null,
        'object' => $object,
        'positional_qualifier' => null,
        'role' => 'chair',
        'scope' => $scope,
    ];
}

/**
 * Collapse one row and return the result.
 *
 * @param  array<string, mixed>  $title
 * @return array{data: array<array-key, mixed>, changed: bool, findings: list<array{entry: string, path: string, status: string, from: string, to: string, article: string|null}>}
 */
function collapseRow(array $title): array
{
    return app(RoleTitleScopeJoinerMigrator::class)->collapseInData(['job_title' => [['title' => $title]]], 'kirk');
}

/**
 * Serve one `organizations` unit named `Department of Pediatrics`.
 */
function serveCollapseUnit(): void
{
    $unit = new class extends Entry
    {
        public function __construct() {}

        /**
         * @param  string  $key
         * @return mixed
         */
        public function value($key)
        {
            return $key === 'public_label' ? 'Department of Pediatrics' : null;
        }

        /**
         * @param  string|null  $id
         * @return string
         */
        public function id($id = null)
        {
            return 'peds';
        }

        public function collectionHandle(): string
        {
            return 'organizations';
        }
    };

    EntryFacade::partialMock()
        ->shouldReceive('find')
        ->andReturnUsing(static fn (string $id): ?Entry => $id === 'peds' ? $unit : null);
}

// ---
// The migration's unchanged-output guarantee
// ---

it('collapses each legacy typed joiner to a preposition and article that compose the same title', function (string $legacy, string $joiner, ?string $article, string $title): void {
    $result = collapseRow(collapseTitle(['scope_joiner' => $legacy, 'scope_name' => 'Northwest Arkansas region']));
    $collapsed = data_get($result, 'data.job_title.0.title');

    Assert::assertIsArray($collapsed);

    expect(data_get($collapsed, 'scope.scope_joiner'))->toBe($joiner)
        ->and(data_get($collapsed, 'scope.scope_article'))->toBe($article)
        ->and(data_get($collapsed, 'scope.scope_name'))->toBe('Northwest Arkansas region')
        ->and(data_get($result, 'findings.0.status'))->toBe(RoleTitleScopeJoinerMigrator::COLLAPSED)
        ->and($result['changed'])->toBeTrue()
        // The title each legacy joiner composed, written out rather than recomputed
        ->and(RoleTitleValue::composeFromMixed($collapsed))->toBe($title);
})->with([
    'for_without_the' => ['for_without_the', 'for', null, 'chair for Northwest Arkansas region'],
    'for_with_the' => ['for_with_the', 'for', 'the', 'chair for the Northwest Arkansas region'],
    'of_without_the' => ['of_without_the', 'of', null, 'chair of Northwest Arkansas region'],
    'of_with_the' => ['of_with_the', 'of', 'the', 'chair of the Northwest Arkansas region'],
    'in_the' => ['in_the', 'in', 'the', 'chair in the Northwest Arkansas region'],
]);

it('collapses a legacy linked joiner and leaves the article to the link', function (string $legacy, string $joiner, string $title): void {
    serveCollapseUnit();

    $result = collapseRow(collapseTitle(['scope_joiner' => $legacy, 'scope_unit' => 'peds', 'scope_name' => '']));
    $collapsed = data_get($result, 'data.job_title.0.title');

    expect(data_get($collapsed, 'scope.scope_joiner'))->toBe($joiner)
        ->and(data_get($collapsed, 'scope.scope_article'))->toBeNull()
        ->and(data_get($result, 'findings.0.status'))->toBe(RoleTitleScopeJoinerMigrator::COLLAPSED)
        ->and(RoleTitleValue::composeFromMixed($collapsed))->toBe($title);
})->with([
    'of_with_the' => ['of_with_the', 'of', 'chair of the Department of Pediatrics'],
    'for_with_the' => ['for_with_the', 'for', 'chair for the Department of Pediatrics'],
    'in_the' => ['in_the', 'in', 'chair in the Department of Pediatrics'],
]);

it('reports a linked scope whose legacy joiner had no article, since its title gains one', function (): void {
    serveCollapseUnit();

    $result = collapseRow(collapseTitle(['scope_joiner' => 'of_without_the', 'scope_unit' => 'peds', 'scope_name' => '']));

    expect(data_get($result, 'findings.0.status'))->toBe(RoleTitleScopeJoinerMigrator::GAINS_ARTICLE)
        ->and(data_get($result, 'findings.0.from'))->toBe('of_without_the')
        ->and(data_get($result, 'findings.0.to'))->toBe('of');
});

it('reports a typed article scope beside an object, since the object now composes', function (): void {
    $result = collapseRow(collapseTitle(['scope_joiner' => 'of_with_the', 'scope_name' => 'Academic Affairs'], 'department'));

    expect(data_get($result, 'findings.0.status'))->toBe(RoleTitleScopeJoinerMigrator::OBJECT_WOULD_APPEAR);
});

it('reports a linked scope beside an object, which validation now refuses', function (): void {
    serveCollapseUnit();

    $result = collapseRow(collapseTitle(['scope_joiner' => 'of_with_the', 'scope_unit' => 'peds', 'scope_name' => ''], 'department'));

    expect(data_get($result, 'findings.0.status'))->toBe(RoleTitleScopeJoinerMigrator::LINKED_WITH_OBJECT);
});

it('treats a none-sentinel or blank object as no object', function (string $object): void {
    $result = collapseRow(collapseTitle(['scope_joiner' => 'of_with_the', 'scope_name' => 'Academic Affairs'], $object));

    expect(data_get($result, 'findings.0.status'))->toBe(RoleTitleScopeJoinerMigrator::COLLAPSED);
})->with([
    'blank' => ['  '],
    // The sentinel optional radios post for "none", read literally: a dataset is built before the app boots
    'the none sentinel' => ['__role_title_none__'],
]);

it('keeps an article-less typed scope beside an object as it was', function (): void {
    $before = RoleTitleValue::composeFromMixed(collapseTitle(['scope_joiner' => 'of', 'scope_name' => 'endocrinology'], 'section'));
    $result = collapseRow(collapseTitle(['scope_joiner' => 'of_without_the', 'scope_name' => 'endocrinology'], 'section'));

    expect(data_get($result, 'findings.0.status'))->toBe(RoleTitleScopeJoinerMigrator::COLLAPSED)
        ->and(RoleTitleValue::composeFromMixed(data_get($result, 'data.job_title.0.title')))->toBe($before)
        ->and($before)->toBe('section chair of endocrinology');
});

it('stores no article for a legacy article joiner with no typed name', function (): void {
    $result = collapseRow(collapseTitle(['scope_joiner' => 'of_with_the', 'scope_name' => '']));

    expect(data_get($result, 'data.job_title.0.title.scope.scope_article'))->toBeNull();
});

it('leaves a joiner that is already a preposition alone', function (): void {
    $title = collapseTitle(['scope_joiner' => 'of', 'scope_article' => 'the', 'scope_name' => 'Academic Affairs']);
    $result = collapseRow($title);

    expect($result['changed'])->toBeFalse()
        ->and($result['findings'])->toBe([])
        ->and(data_get($result, 'data.job_title.0.title'))->toBe($title);
});

it('reads a padded legacy joiner and reports where it found it', function (): void {
    $result = collapseRow(collapseTitle(['scope_joiner' => '  of_with_the  ', 'scope_name' => 'Academic Affairs']));

    expect($result['findings'])->toBe([[
        'entry' => 'kirk',
        'path' => 'job_title.0.title',
        'status' => RoleTitleScopeJoinerMigrator::COLLAPSED,
        'from' => 'of_with_the',
        'to' => 'of',
        'article' => 'the',
    ]]);
});

it('walks past scalars and classifies only a role_title payload', function (): void {
    $result = app(RoleTitleScopeJoinerMigrator::class)->collapseInData([
        'a scalar',
        'job_title' => [
            ['title' => collapseTitle(['scope_joiner' => 'for_with_the', 'scope_name' => 'research'])],
            ['not_a_title' => ['scope' => ['scope_joiner' => 'for_with_the', 'scope_name' => 'research']]],
        ],
    ], 'kirk');

    expect(array_column($result['findings'], 'path'))->toBe(['job_title.0.title'])
        ->and(data_get($result, 'data.job_title.1.not_a_title.scope.scope_joiner'))->toBe('for_with_the');
});

it('reports nothing for a collection that does not exist', function (): void {
    expect(Artisan::call('role-title:collapse-scope-joiners', ['--collection' => 'no_such_collection']))->toBe(0)
        ->and(Artisan::output())->toContain('No legacy role_title scope joiners in `no_such_collection`.');
});

// ---
// A typed `in` scope must take the article
// ---

/**
 * Validation messages the joiner rule reports for one posted scope.
 *
 * @param  array<mixed>  $scope
 * @return list<string>
 */
function joinerMessages(array $scope): array
{
    $messages = Validator::make(
        ['title' => ['configuration' => 'faculty', 'scope' => $scope]],
        ['title.scope.scope_joiner' => ['nullable', 'string', new RoleTitleScopeJoinerRule('title')]]
    )->errors()->get('title.scope.scope_joiner');

    return \array_values(\array_filter($messages, \is_string(...)));
}

it('refuses a typed in scope without the article', function (?string $article): void {
    expect(joinerMessages(['scope_joiner' => 'in', 'scope_article' => $article, 'scope_name' => 'Department of Medicine']))
        ->toBe([__('statamic::fieldtypes.role_title.validation.scope_joiner_requires_article')]);
})->with([
    'none' => [null],
    'not the article' => ['a'],
]);

it('accepts a typed in scope with the article, a linked one, and one with no name', function (array $scope): void {
    serveCollapseUnit();

    expect(joinerMessages($scope))->toBe([]);
})->with([
    'typed with the article' => [['scope_joiner' => 'in', 'scope_article' => ' the ', 'scope_name' => 'Department of Medicine']],
    'linked' => [['scope_joiner' => 'in', 'scope_unit' => 'peds', 'scope_name' => '']],
    'linked, beside a typed name' => [['scope_joiner' => 'in', 'scope_unit' => 'peds', 'scope_name' => 'Department of Medicine']],
    'no name' => [['scope_joiner' => 'in', 'scope_article' => null, 'scope_name' => '  ']],
    'a name that is not text' => [['scope_joiner' => 'in', 'scope_article' => null, 'scope_name' => ['x']]],
]);

it('lets every other preposition go typed without the article', function (string $joiner): void {
    expect(joinerMessages(['scope_joiner' => $joiner, 'scope_article' => null, 'scope_name' => 'pediatrics']))->toBe([]);
})->with(['for', 'of', '  in-ish  ']);

it('reads a padded joiner the way the composer reads it', function (): void {
    expect(joinerMessages(['scope_joiner' => '  in  ', 'scope_article' => null, 'scope_name' => 'Department of Medicine']))
        ->toBe([__('statamic::fieldtypes.role_title.validation.scope_joiner_requires_article')]);
});

it('declares in, and only in, as requiring the article', function (): void {
    expect(RoleTitleDefinition::instance()->articleRequiringOptionKeysForNestedField('scope', 'scope_joiner'))->toBe(['in'])
        ->and(RoleTitleDefinition::instance()->articleRequiringOptionKeysForNestedField('scope', 'scope_article'))->toBe([]);
});

it('registers the joiner rule on the scope joiner, and on no other nested radios', function (): void {
    $field = new Field('title', ['type' => 'role_title']);

    $fieldtype = $field->fieldtype();
    Assert::assertInstanceOf(RoleTitle::class, $fieldtype);
    $fieldtype->setField($field);

    $rules = $fieldtype->extraRules();
    $joinerRules = $rules['title.scope.scope_joiner'] ?? [];
    $articleRules = $rules['title.scope.scope_article'] ?? [];

    expect(array_filter($joinerRules, static fn (mixed $rule): bool => $rule instanceof RoleTitleScopeJoinerRule))->toHaveCount(1)
        ->and(array_filter($articleRules, static fn (mixed $rule): bool => $rule instanceof RoleTitleScopeJoinerRule))->toBe([]);
});

// ---
// The Control Panel labels the article's "no selection" option
// ---

it('preloads a no-article label for the nested scope article', function (): void {
    expect(RoleTitleDefinition::instance()->optionalRadioNoneLabelsByTopLevelRadiosSubfield())
        ->toHaveKey('scope.scope_article', 'No Article')
        ->not->toHaveKey('scope.scope_joiner');
});

// ---
// Statuses that must not over- or under-report (adversarial review)
// ---

it('reports nothing moving for a legacy article row whose scope composes no phrase', function (): void {
    $blank = collapseRow(collapseTitle(['scope_joiner' => 'of_with_the', 'scope_name' => '  '], 'department'));
    $unresolved = collapseRow(collapseTitle(['scope_joiner' => 'of_without_the', 'scope_unit' => 'gone', 'scope_name' => ''], 'department'));

    expect(data_get($blank, 'findings.0.status'))->toBe(RoleTitleScopeJoinerMigrator::COLLAPSED)
        ->and(data_get($unresolved, 'findings.0.status'))->toBe(RoleTitleScopeJoinerMigrator::COLLAPSED);
});

it('reports a linked article-less row beside an object as linked with an object, which also loses the object', function (): void {
    serveCollapseUnit();

    $title = collapseTitle(['scope_joiner' => 'of_without_the', 'scope_unit' => 'peds', 'scope_name' => ''], 'department');
    $result = collapseRow($title);

    expect(data_get($result, 'findings.0.status'))->toBe(RoleTitleScopeJoinerMigrator::LINKED_WITH_OBJECT)
        // The title really does move: the object composed before and is dropped now
        ->and(RoleTitleValue::composeFromMixed(data_get($result, 'data.job_title.0.title')))->toBe('chair of the Department of Pediatrics');
});

it('ignores an object left in a configuration that composes none', function (): void {
    $title = ['configuration' => RoleTitleValue::CONFIGURATION_FACULTY, 'role' => 'professor', 'object' => 'department', 'scope' => ['scope_joiner' => 'of_with_the', 'scope_name' => 'medicine']];

    expect(data_get(collapseRow($title), 'findings.0.status'))->toBe(RoleTitleScopeJoinerMigrator::COLLAPSED)
        ->and(RoleTitleValue::objectSelected($title))->toBeFalse();
});

it('reads an object as selected only where the configuration composes one', function (string $configuration, mixed $object, bool $expected): void {
    expect(RoleTitleValue::objectSelected(['configuration' => $configuration, 'object' => $object]))->toBe($expected);
})->with([
    'academic, set' => ['academic_leadership', 'department', true],
    'clinical, set' => ['clinical_leadership', ' service_line ', true],
    'academic, blank' => ['academic_leadership', '  ', false],
    'academic, the none sentinel' => ['academic_leadership', '__role_title_none__', false],
    'academic, not text' => ['academic_leadership', ['department'], false],
    'faculty, set' => ['faculty', 'department', false],
    'institutional, set' => ['institutional_leadership', 'department', false],
]);

// ---
// No stored content keeps a legacy joiner, which would silently compose `for`
// ---

it('stores no legacy scope joiner in the content repository', function (): void {
    $root = ContentSubmodule::collectionsPath();

    if (! ContentSubmodule::isInitialized($root)) {
        ContentSubmodule::reportMissing('people scope joiners', $root);
    }

    $found = 0;
    $legacy = [];

    /** @var SplFileInfo $file */
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'md') {
            continue;
        }

        $contents = (string) file_get_contents($file->getPathname());

        if (preg_match_all('/scope_joiner:\s*([A-Za-z_]+)/', $contents, $matches) === 0) {
            continue;
        }

        $found += \count($matches[1]);

        foreach ($matches[1] as $joiner) {
            if (\array_key_exists($joiner, RoleTitleScopeJoinerMigrator::LEGACY_JOINERS)) {
                $legacy[] = str_replace($root.'/', '', $file->getPathname()).': '.$joiner;
            }
        }
    }

    // The scan must see stored joiners at all, or an empty legacy list proves nothing
    expect($found)->toBeGreaterThan(0)
        ->and($legacy)->toBe([]);
});

// ---
// scan(): one collection, written only when asked and only where something moved
// ---

/**
 * A migrator over a mocked entry repository serving `$entries` for `people`, with
 * the `people` collection made to exist.
 *
 * @param  list<mixed>  $entries
 */
function joinerMigratorServing(array $entries): RoleTitleScopeJoinerMigrator
{
    CollectionFacade::shouldReceive('find')->with('people')->andReturn(Mockery::mock(CollectionContract::class));

    $repository = Mockery::mock(StacheEntryRepository::class);
    $repository->shouldReceive('whereCollection')->with('people')->andReturn(EntryCollection::make($entries));

    return new RoleTitleScopeJoinerMigrator($repository);
}

/**
 * An entry double with the given id and stored data, expecting `$saves` quiet saves.
 *
 * @param  array<string, mixed>  $data
 */
function joinerEntry(mixed $id, array $data, int $saves): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('data')->withNoArgs()->andReturn(collect($data));
    $entry->shouldReceive('data')->with(Mockery::type('array'))->times($saves)->andReturnSelf();
    $entry->shouldReceive('saveQuietly')->times($saves)->andReturnTrue();

    return $entry;
}

it('reports a scan without saving anything when not writing', function (): void {
    $legacy = ['job_title' => [['title' => collapseTitle(['scope_joiner' => 'for_with_the', 'scope_name' => 'research'])]]];
    $migrator = joinerMigratorServing([joinerEntry('kirk', $legacy, 0)]);

    $findings = $migrator->scan('people', false);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]['entry'])->toBe('kirk')
        ->and($findings[0]['path'])->toBe('job_title.0.title');
});

it('saves only the entries a write scan changed, with the collapsed data', function (): void {
    $legacy = ['job_title' => [['title' => collapseTitle(['scope_joiner' => 'for_with_the', 'scope_name' => 'research'])]]];
    $current = ['job_title' => [['title' => collapseTitle(['scope_joiner' => 'for', 'scope_name' => 'research'])]]];

    $changed = Mockery::mock(Entry::class);
    $changed->shouldReceive('id')->andReturn('kirk');
    $changed->shouldReceive('data')->withNoArgs()->andReturn(collect($legacy));
    $changed->shouldReceive('data')->once()->with(Mockery::on(
        fn (array $data): bool => data_get($data, 'job_title.0.title.scope.scope_joiner') === 'for'
            && data_get($data, 'job_title.0.title.scope.scope_article') === 'the'
    ))->andReturnSelf();
    $changed->shouldReceive('saveQuietly')->once()->andReturnTrue();

    $findings = joinerMigratorServing([$changed, joinerEntry('spock', $current, 0)])->scan('people', true);

    expect(array_column($findings, 'entry'))->toBe(['kirk']);
});

it('skips anything in the collection that is not an entry, and records an id that is not a string as empty', function (): void {
    $legacy = ['job_title' => [['title' => collapseTitle(['scope_joiner' => 'for_with_the', 'scope_name' => 'research'])]]];

    $findings = joinerMigratorServing(['not an entry', joinerEntry(null, $legacy, 0)])->scan('people', false);

    expect(array_column($findings, 'entry'))->toBe(['']);
});

it('reads an entry with no data collection as empty', function (): void {
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn('empty');
    $entry->shouldReceive('data')->withNoArgs()->andReturn(['job_title' => 'not a collection']);
    $entry->shouldReceive('saveQuietly')->never();

    expect(joinerMigratorServing([$entry])->scan('people', true))->toBe([]);
});

it('finds nothing in a collection that does not exist, without reading entries', function (): void {
    CollectionFacade::shouldReceive('find')->with('missing')->andReturnNull();
    $repository = Mockery::mock(StacheEntryRepository::class);
    $repository->shouldReceive('whereCollection')->never();

    expect((new RoleTitleScopeJoinerMigrator($repository))->scan('missing', true))->toBe([]);
});

it('records a top-level list position as a string path', function (): void {
    $result = app(RoleTitleScopeJoinerMigrator::class)->collapseInData(
        [collapseTitle(['scope_joiner' => 'for_with_the', 'scope_name' => 'research'])],
        'kirk'
    );

    expect(array_column($result['findings'], 'path'))->toBe(['0']);
});

it('keeps walking siblings after collapsing a payload', function (): void {
    $result = app(RoleTitleScopeJoinerMigrator::class)->collapseInData(['titles' => [
        'first' => collapseTitle(['scope_joiner' => 'for_with_the', 'scope_name' => 'research']),
        'second' => collapseTitle(['scope_joiner' => 'of_with_the', 'scope_name' => 'surgery']),
    ]], 'kirk');

    expect(array_column($result['findings'], 'path'))->toBe(['titles.first', 'titles.second']);
});

it('stores no article for a legacy article joiner whose scope has no name at all', function (): void {
    $result = collapseRow(collapseTitle(['scope_joiner' => 'for_with_the']));

    expect($result['findings'][0]['article'])->toBeNull()
        ->and($result['findings'][0]['status'])->toBe(RoleTitleScopeJoinerMigrator::COLLAPSED);
});
