<?php

declare(strict_types=1);

use App\Http\Controllers\CP\SchemaTemplates\SchemaBlueprintDefaultsController;
use App\Services\SchemaTemplates\SchemaBlueprintDefaultsRepository;
use App\Services\SchemaTemplates\SchemaSectionDefaultsRepository;
use App\Services\SchemaTemplates\SchemaSiteDefaultsRepository;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Auth\File\User as FileStatamicUser;
use Statamic\Entries\Collection as StatamicCollection;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\User;
use Statamic\Fields\Blueprint as FieldsBlueprint;
use Statamic\Taxonomies\Taxonomy as StatamicTaxonomy;
use Tests\Support\StacheIsolatedTestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\withoutMiddleware;
use function Pest\Laravel\withoutVite;

uses(StacheIsolatedTestCase::class, MockeryPHPUnitIntegration::class);

covers(SchemaBlueprintDefaultsController::class);

/**
 * `SchemaBlueprintDefaultsController` lists the collections and taxonomies whose blueprints
 * can hold Schema Templates defaults, and shows and saves one blueprint's defaults
 * (UAMS-Web/uams-statamic#848).
 *
 * The site and section defaults are stubbed, so the real `resources/addons` file is never
 * read. The acting user is never saved, so nothing is written to the `users` submodule.
 *
 * @command  php artisan test --compact tests/Feature/Http/Controllers/CP/SchemaTemplates/SchemaBlueprintDefaultsControllerTest.php
 */
beforeEach(function (): void {
    withoutMiddleware(PreventRequestForgery::class);
    withoutVite();

    $user = mustBe(FileStatamicUser::class, User::make(), 'the acting user');
    $user->id('blueprint-defaults-user');
    $user->email('blueprint-defaults@example.test');
    $user->makeSuper();

    actingAs($user);

    // The entry pickers on the form list this collection
    Collection::make('schema_objects')->save();

    blueprintDefaultsStub();
});

/**
 * Stub the site's and the section's defaults, expecting the site handle they are read for.
 *
 * @param  array<mixed>  $site
 * @param  array<mixed>  $section
 */
function blueprintDefaultsStub(array $site = [], array $section = [], string $siteHandle = 'default'): void
{
    app()->instance(SchemaSiteDefaultsRepository::class, Mockery::mock(
        SchemaSiteDefaultsRepository::class,
        fn (MockInterface $mock) => $mock->allows('get')->with($siteHandle)->andReturns($site)
    ));
    app()->instance(SchemaSectionDefaultsRepository::class, Mockery::mock(
        SchemaSectionDefaultsRepository::class,
        fn (MockInterface $mock) => $mock->allows('get')->andReturnUsing(
            fn (string $handle, string $type): array => str_starts_with($handle, 'probe_') && in_array($type, ['collection', 'taxonomy'], true) ? $section : []
        )
    ));
}

/**
 * Save a collection or taxonomy with a title.
 */
function blueprintDefaultsSection(string $type, string $handle, mixed $title): void
{
    $section = $type === 'taxonomy'
        ? mustBe(StatamicTaxonomy::class, Taxonomy::make($handle), 'the taxonomy')
        : mustBe(StatamicCollection::class, Collection::make($handle), 'the collection');
    $section->title($title);
    $section->save();
}

/**
 * Save a blueprint for a section.
 */
function blueprintDefaultsBlueprint(string $namespace, string $handle, mixed $title): void
{
    $blueprint = mustBe(FieldsBlueprint::class, Blueprint::make($handle), 'the blueprint');
    $blueprint->setNamespace($namespace);
    $blueprint->setContents(['title' => $title]);
    $blueprint->save();
}

/**
 * The URL of a blueprint defaults route.
 *
 * @param  array<int, string>  $parameters
 */
function blueprintDefaultsRoute(string $action, array $parameters = []): string
{
    return mustBeString(cp_route('schema-templates.blueprint-defaults.'.$action, $parameters), 'the route');
}

