<?php

declare(strict_types=1);

use App\Events\TriggerActivated;
use App\Http\Controllers\Classifieds\ClassifiedListingController;
use App\Http\Controllers\ClinicalExpertiseSectionController;
use App\Http\Controllers\Feed\PostsFeedController;
use App\Http\Controllers\KnowledgeBase\ArticleReactionController;
use App\Http\Controllers\KnowledgeBase\ArticleViewController;
use App\Http\Controllers\People\PeopleLensController;
use App\Http\Controllers\Seo\RobotsController;
use App\Http\Controllers\Seo\SitemapIndexController;
use App\Http\Controllers\SiteSearchSuggestionsController;
use App\Http\Middleware\RedirectGuestToLogin;
use App\Providers\AppServiceProvider;
use App\Services\ClinicalExpertise\ClinicalExpertiseSectionResolver;
use App\Services\NewsRelease\NewsReleasePdfGenerator;
use App\Support\People\Lenses\PeopleLensRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\URL;
use Statamic\Http\Controllers\FrontendController;
use Statamic\Http\Middleware\CP\AuthGuard as CpAuthGuard;
use Statamic\Http\Middleware\CP\Authorize as CpAuthorize;
use Statamic\Http\Middleware\CP\BootPermissions as CpBootPermissions;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// App-owned SEO routes replacing studio1902/statamic-peak-seo (UAMS-Web/uams-statamic-archive#1326, unblocking
// UAMS-Web/uams-statamic-archive#1117). robots.txt and the multisite sitemap index are single root-level
// documents; the per-site sitemap.xml files themselves are served by
// mitydigital/sitemapamic at {site}/sitemap.xml.
Route::get('/robots.txt', RobotsController::class);
Route::get('/sitemaps.xml', SitemapIndexController::class);

// Per-site outbound RSS 2.0 feed at {site}/feed.xml (UAMS-Web/uams-statamic-archive#1042) — the WordPress
// /feed/ analog. Registered only for sites where the `posts` collection is
// enabled (driven off the collection's own sites list, mirroring WordPress's
// per-site feeds); PostsFeedController scopes each feed to the request's site.
// Fixed paths are matched before Statamic's catch-all, so /feed.xml always wins.
$postsCollection = Collection::findByHandle('posts');

if ($postsCollection !== null) {
    $postsSiteHandles = $postsCollection->sites()->all();

    Site::all()
        ->filter(fn ($site) => in_array($site->handle(), $postsSiteHandles, true))
        ->map(fn ($site) => URL::makeRelative($site->url()))
        ->unique()
        ->each(fn ($prefix) => Route::prefix($prefix)->group(function () {
            Route::get('feed.xml', PostsFeedController::class);
        }));
}

// People lens routes (UAMS-Web/uams-statamic-archive#1635 / UAMS-Web/uams-statamic-archive#1636). A lens renders one canonical `people` entry
// (which lives only on the canonical site) through a site-specific template at its
// own URL — the clinical `/provider/{slug}` on `health`, the org-derived lenses on
// their sites. Registered per active site's URL prefix (mirroring the per-site feed
// routes above) and before Statamic's catch-all, so a lens URL reaches
// PeopleLensController; a non-lens URL under the same prefix falls through to the
// front end without shadowing a real page. The registry is the single source of the lens set.
foreach (app(PeopleLensRegistry::class)->all() as $lens) {
    Site::all()
        ->filter(fn ($site) => $lens->activeOnSite($site->handle()))
        ->map(fn ($site) => URL::makeRelative($site->url()))
        ->unique()
        ->each(fn ($prefix) => Route::prefix($prefix)->group(function () use ($lens) {
            Route::get($lens->route, PeopleLensController::class)
                ->defaults('lens', $lens->handle)
                ->middleware('statamic.web');
        }));
}

// Classifieds posting (UAMS-Web/uams-statamic-archive#2519): a signed-in user's own listings on Inside UAMS, and the
// forms to post, edit and remove one. Registered under the classifieds site's URL
// prefix and before Statamic's catch-all, so `statamic.web` resolves the site from the
// URL and the pages carry its layout. `RedirectGuestToLogin` sends a visitor who is not
// signed in to the login page. Posting and editing have separate per-account
// limits, `classifieds-post` and `classifieds-edit` in AppServiceProvider.
$classifiedsSite = Site::get('inside_classifieds');

