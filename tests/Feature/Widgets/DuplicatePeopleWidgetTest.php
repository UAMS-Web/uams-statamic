<?php

declare(strict_types=1);

use App\Services\People\PeopleDuplicateDetector;
use App\Widgets\DuplicatePeople;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use PHPUnit\Framework\Assert;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Tests\Support\StacheIsolatedTestCase;

use function Pest\Laravel\actingAs;

/**
 * CP surface coverage for the {@see DuplicatePeople} dashboard widget (UAMS-Web/uams-statamic-archive#839):
 * permission gating, cluster rendering, and the launch link into the UAMS-Web/uams-statamic-archive#838 merge
 * tool. The widget only suggests — it never merges.
 *
 * @command  php artisan test --compact tests/Feature/Widgets/DuplicatePeopleWidgetTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * Build a person_name structure from a first and last name.
 *
 * @return array<string, mixed>
 */
function dupWidgetName(string $first, string $last): array
{
    return ['first_name' => ['text' => $first], 'last_name' => ['text' => $last]];
}

/**
 * Persist a person entry, bypassing save listeners.
 *
 * @param  array<string, mixed>  $data
 */
function dupWidgetPerson(string $id, string $slug, array $data): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection(PeopleDuplicateDetector::peopleCollection());
    $entry->id($id);
    $entry->slug($slug);
    $entry->published(true);
    $entry->data($data);
    $entry->saveQuietly();
}

/**
 * Render the widget and return its View, asserting it produced one.
 */
function dupWidgetView(DuplicatePeople $widget): View
{
    $result = $widget->html();
    Assert::assertInstanceOf(View::class, $result);

    /** @var View $result */
    return $result;
}

beforeEach(function (): void {
    // A fresh cache so one test's detection scan never bleeds into the next.
    Cache::flush();

    /** @var Collection<string, Statamic\Sites\Site> $allSites */
    $allSites = Site::all();
    $siteHandles = $allSites->map(fn ($site) => $site->handle())->values()->all();

    $collection = CollectionFacade::make(PeopleDuplicateDetector::peopleCollection());
    $collection->title('People');
    $collection->sites($siteHandles);
    $collection->routes('/people/{slug}');
    $collection->save();
});

it('is hidden when no user is authenticated', function (): void {
    dupWidgetPerson('a', 'jane', ['person_name' => dupWidgetName('Jane', 'Smith'), 'npi' => '1234567890']);
    dupWidgetPerson('b', 'jane-2', ['person_name' => dupWidgetName('Jane', 'Smith'), 'npi' => '1234567890']);

    expect((new DuplicatePeople)->html())->toBe('');
});

it('is hidden for a user without the merge permission', function (): void {
    dupWidgetPerson('a', 'jane', ['person_name' => dupWidgetName('Jane', 'Smith'), 'npi' => '1234567890']);
    dupWidgetPerson('b', 'jane-2', ['person_name' => dupWidgetName('Jane', 'Smith'), 'npi' => '1234567890']);

    // A user with no roles holds no permissions.
    /** @var Statamic\Auth\User $restricted */
    $restricted = User::make();
    $restricted->email('restricted-dup@example.test');
    $restricted->save();
    actingAs($restricted);

    expect((new DuplicatePeople)->html())->toBe('');
});

it('lists detected clusters with confidence and a merge link for a permitted user', function (): void {
    dupWidgetPerson('a', 'jane-smith', ['person_name' => dupWidgetName('Jane', 'Smith'), 'npi' => '1234567890']);
    dupWidgetPerson('b', 'jane-smith-2', ['person_name' => dupWidgetName('Jane', 'Smith'), 'npi' => '1234567890']);

    /** @var Statamic\Auth\User $super */
    $super = User::make();
    $super->email('super-dup@example.test');
    $super->makeSuper();
    $super->save();
    actingAs($super);

    $view = dupWidgetView(new DuplicatePeople);

    /** @var list<array{confidence: string, members: list<array{title: string, edit_url: string}>, merge_url: string, count: int}> $clusters */
    $clusters = $view->getData()['clusters'];

    expect($clusters)->toHaveCount(1)
        ->and($clusters[0]['confidence'])->toBe('high')
        ->and($clusters[0]['count'])->toBe(2)
        ->and($clusters[0]['members'])->toHaveCount(2)
        // The launch URL targets the UAMS-Web/uams-statamic-archive#838 reconciliation screen with both ids.
        ->and($clusters[0]['merge_url'])->toContain('ids=')
        ->and($clusters[0]['merge_url'])->toContain('a')
        ->and($clusters[0]['merge_url'])->toContain('b');

    // The rendered panel exposes the launch affordance.
    $html = (string) $view->render();
    expect($html)->toContain('Review &amp; merge')
        ->and($html)->toContain($clusters[0]['merge_url']);
});

it('shows an empty state when no duplicates are detected', function (): void {
    dupWidgetPerson('a', 'jane-smith', ['person_name' => dupWidgetName('Jane', 'Smith'), 'npi' => '1111111111']);
    dupWidgetPerson('b', 'bob-jones', ['person_name' => dupWidgetName('Bob', 'Jones'), 'npi' => '2222222222']);

    /** @var Statamic\Auth\User $super */
    $super = User::make();
    $super->email('super-empty@example.test');
    $super->makeSuper();
    $super->save();
    actingAs($super);

    $view = dupWidgetView(new DuplicatePeople);

    expect($view->getData()['clusters'])->toBe([]);
    expect((string) $view->render())->toContain('No likely-duplicate people detected.');
});

it('does not mutate entries when rendering', function (): void {
    dupWidgetPerson('a', 'jane-smith', ['person_name' => dupWidgetName('Jane', 'Smith'), 'npi' => '1234567890', 'bio' => 'Original']);
    dupWidgetPerson('b', 'jane-smith-2', ['person_name' => dupWidgetName('Jane', 'Smith'), 'npi' => '1234567890', 'bio' => 'Second']);

    /** @var Statamic\Auth\User $super */
    $super = User::make();
    $super->email('super-nomutate@example.test');
    $super->makeSuper();
    $super->save();
    actingAs($super);

    dupWidgetView(new DuplicatePeople);

    /** @var Statamic\Entries\Entry $entryA */
    $entryA = Entry::find('a');
    /** @var string $bioA */
    $bioA = $entryA->get('bio');

    /** @var Statamic\Entries\Entry $entryB */
    $entryB = Entry::find('b');
    /** @var string $bioB */
    $bioB = $entryB->get('bio');

    expect($bioA)->toBe('Original')
        ->and($bioB)->toBe('Second');
});
