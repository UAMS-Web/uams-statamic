<?php

declare(strict_types=1);

use App\Http\Controllers\CP\Redirects\MarketingRedirectController;
use App\Services\Redirects\MarketingRedirectStore;
use App\Support\Redirects\MarketingRedirect;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Assert;
use Statamic\Auth\File\User as FileStatamicUser;
use Statamic\Contracts\Auth\RoleRepository as RoleRepositoryContract;
use Statamic\Facades\Antlers;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Role;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Globals\Variables;
use Tests\Support\StacheIsolatedTestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withoutMiddleware;
use function Pest\Laravel\withoutVite;

/**
 * HTTP coverage for {@see MarketingRedirectController}
 * (UAMS-Web/uams-statamic-archive#1355 + UAMS-Web/uams-statamic-archive#1356): the create screen renders, a staff-created redirect serves on
 * a 404, collisions within an overlapping validity window are rejected, and every
 * endpoint is gated by the `create marketing redirects` permission.
 */
// Gated to the single-process serial tail. NOT for the cross-worker reason
// UAMS-Web/uams-statamic-archive#1596 recorded — that diagnosis ("the two views momentarily disagree across
// workers"; "in isolation that query is a harmless no-op and the file passes
// 22/22") was refuted in UAMS-Web/uams-statamic-archive#1851: on a developer checkout with `content/`
// initialized, this file failed 22/22 in a SINGLE process with no parallelism at
// all. The disagreement was neither momentary nor cross-worker — it was the
// steady state of `StacheIsolatedTestCase`, whose Blink memo kept serving the
// real collection handles while every per-handle lookup returned null. That is
// fixed in the base class, not here.
//
// The gate is kept deliberately, on its own merits: these cases save Statamic
// file users and roles, which land outside the throwaway Stache root, so running
// them alongside the parallel workers is a shared-state hazard independent of the
// resolved defect.
uses(StacheIsolatedTestCase::class)->group('serial');

/**
 * Resolve a Control Panel route to its URL string.
 *
 * `cp_route()` is declared without a return type, so static analysis widens it
 * to `mixed`; this typed wrapper restores the `string` a CP route always yields.
 *
 * @param  array<string, mixed>  $params
 */
function marketingRedirectCpRoute(string $route, array $params = []): string
{
    /** @var string $url */
    $url = cp_route($route, $params);

    return $url;
}

/**
 * The default site's handle. `Site::default()` is typed `mixed` by the facade,
 * so narrow it to the concrete site before reading its `string` handle.
 */
function marketingRedirectDefaultSiteHandle(): string
{
    /** @var Statamic\Sites\Site $site */
    $site = Site::default();

    /** @var string $handle */
    $handle = $site->handle();

    return $handle;
}

/**
 * Persist a super user (holds every permission) for an authorized request.
 */
function marketingRedirectSuperUser(): FileStatamicUser
{
    $user = User::make();
    Assert::assertInstanceOf(FileStatamicUser::class, $user);

    $user->id((string) Str::uuid());
    $user->email('marketing-redirect-super@example.test');
    $user->makeSuper();
    $user->save();

    return $user;
}

/**
 * Persist a plain user that lacks the marketing-redirect permission.
 */
function marketingRedirectPlainUser(): FileStatamicUser
{
    $user = User::make();
    Assert::assertInstanceOf(FileStatamicUser::class, $user);

    $user->id((string) Str::uuid());
    $user->email('marketing-redirect-plain@example.test');
    $user->save();

    return $user;
}

/**
 * Persist a user granted only `create marketing redirects` (not `approve …`),
 * to prove the two permissions are independent (UAMS-Web/uams-statamic-archive#1357).
 */
function marketingRedirectCreatorOnlyUser(): FileStatamicUser
{
    $role = Role::make('marketing-redirect-creator');
    $role->permissions(['access cp', 'create marketing redirects']);
    $role->save();

    $user = User::make();
    Assert::assertInstanceOf(FileStatamicUser::class, $user);

    $user->id((string) Str::uuid());
    $user->email('marketing-redirect-creator-'.Str::uuid().'@example.test');
    $user->assignRole('marketing-redirect-creator');
    $user->save();

    return $user;
}

