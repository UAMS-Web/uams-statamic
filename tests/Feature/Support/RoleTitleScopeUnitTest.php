<?php

declare(strict_types=1);

use App\Fieldtypes\RoleTitle;
use App\Rules\RoleTitleScopeUnitRule;
use App\Services\Organizations\OrganizationPublicLabelService;
use App\Services\People\RoleTitleScopeUnitLinker;
use App\Support\RoleTitleDefinition;
use App\Support\RoleTitleScopeUnit;
use App\Support\RoleTitleValue;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Assert;
use Statamic\Contracts\Auth\User as StatamicUser;
use Statamic\Contracts\Entries\Collection as CollectionContract;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\User;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Stache\Repositories\EntryRepository;
use Tests\TestCase;

uses(TestCase::class);

/**
 * A role_title scope links its organizational unit instead of typing it (UAMS-Web/uams-statamic-archive#2146).
 *
 * The link is read at compose time, so renaming the unit renames every title
 * that links it; a discipline scope has no unit and stays typed text; a row
 * cannot link one unit and name another; and existing typed scopes are linked
 * only where doing so leaves the composed title unchanged, with every other
 * case reported rather than rewritten.
 *
 * Units are stub entries served through a partial mock of the `Entry` facade's
 * `find()`, so nothing here depends on the content tree.
 *
 * @command  php artisan test --compact tests/Feature/Support/RoleTitleScopeUnitTest.php
 */

/**
 * A stub `organizations` entry exposing only what the scope unit reads.
 *
 * @param  array<string, mixed>|ArrayObject<string, mixed>  $values
 */
function scopeUnitStub(string $id, array|ArrayObject $values, string $collection = 'organizations'): Entry
{
    return new class($id, $values instanceof ArrayObject ? $values : new ArrayObject($values), $collection) extends Entry
    {
        /** @param ArrayObject<string, mixed> $values */
        public function __construct(private string $stubId, private ArrayObject $values, private string $stubCollection) {}

        /**
         * @param  string  $key
         * @return mixed
         */
        public function value($key)
        {
            return $this->values->offsetExists($key) ? $this->values[$key] : null;
        }

        /**
         * @param  string|null  $id
         * @return string
         */
        public function id($id = null)
        {
            return $this->stubId;
        }

        public function collectionHandle(): string
        {
            return $this->stubCollection;
        }
    };
}

/**
 * Serve the given stub units from `Entry::find()`.
 *
 * @param  list<Entry>  $units
 */
function serveScopeUnits(array $units): void
{
    $byId = [];

    foreach ($units as $unit) {
        $id = $unit->id();

        if (\is_string($id)) {
            $byId[$id] = $unit;
        }
    }

    EntryFacade::partialMock()
        ->shouldReceive('find')
        ->andReturnUsing(static fn (string $id): ?Entry => $byId[$id] ?? null);
}

/**
 * @param  array<string, mixed>  $scope
 */
function composeLeadership(array $scope, string $role = 'chair'): string
{
    return RoleTitleValue::compose([
        'configuration' => RoleTitleValue::CONFIGURATION_ACADEMIC_LEADERSHIP,
        'title_options' => [],
        'hierarchical_modifier' => null,
        'object' => null,
        'positional_qualifier' => null,
        'role' => $role,
        'scope' => $scope,
    ]);
}

/**
 * A unit with its own public label.
 */
function labeledUnit(string $id, string $label): Entry
{
    return scopeUnitStub($id, labeledUnitValues($label));
}

/**
 * The values of a unit carrying its own public label.
 *
 * @return array<string, mixed>
 */
function labeledUnitValues(string $label): array
{
    return [
        'inherit_public_label_query' => false,
        'public_label' => $label,
    ];
}

it('composes a linked scope from the unit display name', function (): void {
    serveScopeUnits([labeledUnit('peds', 'Department of Pediatrics')]);

    expect(composeLeadership([
        'scope_joiner' => 'of',
        'scope_unit' => 'peds',
        'scope_name' => '',
    ]))->toBe('chair of the Department of Pediatrics');
});

it('renames the composed title when the linked unit is renamed, with no edit to the title', function (): void {
    $values = new ArrayObject(labeledUnitValues('Department of Pediatrics'));
    serveScopeUnits([scopeUnitStub('peds', $values)]);

    $scope = ['scope_joiner' => 'of', 'scope_unit' => 'peds', 'scope_name' => ''];

    expect(composeLeadership($scope))->toBe('chair of the Department of Pediatrics');

    // Rename the unit; the stored title payload is untouched
    $values['public_label'] = 'Department of Pediatrics and Adolescent Medicine';

    expect(composeLeadership($scope))->toBe('chair of the Department of Pediatrics and Adolescent Medicine');
});

it('keeps composing a discipline scope from typed text', function (): void {
    serveScopeUnits([]);

    expect(RoleTitleValue::compose([
        'configuration' => RoleTitleValue::CONFIGURATION_FACULTY,
        'title_options' => [],
        'role' => 'professor',
        'scope' => [
            'scope_joiner' => 'of',
            'scope_unit' => null,
            'scope_name' => 'pediatrics',
        ],
    ]))->toBe('professor of pediatrics');
});

it('reads a posted single-item id list the same as a stored id', function (): void {
    serveScopeUnits([labeledUnit('peds', 'Department of Pediatrics')]);

    expect(composeLeadership([
        'scope_joiner' => 'of',
        'scope_unit' => ['peds'],
        'scope_name' => '',
    ]))->toBe('chair of the Department of Pediatrics');
});