if ($classifiedsSite !== null) {
    Route::prefix(URL::makeRelative($classifiedsSite->url()).'/my-listings')
        ->middleware(['statamic.web', RedirectGuestToLogin::class])
        ->name('classifieds.listings.')
        ->group(function () {
            Route::get('/', [ClassifiedListingController::class, 'index'])->name('index');
            Route::get('new', [ClassifiedListingController::class, 'create'])->name('create');
            Route::post('/', [ClassifiedListingController::class, 'store'])->name('store')->middleware('throttle:classifieds-post');
            Route::get('{listing}/edit', [ClassifiedListingController::class, 'edit'])->name('edit');
            Route::put('{listing}', [ClassifiedListingController::class, 'update'])->name('update')->middleware('throttle:classifieds-edit');
            Route::delete('{listing}', [ClassifiedListingController::class, 'destroy'])->name('destroy')->middleware('throttle:classifieds-edit');
        });
}

// Route for Statamic entries with password protection
// (equivalent to Route::statamic(), which is a runtime macro on the router)
Route::get('entries/{entry}', [FrontendController::class, 'route'])
    ->middleware(['protect.entry'])
    ->defaults('view', 'entry')
    ->defaults('data', [
        'title' => 'Entry',
    ]);

// ---
// Site-search page (UAMS-Web/uams-statamic-archive#815 / UAMS-Web/uams-statamic-archive#56): renders the `/search` view — a relevance-ranked
// result list with a type-filter dropdown over the local search indexes. Registered
// before the `{entry}/{section}` wildcard below so it is never shadowed. Being a
// routeless view (no entry), it has no nav breadcrumb chain, so `breadcrumb_leaf`
// supplies the trailing "Search" crumb (the reusable hook for any routeless view).
// Every sub-site gets the same page under its own prefix (UAMS-Web/uams-statamic#413),
// because the current site comes from the URL and "This site" needs a URL to mean it.
// ---
$siteSearchRouteData = [
    'title' => 'Search',
    'breadcrumb_leaf' => 'Search',
    // Routeless views have no `published` in the cascade, which the shared
    // `layout/article` reads as a draft (`status-draft`); this page is live.
    'published' => true,
    // Render the standard page-header `h1` (the cover-image / entry-meta partials
    // below it are inert without that data), so /search has a page title like any
    // ordinary entry.
    'title_options' => 'normal',
];

Route::statamic('/search', 'search/index', $siteSearchRouteData);

Site::all()
    ->map(fn ($site) => URL::makeRelative($site->url()))
    ->reject(fn ($prefix) => $prefix === '/')
    ->unique()
    ->each(fn ($prefix) => Route::statamic($prefix.'/search', 'search/index', $siteSearchRouteData));

// Type-ahead suggestions for the /search input (UAMS-Web/uams-statamic-archive#1506): a short JSON list from the
// unified `default` index, same site and status rules as the page. Registered before
// the `{entry}/{section}` wildcard below; the `/!/` prefix keeps it clear of entry URIs.
Route::get('/!/search/suggestions', SiteSearchSuggestionsController::class)
    ->middleware('throttle:site-search-suggestions')
    ->name('site-search.suggestions');

// Area-of-Expertise virtual subpages (UAMS-Web/uams-statamic-archive#1058). One wildcard route renders each
// relationship-derived section of a `clinical_expertise` entry at its own SEO URL
// at any nesting depth: `/{entry-uri}/{section}`. `{section}` is constrained to
// the enum single-sourced from ClinicalExpertiseSectionResolver, so only URLs
// ending in a section token reach the controller; app routes are matched before
// the Statamic catch-all, so this rule wins for those URLs. The controller renders
// the section ONLY for a published, publicly-visible AoE whose section resolves,
// and otherwise DELEGATES to the normal front end for the full URL — so it never
// discloses draft/restricted content and never 404s (shadows) a real page whose
// last segment merely happens to be a section token (e.g. `/about/locations`).
Route::get('{entry}/{section}', ClinicalExpertiseSectionController::class)
    ->middleware('statamic.web')
    ->where('entry', '.*')
    ->where('section', ClinicalExpertiseSectionResolver::routeConstraint());

