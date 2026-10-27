<?php

declare(strict_types=1);

use Statamic\Auth\User;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Stache;

/*
 * The core UAMS-Web/uams-statamic-archive#852 deliverable: render a custom CP Vue fieldtype in a real
 * Control Panel entry editor and assert a clean console. This is the
 * reusable pattern future fieldtype tickets copy — give it a fieldtype
 * handle + config and it proves the component mounts without runtime errors.
 *
 * Isolation: a throwaway super user, collection, and blueprint are created in
 * setUp and removed in tearDown so the suite writes nothing durable into the
 * repo. Helpers (seedCpSuperUser / loginToCp / assertNoAppJavaScriptErrors)
 * live in tests/Support/CpSmoke.php.
 */

$collectionHandle = 'fieldtype_smoke';

beforeEach(function () use ($collectionHandle) {
    $this->cpEmail = 'smoke-'.uniqid().'@example.test';
    $this->cpPassword = 'smoke-password-'.uniqid();
    $this->cpUser = seedCpSuperUser($this->cpEmail, $this->cpPassword);

    $collection = Collection::make($collectionHandle);
    $collection->title('Fieldtype Smoke');
    $collection->save();

    $blueprint = Blueprint::makeFromFields([
        'sample_field' => ['type' => 'quantitative_value', 'display' => 'Sample Field'],
    ]);
    $blueprint->setHandle($collectionHandle);
    $blueprint->setNamespace('collections.'.$collectionHandle);
    $blueprint->save();

    Stache::refresh();
});

afterEach(function () use ($collectionHandle) {
    Collection::find($collectionHandle)?->delete();
    /** @var Statamic\Fields\Blueprint|null $smokeBlueprint */
    $smokeBlueprint = Blueprint::find('collections.'.$collectionHandle.'.'.$collectionHandle);
    $smokeBlueprint?->delete();
    /** @var User|null $cpUser */
    $cpUser = $this->cpUser;
    $cpUser?->delete();
    Stache::refresh();
});

it('renders a custom fieldtype in the CP entry editor without JavaScript errors', function () use ($collectionHandle) {
    /** @var string $email */
    $email = $this->cpEmail;
    /** @var string $password */
    $password = $this->cpPassword;
    $loggedIn = loginToCp($email, $password);
    $page = $loggedIn->navigate('/cp/collections/'.$collectionHandle.'/entries/create/default');

    // The publish form mounts client-side after page load; wait for the field
    // label rather than asserting it synchronously (which races the render).
    waitToSee($page, 'Sample Field');

    assertNoAppJavaScriptErrors($page);
});