/**
 * Store one pending redirect as the given user and return its id.
 *
 * @param  array<string, mixed>  $overrides
 */
function marketingRedirectStorePending(FileStatamicUser $creator, array $overrides = []): string
{
    actingAs($creator);

    $response = postJson(marketingRedirectCpRoute('marketing-redirects.store'), array_merge([
        'source' => '/vanity-'.Str::uuid(),
        'destination' => '/target',
        'response' => '301',
    ], $overrides));

    $response->assertStatus(201);

    /** @var string $id */
    $id = $response->json('redirect.id');

    return $id;
}

/**
 * Seed one existing redirect on the shared default-site list via the store.
 *
 * @param  array<string, mixed>  $overrides
 */
function marketingRedirectSeed(array $overrides = []): void
{
    /** @var array{source: string, destination: string, response?: string, valid_from?: string|null, valid_until?: string|null, requester?: string|null, notes?: string|null} $input */
    $input = array_merge([
        'source' => '/flu',
        'destination' => '/existing',
    ], $overrides);

    app(MarketingRedirectStore::class)->create(MarketingRedirect::fromInput($input));
}

beforeEach(function (): void {
    withoutMiddleware(PreventRequestForgery::class);

    // The create screen renders the CP layout, which @vites the (unbuilt in the
    // PHPUnit job) CP bundle; stub CP assets so the controller response is what's
    // under test. The real screen is exercised by the browser suite.
    withoutVite();

    // Repoint the file role repository at a throwaway path so persisting a test
    // role never mutates the tracked resources/users/roles.yaml.
    $rolesDir = storage_path('framework/testing/marketing-redirect-roles/'.uniqid('', true));
    File::ensureDirectoryExists($rolesDir);
    app(RoleRepositoryContract::class)->path($rolesDir.'/roles.yaml');

    // Provide the shared redirects global the utility writes into.
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::make('redirects');
    $set->title('Redirects');
    $set->save();
    /** @var Variables $vars */
    $vars = $set->makeLocalization(marketingRedirectDefaultSiteHandle());
    $vars->data(['redirects' => []]);
    $vars->save();
});

it('renders the create screen for an authorized user', function (): void {
    actingAs(marketingRedirectSuperUser());

    get(marketingRedirectCpRoute('marketing-redirects.create'))->assertOk();
});

/**
 * Render the redirect for `$uri` through the same {{ find_redirect_rule }} tag
 * the 404 template uses, on the given site.
 */
function marketingRedirectServed(string $uri, string $siteHandle): string
{
    return (string) Antlers::parse(
        '{{ find_redirect_rule in="'.$siteHandle.'" }}{{ target_url }}|{{ response }}{{ /find_redirect_rule }}',
        ['current_uri' => $uri],
    );
}

