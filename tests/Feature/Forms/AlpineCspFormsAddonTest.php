<?php

declare(strict_types=1);

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Statamic\Facades\Blueprint;
use Statamic\Fields\BlueprintRepository;
use Tests\Support\ThrowawayRoot;
use Tests\TestCase;
use UAMSWeb\Aether\Forms\AlpineCsp\JsDrivers\AlpineCsp;

use function Pest\Laravel\get;

uses(TestCase::class);

/**
 * The host side of the `uams-web/aether-alpine-csp-forms` add-on (UAMS-Web/uams-statamic-archive#3073).
 *
 * The CSP-safe `alpine_csp` driver and its field-conditions evaluator moved from
 * this app into the add-on, which carries their own tests. What stays here is what
 * only the host can get wrong: the two public-form entry points selecting the
 * driver, the layouts loading the add-on's front-end script ahead of the bundle
 * that starts Alpine, and a public form served under a policy with no
 * `'unsafe-eval'`.
 *
 * @command php artisan test --compact tests/Feature/Forms/AlpineCspFormsAddonTest.php
 */
const ALPINE_CSP_ADDON_FORM_HANDLE = 'alpine_csp_addon_conditions';

/** The URL the add-on's front-end script is published at. */
const ALPINE_CSP_ADDON_SCRIPT = '/vendor/aether-alpine-csp-forms/frontend/alpine-csp.js';

$alpineCspAddonFormsDir = '';

/**
 * Register a two-field form whose second field is conditional on the first, so a
 * rendered form carries a `show_field` wrapper.
 */
function makeAlpineCspAddonForm(): void
{
    app(BlueprintRepository::class)->setFallback(
        'forms.'.ALPINE_CSP_ADDON_FORM_HANDLE,
        fn () => Blueprint::makeFromFields([
            'referrer' => [
                'type' => 'text',
                'display' => 'Referrer',
            ],
            'referrer_note' => [
                'type' => 'textarea',
                'display' => 'Referrer Note',
                'if' => ['referrer' => 'equals other'],
            ],
        ]),
    );

    $form = makeForm(ALPINE_CSP_ADDON_FORM_HANDLE);
    $form->title('Alpine CSP Add-on Conditions');
    $form->save();
}

beforeEach(function () use (&$alpineCspAddonFormsDir): void {
    $alpineCspAddonFormsDir = storage_path('framework/testing/forms-'.ThrowawayRoot::segment().'-'.uniqid('', true));
    File::ensureDirectoryExists($alpineCspAddonFormsDir);
    Config::set('statamic.forms.forms', $alpineCspAddonFormsDir);
});

afterEach(function () use (&$alpineCspAddonFormsDir): void {
    foreach ([$alpineCspAddonFormsDir, storage_path('forms/'.ALPINE_CSP_ADDON_FORM_HANDLE)] as $dir) {
        if ($dir !== '' && File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

it('resolves the alpine_csp handle to the add-on driver', function (): void {
    // The app no longer registers a driver of its own, so the handle both entry
    // points select resolves only if the add-on's provider was discovered.
    expect(app('statamic.form-js-drivers')->get('alpine_csp'))->toBe(AlpineCsp::class);
});

it('wires both public-form entry points to the csp driver', function (): void {
    // ---
    // The driver only reaches production through these two templates. If an entry
    // point silently reverted to `js="alpine"`, the Statamic global would return to
    // every form on the site, and nothing rendering a fixture view would notice.
    // ---
    $entryPoints = [
        'views/sets/_form.antlers.html',
        'views/page_builder/_uamsweb_block_form.antlers.html',
    ];

    foreach ($entryPoints as $entryPoint) {
        $template = File::get(resource_path($entryPoint));

        expect($template)->toContain('js="alpine_csp"');
        expect($template)->not->toContain('js="alpine"');
    }
});

it('loads the add-on script before the bundle that starts alpine', function (string $layout): void {
    $template = File::get(resource_path($layout));

    $script = '<script defer src="'.ALPINE_CSP_ADDON_SCRIPT.'"></script>';
    $bundle = '{{ vite :src="( $vite_layout_assets | join(\'|\') )"}}';

    // ---
    // The script registers `$showField` from an `alpine:init` listener, and site.js
    // dispatches that event when it calls Alpine.start(). A classic deferred script
    // and the module bundle run in document order, so the tag has to come first.
    // ---
    expect(substr_count($template, $script))->toBe(1);
    expect(substr_count($template, $bundle))->toBe(1);
    expect(strpos($template, $script))->toBeLessThan((int) strpos($template, $bundle));

    // The bundle the tag precedes is the one carrying site.js.
    expect($template)->toContain("merge_array(['resources/js/site.js'])");
})->with([
    'site layout' => ['views/layout.antlers.html'],
    'news release pdf layout' => ['views/pdf/news_release.antlers.html'],
]);

it('points the layout at the path the add-on publishes its script to', function (): void {
    // The add-on publishes resources/dist/frontend into public/vendor/aether-alpine-csp-forms/frontend.
    $source = base_path('vendor/uams-web/aether-alpine-csp-forms/resources/dist/frontend/alpine-csp.js');

    expect(File::exists($source))->toBeTrue();
    expect(File::get($source))->toContain('alpine:init');
});

it('serves a csp-driven form under a policy without unsafe-eval', function (): void {
    // ---
    // Statamic's front-end `/{segments?}` route is registered first and matches
    // every path, so a probe appended after it is never reached. Rebuild the
    // collection with the probe ahead of it, so the request really renders the form.
    // ---
    $probe = Route::middleware(SecurityHeaders::class)->get(
        '/__alpine_csp_addon_probe',
        fn (): string => (string) View::make('alpine_csp_addon_test::form')->render(),
    );
    $routes = new RouteCollection;
    $routes->add($probe);
    foreach (app('router')->getRoutes()->getRoutes() as $route) {
        if ($route !== $probe) {
            $routes->add($route);
        }
    }

    app('router')->setRoutes($routes);

    makeAlpineCspAddonForm();

    $viewsDir = storage_path('framework/testing/views-'.ThrowawayRoot::segment().'-'.uniqid('', true));
    File::ensureDirectoryExists($viewsDir);
    View::addNamespace('alpine_csp_addon_test', $viewsDir);
    File::put(
        $viewsDir.'/form.antlers.html',
        '{{ form:create handle="'.ALPINE_CSP_ADDON_FORM_HANDLE.'" js="alpine_csp" }}'
        .'{{ partial:vendor/statamic/forms/fields }}'
        .'{{ /form:create }}',
    );

    try {
        $response = get('/__alpine_csp_addon_probe');
    } finally {
        File::deleteDirectory($viewsDir);
    }

    $response->assertOk();

    // The conditional wrapper calls the add-on's magic, not the window global the
    // CSP build cannot resolve.
    $html = (string) $response->getContent();
    expect($html)->toContain('x-if="$showField(');
    expect($html)->not->toContain('Statamic.$conditions');

    // The same response is served under a script-src that admits the same-origin
    // add-on script and still has no 'unsafe-eval' for the stock driver to need.
    $policy = (string) ($response->headers->get('Content-Security-Policy')
        ?? $response->headers->get('Content-Security-Policy-Report-Only'));

    preg_match('/(?:^|; )(script-src [^;]*)/', $policy, $matches);
    $scriptSrc = $matches[1] ?? '';

    expect($scriptSrc)->toStartWith('script-src ');
    expect($scriptSrc)->toContain("'self'");
    expect($scriptSrc)->not->toContain("'unsafe-eval'");
});
