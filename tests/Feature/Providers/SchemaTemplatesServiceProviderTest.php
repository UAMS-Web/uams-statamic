<?php

declare(strict_types=1);

use App\Http\Controllers\CP\SchemaTemplates\SchemaBlueprintDefaultsController;
use App\Http\Controllers\CP\SchemaTemplates\SchemaJsonLdCacheController;
use App\Http\Controllers\CP\SchemaTemplates\SchemaSectionDefaultsController;
use App\Http\Controllers\CP\SchemaTemplates\SchemaSiteDefaultsController;
use App\Http\Controllers\CP\SchemaTemplates\SchemaTemplatesIndexController;
use App\Providers\SchemaTemplatesServiceProvider;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Statamic\Auth\File\User as FileUser;
use Statamic\CP\Navigation\Nav as Navigation;
use Statamic\CP\Navigation\NavItem;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Facades\User;
use Tests\TestCase;

uses(TestCase::class);

covers(SchemaTemplatesServiceProvider::class);

/**
 * `SchemaTemplatesServiceProvider` registers the Schema Templates permissions,
 * the Control Panel routes of its four screens, the Inertia props that tell the
 * Vue pages which of them the user may edit, and the Tools navigation entry
 * (UAMS-Web/uams-statamic#872).
 *
 * The current user is a Mockery user through the `User` facade, so a case grants
 * exactly one ability without saving a role to the shared `resources/users/roles.yaml`.
 *
 * @command  php artisan test --compact tests/Feature/Providers/SchemaTemplatesServiceProviderTest.php
 */

/**
 * The four abilities, keyed by the Inertia prop that reports each.
 *
 * @return array<string, string>
 */
function schemaTemplatesAbilities(): array
{
    return [
        'canEditSchemaSiteDefaults' => 'edit schema site defaults',
        'canEditSchemaSectionDefaults' => 'edit schema section defaults',
        'canEditSchemaBlueprintDefaults' => 'edit schema blueprint defaults',
        'canManageSchemaJsonLdCache' => 'manage schema json ld cache',
    ];
}

/**
 * Make the current Control Panel user one who holds only the given abilities,
 * or no user at all.
 *
 * @param  list<string>|null  $abilities
 */
function actAsSchemaTemplatesUser(?array $abilities): void
{
    if ($abilities === null) {
        User::shouldReceive('current')->andReturn(null);

        return;
    }

    $user = Mockery::mock(FileUser::class);
    $user->shouldReceive('can')->andReturnUsing(
        static fn (string $ability): bool => in_array($ability, $abilities, true)
    );

    User::shouldReceive('current')->andReturn($user);
}

/**
 * The Tools navigation this provider adds, built on an empty navigation.
 *
 * @return list<NavItem>
 */
function schemaTemplatesNavItems(): array
{
    $root = Nav::getFacadeRoot();
    if (! $root instanceof Navigation) {
        throw new RuntimeException('The Nav facade does not resolve to the navigation');
    }

    /** @var list<Closure> $extensions */
    $extensions = new ReflectionProperty(Navigation::class, 'extensions')->getValue($root);

    $ours = array_values(array_filter(
        $extensions,
        static fn (Closure $extension): bool => new ReflectionFunction($extension)->getClosureThis() instanceof SchemaTemplatesServiceProvider
    ));

    expect($ours)->toHaveCount(1);

    $nav = new Navigation;
    $ours[0]($nav);

    /** @var list<NavItem> $items */
    $items = array_values($nav->items());

    return $items;
}

/**
 * The value of a shared Inertia prop, which the provider shares as a closure.
 */
function schemaTemplatesSharedProp(string $prop): mixed
{
    $shared = Inertia::getShared($prop);

    if (! $shared instanceof Closure) {
        throw new RuntimeException($prop.' is not shared as a closure');
    }

    return $shared();
}

/**
 * A navigation item's label, URL and the ability that gates it.
 *
 * @return array{mixed, mixed, mixed}
 */
function schemaTemplatesNavEntry(NavItem $item): array
{
    $authorization = $item->authorization();

    return [$item->display(), $item->url(), $authorization instanceof stdClass ? $authorization->ability : null];
}

it('registers each permission, labeled, in the Schema Templates group', function (string $ability, string $label): void {
    $permission = Permission::get($ability);
    /** @var array{handle: string, label: string}|null $group */
    $group = collect(Permission::tree())->firstWhere('handle', 'schema_templates');

    expect($permission)->not->toBeNull()
        ->and($permission->label())->toBe($label)
        ->and($permission->group())->toBe('schema_templates')
        ->and($group['label'] ?? null)->toBe('Schema Templates');
})->with([
    'site defaults' => ['edit schema site defaults', 'Edit Schema Site Defaults'],
    'section defaults' => ['edit schema section defaults', 'Edit Schema Section Defaults'],
    'blueprint defaults' => ['edit schema blueprint defaults', 'Edit Schema Blueprint Defaults'],
    'the JSON-LD cache' => ['manage schema json ld cache', 'Manage Schema JSON-LD cache'],
]);

