<?php

declare(strict_types=1);

namespace App\Providers;

use App\Policies\CustomUserPolicy;
use App\Policies\ScopedEntryPolicy;
use App\Services\LinkHealth\LinkClassifier;
use App\Services\Tables\TableCellSanitizer; // Added by studio1902/statamic-peak
use App\Support\Forms\FormRequestSubmissionContext;
use App\Support\Imaging\BrandLogoRasterizer;
use App\Support\Imaging\SvgRasterizer;
use App\Support\People\PeopleEditingScope;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable; // Added by studio1902/statamic-peak
use Illuminate\Contracts\View\Factory; // Added by studio1902/statamic-peak
use Illuminate\Contracts\View\View; // Added by studio1902/statamic-peak
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route; // Added by studio1902/statamic-peak
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Statamic\Entries\Collection as EntryCollection;
use Statamic\Entries\Entry as EntryItem;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Permission;
use Statamic\Facades\Site as SiteFacade;
use Statamic\Facades\User as UserFacade;
use Statamic\Hooks\CP\EntriesIndexQuery;
use Statamic\Policies\EntryPolicy;
use Statamic\Policies\UserPolicy;
use Statamic\Query\Builder as QueryBuilder;
use Statamic\Sites\Site;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Statamic;
use UAMSWeb\Aether\Forms\Settings\Contracts\SubmissionContext;

// Added by studio1902/statamic-peak

