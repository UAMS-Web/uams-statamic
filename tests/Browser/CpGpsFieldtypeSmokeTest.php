<?php

declare(strict_types=1);

use Statamic\Auth\User;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Stache;

/*
 * Issue UAMS-Web/uams-statamic-archive#961 CP smoke: mount the `gps` fieldtype in a real Control Panel entry
 * editor and assert a clean JavaScript console. This exercises the Leaflet +
 * OpenStreetMap picker initialization (GpsFieldtype.vue) inside the publish
 * form — including the Street / Aerial base-layer control and the paste-
 * coordinates box — and closes the "renders in the Control Panel with no
 * console errors" acceptance criterion.
 *
 * Built on the reusable harness (tests/Support/CpSmoke.php) and modeled on
 * CpProductFieldtypeSmokeTest. A throwaway super user, collection, and
 * blueprint are created in beforeEach and removed in afterEach so nothing
 * durable is written.
 */

$collectionHandle = 'gps_smoke';

beforeEach(function () use ($collectionHandle) {
    $this->cpEmail = 'gps-smoke-'.uniqid().'@example.test';
    $this->cpPassword = 'gps-smoke-password-'.uniqid();
    $this->cpUser = seedCpSuperUser($this->cpEmail, $this->cpPassword);

    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::make($collectionHandle);
    $collection->title('GPS Smoke');
    $collection->save();

    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::makeFromFields([
        'coordinates' => [
            'type' => 'gps',
            'display' => 'Coordinates',
        ],
    ]);
    $blueprint->setHandle($collectionHandle);
    $blueprint->setNamespace('collections.'.$collectionHandle);
    $blueprint->save();

    Stache::refresh();
});

afterEach(function () use ($collectionHandle) {
    Collection::find($collectionHandle)?->delete();
    Blueprint::find('collections.'.$collectionHandle.'.'.$collectionHandle)?->delete();
    /** @var User|null $user */
    $user = $this->cpUser;
    $user?->delete();
    Stache::refresh();
});

it('renders the gps fieldtype in the CP entry editor without JavaScript errors', function () use ($collectionHandle) {
    /** @var string $cpEmail */
    $cpEmail = $this->cpEmail;
    /** @var string $cpPassword */
    $cpPassword = $this->cpPassword;

    $loggedIn = loginToCp($cpEmail, $cpPassword);
    $page = $loggedIn->navigate('/cp/collections/'.$collectionHandle.'/entries/create/default');

    // Wait for the Leaflet-backed picker to mount (its labels render client-side
    // after page load); the remaining labels render together, so a synchronous
    // assertSee on them is safe once the first has appeared.
    waitToSee($page, 'Paste Coordinates');
    $page->assertSee('Map')
        ->assertSee('Generated Coordinates')
        ->assertSee('Street')
        ->assertSee('Aerial')
        ->assertSee('Reset View');

    assertNoAppJavaScriptErrors($page);
});