it('lets the link win over stale typed text, so a row cannot print a unit it does not link', function (): void {
    serveScopeUnits([labeledUnit('peds', 'Department of Pediatrics')]);

    expect(composeLeadership([
        'scope_joiner' => 'of',
        'scope_unit' => 'peds',
        'scope_name' => 'Department of Medicine',
    ]))->toBe('chair of the Department of Pediatrics');
});

it('composes no scope for a link that does not resolve, rather than the typed text', function (): void {
    serveScopeUnits([]);

    expect(composeLeadership([
        'scope_joiner' => 'of',
        'scope_unit' => 'gone',
        'scope_name' => 'Department of Medicine',
    ]))->toBe('chair');
});

it('ignores a linked entry outside the organizations collection', function (): void {
    serveScopeUnits([scopeUnitStub('page', ['inherit_public_label_query' => false, 'public_label' => 'A Page'], 'pages')]);

    expect(RoleTitleScopeUnit::displayName('page'))->toBeNull();
});

it('gives a Workday-named unit without a public label no public name', function (): void {
    serveScopeUnits([scopeUnitStub('wd', [
        'inherit_public_label_query' => false,
        'public_label' => null,
        'workday_supervisory_organization_name' => 'ICE | CORE PA Clinic Access B',
    ])]);

    expect(RoleTitleScopeUnit::displayName('wd'))->toBeNull();
});

it('falls back to the authored title for an unlabeled unit with no Workday name', function (): void {
    serveScopeUnits([scopeUnitStub('ext', [
        'inherit_public_label_query' => false,
        'public_label' => null,
        'title' => 'Arkansas Children\'s Hospital',
    ])]);

    expect(RoleTitleScopeUnit::displayName('ext'))->toBe('Arkansas Children\'s Hospital');
});

it('clears the typed name and article on save when a unit is linked, and keeps them otherwise', function (): void {
    $field = new Field('title', ['type' => 'role_title']);

    $fieldtype = $field->fieldtype();
    Assert::assertInstanceOf(RoleTitle::class, $fieldtype);
    $fieldtype->setField($field);

    $linked = $fieldtype->process([
        'configuration' => RoleTitleValue::CONFIGURATION_ACADEMIC_LEADERSHIP,
        'role' => 'chair',
        'scope' => ['scope_joiner' => 'of', 'scope_article' => 'the', 'scope_unit' => ['peds'], 'scope_name' => 'Department of Medicine'],
    ]);

    expect(data_get($linked, 'scope.scope_unit'))->toBe('peds')
        ->and(data_get($linked, 'scope.scope_name'))->toBe('')
        ->and(data_get($linked, 'scope.scope_article'))->toBeNull();

    $typed = $fieldtype->process([
        'configuration' => RoleTitleValue::CONFIGURATION_FACULTY,
        'role' => 'professor',
        'scope' => ['scope_joiner' => 'for', 'scope_article' => 'the', 'scope_unit' => null, 'scope_name' => 'Northwest Arkansas region'],
    ]);

    expect(data_get($typed, 'scope.scope_unit'))->toBeNull()
        ->and(data_get($typed, 'scope.scope_name'))->toBe('Northwest Arkansas region')
        ->and(data_get($typed, 'scope.scope_article'))->toBe('the');
});

/**
 * Validate a job_title row's scope unit with the rule, reporting pass/fail.
 *
 * @param  array<string, mixed>  $row
 */
function scopeUnitRowPasses(array $row, ?string $rowLinkHandle = 'department'): bool
{
    return ! Validator::make(
        ['job_title' => [$row]],
        ['job_title.0.title.scope.scope_unit' => ['nullable', new RoleTitleScopeUnitRule('job_title.0.title', $rowLinkHandle)]]
    )->fails();
}

/**
 * The validation messages the rule reports for a job_title row's scope unit.
 *
 * @param  array<string, mixed>  $row
 * @return list<string>
 */
function scopeUnitRowMessages(array $row, ?string $rowLinkHandle = 'department'): array
{
    $messages = Validator::make(
        ['job_title' => [$row]],
        ['job_title.0.title.scope.scope_unit' => ['nullable', new RoleTitleScopeUnitRule('job_title.0.title', $rowLinkHandle)]]
    )->errors()->get('job_title.0.title.scope.scope_unit');

    return \array_values(\array_filter($messages, \is_string(...)));
}

/**
 * @param  array<string, mixed>  $scope
 * @param  array<string, mixed>  $sibling
 * @return array<string, mixed>
 */
function scopeUnitRow(array $scope, array $sibling = []): array
{
    return ['title' => ['configuration' => 'academic_leadership', 'scope' => $scope]] + $sibling;
}

it('accepts a linked unit that names a unit and agrees with the row', function (): void {
    serveScopeUnits([labeledUnit('peds', 'Department of Pediatrics')]);

    expect(scopeUnitRowPasses(scopeUnitRow(
        ['scope_joiner' => 'of', 'scope_unit' => 'peds'],
        ['department' => ['peds']]
    )))->toBeTrue();
});

it('refuses a row that links one unit and names a different one', function (): void {
    serveScopeUnits([
        labeledUnit('peds', 'Department of Pediatrics'),
        labeledUnit('med', 'Department of Medicine'),
    ]);

    expect(scopeUnitRowPasses(scopeUnitRow(
        ['scope_joiner' => 'of', 'scope_unit' => 'med'],
        ['department' => ['peds']]
    )))->toBeFalse();
});

