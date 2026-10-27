<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;
use Statamic\Auth\File\User as FileStatamicUser;
use Statamic\Contracts\Entries\Collection as CollectionContract;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Entries\Collection as ConcreteCollection;
use Statamic\Entries\Entry as ConcreteEntry;
use Statamic\Facades\Collection;
use Statamic\Facades\User;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withoutMiddleware;

/**
 * HTTP coverage for the custom `LocalizeEntryController`.
 *
 * Guards the fix for issue UAMS-Web/uams-statamic-archive#870: the target `site` handle must be validated
 * against the collection's configured sites before it reaches
 * `makeLocalization()`, so an arbitrary or unconfigured handle is rejected with
 * a validation error rather than being passed into localization creation.
 *
 * Mutation: content-dependent on App\Http\Controllers\CP\Collections\LocalizeEntryController —
 * the sole test self-skips without the content/ submodule, so --covered-only reports
 * 0 mutants locally; coverage runs in CI.
 */
uses(TestCase::class);

/**
 * Statamic user persisted during the test; deleted via the user facade in
 * afterEach so we don't depend on guessing the on-disk path (file-backed users
 * land at `users/{email}.yaml` at the project root, a private git submodule — a
 * manual unlink would silently miss). Narrowed to the concrete file-backed
 * implementation that makeSuperUser() actually persists so afterEach can call
 * `delete()` (declared on `Statamic\Auth\User`, the file user's parent).
 *
 * @var FileStatamicUser|null
 */
$localizeEntryControllerTestCreatedUser = null;

/**
 * Resolve a collection that lives in the `content/` submodule, skipping the test
 * when it is absent (the Stache returns `null` without the submodule).
 *
 * @param  string  $handle  The collection handle (e.g. `people`, `posts`)
 * @return CollectionContract The resolved collection
 */
function localizeEntryControllerTestRequireContentCollection(string $handle): CollectionContract
{
    $collection = Collection::find($handle);

    if (! $collection) {
        ContentSubmodule::reportMissing("{$handle} collection");
    }

    return $collection;
}

/**
 * Assert a real committed entry resolved, skipping the test when it did not —
 * the entry is indexed only with the `content/` submodule present.
 *
 * @param  EntryContract|null  $entry  The resolved entry, or `null`
 * @param  string  $description  What the missing entry provides
 * @return EntryContract The non-null entry
 */
function localizeEntryControllerTestRequireContentEntry(?EntryContract $entry, string $description): EntryContract
{
    if (! $entry) {
        ContentSubmodule::reportMissing($description);
    }

    return $entry;
}

/**
 * Create, persist, and return a super Statamic user, recording it via the
 * by-reference handle so afterEach can clean it up.
 *
 * @param  FileStatamicUser|null  $createdUser  By-reference store for the persisted user
 *
 * @param-out  FileStatamicUser  $createdUser
 *
 * @return FileStatamicUser The persisted super user
 */
function localizeEntryControllerTestMakeSuperUser(?FileStatamicUser &$createdUser): FileStatamicUser
{
    $user = User::make();

    // Narrow the repository user to the file-backed implementation
    Assert::assertInstanceOf(FileStatamicUser::class, $user);

    // Configure the user via discrete setter calls; the fluent setters are
    // typed `mixed`, so chaining them off one another would lose the type. Each
    // call targets the narrowed `$user` instead.
    $user->id((string) Str::uuid());
    $user->email('localize-entry-controller-test@example.test');
    $user->password('password');
    $user->makeSuper();

    $user->save();

    $createdUser = $user;

    return $user;
}

/**
 * Prepare the test case and disable CSRF to isolate the validation assertion.
 */
beforeEach(function (): void {
    withoutMiddleware(PreventRequestForgery::class);
});

/**
 * Remove the temporary Statamic user via the facade if one was created.
 */
afterEach(function () use (&$localizeEntryControllerTestCreatedUser): void {
    if ($localizeEntryControllerTestCreatedUser !== null) {
        $localizeEntryControllerTestCreatedUser->delete();
        $localizeEntryControllerTestCreatedUser = null;
    }
});

it('rejects an unallowed site handle', function () use (&$localizeEntryControllerTestCreatedUser): void {
    // Resolve a real collection + entry, skipping when content/ is absent
    $collection = localizeEntryControllerTestRequireContentCollection('pages');

    // Narrow the contract to the concrete collection bound at runtime so
    // queryEntries() (declared only on Statamic\Entries\Collection) resolves
    /** @var ConcreteCollection $collection */
    // queryEntries() is untyped (returns mixed); narrow it to the concrete
    // entry query builder before calling first(), which also returns mixed
    /** @var EntryQueryBuilder $query */
    $query = $collection->queryEntries();
    /** @var EntryContract|null $firstEntry */
    $firstEntry = $query->first();
    $entry = localizeEntryControllerTestRequireContentEntry(
        $firstEntry,
        'pages entry'
    );

    // id() is declared only on the concrete entry, not the contract
    /** @var ConcreteEntry $entry */

    // Authenticate a super user so the edit authorization passes and the
    // request reaches the site validation
    actingAs(localizeEntryControllerTestMakeSuperUser($localizeEntryControllerTestCreatedUser));

    // cp_route() returns mixed (null when the CP is disabled); narrow it to a
    // string for the postJson URI
    /** @var string $localizeRoute */
    $localizeRoute = cp_route(
        'collections.entries.localize',
        [$collection->handle(), $entry->id()]
    );

    // Post a handle that is guaranteed not to be a configured site
    $response = postJson(
        $localizeRoute,
        ['site' => 'not-a-real-site']
    );

    // The unconfigured handle is rejected by validation
    $response->assertStatus(422);
    $response->assertJsonValidationErrors('site');
});
