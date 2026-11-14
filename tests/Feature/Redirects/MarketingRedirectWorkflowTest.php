<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Assert;
use Statamic\Auth\File\User as FileStatamicUser;
use Statamic\Facades\Antlers;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Globals\Variables;
use Tests\Support\StacheIsolatedTestCase;
use UAMSWeb\Aether\Redirects\Services\MarketingRedirectStore;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withoutMiddleware;
use function Pest\Laravel\withoutVite;

/**
 * Site coverage for the marketing-redirect workflow now that it ships in
 * `uams-web/aether-redirects` (#398): with the add-on installed, the Control
 * Panel create and review pages render, a request is saved as pending, a second
 * user approves it, the approved rule serves through the 404 template's tag,
 * and a colliding source is refused. The workflow's full suite lives in the
 * add-on; this file proves the host wires it up.
 *
 * @command  php artisan test --compact tests/Feature/Redirects/MarketingRedirectWorkflowTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * Resolve a Control Panel route to its URL string.
 *
 * @param  string  $route  Name of the Control Panel route.
 * @param  array<string, mixed>  $params  Route parameters.
 */
function marketingWorkflowCpRoute(string $route, array $params = []): string
{
    /** @var string $url */
    $url = cp_route($route, $params);

    return $url;
}

/**
 * The default site's handle, narrowed from the facade's `mixed`.
 */
function marketingWorkflowDefaultSite(): string
{
    /** @var Statamic\Sites\Site $site */
    $site = Site::default();

    /** @var string $handle */
    $handle = $site->handle();

    return $handle;
}

/**
 * Persist a super user, which holds both marketing-redirect permissions.
 */
function marketingWorkflowSuperUser(): FileStatamicUser
{
    $user = User::make();
    Assert::assertInstanceOf(FileStatamicUser::class, $user);

    $user->id((string) Str::uuid());
    $user->email('marketing-workflow-'.Str::uuid().'@example.test');
    $user->makeSuper();
    $user->save();

    return $user;
}

/**
 * Render the rule for `$uri` through the tag the 404 template uses.
 *
 * @param  string  $uri  Requested path.
 * @return string `target|response` when a rule serves, empty otherwise.
 */
function marketingWorkflowServed(string $uri): string
{
    return (string) Antlers::parse(
        '{{ find_redirect_rule in="'.marketingWorkflowDefaultSite().'" }}{{ target_url }}|{{ response }}{{ /find_redirect_rule }}',
        ['current_uri' => $uri],
        true,
    );
}

beforeEach(function (): void {
    withoutMiddleware(PreventRequestForgery::class);

    // The CP layout loads the CP bundle, which is not built for this job.
    withoutVite();

    // Provide the shared redirects global the workflow writes into.
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::make('redirects');
    $set->title('Redirects');
    $set->save();
    /** @var Variables $vars */
    $vars = $set->makeLocalization(marketingWorkflowDefaultSite());
    $vars->data(['redirects' => []]);
    $vars->save();
});

it('renders the add-on create and review pages', function (string $route, string $component): void {
    actingAs(marketingWorkflowSuperUser());

    get(marketingWorkflowCpRoute($route))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component($component, false));
})->with([
    'create' => ['marketing-redirects.create', 'aether-redirects::MarketingRedirectCreate'],
    'review' => ['marketing-redirects.review', 'aether-redirects::MarketingRedirectReview'],
]);

it('saves a pending request that serves only after a second user approves it', function (): void {
    actingAs(marketingWorkflowSuperUser());

    postJson(marketingWorkflowCpRoute('marketing-redirects.store'), [
        'source' => '/flu-shot',
        'destination' => '/clinics/flu',
        'response' => '301',
    ])
        ->assertStatus(201)
        ->assertJsonPath('redirect.status', 'pending');

    // A pending request does not serve.
    expect(marketingWorkflowServed('/flu-shot'))->not->toContain('/clinics/flu');

    /** @var string $id */
    $id = app(MarketingRedirectStore::class)->all()[0]['id'];

    // A different user approves it, and the rule then serves.
    actingAs(marketingWorkflowSuperUser());

    postJson(marketingWorkflowCpRoute('marketing-redirects.approve', ['redirect' => $id]))
        ->assertOk()
        ->assertJsonPath('redirect.status', 'approved');

    expect(marketingWorkflowServed('/flu-shot'))->toBe('/clinics/flu|301');
});

it('refuses a source that collides with an existing request', function (): void {
    actingAs(marketingWorkflowSuperUser());

    postJson(marketingWorkflowCpRoute('marketing-redirects.store'), [
        'source' => '/vip',
        'destination' => '/first',
        'response' => '301',
    ])->assertStatus(201);

    postJson(marketingWorkflowCpRoute('marketing-redirects.store'), [
        'source' => '/VIP/',
        'destination' => '/second',
        'response' => '301',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['source' => 'A redirect from /vip to /first']);

    // The colliding request was not written.
    expect(app(MarketingRedirectStore::class)->all())->toHaveCount(1);
});