it('accepts a linked unit when the row links none', function (): void {
    serveScopeUnits([labeledUnit('peds', 'Department of Pediatrics')]);

    expect(scopeUnitRowPasses(scopeUnitRow(
        ['scope_joiner' => 'of', 'scope_unit' => 'peds']
    )))->toBeTrue();
});

it('ignores the row link when the blueprint names no row-link field', function (): void {
    serveScopeUnits([
        labeledUnit('peds', 'Department of Pediatrics'),
        labeledUnit('med', 'Department of Medicine'),
    ]);

    expect(scopeUnitRowPasses(scopeUnitRow(
        ['scope_joiner' => 'of', 'scope_unit' => 'med'],
        ['department' => ['peds']]
    ), null))->toBeTrue();
});

it('accepts a linked unit with any preposition, since a link always takes the article', function (string $joiner): void {
    serveScopeUnits([labeledUnit('peds', 'Department of Pediatrics')]);

    expect(scopeUnitRowPasses(scopeUnitRow(
        ['scope_joiner' => $joiner, 'scope_unit' => 'peds']
    )))->toBeTrue();
})->with([
    'for' => 'for',
    'of' => 'of',
    'in' => 'in',
    'absent, so the default' => '',
]);

it('refuses a linked unit beside a selected object, which the composer would drop', function (): void {
    serveScopeUnits([labeledUnit('peds', 'Department of Pediatrics')]);

    $row = ['title' => ['configuration' => 'academic_leadership', 'object' => 'department', 'scope' => ['scope_joiner' => 'of', 'scope_unit' => 'peds']]];

    expect(scopeUnitRowMessages($row))
        ->toBe([__('statamic::fieldtypes.role_title.validation.scope_unit_object')]);
});

it('treats a blank or none-sentinel object as no object', function (string $object): void {
    serveScopeUnits([labeledUnit('peds', 'Department of Pediatrics')]);

    $row = ['title' => ['configuration' => 'academic_leadership', 'object' => $object, 'scope' => ['scope_joiner' => 'of', 'scope_unit' => 'peds']]];

    expect(scopeUnitRowMessages($row))->toBe([]);
})->with([
    'blank' => ['   '],
    // The sentinel optional radios post for "none", read literally: a dataset is built before the app boots
    'the none sentinel' => ['__role_title_none__'],
]);

it('refuses a link that does not resolve, and a unit with no public name, with one message each', function (): void {
    serveScopeUnits([scopeUnitStub('wd', [
        'inherit_public_label_query' => false,
        'workday_supervisory_organization_name' => 'ICE | CORE PA Clinic Access B',
    ])]);

    // A joiner that names no unit and a disagreeing row link, so a check that
    // fell through after the first failure would add a second message
    $sibling = ['department' => ['elsewhere']];

    expect(scopeUnitRowMessages(scopeUnitRow(['scope_joiner' => 'for', 'scope_unit' => 'gone'], $sibling)))
        ->toBe([__('statamic::fieldtypes.role_title.validation.scope_unit_not_found')])
        ->and(scopeUnitRowMessages(scopeUnitRow(['scope_joiner' => 'for', 'scope_unit' => 'wd'], $sibling)))
        ->toBe([__('statamic::fieldtypes.role_title.validation.scope_unit_unnamed')]);
});

it('accepts a linked unit whose posted scope carries no joiner', function (): void {
    serveScopeUnits([labeledUnit('peds', 'Department of Pediatrics')]);

    expect(scopeUnitRowMessages(scopeUnitRow(['scope_unit' => 'peds'])))->toBe([]);
});

it('accepts an unlinked scope, which the typed-name path governs', function (): void {
    serveScopeUnits([]);

    expect(scopeUnitRowPasses(scopeUnitRow(['scope_joiner' => 'for', 'scope_unit' => null])))->toBeTrue();
});

/**
 * Run the linker over one entry's job_title rows.
 *
 * @param  list<mixed>  $rows
 * @param  array<string, list<string>>  $index
 * @return array{data: array<array-key, mixed>, changed: bool, findings: list<array{entry: string, path: string, status: string, typed: string, unit: string|null, unit_name: string|null}>}
 */
function linkScopeRows(array $rows, array $index, bool $write): array
{
    return app(RoleTitleScopeUnitLinker::class)->linkInData(
        ['job_title' => $rows],
        'kirk',
        ['department', 'service_line'],
        $index,
        $write
    );
}

/**
 * The title payload of an institutional leadership title with the given scope.
 *
 * @param  array<string, mixed>  $scope
 * @return array<string, mixed>
 */
function scopeTitle(array $scope): array
{
    return [
        'configuration' => 'institutional_leadership',
        'role' => 'dean',
        'scope' => $scope,
    ];
}

/**
 * A job_title row holding one title with the given scope.
 *
 * @param  array<string, mixed>  $scope
 * @param  array<string, mixed>  $sibling
 * @return array<string, mixed>
 */
function scopeRow(array $scope, array $sibling = []): array
{
    return ['title' => scopeTitle($scope)] + $sibling;
}

/**
 * A job_title row holding a typed scope, with the article by default: the typed
 * scopes the linker considers are the ones that take it (UAMS-Web/uams-statamic-archive#2546).
 *
 * @param  array<string, mixed>  $sibling
 * @return array<string, mixed>
 */
function typedScopeRow(string $joiner, string $name, array $sibling = [], ?string $article = 'the'): array
{
    return scopeRow(['scope_joiner' => $joiner, 'scope_article' => $article, 'scope_name' => $name], $sibling);
}

