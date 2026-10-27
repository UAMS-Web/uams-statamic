<?php

declare(strict_types=1);

use App\Services\Organizations\OrganizationPublicLabelService;
use Statamic\Entries\Entry;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Covers `organizations` public-label resolution and the generated title (UAMS-Web/uams-statamic-archive#2125).
 *
 * Inheritance keys on the ancestor's own `inherit_public_label_query` rather than
 * on it holding a non-empty label, so an ancestor that itself inherits is never
 * treated as a source. Inheritance is display-only and never touches identity.
 *
 * Antlers/blueprint-free: the service reads `value()` and `id()`, so the cases
 * below drive it with a stub entry rather than committed content.
 *
 * @command php artisan test --compact tests/Feature/Services/Organizations/OrganizationPublicLabelServiceTest.php
 */

/**
 * A stub entry exposing only what the service reads.
 *
 * @param  array<string, mixed>  $values
 */
function orgEntry(array $values, ?string $id = null): Entry
{
    return new class($values, $id) extends Entry
    {
        /** @param array<string, mixed> $values */
        public function __construct(private array $values, private ?string $stubId) {}

        /**
         * Annotated rather than natively typed: `HasOrigin::value($key)` has no
         * parameter type, and PHP parameter types are contravariant, so a native
         * `string` here is a fatal rather than a narrowing.
         *
         * @param  string  $key
         * @return mixed
         */
        public function value($key)
        {
            return $this->values[$key] ?? null;
        }

        /**
         * Annotated for the same reason: `Entry::id($id = null)` is untyped.
         *
         * @param  string|null  $id
         * @return string|null
         */
        public function id($id = null)
        {
            return $this->stubId;
        }
    };
}

function labelService(): OrganizationPublicLabelService
{
    return app(OrganizationPublicLabelService::class);
}

it('uses its own label when the entry does not inherit', function () {
    expect(labelService()->resolvePublicLabel(orgEntry([
        'inherit_public_label_query' => false,
        'public_label' => 'Institute for Digital Health and Innovation',
    ])))->toBe('Institute for Digital Health and Innovation');
});

it('returns null for a non-inheriting entry whose own label is blank', function () {
    expect(labelService()->resolvePublicLabel(orgEntry([
        'inherit_public_label_query' => false,
        'public_label' => '   ',
    ])))->toBeNull();
});

it('composes the title as Workday name, ID, then public label', function () {
    expect(labelService()->composeTitle(orgEntry([
        'inherit_public_label_query' => false,
        'public_label' => 'Institute for Digital Health and Innovation',
        'workday_supervisory_organization_name' => 'COM | IDHI Institute for Dig Hlth & Innv',
        'workday_supervisory_organization_id' => 'SOC300001877',
        'title' => 'authored',
    ])))->toBe('COM | IDHI Institute for Dig Hlth & Innv (SOC300001877) — Institute for Digital Health and Innovation');
});

it('drops the trailing segment when the label merely repeats the Workday name', function () {
    expect(labelService()->composeTitle(orgEntry([
        'inherit_public_label_query' => false,
        'public_label' => 'ICE | PeriopSvc Perfusion',
        'workday_supervisory_organization_name' => 'ICE | PeriopSvc Perfusion',
        'workday_supervisory_organization_id' => 'SOC300000999',
        'title' => 'authored',
    ])))->toBe('ICE | PeriopSvc Perfusion (SOC300000999)');
});

it('drops the trailing segment when there is no label at all', function () {
    expect(labelService()->composeTitle(orgEntry([
        'inherit_public_label_query' => false,
        'workday_supervisory_organization_name' => 'AA | CW ESSC',
        'workday_supervisory_organization_id' => 'SOC300000123',
        'title' => 'authored',
    ])))->toBe('AA | CW ESSC (SOC300000123)');
});

it('omits the parenthesised ID for an ID-less UAMS node', function () {
    expect(labelService()->composeTitle(orgEntry([
        'inherit_public_label_query' => false,
        'public_label' => 'Office of the Provost',
        'workday_supervisory_organization_name' => 'AA | Provost',
        'title' => 'authored',
    ])))->toBe('AA | Provost — Office of the Provost');
});

it('falls back to the public label when there is no Workday name', function () {
    expect(labelService()->composeTitle(orgEntry([
        'inherit_public_label_query' => false,
        'public_label' => 'Arkansas Children\'s',
        'title' => 'authored title',
    ])))->toBe('Arkansas Children\'s');
});

it('falls back to the authored title when there is neither a Workday name nor a label', function () {
    expect(labelService()->composeTitle(orgEntry([
        'inherit_public_label_query' => false,
        'title' => 'Central Arkansas Veterans Healthcare System',
    ])))->toBe('Central Arkansas Veterans Healthcare System');
});

it('returns null when an inheriting entry has no resolvable id', function () {
    expect(labelService()->resolvePublicLabel(orgEntry(['inherit_public_label_query' => true], null)))->toBeNull();
});

it('returns null, without walking ancestors, when an inheriting entry has an empty id', function () {
    Statamic\Facades\Entry::shouldReceive('findOrFail')->never();

    expect(labelService()->resolvePublicLabel(orgEntry(['inherit_public_label_query' => true], '')))->toBeNull();
});

/**
 * A stub entry that also answers `parent()`, for the structural ancestor walk.
 *
 * @param  array<string, mixed>  $values
 */
function orgTreeEntry(array $values, string $id, ?string $parentId): Entry
{
    return new class($values, $id, $parentId) extends Entry
    {
        /** @param array<string, mixed> $values */
        public function __construct(private array $values, private string $stubId, private ?string $parentId) {}

        /**
         * @param  string  $key
         * @return mixed
         */
        public function value($key)
        {
            return $this->values[$key] ?? null;
        }

        /**
         * @param  string|null  $id
         * @return string
         */
        public function id($id = null)
        {
            return $this->stubId;
        }

        /**
         * @return Entry|null
         */
        public function parent()
        {
            return $this->parentId === null ? null : orgEntry([], $this->parentId);
        }
    };
}

/**
 * Route `Entry::findOrFail()` and `Entry::find()` to the given stubs by id.
 *
 * @param  array<string, Entry>  $entries
 */
function orgTree(array $entries): void
{
    Statamic\Facades\Entry::shouldReceive('findOrFail')->andReturnUsing(fn (string $id): Entry => $entries[$id]);
    Statamic\Facades\Entry::shouldReceive('find')->andReturnUsing(fn (string $id): ?Entry => $entries[$id] ?? null);
}

it('takes the label of the nearest ancestor that does not inherit', function () {
    orgTree([
        'clinic' => $clinic = orgTreeEntry(['inherit_public_label_query' => true], 'clinic', 'division'),
        'division' => orgTreeEntry(['inherit_public_label_query' => true, 'public_label' => 'Skipped'], 'division', 'department'),
        'department' => orgTreeEntry(['public_label' => '  Pharmacy  '], 'department', 'college'),
        'college' => orgTreeEntry(['public_label' => 'College of Pharmacy'], 'college', null),
    ]);

    expect(labelService()->resolvePublicLabel($clinic))->toBe('Pharmacy');
});

it('gives no label when every ancestor inherits', function () {
    orgTree([
        'clinic' => $clinic = orgTreeEntry(['inherit_public_label_query' => true], 'clinic', 'division'),
        'division' => orgTreeEntry(['inherit_public_label_query' => true, 'public_label' => 'Skipped'], 'division', null),
    ]);

    expect(labelService()->resolvePublicLabel($clinic))->toBeNull();
});

it('passes over an ancestor that can no longer be found', function () {
    $clinic = orgTreeEntry(['inherit_public_label_query' => true], 'clinic', 'division');
    $division = orgTreeEntry(['public_label' => 'Gone'], 'division', 'department');
    $department = orgTreeEntry(['public_label' => 'Pharmacy'], 'department', null);

    Statamic\Facades\Entry::shouldReceive('findOrFail')->with('clinic')->andReturn($clinic);
    // The walk finds the division; by the time the label is read, it is gone.
    Statamic\Facades\Entry::shouldReceive('find')->with('division')->andReturn($division, null);
    Statamic\Facades\Entry::shouldReceive('find')->with('department')->andReturn($department);

    expect(labelService()->resolvePublicLabel($clinic))->toBe('Pharmacy');
});

it('names an entry by its public label first', function () {
    expect(labelService()->displayName(orgEntry([
        'public_label' => 'Pharmacy',
        'workday_supervisory_organization_name' => 'Workday Pharmacy',
        'title' => 'Authored',
    ])))->toBe('Pharmacy');
});

it('gives a Workday-named entry with no label no display name', function () {
    expect(labelService()->displayName(orgEntry([
        'workday_supervisory_organization_name' => 'Workday Pharmacy',
        'title' => 'Authored',
    ])))->toBeNull();
});

it('names an entry with neither a label nor a Workday name by its trimmed title', function (mixed $title, ?string $expected) {
    expect(labelService()->displayName(orgEntry(['title' => $title])))->toBe($expected);
})->with([
    'a title' => ['  Authored Title  ', 'Authored Title'],
    'a blank title' => ['   ', null],
    'no title' => [null, null],
]);
