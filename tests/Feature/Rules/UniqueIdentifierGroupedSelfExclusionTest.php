<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;
use Statamic\Auth\File\User as FileUser;
use Statamic\Entries\Entry as ConcreteEntry;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Statamic\Fields\Blueprint as StatamicBlueprint;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\StacheIsolatedTestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patchJson;

/**
 * Re-saving a person through the Control Panel must not flag the person's own
 * Workday ids as held by another entry.
 *
 * The four Workday ids live inside the `uams_workday_id` group, so their
 * `unique_identifier:people,{id},…` rules are built by the group fieldtype's
 * own nested validator. That validator is given no replacements, so `{id}` is
 * substituted with the literal `NULL` before the Control Panel's replacements
 * can reach it, and the rule excludes nothing -- the entry then collides with
 * itself on every save. These tests drive the real update route so the whole
 * substitution chain runs.
 *
 * @command  php artisan test --compact tests/Feature/Rules/UniqueIdentifierGroupedSelfExclusionTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    // Stand up the collection with the real person blueprint, which carries the grouped rules
    $collection = Collection::make('people');
    $collection->title('People');
    $collection->save();

    $contents = Yaml::parseFile(base_path('resources/blueprints/collections/people/person.yaml'));

    $blueprint = mustBe(StatamicBlueprint::class, Blueprint::make('person'), 'the person blueprint');
    $blueprint->setNamespace('collections.people');
    $blueprint->setContents(\is_array($contents) ? $contents : []);
    $blueprint->save();
});

/**
 * A saved person with Workday ids in the `uams_workday_id` group.
 *
 * @param  string  $slug  Entry slug
 * @param  array<string, string>  $workday  Values for the `uams_workday_id` group
 * @return ConcreteEntry The saved entry
 */
function groupedSelfExclusionPerson(string $slug, array $workday): ConcreteEntry
{
    $entry = mustBe(ConcreteEntry::class, Entry::make(), 'a new person entry');
    $entry->collection('people');
    $entry->blueprint('person');
    $entry->locale('default');
    $entry->slug($slug);
    $entry->data([
        'title' => ucwords(str_replace('-', ' ', $slug)),
        'uams_worker_query' => true,
        'uams_worker_subtype' => 'employee',
        'uams_workday_id' => $workday,
    ]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * Send the entry's values back through the Control Panel update route, as a
 * super user, with the given Workday ids.
 *
 * @param  ConcreteEntry  $entry  The entry being re-saved
 * @param  array<string, string>  $workday  Values for the `uams_workday_id` group
 * @return TestResponse<Response> The update response
 */
function groupedSelfExclusionResave(ConcreteEntry $entry, array $workday): TestResponse
{
    $user = mustBe(FileUser::class, User::make(), 'a new user');
    $user->email('grouped-self-exclusion@example.com');
    $user->makeSuper();
    $user->save();

    actingAs($user);

    return patchJson(mustBeString(cp_route('collections.entries.update', ['people', mustBeString($entry->id(), 'the entry id')]), 'the update route'), [
        'title' => $entry->get('title'),
        'slug' => $entry->slug(),
        'published' => true,
        'uams_worker_query' => true,
        'uams_worker_subtype' => 'employee',
        'uams_workday_id' => $workday,
    ]);
}

it('does not flag a person\'s own grouped Workday ids when the person is saved again', function (): void {
    $workday = ['workday_universal_id' => '400026157', 'workday_employee_id' => '2017010'];
    $person = groupedSelfExclusionPerson('grouped-self-exclusion', $workday);

    $response = groupedSelfExclusionResave($person, $workday);

    $response->assertJsonMissingValidationErrors([
        'uams_workday_id.workday_universal_id',
        'uams_workday_id.workday_employee_id',
    ]);
});

it('still flags a grouped Workday id held by a different person', function (): void {
    groupedSelfExclusionPerson('grouped-holder', ['workday_universal_id' => '400026157', 'workday_employee_id' => '2017010']);
    $second = groupedSelfExclusionPerson('grouped-second', ['workday_universal_id' => '400099999', 'workday_employee_id' => '2099999']);

    $response = groupedSelfExclusionResave($second, ['workday_universal_id' => '400026157', 'workday_employee_id' => '2099999']);

    $response->assertJsonValidationErrors(['uams_workday_id.workday_universal_id']);
    $response->assertJsonMissingValidationErrors(['uams_workday_id.workday_employee_id']);
});