it('links an exact match and leaves the composed title unchanged', function (): void {
    serveScopeUnits([labeledUnit('com', 'UAMS College of Medicine')]);

    $before = RoleTitleValue::compose(scopeTitle(['scope_joiner' => 'of', 'scope_article' => 'the', 'scope_name' => 'UAMS College of Medicine']));

    $result = linkScopeRows([typedScopeRow('of', 'UAMS College of Medicine')], ['UAMS College of Medicine' => ['com']], true);
    $linkedTitle = data_get($result, 'data.job_title.0.title');

    expect($result['changed'])->toBeTrue()
        ->and($result['findings'])->toBe([[
            'entry' => 'kirk',
            'path' => 'job_title.0.title',
            'status' => RoleTitleScopeUnitLinker::MATCH,
            'typed' => 'UAMS College of Medicine',
            'unit' => 'com',
            'unit_name' => 'UAMS College of Medicine',
        ]])
        ->and(data_get($linkedTitle, 'scope.scope_unit'))->toBe('com')
        ->and(data_get($linkedTitle, 'scope.scope_name'))->toBe('')
        ->and(RoleTitleValue::composeFromMixed($linkedTitle))->toBe($before)
        ->and($before)->toBe('dean of the UAMS College of Medicine');
});

it('writes nothing on a dry run', function (): void {
    serveScopeUnits([labeledUnit('com', 'UAMS College of Medicine')]);

    $result = linkScopeRows([typedScopeRow('of', 'UAMS College of Medicine')], ['UAMS College of Medicine' => ['com']], false);

    expect($result['changed'])->toBeFalse()
        ->and(data_get($result, 'findings.0.status'))->toBe(RoleTitleScopeUnitLinker::MATCH)
        ->and(data_get($result, 'data.job_title.0.title.scope'))->not->toHaveKey('scope_unit');
});

it('reports, and does not rewrite, a typed name that differs from the row unit', function (): void {
    serveScopeUnits([labeledUnit('peds', 'Department of Pediatrics')]);

    $row = typedScopeRow('of', 'Department of Medicine', ['department' => ['peds']]);
    $result = linkScopeRows([$row], [], true);

    expect($result['changed'])->toBeFalse()
        ->and($result['findings'])->toBe([[
            'entry' => 'kirk',
            'path' => 'job_title.0.title',
            'status' => RoleTitleScopeUnitLinker::DIFFERS_FROM_ROW,
            'typed' => 'Department of Medicine',
            'unit' => 'peds',
            'unit_name' => 'Department of Pediatrics',
        ]])
        ->and(data_get($result, 'data.job_title.0'))->toBe($row);
});

it('links from the row unit when the typed name equals it', function (): void {
    serveScopeUnits([labeledUnit('peds', 'Department of Pediatrics')]);

    $result = linkScopeRows([typedScopeRow('of', 'Department of Pediatrics', ['department' => ['peds']])], [], true);

    expect($result['findings'])->toBe([[
        'entry' => 'kirk',
        'path' => 'job_title.0.title',
        'status' => RoleTitleScopeUnitLinker::MATCH,
        'typed' => 'Department of Pediatrics',
        'unit' => 'peds',
        'unit_name' => 'Department of Pediatrics',
    ]])->and(data_get($result, 'data.job_title.0.title.scope.scope_unit'))->toBe('peds');
});

it('reports a typed name that matches no unit, or more than one, and rewrites neither', function (): void {
    serveScopeUnits([]);

    $result = linkScopeRows([
        typedScopeRow('of', 'UAMS Medical Center'),
        typedScopeRow('of', 'Graduate School'),
    ], ['Graduate School' => ['gs-1', 'gs-2']], true);

    expect($result['findings'])->toBe([
        ['entry' => 'kirk', 'path' => 'job_title.0.title', 'status' => RoleTitleScopeUnitLinker::NO_UNIT, 'typed' => 'UAMS Medical Center', 'unit' => null, 'unit_name' => null],
        ['entry' => 'kirk', 'path' => 'job_title.1.title', 'status' => RoleTitleScopeUnitLinker::AMBIGUOUS, 'typed' => 'Graduate School', 'unit' => null, 'unit_name' => null],
    ])->and($result['changed'])->toBeFalse();
});

it('does not treat a matching discipline scope as a unit', function (): void {
    serveScopeUnits([]);

    $result = linkScopeRows([typedScopeRow('of', 'pediatrics', [], null)], ['pediatrics' => ['peds']], true);

    expect($result['findings'])->toBe([])
        ->and($result['changed'])->toBeFalse();
});

it('reports an existing link that no longer resolves, or disagrees with the row', function (): void {
    serveScopeUnits([
        labeledUnit('peds', 'Department of Pediatrics'),
        labeledUnit('med', 'Department of Medicine'),
    ]);

    $broken = scopeRow(['scope_joiner' => 'of', 'scope_name' => '', 'scope_unit' => 'gone']);
    $disagrees = scopeRow(['scope_joiner' => 'of', 'scope_name' => '', 'scope_unit' => 'med'], ['department' => ['peds']]);
    $agrees = scopeRow(['scope_joiner' => 'of', 'scope_name' => '', 'scope_unit' => 'peds'], ['department' => ['peds']]);

    $result = linkScopeRows([$broken, $disagrees, $agrees], [], true);

    expect($result['findings'])->toBe([
        ['entry' => 'kirk', 'path' => 'job_title.0.title', 'status' => RoleTitleScopeUnitLinker::BROKEN_LINK, 'typed' => '', 'unit' => 'gone', 'unit_name' => null],
        ['entry' => 'kirk', 'path' => 'job_title.1.title', 'status' => RoleTitleScopeUnitLinker::LINK_DIFFERS_FROM_ROW, 'typed' => '', 'unit' => 'med', 'unit_name' => 'Department of Medicine'],
    ])->and($result['changed'])->toBeFalse();
});