/**
 * Route to handle trigger actions from the Statamic control panel.
 *
 * Requires a signed-in Statamic user who may access the CP AND holds the
 * resource-specific `activate field triggers` permission (registered in
 * {@see AppServiceProvider::bootPermissions()}). Bare `access cp`
 * is no longer sufficient: without the dedicated permission a low-privilege CP
 * user (e.g. the `editor`/`marketeer` roles) is rejected with 403, so they can
 * no longer drive every registered `TriggerActivated` listener with
 * attacker-controlled values (UAMS-Web/uams-statamic-archive#863). Super users bypass via Statamic's gate.
 * The `Trigger` fieldtype posts here; guests cannot dispatch `TriggerActivated`.
 */
Route::post('/!/trigger/activate', function (Request $request) {
    // Extract trigger handle and form values
    $triggerHandle = $request->input('trigger_handle');
    $formValues = $request->input(
        'form_values',
        []
    );

    // Validate trigger handle: a non-empty string (a non-string handle would
    // otherwise reach the typed event constructor and 500)
    if (! \is_string($triggerHandle) || $triggerHandle === '') {
        Log::error(
            'web.php::route:/!/trigger/activate: Missing trigger_handle in trigger request',
            ['trigger_handle' => $triggerHandle]
        );

        return response()->json(
            ['error' => 'Missing trigger_handle'],
            400
        );
    }

    // Validate form values
    if (! \is_array($formValues)) {
        Log::error(
            'web.php::route:/!/trigger/activate: Invalid form_values in trigger request',
            ['form_values' => $formValues]
        );

        return response()->json(
            ['error' => 'Invalid form_values'],
            400
        );
    }

    // Dispatch event with null entry (handles new entries). Multiple listeners
    // are registered (one per trigger handle); each returns an empty array for
    // triggers it does not own, so use the first non-empty response.
    $responses = event(
        new TriggerActivated(
            null,
            $triggerHandle,
            $formValues
        )
    );

    // Default to no changes, then take the first listener that returned values
    $updatedValues = [];
    foreach ((array) $responses as $response) {
        if (\is_array($response) && $response !== []) {
            $updatedValues = $response;

            break;
        }
    }

    // Return success response with updated values
    return response()->json([
        'success' => 'Trigger action activated successfully.',
        'updated_values' => $updatedValues,
    ]);
})->middleware(
    [
        'statamic.web',
        CpAuthGuard::class,
        CpBootPermissions::class,
        CpAuthorize::class,
        // Require the resource-specific permission, not just `access cp` (UAMS-Web/uams-statamic-archive#863).
        // Runs after CpBootPermissions so the gate knows the ability, and after
        // CpAuthGuard so it authorizes against the CP user.
        'can:activate field triggers',
    ]
);

// Knowledge base article engagement endpoints (UAMS-Web/uams-statamic-archive#286 ratings, UAMS-Web/uams-statamic-archive#287 views). The
// article page posts here from JavaScript so the counters survive page static-
// caching (an in-render count cannot) and are attributable to a real request. Both
// are CSRF-exempt (see bootstrap/app.php): the session they rely on is established
// by the request itself, so a first hit from a cached page has no token to send —
// abuse is instead bounded by the per-session repeat guard, bot filtering, and the
// `kb-engagement` rate limiter. `{id}` is validated against a published article by
// the controller, so a bad id 404s rather than touching any counter.
Route::post('/!/kb/articles/{id}/view', ArticleViewController::class)
    ->middleware('throttle:kb-engagement')
    ->name('kb.articles.view');
Route::post('/!/kb/articles/{id}/react', ArticleReactionController::class)
    ->middleware('throttle:kb-engagement')
    ->name('kb.articles.react');

// Route::get('example', [FrontendController::class, 'route'])
//     ->defaults('view', 'example-view')
//     ->defaults('data', ['title' => 'Example']);

// ---
// Local-only HTML preview for the news release PDF Antlers template (same
// output as Browsershot input). Do not register outside local.
// ---

if (app()->environment('local')) {
    Route::get('/dev/news-release-pdf-html/{id}', function (string $id) {
        // Resolve the posts entry by id
        $entry = Entry::find($id);

        // Require a valid entry id
        abort_if($entry === null, 404);

        // Build the PDF HTML shell using the shared generator
        $html = app(NewsReleasePdfGenerator::class)->renderPressReleaseHtml($entry);

        // Return raw HTML for browser inspection
        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    })->name('dev.news-release-pdf-html');
}