/**
 * Application Service Provider
 *
 * This is Laravel's default application service provider that has been modified
 * by studio1902/statamic-peak to include additional functionality.
 *
 * Modifications sourced from studio1902/statamic-peak:
 * - https://statamic.com/starter-kits/studio1902/peak
 * - https://peak.1902.studio/
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * Added by studio1902/statamic-peak.
     */
    public const string HOME = '/home';

    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        // Added by studio1902/statamic-peak: Bind custom user policy
        $this->app->bind(
            UserPolicy::class,
            CustomUserPolicy::class
        );

        // Scope `people` editing to the organizations of the editor's user groups (UAMS-Web/uams-statamic-archive#3182)
        $this->app->bind(
            EntryPolicy::class,
            ScopedEntryPolicy::class
        );

        // Carry the submitter's request context onto the form settings add-on's
        // queued notification job (UAMS-Web/uams-statamic-archive#830, #263)
        $this->app->bind(
            SubmissionContext::class,
            FormRequestSubmissionContext::class
        );

        // Link-health classification needs two facts the container cannot infer: which hosts
        // this install serves, and which entry ids exist. Bound lazily so the cost — a walk of
        // every site and every entry id — is paid only by a scan that asks for it, never on an
        // ordinary request boot (UAMS-Web/uams-statamic-archive#1766).
        $this->app->bind(LinkClassifier::class, static function (): LinkClassifier {
            /** @var Collection<int|string, Site> $sites */
            $sites = SiteFacade::all();

            $hosts = [];

            foreach ($sites as $site) {
                $url = $site->absoluteUrl();
                $host = \is_string($url) ? parse_url($url, PHP_URL_HOST) : null;

                if (\is_string($host) && $host !== '') {
                    $hosts[strtolower($host)] = true;
                }
            }

            /** @var EntryQueryBuilder $query */
            $query = EntryFacade::query();
            /** @var Collection<int, mixed> $entries */
            $entries = $query->get();
            $ids = [];

            foreach ($entries as $entry) {
                $id = $entry instanceof EntryItem ? $entry->id() : null;

                if (\is_string($id)) {
                    $ids[$id] = true;
                }
            }

            return new LinkClassifier(array_keys($hosts), $ids);
        });

        // Share one table-cell sanitizer so its compiled HTML definition is
        // built once and reused across every cell rendered in a request.
        $this->app->singleton(TableCellSanitizer::class);

        // Brand-logo SVG→PNG raster fallback (UAMS-Web/uams-statamic-archive#50). Caches generated PNGs under
        // the web root (mirroring Glide's `public/img` cache) so no storage:link
        // symlink is required, and rasterizes via Imagick (the configured driver).
        $this->app->singleton(BrandLogoRasterizer::class, fn (): BrandLogoRasterizer => new BrandLogoRasterizer(
            SvgRasterizer::imagick(),
            Storage::build([
                'driver' => 'local',
                'root' => public_path(),
                'url' => '/',
                'visibility' => 'public',
            ]),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // #206 — the emailed password-reset link must land on the front end, not the
        // Control Panel.
        //
        // Statamic registers `GET !/auth/password/reset/{token}` as
        // `statamic.password.reset`, handled by ResetPasswordController@showResetForm —
        // which renders Control Panel Inertia UI, with a "back to login" pointing at
        // `cp_route('login')`. A public visitor following the email arrives in an admin
        // interface. A template cannot fix it the way it fixes the other four auth pages:
        // those have no display route, so a `pages` entry supplies one, while this URI is
        // already claimed and the controller answers before the entry resolver runs.
        //
        // It is registered from `booted()` rather than `routes/web.php` because Laravel's
        // RouteCollection keys on method+URI and the LAST registration wins. Statamic
        // registers this URI after the app's route file is loaded, so an override there is
        // silently replaced — measured: the override was absent from all 2,662 routes, with
        // the Statamic handler still listed. `booted()` runs after every provider, so this
        // one is the survivor.
        //
        // Deliberately NARROWER than the alternative #206 rejected for the five auth
        // pages. That rejection was about freezing five editor-owned page URLs in code;
        // this is one handler for a URI Statamic already owns, no editor would ever set,
        // and an emailed link resolves against. Decided on the ticket, 2026-09-08.
        //
        // The throttle is carried over deliberately: Statamic ships
        // `throttle:statamic.password-reset-form` on the route being replaced, and dropping
        // it would silently remove rate limiting from a public auth endpoint (AC6).
        $this->app->booted(function (): void {
            Route::get('!/auth/password/reset/{token}', fn (string $token): Factory|View => view('account.reset_password', ['token' => $token]))
                ->middleware('throttle:statamic.password-reset-form')
                ->name('statamic.password.reset');
        });

        // Register CP Vite bundle (fieldtypes) with hot file and build directory
        Statamic::vite(
            'app',
            [
                'input' => [
                    'resources/js/cp.js',
                    'resources/css/tailwind/cp.css',
                ],
                'hotFile' => public_path('cp-hot'),
                'buildDirectory' => 'vendor/app',
            ]
        );

        // Custom route booting (API rate limiting).
        $this->bootRoute();

        // Register the resource permission gating the field-trigger activate
        // endpoint (UAMS-Web/uams-statamic-archive#863)
        $this->bootPermissions();

        // Show a scoped editor only the `people` entries in their scope (UAMS-Web/uams-statamic-archive#3182)
        $this->bootPeopleEditingScope();
    }

    /**
     * Limit the Control Panel `people` listing and search to a scoped editor's entries.
     *
     * The entry policy stops a scoped editor opening an entry outside their
     * organizations; this keeps those entries out of the listing and its search
     * results too, so the editor is never offered a row they cannot open. The
     * listing query and the search query both reach this hook, and both accept
     * `whereIn('id', ...)`.
     */
    public function bootPeopleEditingScope(): void
    {
        EntriesIndexQuery::hook('query', function (mixed $payload, \Closure $next): mixed {
            /** @var object{query: mixed, collection: mixed} $payload */
            $collection = $payload->collection;
            $query = $payload->query;
            $user = UserFacade::current();
            $scope = app(PeopleEditingScope::class);

            if (
                $collection instanceof EntryCollection
                && $collection->handle() === PeopleEditingScope::COLLECTION
                && $query instanceof QueryBuilder
                && $user !== null
                && $scope->appliesTo($user)
            ) {
                $query->whereIn('id', $scope->editableEntryIds($user));
            }

            return $next($payload);
        });
    }

    /**
     * Register the CP permission that gates `POST /!/trigger/activate`.
     *
     * Without this, the endpoint was reachable by any CP user holding bare
     * `access cp` (e.g. the `editor` and `marketeer` roles), letting them drive
     * every registered `TriggerActivated` listener with attacker-controlled
     * values. The route now requires `activate field triggers`, which no default
     * role carries (super users still bypass via Statamic's gate).
     */
    public function bootPermissions(): void
    {
        Permission::group('field_triggers', 'Field Triggers', function (): void {
            Permission::register('activate field triggers')
                ->label(__('Activate field triggers'));
        });
    }

    /**
     * Configure route-specific services.
     *
     * Added by studio1902/statamic-peak to implement API rate limiting.
     */
    public function bootRoute(): void
    {
        RateLimiter::for(
            'api',
            function (Request $request) {
                /** @var Authenticatable|null $user */
                $user = $request->user();

                return Limit::perMinute(60)->by($user?->getAuthIdentifier() ?: $request->ip());
            }
        );

        // Rate limiter for the public knowledge base engagement endpoints (UAMS-Web/uams-statamic-archive#286
        // ratings, UAMS-Web/uams-statamic-archive#287 views). Keyed per client IP, it bounds scripted abuse of
        // the view / react beacons on top of the per-session repeat-vote guard —
        // 60/min is well above any human's article-reading cadence.
        RateLimiter::for(
            'kb-engagement',
            fn (Request $request) => Limit::perMinute(60)->by($request->ip() ?: 'unknown')
        );

        // Rate limiter for the site-search type-ahead (UAMS-Web/uams-statamic-archive#1506). A reader types a few
        // characters a second and the client debounces, so 120/min per client IP is
        // well above real use while bounding a script that enumerates the index.
        RateLimiter::for(
            'site-search-suggestions',
            fn (Request $request) => Limit::perMinute(120)->by($request->ip() ?: 'unknown')
        );

        // Rate limiters for posting and editing classified listings (UAMS-Web/uams-statamic-archive#2519). Each has
        // its own key prefix, so edits never count against the posting allowance, and
        // is keyed by account, falling back to the client IP.
        foreach (['classifieds-post' => 'posts_per_hour', 'classifieds-edit' => 'edits_per_hour'] as $limiter => $setting) {
            RateLimiter::for($limiter, function (Request $request) use ($limiter, $setting) {
                /** @var Authenticatable|null $user */
                $user = $request->user();
                $perHour = config('classifieds.throttle.'.$setting);
                $identity = $user?->getAuthIdentifier();

                return Limit::perHour(is_int($perHour) ? $perHour : 20)
                    ->by($limiter.':'.(is_string($identity) ? $identity : ($request->ip() ?? 'unknown')));
            });
        }
    }
}
