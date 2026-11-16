<?php

declare(strict_types=1);

use App\Fieldtypes\Entries;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Statamic\Entries\Entry as EntryItem;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Statamic\Fields\Blueprint as BlueprintItem;
use Statamic\Fields\Field;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\StacheIsolatedTestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\withoutMiddleware;
use function Pest\Laravel\withoutVite;

/**
 * A computed `entries` field on a real Control Panel edit page
 * (UAMS-Web/uams-statamic#500). The computed returns an `EntryCollection`, which
 * used to reach `findEntry()` as an array key and fail the whole page, empty or
 * not. The blueprint declares the fields, unlike a fixture without them, which
 * never reaches this path.
 *
 * @command php artisan test --compact tests/Feature/Fieldtypes/EntriesComputedValueTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * Save a published `vessels` entry.
 *
 * @param  array<string, mixed>  $data
 */
function saveVessel(string $id, array $data = []): EntryItem
{
    /** @var EntryItem $entry */
    $entry = Entry::make();
    $entry->collection('vessels');
    $entry->locale('default');
    $entry->blueprint('vessel');
    $entry->id($id);
    $entry->slug($id);
    $entry->data(['title' => ucfirst($id), ...$data]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * Request an entry's edit page as a super user.
 *
 * @return TestResponse<Response>
 */
function getVesselEditPage(string $id): TestResponse
{
    return get(mustBeString(cp_route('collections.entries.edit', ['vessels', $id]), 'the edit page URL'));
}

beforeEach(function (): void {
    withoutMiddleware(PreventRequestForgery::class);
    withoutVite();

    $vessels = CollectionFacade::make('vessels');
    $vessels->title('Vessels');
    $vessels->sites(['default']);
    $vessels->routes('/vessels/{slug}');
    $vessels->save();

    $blueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'escorts' => ['type' => 'entries', 'collections' => ['vessels'], 'visibility' => 'computed'],
        'flagship' => ['type' => 'entries', 'collections' => ['vessels'], 'max_items' => 1, 'visibility' => 'computed'],
    ]);
    $blueprint->setHandle('vessel');
    $blueprint->setNamespace('collections.vessels');
    $blueprint->save();

    CollectionFacade::computed('vessels', 'escorts', fn (EntryItem $entry): EntryCollection => EntryCollection::make(
        $entry->id() === 'enterprise' ? [Entry::find('reliant')] : []
    ));
    CollectionFacade::computed('vessels', 'flagship', fn (): EntryCollection => EntryCollection::make());

    saveVessel('enterprise');
    saveVessel('reliant');

    $user = mustBe(Statamic\Auth\File\User::class, User::make(), 'the test user');
    $user->id('kirk');
    $user->email('kirk@enterprise.test');
    $user->makeSuper();
    $user->save();

    actingAs($user);
});

it('opens an edit page whose computed entries fields hold entries or nothing', function (string $id): void {
    getVesselEditPage($id)->assertOk();
})->with(['holding an entry' => 'enterprise', 'holding nothing' => 'reliant']);

it('offers a computed collection to the Control Panel as entry ids', function (): void {
    /** @var EntryItem $enterprise */
    $enterprise = Entry::find('enterprise');

    /** @var BlueprintItem $blueprint */
    $blueprint = $enterprise->blueprint();
    $field = mustBe(Field::class, $blueprint->field('escorts'), 'the escorts field');
    $field->setValue($enterprise->value('escorts'));

    /** @var Field $processed */
    $processed = $field->preProcess();
    $fieldtype = mustBe(Entries::class, $processed->fieldtype(), 'the escorts fieldtype');

    /** @var array<int, string> $ids */
    $ids = $processed->value();

    expect($ids)->toBe(['reliant'])
        ->and(data_get($fieldtype->getItemData($ids), '*.title'))->toBe(['Reliant']);
});

it('keeps stored ids and an empty value as they were', function (mixed $stored, array $expected): void {
    expect((new Entries)->preProcess($stored))->toBe($expected);
})->with([
    'a list' => [['reliant', 'enterprise'], ['reliant', 'enterprise']],
    'one id' => ['reliant', ['reliant']],
    'nothing' => [null, []],
]);

it('writes no computed field to the content file when the entry saves', function (): void {
    $url = mustBeString(cp_route('collections.entries.update', ['vessels', 'enterprise']), 'the update URL');

    patchJson($url, [
        'title' => 'Enterprise-A',
        'slug' => 'enterprise',
        'escorts' => ['reliant'],
        'flagship' => [],
    ])->assertOk();

    /** @var EntryItem $enterprise */
    $enterprise = Entry::find('enterprise');
    $contents = File::get(mustBeString($enterprise->path(), 'the entry path'));

    expect($contents)->toContain('Enterprise-A')
        ->not->toContain('escorts')
        ->not->toContain('flagship');
});