it('registers the scope unit rule with the blueprint row link', function (): void {
    $field = new Field('title', ['type' => 'role_title', 'scope_unit_row_link' => ' service_line ']);
    $field->setParentField(new Field('job_title', ['type' => 'replicator']), 0);

    $fieldtype = $field->fieldtype();
    Assert::assertInstanceOf(RoleTitle::class, $fieldtype);
    $fieldtype->setField($field);

    $rules = $fieldtype->extraRules()['title.scope.scope_unit'] ?? [];

    expect($rules)->toHaveCount(2)
        ->and($rules[0] ?? null)->toBe('nullable')
        ->and($rules[1] ?? null)->toBeInstanceOf(RoleTitleScopeUnitRule::class);
});

it('checks the row link named by the blueprint, and none when it names none', function (?string $configured, bool $passes): void {
    serveScopeUnits([
        labeledUnit('peds', 'Department of Pediatrics'),
        labeledUnit('med', 'Department of Medicine'),
    ]);

    $config = ['type' => 'role_title'];

    if ($configured !== null) {
        $config['scope_unit_row_link'] = $configured;
    }

    $field = new Field('title', $config);

    $fieldtype = $field->fieldtype();
    Assert::assertInstanceOf(RoleTitle::class, $fieldtype);
    $fieldtype->setField($field);

    $validator = Validator::make(
        ['title' => ['configuration' => 'academic_leadership', 'scope' => ['scope_joiner' => 'of', 'scope_unit' => 'med']], 'department' => ['peds']],
        ['title.scope.scope_unit' => $fieldtype->extraRules()['title.scope.scope_unit']]
    );

    expect($validator->fails())->toBe(! $passes);
})->with([
    'named, and it disagrees' => ['department', false],
    'named with padding, and it disagrees' => ['  department  ', false],
    'named blank' => ['   ', true],
    'not named' => [null, true],
]);

it('ships the picker config and an editor row for the scope unit', function (): void {
    $field = new Field('title', ['type' => 'role_title']);

    $fieldtype = $field->fieldtype();
    Assert::assertInstanceOf(RoleTitle::class, $fieldtype);
    $fieldtype->setField($field);

    $meta = $fieldtype->preload();
    $labels = $meta['cp_nested_field_labels'] ?? null;
    $blocks = data_get($meta, 'cp_editor_blocks_by_configuration.academic_leadership');

    Assert::assertIsArray($labels);
    Assert::assertIsArray($blocks);

    expect($meta['scope_unit_field_config'] ?? null)->toMatchArray([
        'type' => 'entries',
        'handle' => 'scope_unit',
        'collections' => ['organizations'],
        'max_items' => 1,
    ])->and($meta['scope_unit_meta'] ?? null)->toBe([])
        ->and($labels['scope.scope_unit'] ?? null)->toBe(__('statamic::fieldtypes.role_title.field.scope_unit'));

    $nestedTypes = [];

    foreach ($blocks as $block) {
        if (data_get($block, 'key') !== 'scope') {
            continue;
        }

        $nestedRows = data_get($block, 'nested');
        Assert::assertIsArray($nestedRows);

        foreach ($nestedRows as $nested) {
            $nestedTypes[] = [data_get($nested, 'nested_key'), data_get($nested, 'type')];
        }
    }

    expect($nestedTypes)->toBe([
        ['scope_joiner', 'radios'],
        ['scope_article', 'radios'],
        ['scope_unit', 'entries'],
        ['scope_name', 'text'],
    ]);
});

it('builds relationship picker meta only for a signed-in editor', function (): void {
    expect(RoleTitleScopeUnit::preloadMeta(null))->toBe([]);

    $user = User::make();
    Assert::assertInstanceOf(StatamicUser::class, $user);
    $user->email('kirk@enterprise.example');
    $user->makeSuper();

    $this->actingAs($user);

    $meta = RoleTitleScopeUnit::preloadMeta(null);

    expect($meta)->toHaveKey('itemDataUrl')
        ->and($meta['data'])->toBe([]);
});

it('normalizes a stored or posted link to one trimmed id, and anything else to null', function (mixed $raw, ?string $expected): void {
    expect(RoleTitleScopeUnit::normalizeId($raw))->toBe($expected);
})->with([
    'a padded id' => [' peds ', 'peds'],
    'a padded single-item list' => [[' peds '], 'peds'],
    'whitespace' => ['   ', null],
    'an empty list' => [[], null],
    'not a string' => [42, null],
]);

it('reads no link from a scope that is not an array', function (mixed $scope): void {
    expect(RoleTitleScopeUnit::idFromScope($scope))->toBeNull();
})->with([
    'null' => [null],
    'a string' => ['peds'],
]);

it('reads the link collections from config, keeping only handles', function (): void {
    config()->set('role_title.subfields.scope.fields.scope_unit.field.collections', [5 => 'organizations', 6 => 7, 8 => 'departments']);
    RoleTitleDefinition::forgetInstance();

    try {
        expect(RoleTitleScopeUnit::collections())->toBe(['organizations', 'departments']);
    } finally {
        RoleTitleDefinition::forgetInstance();
    }
});

it('holds the linked id as the picker field value', function (): void {
    expect(RoleTitleScopeUnit::field('peds')->value())->toBe(['peds'])
        ->and(RoleTitleScopeUnit::field(null)->value())->toBe([]);
});

