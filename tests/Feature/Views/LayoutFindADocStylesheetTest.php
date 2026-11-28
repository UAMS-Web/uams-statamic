<?php

declare(strict_types=1);

use Illuminate\Foundation\Vite as LaravelVite;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

uses(StacheIsolatedTestCase::class);

/**
 * Whether `layout.antlers.html` requests the Find-a-Doc stylesheet for a served entry
 * (UAMS-Web/uams-statamic#1629). The layout checks the page's template against a list of
 * Find-a-Doc templates; an entry that inherits its template from its collection stores none,
 * so the check has to read the template the response renders rather than the stored field.
 *
 * Each case serves a real entry from an isolated Stache through the layout, with Laravel's Vite
 * replaced by a recorder, because the suite's default `withoutVite()` stub prints nothing to
 * assert against.
 *
 * @command  php artisan test --compact tests/Feature/Views/LayoutFindADocStylesheetTest.php
 */
const LAYOUT_FAD_SHEET = 'resources/scss/bootstrap/uamswp_find_a_doc/app.scss';

/**
 * Laravel's Vite with its output replaced by a record of the entry points requested.
 */
class LayoutFadStylesheetVite extends LaravelVite
{
    /**
     * The entry points requested since the last reset.
     *
     * @var list<string>
     */
    public static array $requested = [];

    /**
     * Record the entry points and render nothing.
     *
     * @param  string|string[]  $entrypoints  The entry points.
     * @param  string|null  $buildDirectory  The build directory, unused.
     * @return HtmlString An empty string.
     */
    public function __invoke($entrypoints, $buildDirectory = null): HtmlString
    {
        foreach ((array) $entrypoints as $entrypoint) {
            self::$requested[] = $entrypoint;
        }

        return new HtmlString('');
    }
}

beforeEach(function (): void {
    $this->app->instance(LaravelVite::class, new LayoutFadStylesheetVite);
    LayoutFadStylesheetVite::$requested = [];

    // The layout's navigation reads the `pages` structure.
    $pages = CollectionFacade::make('pages');
    $pages->title('Pages');
    $pages->routes('/{slug}');
    $pages->structureContents(['root' => true]);
    $pages->save();

    // A one-line template outside the Find-a-Doc list, so a control page exercises the layout
    // alone rather than a real template's own data needs.
    // A directory of its own per test, so parallel workers never share it.
    $views = sys_get_temp_dir().'/layout-fad-views-'.bin2hex(random_bytes(8));
    $this->layoutFadViews = $views;
    File::ensureDirectoryExists($views);
    File::put($views.'/layout_fad_plain.antlers.html', '<p>{{ title }}</p>');
    View::addLocation($views);
});

afterEach(function (): void {
    File::deleteDirectory($this->layoutFadViews);
});

/**
 * Serve one entry through the layout and return the entry points it requested.
 *
 * @param  string  $collectionTemplate  The collection's default template.
 * @param  array<string, mixed>  $data  The entry's stored data.
 * @return list<string> The requested entry points.
 */
function layoutFadRequested(string $collectionTemplate, array $data = []): array
{
    $collection = CollectionFacade::make('layout_fad');
    $collection->title('Layout Find-a-Doc');
    $collection->routes('/layout-fad/{slug}');
    $collection->template($collectionTemplate);
    $collection->layout('layout');
    $collection->save();

    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('layout_fad');
    $entry->slug('one');
    $entry->data(['title' => 'Layout Entry', ...$data]);
    $entry->save();

    test()->get('/layout-fad/one')->assertOk();

    return LayoutFadStylesheetVite::$requested;
}

it('requests the sheet for an entry that inherits a Find-a-Doc template from its collection', function (): void {
    $requested = layoutFadRequested('clinical_resources/show');

    // Control: the layout ran and requested its theme assets.
    expect($requested)->toContain('resources/scss/bootstrap/uams_2020/app.scss')
        ->and($requested)->toContain(LAYOUT_FAD_SHEET);
});

it('still requests the sheet for an entry that stores a Find-a-Doc template', function (): void {
    $requested = layoutFadRequested('layout_fad_plain', ['template' => 'clinical_resources/show']);

    expect($requested)->toContain(LAYOUT_FAD_SHEET);
});

it('requests the sheet after the People showcase sheets and before the theme', function (): void {
    $requested = layoutFadRequested('clinical_resources/show');
    $position = array_flip($requested);

    expect($position[LAYOUT_FAD_SHEET])->toBeGreaterThan($position['resources/scss/bootstrap/uams_2020/wp-blocks.scss'])
        ->and($position[LAYOUT_FAD_SHEET])->toBeLessThan($position['resources/scss/bootstrap/uams_2020/app.scss']);
});

it('does not request the sheet for a plain page with no Find-a-Doc template or block', function (): void {
    $requested = layoutFadRequested('layout_fad_plain');

    // Control: the layout ran and requested its theme assets.
    expect($requested)->toContain('resources/scss/bootstrap/uams_2020/app.scss')
        ->and($requested)->not->toContain(LAYOUT_FAD_SHEET);
});