it('creates a pending redirect that does NOT serve until an approver approves it', function (): void {
    // A distinct creator and approver — the creator cannot approve their own (UAMS-Web/uams-statamic-archive#1357).
    actingAs(marketingRedirectSuperUser());

    postJson(marketingRedirectCpRoute('marketing-redirects.store'), [
        'source' => 'Flu-Shot/',
        'destination' => '/clinics/flu',
        'response' => '301',
    ])
        ->assertStatus(201)
        ->assertJsonPath('saved', true)
        ->assertJsonPath('redirect.source', '/Flu-Shot')
        ->assertJsonPath('redirect.target_url', '/clinics/flu')
        ->assertJsonPath('redirect.response', '301')
        // The created redirect enters the queue as pending (UAMS-Web/uams-statamic-archive#1357).
        ->assertJsonPath('redirect.status', 'pending');

    // While pending it does NOT serve — the core of the approval gate.
    expect(marketingRedirectServed('/Flu-Shot', marketingRedirectDefaultSiteHandle()))->not->toContain('/clinics/flu');

    // A different approver promotes it, and then it serves through the tag on the
    // default site and (via the UAMS-Web/uams-statamic-archive#1110 read-time merge) on a non-default site too.
    $store = app(MarketingRedirectStore::class);
    $id = $store->all()[0]['id'];

    actingAs(marketingRedirectSuperUser());

    postJson(marketingRedirectCpRoute('marketing-redirects.approve', ['redirect' => $id]))
        ->assertOk()
        ->assertJsonPath('saved', true)
        ->assertJsonStructure(['saved', 'message', 'redirect'])
        ->assertJsonPath('redirect.status', 'approved');

    $default = marketingRedirectDefaultSiteHandle();
    /** @var Collection<int|string, Statamic\Sites\Site> $sites */
    $sites = Site::all();
    /** @var array<int|string, string> $handles */
    $handles = $sites->map(fn (Statamic\Sites\Site $site) => $site->handle())->all();
    $other = collect($handles)
        ->first(fn (string $handle): bool => $handle !== $default) ?? $default;

    foreach ([$default, $other] as $siteHandle) {
        expect(marketingRedirectServed('/Flu-Shot', $siteHandle))->toBe('/clinics/flu|301');
    }
});

it('retains a rejected redirect with its reason and never serves it', function (): void {
    actingAs(marketingRedirectSuperUser());

    postJson(marketingRedirectCpRoute('marketing-redirects.store'), [
        'source' => '/gone',
        'destination' => '/nowhere',
        'response' => '301',
    ])->assertStatus(201);

    $store = app(MarketingRedirectStore::class);
    /** @var string $id */
    $id = $store->all()[0]['id'];

    // A different approver rejects the request.
    actingAs(marketingRedirectSuperUser());

    postJson(marketingRedirectCpRoute('marketing-redirects.reject', ['redirect' => $id]), [
        'reason' => 'Destination is off-domain.',
    ])
        ->assertOk()
        ->assertJsonPath('saved', true)
        ->assertJsonStructure(['saved', 'message', 'redirect'])
        ->assertJsonPath('redirect.status', 'rejected')
        ->assertJsonPath('redirect.rejection_reason', 'Destination is off-domain.');

    // A rejected redirect is retained but never serves.
    /** @var array{rejection_reason: string} $rejected */
    $rejected = $store->find($id);
    expect($rejected['rejection_reason'])->toBe('Destination is off-domain.')
        ->and(marketingRedirectServed('/gone', marketingRedirectDefaultSiteHandle()))->not->toContain('/nowhere');
});

it('stores the redirect on the shared default site so it serves on every site', function (): void {
    actingAs(marketingRedirectSuperUser());

    postJson(marketingRedirectCpRoute('marketing-redirects.store'), [
        'source' => '/vanity',
        'destination' => '/target',
        'response' => '302',
    ])->assertStatus(201);

    // The shared default-site list (served everywhere via UAMS-Web/uams-statamic-archive#1110) holds the row.
    $stored = app(MarketingRedirectStore::class)->all();

    expect($stored)->toHaveCount(1)
        ->and($stored[0]['source'])->toBe('/vanity')
        ->and($stored[0]['target_url'])->toBe('/target')
        ->and($stored[0]['response'])->toBe('302')
        ->and($stored[0]['enabled'])->toBeTrue();
});

it('captures the optional validity window and requester metadata', function (): void {
    actingAs(marketingRedirectSuperUser());

    postJson(marketingRedirectCpRoute('marketing-redirects.store'), [
        'source' => '/campaign',
        'destination' => '/promo',
        'response' => '301',
        'valid_from' => '2026-06-01',
        'valid_until' => '2026-06-30',
        'requester' => 'Marketing',
        'notes' => 'summer push',
    ])->assertStatus(201);

    $stored = app(MarketingRedirectStore::class)->all();

    expect($stored[0]['valid_from'])->toBe('2026-06-01')
        ->and($stored[0]['valid_until'])->toBe('2026-06-30')
        ->and($stored[0]['requester'])->toBe('Marketing')
        ->and($stored[0]['notes'])->toBe('summer push');
});

