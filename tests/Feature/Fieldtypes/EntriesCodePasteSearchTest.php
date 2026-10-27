<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Statamic\Auth\File\Role as FileRole;
use Statamic\Auth\File\User as FileUser;
use Statamic\Contracts\Auth\RoleRepository as RoleRepositoryContract;
use Statamic\Entries\Entry as ConcreteEntry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Role;
use Statamic\Facades\Search;
use Statamic\Facades\User;
use Statamic\Search\Index;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\StacheIsolatedTestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

/**
 * Establishes what Statamic's own `entries` fieldtype already does for a picker
 * that finds an entry by pasting its code, which `terms_search_index` provides
 * for taxonomy terms (UAMS-Web/uams-statamic-archive#2604).
 *
 * The collections being converted from taxonomies (UAMS-Web/uams-statamic-archive#2596, UAMS-Web/uams-statamic-archive#2597) need that
 * picker. These tests drive the Control Panel's relationship index route with a
 * field config encoded the way the picker sends it, so each criterion is
 * measured against the fieldtype that would actually serve the picker: a code
 * found through a search index, single and multiple selection, the current site
 * versus `select_across_sites`, and a collection the user may not view.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/EntriesCodePasteSearchTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * Collection handle for the probe vocabulary.
 */
const CODE_PASTE_COLLECTION = 'code_paste_probe';

/**
 * Search index handle over the probe vocabulary.
 */
const CODE_PASTE_INDEX = 'code_paste_probe';

beforeEach(function (): void {
    // Repoint the file role repository at a throwaway path, so the roles the
    // permission tests save never reach the tracked resources/users/roles.yaml
    $rolesDirectory = storage_path('framework/testing/code-paste-roles/'.uniqid('', true));
    File::ensureDirectoryExists($rolesDirectory);
    app(RoleRepositoryContract::class)->path($rolesDirectory.'/roles.yaml');

    // Stand up a two-site collection whose entries carry a code
    $collection = Collection::make(CODE_PASTE_COLLECTION);
    $collection->title('Code Paste Probe');
    $collection->sites(['default', 'health']);
    $collection->save();

    // Index the title and the code, as the NUCC index does for its terms
    config()->set('statamic.search.indexes.'.CODE_PASTE_INDEX, [
        'driver' => 'local',
        'searchables' => 'collection:'.CODE_PASTE_COLLECTION,
        'fields' => ['title', 'code'],
    ]);

    codePasteEntry('thoracic-surgeon', 'default', 'Thoracic Surgeon', '208G00000X');
    codePasteEntry('general-surgery', 'default', 'General Surgery', '208600000X');
    codePasteEntry('pediatric-cardiology', 'health', 'Pediatric Cardiology', '2080P0202X');

    mustBe(Index::class, Search::index(CODE_PASTE_INDEX), 'the probe search index')->update();
});

/**
 * Save a probe entry with a code on a site.
 *
 * @param  string  $slug  Entry slug
 * @param  string  $site  Site handle
 * @param  string  $title  Entry title
 * @param  string  $code  The code an editor would paste
 */
function codePasteEntry(string $slug, string $site, string $title, string $code): void
{
    $entry = mustBe(ConcreteEntry::class, Entry::make(), 'a probe entry');
    $entry->collection(CODE_PASTE_COLLECTION);
    $entry->locale($site);
    $entry->slug($slug);
    $entry->data(['title' => $title, 'code' => $code]);
    $entry->saveQuietly();
}

/**
 * Sign in as a super user.
 */
function codePasteSuperUser(): void
{
    $user = mustBe(FileUser::class, User::make(), 'a super user');
    $user->email('code-paste-super@example.com');
    $user->makeSuper();
    $user->save();

    actingAs($user);
}

/**
 * Ask the relationship index for the picker's options, as the picker does.
 *
 * @param  array<string, mixed>  $config  Field config beyond the type and collection
 * @param  string  $search  What the editor typed or pasted
 * @param  string  $site  The site the entry being edited belongs to
 * @return TestResponse<Response> The index response
 */