it('reads a padded object the way the composer reads it', function (): void {
    serveScopeUnits([labeledUnit('peds', 'Department of Pediatrics')]);

    $row = ['title' => ['configuration' => 'academic_leadership', 'object' => '  department  ', 'scope' => ['scope_joiner' => 'of', 'scope_unit' => 'peds']]];

    expect(scopeUnitRowMessages($row))
        ->toBe([__('statamic::fieldtypes.role_title.validation.scope_unit_object')]);
});

it('walks every sibling, past scalars and past a classified payload, from any root', function (): void {
    serveScopeUnits([]);

    $linker = app(RoleTitleScopeUnitLinker::class);

    // A scalar before the rows, and two payloads side by side in one row
    $entry = linkScopeRows([
        'a scalar that must not stop the walk',
        [
            'title' => scopeTitle(['scope_joiner' => 'of', 'scope_article' => 'the', 'scope_name' => 'UAMS Medical Center']),
            'second_title' => scopeTitle(['scope_joiner' => 'in', 'scope_article' => 'the', 'scope_name' => 'Graduate School']),
        ],
    ], [], false);

    // A list at the root, whose first key is an integer
    $rootList = $linker->linkInData(
        [typedScopeRow('of', 'UAMS Medical Center')],
        'spock',
        [],
        [],
        false
    );

    expect(array_column($entry['findings'], 'path'))->toBe(['job_title.1.title', 'job_title.1.second_title'])
        ->and(array_column($rootList['findings'], 'path'))->toBe(['0.title']);
});

it('classifies only a role_title payload, not any array with a scope key', function (): void {
    serveScopeUnits([]);

    $result = linkScopeRows([
        ['title' => ['scope' => ['scope_joiner' => 'of', 'scope_article' => 'the', 'scope_name' => 'UAMS Medical Center']]],
    ], [], false);

    expect($result['findings'])->toBe([]);
});

it('compares a typed name trimmed, and reports none for a blank or missing one', function (): void {
    serveScopeUnits([]);

    $blank = typedScopeRow('of', '   ');
    $missing = scopeRow(['scope_joiner' => 'of', 'scope_article' => 'the']);

    $result = linkScopeRows([
        typedScopeRow('of', '  UAMS College of Medicine  '),
        $blank,
        $missing,
    ], ['UAMS College of Medicine' => ['com']], false);

    expect($result['findings'])->toBe([[
        'entry' => 'kirk',
        'path' => 'job_title.0.title',
        'status' => RoleTitleScopeUnitLinker::MATCH,
        'typed' => 'UAMS College of Medicine',
        'unit' => 'com',
        'unit_name' => 'UAMS College of Medicine',
    ]]);
});

it('reports a broken link with no typed name when the stored name is not text', function (): void {
    serveScopeUnits([]);

    $row = scopeRow(['scope_joiner' => 'of', 'scope_name' => null, 'scope_unit' => 'gone']);

    expect(data_get(linkScopeRows([$row], [], false), 'findings.0.typed'))->toBe('');
});

it('reports nothing for an agreeing link that still carries a leftover typed name', function (): void {
    serveScopeUnits([labeledUnit('peds', 'Department of Pediatrics')]);

    // Content written outside the fieldtype can keep a typed name beside its link
    $row = scopeRow(
        ['scope_joiner' => 'of', 'scope_name' => 'Department of Medicine', 'scope_unit' => 'peds'],
        ['department' => ['peds']]
    );

    expect(linkScopeRows([$row], [], false)['findings'])->toBe([]);
});

it('composes a linked unit from its formal name before its public label (#2546)', function (): void {
    serveScopeUnits([scopeUnitStub('com', [
        'inherit_public_label_query' => false,
        'public_label' => 'College of Medicine',
        'formal_name' => '  UAMS College of Medicine  ',
    ])]);

    expect(RoleTitleScopeUnit::displayName('com'))->toBe('UAMS College of Medicine')
        ->and(composeLeadership(['scope_joiner' => 'of', 'scope_unit' => 'com', 'scope_name' => '']))
        ->toBe('chair of the UAMS College of Medicine');
});

it('falls back to the public label when the formal name is blank', function (mixed $formal): void {
    serveScopeUnits([scopeUnitStub('com', [
        'inherit_public_label_query' => false,
        'public_label' => 'College of Medicine',
        'formal_name' => $formal,
    ])]);

    expect(RoleTitleScopeUnit::displayName('com'))->toBe('College of Medicine');
})->with([
    'whitespace' => ['   '],
    'null' => [null],
    'not text' => [42],
]);

it('links a typed name that equals a unit formal name, and leaves the title unchanged', function (): void {
    $com = scopeUnitStub('com', [
        'inherit_public_label_query' => false,
        'public_label' => 'College of Medicine',
        'formal_name' => 'UAMS College of Medicine',
    ]);
    serveScopeUnits([$com]);

    $row = typedScopeRow('of', 'UAMS College of Medicine');
    $before = RoleTitleValue::composeFromMixed($row['title']);

    $result = linkScopeRows([$row], ['UAMS College of Medicine' => ['com']], true);
    $linkedTitle = data_get($result, 'data.job_title.0.title');

    expect(data_get($result, 'findings.0.status'))->toBe(RoleTitleScopeUnitLinker::MATCH)
        ->and(data_get($linkedTitle, 'scope.scope_unit'))->toBe('com')
        ->and(data_get($linkedTitle, 'scope.scope_article'))->toBeNull()
        ->and(RoleTitleValue::composeFromMixed($linkedTitle))->toBe($before)
        ->and($before)->toBe('dean of the UAMS College of Medicine');
});