/**
 * The edit form for a section's blueprint, as the data behind the page.
 *
 * @return array<mixed>
 */
function blueprintDefaultsForm(string $type = 'collection', string $section = 'probe_posts', string $blueprint = 'article'): array
{
    $response = getJson(blueprintDefaultsRoute('edit', [$type, $section, $blueprint]));
    $response->assertOk();

    return mustBeArray($response->json(), 'the form');
}

/**
 * One setting of each field on the form, by field handle.
 *
 * @param  array<mixed>  $form
 * @return array<string, mixed>
 */
function blueprintDefaultsFieldSetting(array $form, string $setting): array
{
    $settings = [];
    foreach (mustBeArray(data_get($form, 'blueprint.tabs.0.sections.0.fields'), 'the fields') as $field) {
        $field = mustBeArray($field, 'a field');
        $settings[mustBeString($field['handle'] ?? null, 'the handle')] = $field[$setting] ?? null;
    }

    return $settings;
}

/**
 * Refuse one ability to a super user, who passes every other Control Panel gate.
 */
function blueprintDefaultsDeny(string $denied): void
{
    Gate::before(fn (mixed $user, string $ability): ?bool => $ability === $denied ? false : null);
}

it('lists the collections and taxonomies that have blueprints, with each blueprint', function (): void {
    // Neither of these has a blueprint, and each comes before a section that does
    blueprintDefaultsSection('collection', 'a_bare_collection', 'Bare');
    blueprintDefaultsSection('taxonomy', 'a_bare_taxonomy', 'Bare');

    blueprintDefaultsSection('collection', 'probe_posts', 'Probe posts');
    blueprintDefaultsSection('collection', 'probe_videos', 'Probe videos');
    blueprintDefaultsSection('taxonomy', 'probe_topics', 'Probe topics');
    $videos = mustBe(StatamicCollection::class, Collection::find('probe_videos'), 'the collection');
    $videos->icon('video');
    $videos->save();

    blueprintDefaultsBlueprint('collections.probe_posts', 'article', 'Article');
    blueprintDefaultsBlueprint('collections.probe_posts', 'notice', 'Notice');
    blueprintDefaultsBlueprint('collections.probe_videos', 'clip', 'Clip');
    blueprintDefaultsBlueprint('taxonomies.probe_topics', 'topic', 'Topic');

    get(blueprintDefaultsRoute('index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('app::SchemaTemplatesBlueprintDefaultsIndex', false)
            ->where('collections', [
                [
                    'title' => 'Probe posts',
                    'handle' => 'probe_posts',
                    'icon' => 'collections',
                    'type' => 'collection',
                    'blueprints' => [['handle' => 'article', 'title' => 'Article'], ['handle' => 'notice', 'title' => 'Notice']],
                ],
                [
                    'title' => 'Probe videos',
                    'handle' => 'probe_videos',
                    'icon' => 'video',
                    'type' => 'collection',
                    'blueprints' => [['handle' => 'clip', 'title' => 'Clip']],
                ],
            ])
            ->where('taxonomies', [
                [
                    'title' => 'Probe topics',
                    'handle' => 'probe_topics',
                    'icon' => 'taxonomies',
                    'type' => 'taxonomy',
                    'blueprints' => [['handle' => 'topic', 'title' => 'Topic']],
                ],
            ])
            ->etc());
});

it('refuses each page to a user without the permission', function (): void {
    blueprintDefaultsDeny('edit schema blueprint defaults');
    app()->instance(SchemaBlueprintDefaultsRepository::class, Mockery::mock(
        SchemaBlueprintDefaultsRepository::class,
        fn (MockInterface $mock) => $mock->expects('put')->never()
    ));

    getJson(blueprintDefaultsRoute('index'))->assertForbidden();
    getJson(blueprintDefaultsRoute('edit', ['collection', 'probe_posts', 'article']))->assertForbidden();
    patchJson(blueprintDefaultsRoute('update', ['collection', 'probe_posts', 'article']), ['enabled' => 'true'])->assertForbidden();
});

it('shows the form for a collection\'s or a taxonomy\'s blueprint', function (string $type, string $namespace): void {
    blueprintDefaultsSection('collection', 'probe_posts', 'The collection');
    blueprintDefaultsSection('taxonomy', 'probe_posts', 'The taxonomy');
    blueprintDefaultsBlueprint('collections.probe_posts', 'article', 'The collections blueprint');
    blueprintDefaultsBlueprint('taxonomies.probe_posts', 'article', 'The taxonomies blueprint');

    $form = blueprintDefaultsForm($type);

    expect($form['title'] ?? null)->toBe(sprintf("The %s \u{203A} The %s blueprint \u{2014} Schema Blueprint Defaults", $type, $namespace))
        ->and($form['icon'] ?? null)->toBe('blueprints')
        ->and($form['submitUrl'] ?? null)->toBe(blueprintDefaultsRoute('update', [$type, 'probe_posts', 'article']))
        ->and($form['submitMethod'] ?? null)->toBe('PATCH')
        ->and(array_keys(blueprintDefaultsFieldSetting($form, 'handle')))->toBe([
            'enabled', 'inherit_webpage_entry', 'webpage_entry', 'inherit_main_content_entry', 'main_content_entry',
        ]);
})->with([
    ['collection', 'collections'],
    ['taxonomy', 'taxonomies'],
]);

it('names the section and the blueprint by handle when there is none, or its title is not text', function (): void {
    expect(blueprintDefaultsForm('collection', 'probe_none', 'nothing')['title'] ?? null)
        ->toBe("probe_none \u{203A} nothing \u{2014} Schema Blueprint Defaults")
        ->and(blueprintDefaultsForm('taxonomy', 'probe_none', 'nothing')['title'] ?? null)
        ->toBe("probe_none \u{203A} nothing \u{2014} Schema Blueprint Defaults");

    blueprintDefaultsSection('collection', 'probe_posts', ['not' => 'text']);
    blueprintDefaultsBlueprint('collections.probe_posts', 'article', ['not' => 'text']);

    expect(blueprintDefaultsForm()['title'] ?? null)
        ->toBe("probe_posts \u{203A} article \u{2014} Schema Blueprint Defaults");
});

it('shows a section or blueprint title that is a number', function (): void {
    blueprintDefaultsSection('collection', 'probe_posts', 12);
    blueprintDefaultsBlueprint('collections.probe_posts', 'article', 34);

    expect(blueprintDefaultsForm()['title'] ?? null)->toBe("12 \u{203A} 34 \u{2014} Schema Blueprint Defaults");
});

it('shows the saved defaults, and otherwise inherits', function (): void {
    blueprintDefaultsSection('collection', 'probe_posts', 'Probe posts');

    expect(blueprintDefaultsForm()['values'] ?? null)->toBe([
        'enabled' => 'inherit',
        'inherit_webpage_entry' => 'true',
        'webpage_entry' => [],
        'inherit_main_content_entry' => 'true',
        'main_content_entry' => [],
    ]);

    (new SchemaBlueprintDefaultsRepository)->put('probe_posts', 'article', 'collection', [
        'enabled' => false,
        'inherit_webpage_entry' => false,
        'webpage_entry' => 'entry-one',
    ]);

    expect(blueprintDefaultsForm()['values'] ?? null)->toBe([
        'enabled' => 'false',
        'inherit_webpage_entry' => 'false',
        'webpage_entry' => ['entry-one'],
        'inherit_main_content_entry' => 'true',
        'main_content_entry' => [],
    ])
        // Another blueprint, or the taxonomy of the same name, has defaults of its own
        ->and(data_get(blueprintDefaultsForm('collection', 'probe_posts', 'other'), 'values.enabled'))->toBe('inherit')
        ->and(data_get(blueprintDefaultsForm('taxonomy'), 'values.enabled'))->toBe('inherit');
});

it('locks the inherit fields off when the section is disabled, or inherits from a disabled site', function (array $site, array $section, bool $disabled): void {
    blueprintDefaultsStub($site, $section);
    blueprintDefaultsSection('collection', 'probe_posts', 'Probe posts');

    $form = blueprintDefaultsForm();
    $readOnly = blueprintDefaultsFieldSetting($form, 'read_only');

    expect($readOnly['inherit_webpage_entry'] ?? null)->toBe($disabled)
        ->and($readOnly['inherit_main_content_entry'] ?? null)->toBe($disabled)
        ->and(data_get($form, 'values.inherit_webpage_entry'))->toBe($disabled ? 'false' : 'true')
        ->and(data_get($form, 'values.inherit_main_content_entry'))->toBe($disabled ? 'false' : 'true');
})->with([
    'nothing set' => [[], [], false],
    'site disabled, section unset' => [['enabled' => 'false'], [], false],
    'site disabled, section inherits' => [['enabled' => 'false'], ['enabled' => 'inherit'], true],
    'site disabled as a boolean, section inherits' => [['enabled' => false], ['enabled' => 'inherit'], true],
    'site enabled, section inherits' => [['enabled' => 'true'], ['enabled' => 'inherit'], false],
    'site enabled as a boolean, section inherits' => [['enabled' => true], ['enabled' => 'inherit'], false],
    'site unset, section inherits' => [[], ['enabled' => 'inherit'], false],
    'site disabled, section enabled' => [['enabled' => 'false'], ['enabled' => 'true'], false],
    'site enabled, section disabled' => [['enabled' => 'true'], ['enabled' => 'false'], true],
    'site unset, section disabled' => [[], ['enabled' => 'false'], true],
]);

it('reads the selected site\'s defaults', function (): void {
    Site::setSites([
        'default' => ['name' => 'Default', 'url' => '/', 'locale' => 'en_US'],
        '7' => ['name' => 'Seven', 'url' => '/seven/', 'locale' => 'en_US'],
    ]);
    blueprintDefaultsSection('collection', 'probe_posts', 'Probe posts');

    // A handle that is digits is an integer by the time the site holds it
    Site::setSelected('7');
    blueprintDefaultsStub(['enabled' => 'false'], ['enabled' => 'inherit'], '7');

    expect(blueprintDefaultsFieldSetting(blueprintDefaultsForm(), 'read_only')['inherit_webpage_entry'] ?? null)->toBeTrue();
});

it('saves the submitted defaults, without those left empty', function (string $type): void {
    app()->instance(SchemaBlueprintDefaultsRepository::class, Mockery::mock(
        SchemaBlueprintDefaultsRepository::class,
        fn (MockInterface $mock) => $mock->expects('put')->once()->with('probe_posts', 'article', $type, [
            'enabled' => 'inherit',
            'inherit_webpage_entry' => false,
            'webpage_entry' => 'entry-one',
        ])
    ));

    patchJson(blueprintDefaultsRoute('update', [$type, 'probe_posts', 'article']), [
        'enabled' => 'inherit',
        'inherit_webpage_entry' => 'false',
        'webpage_entry' => ['entry-one'],
        'inherit_main_content_entry' => '',
        'main_content_entry' => [],
        'not_a_field' => 'dropped',
    ])->assertOk()->assertExactJson(['saved' => true]);
})->with(['collection', 'taxonomy']);

it('saves the same whether the section is disabled or not', function (array $section): void {
    blueprintDefaultsStub([], $section);
    app()->instance(SchemaBlueprintDefaultsRepository::class, Mockery::mock(
        SchemaBlueprintDefaultsRepository::class,
        fn (MockInterface $mock) => $mock->expects('put')->once()->with('probe_posts', 'article', 'collection', [
            'enabled' => true,
            'inherit_webpage_entry' => true,
        ])
    ));

    patchJson(blueprintDefaultsRoute('update', ['collection', 'probe_posts', 'article']), [
        'enabled' => 'true',
        'inherit_webpage_entry' => 'true',
    ])->assertOk();
})->with([
    'enabled' => [['enabled' => 'true']],
    'disabled' => [['enabled' => 'false']],
]);