it('adds each Control Panel route under schema-templates', function (string $name, string $method, string $uri, string $action): void {
    /** @var Route|null $route */
    $route = app('router')->getRoutes()->getByName('statamic.cp.schema-templates.'.$name);

    expect($route)->not->toBeNull()
        ->and($route?->uri())->toBe(config()->string('statamic.cp.route').'/schema-templates'.$uri)
        ->and($route?->methods())->toBe($method === 'GET' ? ['GET', 'HEAD'] : [$method])
        ->and($route?->getActionName())->toBe($action);
})->with([
    'the index' => ['index', 'GET', '', SchemaTemplatesIndexController::class.'@index'],
    'the site defaults form' => ['site-defaults.edit', 'GET', '/site-defaults/edit', SchemaSiteDefaultsController::class.'@edit'],
    'the site defaults save' => ['site-defaults.update', 'PATCH', '/site-defaults', SchemaSiteDefaultsController::class.'@update'],
    'the section defaults list' => ['section-defaults.index', 'GET', '/section-defaults', SchemaSectionDefaultsController::class.'@index'],
    'the section defaults form' => ['section-defaults.edit', 'GET', '/section-defaults/{sectionType}/{sectionHandle}/edit', SchemaSectionDefaultsController::class.'@edit'],
    'the section defaults save' => ['section-defaults.update', 'PATCH', '/section-defaults/{sectionType}/{sectionHandle}', SchemaSectionDefaultsController::class.'@update'],
    'the blueprint defaults list' => ['blueprint-defaults.index', 'GET', '/blueprint-defaults', SchemaBlueprintDefaultsController::class.'@index'],
    'the blueprint defaults form' => ['blueprint-defaults.edit', 'GET', '/blueprint-defaults/{sectionType}/{sectionHandle}/{blueprintHandle}/edit', SchemaBlueprintDefaultsController::class.'@edit'],
    'the blueprint defaults save' => ['blueprint-defaults.update', 'PATCH', '/blueprint-defaults/{sectionType}/{sectionHandle}/{blueprintHandle}', SchemaBlueprintDefaultsController::class.'@update'],
    'the JSON-LD cache screen' => ['json-ld-cache.index', 'GET', '/json-ld-cache', SchemaJsonLdCacheController::class.'@index'],
    'the JSON-LD cache clear' => ['json-ld-cache.clear', 'POST', '/json-ld-cache/clear', SchemaJsonLdCacheController::class.'@clear'],
    'the JSON-LD cache warm' => ['json-ld-cache.warm', 'POST', '/json-ld-cache/warm', SchemaJsonLdCacheController::class.'@warm'],
]);

it('shares each ability as an Inertia prop that reads only that ability', function (string $granted): void {
    actAsSchemaTemplatesUser([$granted]);

    foreach (schemaTemplatesAbilities() as $prop => $ability) {
        expect(schemaTemplatesSharedProp($prop))->toBe($ability === $granted);
    }
})->with(array_values(schemaTemplatesAbilities()));

it('shares every ability as false when no one is signed in', function (): void {
    actAsSchemaTemplatesUser(null);

    foreach (array_keys(schemaTemplatesAbilities()) as $prop) {
        expect(schemaTemplatesSharedProp($prop))->toBeFalse();
    }
});

it('adds nothing to the navigation for a user with none of the abilities', function (): void {
    actAsSchemaTemplatesUser([]);

    expect(schemaTemplatesNavItems())->toBe([]);
});

it('adds the Schema Templates tool for a user with any one ability, with every screen gated by its own', function (string $granted): void {
    actAsSchemaTemplatesUser([$granted]);

    $items = schemaTemplatesNavItems();

    // Nav::item() registers each child as it builds it, so the tool comes with its four screens
    expect(array_map(static fn (NavItem $item): mixed => $item->display(), $items))->toBe([
        'Schema Templates', 'Site Defaults', 'Section Defaults', 'Blueprint Defaults', 'JSON-LD cache',
    ]);

    $tools = $items[0];
    /** @var Collection<int, NavItem> $children */
    $children = $tools->children();

    expect($tools->section())->toBe('Tools')
        ->and($tools->display())->toBe('Schema Templates')
        ->and($tools->url())->toBe(cp_route('schema-templates.index'))
        ->and($tools->icon())->toBe('programming-script-code')
        ->and($children->map(schemaTemplatesNavEntry(...))->all())->toBe([
            ['Site Defaults', cp_route('schema-templates.site-defaults.edit'), 'edit schema site defaults'],
            ['Section Defaults', cp_route('schema-templates.section-defaults.index'), 'edit schema section defaults'],
            ['Blueprint Defaults', cp_route('schema-templates.blueprint-defaults.index'), 'edit schema blueprint defaults'],
            ['JSON-LD cache', cp_route('schema-templates.json-ld-cache.index'), 'manage schema json ld cache'],
        ]);
})->with(array_values(schemaTemplatesAbilities()));