it('does not consider a typed scope without the article, which names a discipline', function (): void {
    serveScopeUnits([labeledUnit('com', 'UAMS College of Medicine')]);

    $result = linkScopeRows([typedScopeRow('of', 'UAMS College of Medicine', [], null)], ['UAMS College of Medicine' => ['com']], true);

    expect($result['findings'])->toBe([])
        ->and($result['changed'])->toBeFalse();
});

it('reports, and does not link, a typed article scope beside an object that composes (#2546)', function (): void {
    serveScopeUnits([labeledUnit('peds', 'Department of Pediatrics')]);

    // Typed, so the object composes today; linking would drop it
    $row = ['title' => [
        'configuration' => 'academic_leadership',
        'role' => 'chair',
        'object' => 'department',
        'scope' => ['scope_joiner' => 'of', 'scope_article' => 'the', 'scope_name' => 'Department of Pediatrics'],
    ]];
    $before = RoleTitleValue::composeFromMixed($row['title']);

    $result = linkScopeRows([$row], ['Department of Pediatrics' => ['peds']], true);

    expect($result['findings'])->toBe([[
        'entry' => 'kirk',
        'path' => 'job_title.0.title',
        'status' => RoleTitleScopeUnitLinker::OBJECT_SELECTED,
        'typed' => 'Department of Pediatrics',
        'unit' => null,
        'unit_name' => null,
    ]])->and($result['changed'])->toBeFalse()
        ->and(RoleTitleValue::composeFromMixed(data_get($result, 'data.job_title.0.title')))->toBe($before)
        ->and($before)->toBe('department chair of the Department of Pediatrics');
});

it('accepts a linked unit beside an object left in a configuration that composes none', function (): void {
    serveScopeUnits([labeledUnit('peds', 'Department of Pediatrics')]);

    $row = ['title' => ['configuration' => 'faculty', 'object' => 'department', 'scope' => ['scope_joiner' => 'of', 'scope_unit' => 'peds']]];

    expect(scopeUnitRowMessages($row))->toBe([]);
});

/**
 * A person entry holding the given job_title rows, which counts its quiet saves in
 * `$saves`, keyed by id, instead of writing anything.
 *
 * @param  list<mixed>  $rows
 * @param  ArrayObject<string, int>  $saves
 */
function scanPerson(?string $id, array $rows, ArrayObject $saves): Entry
{
    $person = new class($saves) extends Entry
    {
        /** @param ArrayObject<string, int> $saves */
        public function __construct(private ArrayObject $saves)
        {
            parent::__construct();
        }

        public function saveQuietly(): bool
        {
            $id = mustBeString($this->id(), 'the person id');
            $this->saves[$id] = ($this->saves[$id] ?? 0) + 1;

            return true;
        }
    };
    if ($id !== null) {
        $person->id($id);
    }

    $person->collection('people');
    $person->data(['job_title' => $rows]);

    return $person;
}

/**
 * A linker reading the given entries per collection, never the Stache.
 *
 * @param  array<string, list<mixed>>  $byCollection
 */
function scanLinker(array $byCollection): RoleTitleScopeUnitLinker
{
    $repository = Mockery::mock(EntryRepository::class);

    foreach ($byCollection as $handle => $entries) {
        $repository->shouldReceive('whereCollection')->with($handle)->andReturn(EntryCollection::make($entries));
    }

    return new RoleTitleScopeUnitLinker(app(OrganizationPublicLabelService::class), $repository);
}

it('scans a collection on a dry run, reporting each scope and saving nothing', function (): void {
    $medicine = labeledUnit('com', 'UAMS College of Medicine');
    serveScopeUnits([$medicine]);
    $saves = new ArrayObject;
    $person = scanPerson('kirk', [typedScopeRow('of', 'UAMS College of Medicine')], $saves);

    $findings = scanLinker(['people' => [$person], 'organizations' => [$medicine]])->scan('people', false);

    expect($findings)->toBe([[
        'entry' => 'kirk',
        'path' => 'job_title.0.title',
        'status' => RoleTitleScopeUnitLinker::MATCH,
        'typed' => 'UAMS College of Medicine',
        'unit' => 'com',
        'unit_name' => 'UAMS College of Medicine',
    ]])
        ->and($saves['kirk'] ?? 0)->toBe(0)
        ->and(data_get($person->get('job_title'), '0.title.scope.scope_unit'))->toBeNull();
});

it('links and saves quietly only the entries a scan changes', function (): void {
    $medicine = labeledUnit('com', 'UAMS College of Medicine');
    serveScopeUnits([$medicine]);
    $saves = new ArrayObject;
    $linked = scanPerson('kirk', [typedScopeRow('of', 'UAMS College of Medicine')], $saves);
    $unmatched = scanPerson('spock', [typedScopeRow('of', 'Starfleet Academy')], $saves);

    $findings = scanLinker(['people' => [$linked, $unmatched], 'organizations' => [$medicine]])->scan('people', true);

    expect(array_column($findings, 'status', 'entry'))->toBe(['kirk' => RoleTitleScopeUnitLinker::MATCH, 'spock' => RoleTitleScopeUnitLinker::NO_UNIT])
        ->and($saves['kirk'] ?? 0)->toBe(1)
        ->and(data_get($linked->get('job_title'), '0.title.scope.scope_unit'))->toBe('com')
        ->and($saves['spock'] ?? 0)->toBe(0);
});