it('rejects a source collision within an overlapping validity window', function (): void {
    marketingRedirectSeed(['source' => '/flu', 'valid_from' => '2026-01-01', 'valid_until' => '2026-01-31']);

    actingAs(marketingRedirectSuperUser());

    postJson(marketingRedirectCpRoute('marketing-redirects.store'), [
        'source' => '/Flu/',
        'destination' => '/new-place',
        'response' => '301',
        'valid_from' => '2026-01-15',
        'valid_until' => '2026-02-15',
    ])
        ->assertStatus(422)
        // The message must NAME the conflicting redirect (AC UAMS-Web/uams-statamic-archive#1356), not just flag an error.
        ->assertJsonValidationErrors(['source' => 'A redirect from /flu to /existing']);

    // The colliding redirect was not written.
    expect(app(MarketingRedirectStore::class)->all())->toHaveCount(1);
});

it('lets a source be re-requested after its earlier request was rejected', function (): void {
    // Submit /promo, then a different approver rejects it (row retained, never serves).
    $id = marketingRedirectStorePending(marketingRedirectSuperUser(), ['source' => '/promo', 'destination' => '/campaign-a']);

    actingAs(marketingRedirectSuperUser());
    postJson(marketingRedirectCpRoute('marketing-redirects.reject', ['redirect' => $id]), ['reason' => 'Wrong destination.'])->assertOk();

    // The corrected request for the same source must succeed — a rejected row
    // never serves, so it must not reserve the source (UAMS-Web/uams-statamic-archive#1357). Regression for the
    // collision-detector-is-status-blind defect.
    actingAs(marketingRedirectSuperUser());
    postJson(marketingRedirectCpRoute('marketing-redirects.store'), [
        'source' => '/promo',
        'destination' => '/campaign-b',
        'response' => '301',
    ])->assertStatus(201);
});