function codePasteSearch(array $config, string $search, string $site = 'default'): TestResponse
{
    $fieldConfig = ['type' => 'entries', 'collections' => [CODE_PASTE_COLLECTION]] + $config;
    $encoded = base64_encode((string) json_encode($fieldConfig));

    return getJson(mustBeString(cp_route('relationship.index'), 'the relationship index route').'?'.http_build_query([
        'config' => $encoded,
        'search' => $search,
        'site' => $site,
    ]));
}

/**
 * Titles of the options a response offers.
 *
 * @param  TestResponse<Response>  $response  The index response
 * @return list<string> Offered titles
 */
function codePasteTitles(TestResponse $response): array
{
    $response->assertOk();

    $rows = $response->json('data');
    $titles = [];

    // Collect each offered row's title, skipping anything that is not one
    foreach (\is_array($rows) ? $rows : [] as $row) {
        $title = \is_array($row) ? ($row['title'] ?? null) : null;

        if (\is_string($title)) {
            $titles[] = $title;
        }
    }

    return $titles;
}

it('finds an entry by its pasted code through the search index', function (array $selection): void {
    codePasteSuperUser();

    $titles = codePasteTitles(codePasteSearch(['search_index' => CODE_PASTE_INDEX] + $selection, '208G00000X'));

    expect($titles)->toBe(['Thoracic Surgeon']);
})->with([
    'single' => [['max_items' => 1]],
    'multiple' => [[]],
]);

it('does not find a code without the search index, since the default search matches only the title', function (): void {
    codePasteSuperUser();

    expect(codePasteTitles(codePasteSearch([], '208G00000X')))->toBe([])
        ->and(codePasteTitles(codePasteSearch([], 'Thoracic')))->toBe(['Thoracic Surgeon']);
});

it('offers only the current site\'s entries without select_across_sites', function (): void {
    codePasteSuperUser();

    expect(codePasteTitles(codePasteSearch(['search_index' => CODE_PASTE_INDEX], '2080P0202X', 'default')))->toBe([])
        ->and(codePasteTitles(codePasteSearch(['search_index' => CODE_PASTE_INDEX], '2080P0202X', 'health')))->toBe(['Pediatric Cardiology']);
});

it('finds an entry held on another site with select_across_sites', function (): void {
    codePasteSuperUser();

    $titles = codePasteTitles(codePasteSearch(
        ['search_index' => CODE_PASTE_INDEX, 'select_across_sites' => true],
        '2080P0202X',
        'default',
    ));

    expect($titles)->toBe(['Pediatric Cardiology']);
});

/**
 * Sign in as a user whose role grants Control Panel and site access, plus any
 * further permissions given.
 *
 * @param  string  $handle  Role and user handle
 * @param  list<string>  $permissions  Permissions beyond Control Panel and site access
 */
function codePasteRoleUser(string $handle, array $permissions): void
{
    $role = mustBe(FileRole::class, Role::make($handle), 'a role');
    $role->title($handle);
    $role->permissions(array_merge(['access cp', 'access default site', 'access health site'], $permissions));
    $role->save();

    $user = mustBe(FileUser::class, User::make(), 'a role user');
    $user->email($handle.'@example.com');
    $user->assignRole($handle);
    $user->save();

    actingAs($user);
}

it('offers nothing from a collection the user may not view', function (): void {
    codePasteRoleUser('code_paste_limited', []);

    expect(codePasteTitles(codePasteSearch(['search_index' => CODE_PASTE_INDEX], '208G00000X')))->toBe([]);
});

it('offers the entry once the same role may view the collection', function (): void {
    codePasteRoleUser('code_paste_viewer', ['view '.CODE_PASTE_COLLECTION.' entries']);

    expect(codePasteTitles(codePasteSearch(['search_index' => CODE_PASTE_INDEX], '208G00000X')))->toBe(['Thoracic Surgeon']);
});