it('indexes every named unit under its name, skipping a unit with no name and anything that is not an entry', function (): void {
    $first = labeledUnit('school-a', 'Graduate School');
    $second = labeledUnit('school-b', 'Graduate School');
    $unnamed = scopeUnitStub('nameless', ['inherit_public_label_query' => false]);
    serveScopeUnits([$first, $second, $unnamed]);
    $saves = new ArrayObject;
    $person = scanPerson('kirk', [typedScopeRow('of', 'Graduate School')], $saves);

    $findings = scanLinker(['people' => [$person, 'not an entry'], 'organizations' => [$first, $second, $unnamed, 42]])->scan('people', true);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]['status'])->toBe(RoleTitleScopeUnitLinker::AMBIGUOUS)
        ->and($saves['kirk'] ?? 0)->toBe(0);
});

it('scans nothing in a collection that does not exist', function (): void {
    $repository = Mockery::mock(EntryRepository::class);
    $repository->shouldReceive('whereCollection')->with('organizations')->andReturn(EntryCollection::make([]));
    $repository->shouldNotReceive('whereCollection')->with('no_such_collection');

    $linker = new RoleTitleScopeUnitLinker(app(OrganizationPublicLabelService::class), $repository);

    expect($linker->scan('no_such_collection', true))->toBe([]);
});

it('reads the row link of every role_title field in every blueprint, trimmed, once each', function (): void {
    $blueprintWith = function (array $fields): Blueprint {
        $blueprint = Mockery::mock(Blueprint::class);
        $blueprint->shouldReceive('fields->all')->andReturn(collect($fields));

        return $blueprint;
    };
    $people = Mockery::mock(CollectionContract::class);
    $people->shouldReceive('entryBlueprints')->andReturn(collect([
        $blueprintWith([
            new Field('job_title', ['type' => 'role_title', 'scope_unit_row_link' => ' department ']),
            // A role_title nested inside another field's config is found too
            new Field('roles', ['type' => 'grid', 'fields' => [
                ['handle' => 'title', 'field' => ['type' => 'role_title', 'scope_unit_row_link' => 'service_line']],
                ['handle' => 'note', 'field' => ['type' => 'text', 'scope_unit_row_link' => 'not_a_title']],
            ]]),
            new Field('blank', ['type' => 'role_title', 'scope_unit_row_link' => '   ']),
            new Field('unlinked', ['type' => 'role_title']),
        ]),
        $blueprintWith([
            new Field('again', ['type' => 'role_title', 'scope_unit_row_link' => 'department']),
            new Field('later', ['type' => 'role_title', 'scope_unit_row_link' => 'program']),
        ]),
    ]));
    CollectionFacade::shouldReceive('find')->with('people')->andReturn($people);
    $handles = new ReflectionMethod(RoleTitleScopeUnitLinker::class, 'rowLinkHandles');

    expect($handles->invoke(scanLinker([]), 'people'))->toBe(['department', 'service_line', 'program']);
});

it('scans with the row link the people blueprint names', function (): void {
    $medicine = labeledUnit('com', 'UAMS College of Medicine');
    serveScopeUnits([$medicine]);
    $saves = new ArrayObject;
    // The unit is known only through the row's department link, not the name index
    $person = scanPerson('kirk', [typedScopeRow('of', 'UAMS College of Medicine', ['department' => ['com']])], $saves);

    $findings = scanLinker(['people' => [$person], 'organizations' => []])->scan('people', false);

    expect(array_column($findings, 'status'))->toBe([RoleTitleScopeUnitLinker::MATCH])
        ->and($findings[0]['unit'] ?? null)->toBe('com');
});

it('indexes a unit under its formal name before its public label', function (): void {
    $medicine = scopeUnitStub('com', [
        'inherit_public_label_query' => false,
        'public_label' => 'College of Medicine',
        'formal_name' => 'UAMS College of Medicine',
    ]);
    serveScopeUnits([$medicine]);
    $saves = new ArrayObject;
    $person = scanPerson('kirk', [typedScopeRow('of', 'UAMS College of Medicine')], $saves);

    $findings = scanLinker(['people' => [$person], 'organizations' => [$medicine]])->scan('people', false);

    expect(array_column($findings, 'status'))->toBe([RoleTitleScopeUnitLinker::MATCH]);
});

it('records an entry with no id under an empty id', function (): void {
    serveScopeUnits([]);
    $person = scanPerson(null, [typedScopeRow('of', 'Starfleet Academy')], new ArrayObject);

    $findings = scanLinker(['people' => [$person], 'organizations' => []])->scan('people', false);

    expect(array_column($findings, 'entry'))->toBe(['']);
});

it('reports nothing for an agreeing link, even beside a typed article scope', function (): void {
    serveScopeUnits([labeledUnit('peds', 'Department of Pediatrics')]);

    // The link settles the row, so the typed name is never matched against the index
    $row = scopeRow(['scope_joiner' => 'of', 'scope_article' => 'the', 'scope_name' => 'Department of Medicine', 'scope_unit' => 'peds']);

    expect(linkScopeRows([$row], [], false)['findings'])->toBe([]);
});

it('reads a padded article the way the composer reads it', function (): void {
    serveScopeUnits([labeledUnit('com', 'UAMS College of Medicine')]);

    $row = typedScopeRow('of', 'UAMS College of Medicine', [], ' the ');

    expect(array_column(linkScopeRows([$row], ['UAMS College of Medicine' => ['com']], false)['findings'], 'status'))
        ->toBe([RoleTitleScopeUnitLinker::MATCH]);
});