it('still blocks re-requesting a source that already has an approved redirect', function (): void {
    // Submit /vip, then a different approver approves it (it now serves).
    $id = marketingRedirectStorePending(marketingRedirectSuperUser(), ['source' => '/vip', 'destination' => '/a']);

    actingAs(marketingRedirectSuperUser());
    postJson(marketingRedirectCpRoute('marketing-redirects.approve', ['redirect' => $id]))->assertOk();

    // A second request for the same source collides — an approved redirect serves.
    actingAs(marketingRedirectSuperUser());
    postJson(marketingRedirectCpRoute('marketing-redirects.store'), [
        'source' => '/vip',
        'destination' => '/b',
        'response' => '301',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('source');
});

it('allows the same source with a non-overlapping validity window', function (): void {
    marketingRedirectSeed(['source' => '/flu', 'valid_from' => '2026-01-01', 'valid_until' => '2026-01-31']);

    actingAs(marketingRedirectSuperUser());

    postJson(marketingRedirectCpRoute('marketing-redirects.store'), [
        'source' => '/flu',
        'destination' => '/february-place',
        'response' => '301',
        'valid_from' => '2026-02-01',
        'valid_until' => '2026-02-28',
    ])->assertStatus(201);

    expect(app(MarketingRedirectStore::class)->all())->toHaveCount(2);
});

it('rejects an inverted validity window', function (): void {
    actingAs(marketingRedirectSuperUser());

    postJson(marketingRedirectCpRoute('marketing-redirects.store'), [
        'source' => '/vanity',
        'destination' => '/target',
        'response' => '301',
        'valid_from' => '2026-06-30',
        'valid_until' => '2026-06-01',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('valid_until');
});

it('validates the required and constrained fields', function (): void {
    actingAs(marketingRedirectSuperUser());

    postJson(marketingRedirectCpRoute('marketing-redirects.store'), [
        'source' => '',
        'destination' => '',
        'response' => '418',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['source', 'destination', 'response']);
});

it('denies the create screen to users without the permission', function (): void {
    actingAs(marketingRedirectPlainUser());

    // A web (non-JSON) CP request for an unauthorized user is redirected away
    // rather than rendered; the authoritative 403 gate is asserted on the JSON
    // store endpoint below.
    get(marketingRedirectCpRoute('marketing-redirects.create'))->assertStatus(302);
});

it('forbids storing a redirect for users without the permission', function (): void {
    actingAs(marketingRedirectPlainUser());

    postJson(marketingRedirectCpRoute('marketing-redirects.store'), [
        'source' => '/vanity',
        'destination' => '/target',
        'response' => '301',
    ])->assertForbidden();

    // Nothing was written.
    expect(app(MarketingRedirectStore::class)->all())->toBeEmpty();
});

it('renders the review queue for an approver', function (): void {
    actingAs(marketingRedirectSuperUser());

    get(marketingRedirectCpRoute('marketing-redirects.review'))->assertOk();
});

it('forbids a creator from approving their own request (no self-approve)', function (): void {
    // One user both creates and tries to approve — the guard must forbid it.
    $creator = marketingRedirectSuperUser();
    $id = marketingRedirectStorePending($creator);

    actingAs($creator);

    postJson(marketingRedirectCpRoute('marketing-redirects.approve', ['redirect' => $id]))
        ->assertStatus(403)
        ->assertJsonStructure(['message']);

    // The request stays pending — the self-approve did not take effect.
    /** @var array{status: string} $found */
    $found = app(MarketingRedirectStore::class)->find($id);
    expect($found['status'])->toBe('pending');
});

it('forbids a creator from rejecting their own request', function (): void {
    $creator = marketingRedirectSuperUser();
    $id = marketingRedirectStorePending($creator);

    actingAs($creator);

    postJson(marketingRedirectCpRoute('marketing-redirects.reject', ['redirect' => $id]), ['reason' => 'nope'])
        ->assertStatus(403);

    /** @var array{status: string} $found */
    $found = app(MarketingRedirectStore::class)->find($id);
    expect($found['status'])->toBe('pending');
});

it('rejects a second review of an already-reviewed request', function (): void {
    $id = marketingRedirectStorePending(marketingRedirectSuperUser());

    // A different approver approves it.
    actingAs(marketingRedirectSuperUser());
    postJson(marketingRedirectCpRoute('marketing-redirects.approve', ['redirect' => $id]))->assertOk();

    // A further approve or reject is a no-op 422 — it was already reviewed.
    postJson(marketingRedirectCpRoute('marketing-redirects.approve', ['redirect' => $id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status' => 'already been reviewed']);

    postJson(marketingRedirectCpRoute('marketing-redirects.reject', ['redirect' => $id]), ['reason' => 'late'])
        ->assertStatus(422);
});

it('returns 404 when approving or rejecting an unknown id', function (): void {
    actingAs(marketingRedirectSuperUser());

    postJson(marketingRedirectCpRoute('marketing-redirects.approve', ['redirect' => 'does-not-exist']))
        ->assertStatus(404)
        ->assertJsonStructure(['message']);

    postJson(marketingRedirectCpRoute('marketing-redirects.reject', ['redirect' => 'does-not-exist']), ['reason' => 'x'])
        ->assertStatus(404)
        ->assertJsonStructure(['message']);
});

it('requires a reason to reject', function (): void {
    $id = marketingRedirectStorePending(marketingRedirectSuperUser());

    actingAs(marketingRedirectSuperUser());

    postJson(marketingRedirectCpRoute('marketing-redirects.reject', ['redirect' => $id]), ['reason' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');

    // Still pending — the invalid rejection did not take effect.
    /** @var array{status: string} $found */
    $found = app(MarketingRedirectStore::class)->find($id);
    expect($found['status'])->toBe('pending');
});

it('separates the create and approve permissions', function (): void {
    $creatorOnly = marketingRedirectCreatorOnlyUser();

    // A creator-only user CAN create a request…
    $ownId = marketingRedirectStorePending($creatorOnly);

    // …but CANNOT reach the review queue…
    actingAs($creatorOnly);
    get(marketingRedirectCpRoute('marketing-redirects.review'))->assertStatus(302);

    // …nor approve/reject a request submitted by someone ELSE. Using another
    // user's request is deliberate: it isolates the permission gate from the
    // self-review guard, so a dropped `authorize()` cannot pass unnoticed.
    $otherId = marketingRedirectStorePending(marketingRedirectSuperUser());
    actingAs($creatorOnly);
    postJson(marketingRedirectCpRoute('marketing-redirects.approve', ['redirect' => $otherId]))->assertForbidden();
    postJson(marketingRedirectCpRoute('marketing-redirects.reject', ['redirect' => $otherId]), ['reason' => 'x'])->assertForbidden();

    /** @var array{status: string} $otherFound */
    $otherFound = app(MarketingRedirectStore::class)->find($otherId);
    /** @var array{status: string} $ownFound */
    $ownFound = app(MarketingRedirectStore::class)->find($ownId);
    expect($otherFound['status'])->toBe('pending')
        ->and($ownFound['status'])->toBe('pending');
});

it('forbids the review endpoints for a user without any redirect permission', function (): void {
    // A real pending request from an authorized user, submitted by someone the
    // plain user is NOT — so a dropped permission gate would let it through.
    $id = marketingRedirectStorePending(marketingRedirectSuperUser());

    actingAs(marketingRedirectPlainUser());

    get(marketingRedirectCpRoute('marketing-redirects.review'))->assertStatus(302);
    postJson(marketingRedirectCpRoute('marketing-redirects.approve', ['redirect' => $id]))->assertForbidden();
    postJson(marketingRedirectCpRoute('marketing-redirects.reject', ['redirect' => $id]), ['reason' => 'x'])->assertForbidden();

    // The permission gate blocked it before any state change.
    /** @var array{status: string} $found */
    $found = app(MarketingRedirectStore::class)->find($id);
    expect($found['status'])->toBe('pending');
});

it('renders the pending queue payload with per-row action URLs and self-review flags', function (): void {
    // Two pending requests: one the viewer submitted (with a full field set),
    // one from someone else.
    $viewer = marketingRedirectSuperUser();
    $ownId = marketingRedirectStorePending($viewer, [
        'source' => '/own',
        'destination' => '/own-dest',
        'response' => '302',
        'requester' => 'Me',
        'notes' => 'Launch week',
        'valid_from' => '2026-08-01',
        'valid_until' => '2026-08-31',
    ]);
    $otherId = marketingRedirectStorePending(marketingRedirectSuperUser(), ['source' => '/other', 'destination' => '/other-dest']);

    // An approved request must NOT appear in the queue.
    $approvedId = marketingRedirectStorePending(marketingRedirectSuperUser());
    actingAs(marketingRedirectSuperUser());
    postJson(marketingRedirectCpRoute('marketing-redirects.approve', ['redirect' => $approvedId]))->assertOk();

    actingAs($viewer);
    get(marketingRedirectCpRoute('marketing-redirects.review'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
            // The page is a JS-registered Vue component, not an on-disk Inertia
            // page file, so skip the view-finder existence check (second arg).
                ->component('app::MarketingRedirectReview', false)
                ->has('redirects', 2)
            // Row 0 is the viewer's own request: not reviewable, with its action URLs.
                ->where('redirects.0.source', '/own')
                ->where('redirects.0.destination', '/own-dest')
                ->where('redirects.0.response', '302')
                ->where('redirects.0.requester', 'Me')
                ->where('redirects.0.notes', 'Launch week')
                ->where('redirects.0.valid_from', '2026-08-01')
                ->where('redirects.0.valid_until', '2026-08-31')
                ->where('redirects.0.can_review', false)
                ->where('redirects.0.approve_url', marketingRedirectCpRoute('marketing-redirects.approve', ['redirect' => $ownId]))
                ->where('redirects.0.reject_url', marketingRedirectCpRoute('marketing-redirects.reject', ['redirect' => $ownId]))
            // Row 1 was submitted by someone else, so the viewer may review it.
                ->where('redirects.1.source', '/other')
                ->where('redirects.1.can_review', true)
        );
});
