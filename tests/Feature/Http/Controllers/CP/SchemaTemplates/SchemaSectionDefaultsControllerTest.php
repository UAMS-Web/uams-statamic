<?php

declare(strict_types=1);

use App\Http\Controllers\CP\SchemaTemplates\SchemaSectionDefaultsController;
use App\Services\SchemaTemplates\SchemaSectionDefaultsRepository;
use App\Services\SchemaTemplates\SchemaSiteDefaultsRepository;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Auth\File\User as FileStatamicUser;
use Statamic\Entries\Collection as StatamicCollection;
use Statamic\Facades\Collection;
use Statamic\Facades\User;
use Tests\Support\StacheIsolatedTestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\withoutMiddleware;
use function Pest\Laravel\withoutVite;

uses(StacheIsolatedTestCase::class, MockeryPHPUnitIntegration::class);

covers(SchemaSectionDefaultsController::class);

/**
 * The Schema Section Defaults form never locks its inherit fields, and defaults them to "Inherit"
 * whatever the site says (UAMS-Web/uams-statamic#1549, built in #1555).
 *
 * Below a section is the blueprint, whose `enabled` field is never locked: it can turn output on
 * under a disabled site or section, and then the cascade reads the section's inherit fields
 * whenever the blueprint inherits.
 *
 * The site defaults are stubbed, to show that no setting of them changes the form, so the real
 * `resources/addons` file is never read. The acting
 * user is never saved, so nothing is written to the `users` submodule.
 *
 * @command  php artisan test --compact tests/Feature/Http/Controllers/CP/SchemaTemplates/SchemaSectionDefaultsControllerTest.php
 */
beforeEach(function (): void {
    withoutMiddleware(PreventRequestForgery::class);
    withoutVite();

    $user = mustBe(FileStatamicUser::class, User::make(), 'the acting user');
    $user->id('section-defaults-user');
    $user->email('section-defaults@example.test');
    $user->makeSuper();

    actingAs($user);

    // The entry pickers on the form list this collection
    Collection::make('schema_objects')->save();

    $section = mustBe(StatamicCollection::class, Collection::make('probe_posts'), 'the collection');
    $section->title('Probe posts');
    $section->save();
});

/**
 * Stub the site's defaults.
 *
 * @param  array<mixed>  $site
 */
function sectionDefaultsSite(array $site): void
{
    app()->instance(SchemaSiteDefaultsRepository::class, Mockery::mock(
        SchemaSiteDefaultsRepository::class,
        fn (MockInterface $mock) => $mock->allows('get')->with('default')->andReturns($site)
    ));
}

/**
 * The edit form for the probe collection's section defaults, as the data behind the page.
 *
 * @return array<mixed>
 */
function sectionDefaultsForm(): array
{
    $response = getJson(mustBeString(cp_route('schema-templates.section-defaults.edit', ['collection', 'probe_posts']), 'the route'));
    $response->assertOk();

    return mustBeArray($response->json(), 'the form');
}

/**
 * Whether each inherit field is read-only, and the value each shows.
 *
 * @param  array<mixed>  $form
 * @return list<mixed>
 */
function sectionDefaultsInheritState(array $form): array
{
    $readOnly = [];
    foreach (mustBeArray(data_get($form, 'blueprint.tabs.0.sections.0.fields'), 'the fields') as $field) {
        $field = mustBeArray($field, 'a field');
        $readOnly[mustBeString($field['handle'] ?? null, 'the handle')] = $field['read_only'] ?? null;
    }

    return [
        $readOnly['inherit_webpage_entry'] ?? null,
        $readOnly['inherit_main_content_entry'] ?? null,
        data_get($form, 'values.inherit_webpage_entry'),
        data_get($form, 'values.inherit_main_content_entry'),
    ];
}

it('never locks the inherit fields and defaults them to Inherit, whatever the site and the section have enabled', function (): void {
    $settings = ['unset' => null, "'inherit'" => 'inherit', "'true'" => 'true', 'true' => true, "'false'" => 'false', 'false' => false];

    $unexpected = [];
    foreach (array_diff_key($settings, ["'inherit'" => true]) as $siteName => $site) {
        sectionDefaultsSite($site === null ? [] : ['enabled' => $site]);

        foreach ($settings as $sectionName => $section) {
            (new SchemaSectionDefaultsRepository)->put('probe_posts', 'collection', $section === null ? [] : ['enabled' => $section]);
            $seen = sectionDefaultsInheritState(sectionDefaultsForm());

            if ($seen !== [false, false, 'true', 'true']) {
                $unexpected["site {$siteName}, section {$sectionName}"] = $seen;
            }
        }
    }

    expect($unexpected)->toBe([]);
});

it('shows a saved inherit value as it is saved, and saving the form keeps it', function (array $site, mixed $section, bool $saved): void {
    sectionDefaultsSite($site);
    (new SchemaSectionDefaultsRepository)->put('probe_posts', 'collection', [
        'enabled' => $section,
        'inherit_webpage_entry' => $saved,
        'inherit_main_content_entry' => $saved,
    ]);

    $form = sectionDefaultsForm();
    $shown = $saved ? 'true' : 'false';

    expect(sectionDefaultsInheritState($form))->toBe([false, false, $shown, $shown]);

    // The Control Panel submits the values the form shows (measured in a browser, #1549)
    patchJson(mustBeString(cp_route('schema-templates.section-defaults.update', ['collection', 'probe_posts']), 'the route'), mustBeArray(data_get($form, 'values'), 'the values'))
        ->assertOk();

    expect((new SchemaSectionDefaultsRepository)->get('probe_posts', 'collection'))->toMatchArray([
        'inherit_webpage_entry' => $saved,
        'inherit_main_content_entry' => $saved,
    ]);
})->with([
    'Inherit saved, site disabled, section inherits' => [['enabled' => false], 'inherit', true],
    'Inherit saved, site disabled, section disabled' => [['enabled' => false], false, true],
    'Inherit saved, site enabled' => [['enabled' => true], 'inherit', true],
    'off saved, site disabled' => [['enabled' => false], 'inherit', false],
    'off saved, site enabled' => [['enabled' => true], 'inherit', false],
]);
