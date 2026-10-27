<?php

declare(strict_types=1);

use Statamic\Auth\User;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Stache;

/*
 * Issue UAMS-Web/uams-statamic-archive#300 CP smoke: mount the `product` fieldtype in a real Control Panel
 * entry editor — in both `single` (base price + quantity) and `multiple`
 * (priced choices) modes — and assert a clean JavaScript console. This closes
 * the "renders in the Control Panel with no console errors" acceptance
 * criterion that could not be automated until the UAMS-Web/uams-statamic-archive#852 browser harness landed.
 *
 * Built on the reusable harness (tests/Support/CpSmoke.php) and modeled on
 * CpFieldtypeSmokeTest. A throwaway super user, collection, and blueprint are
 * created in beforeEach and removed in afterEach so nothing durable is written.
 */

$collectionHandle = 'product_smoke';

beforeEach(function () use ($collectionHandle) {
    $this->cpEmail = 'product-smoke-'.uniqid().'@example.test';
    $this->cpPassword = 'product-smoke-password-'.uniqid();
    $this->cpUser = seedCpSuperUser($this->cpEmail, $this->cpPassword);

    $collection = Collection::make($collectionHandle);
    $collection->title('Product Smoke');
    $collection->save();

    // Both fieldtype modes on one blueprint so a single render exercises every
    // branch of ProductFieldtype.vue: the single base-price + quantity input
    // and the multiple-choice Select of priced options.
    $blueprint = Blueprint::makeFromFields([
        'single_product' => [
            'type' => 'product',
            'display' => 'Single Product',
            'selection_type' => 'single',
            'price' => 49.99,
            'disable_quantity' => false,
        ],
        'multiple_product' => [
            'type' => 'product',
            'display' => 'Multiple Product',
            'selection_type' => 'multiple',
            'options' => [
                ['key' => 'tshirt', 'label' => 'T-Shirt', 'price' => 25.00],
                ['key' => 'hoodie', 'label' => 'Hoodie', 'price' => 45.00],
            ],
        ],
    ]);
    $blueprint->setHandle($collectionHandle);
    $blueprint->setNamespace('collections.'.$collectionHandle);
    $blueprint->save();

    Stache::refresh();
});

afterEach(function () use ($collectionHandle) {
    Collection::find($collectionHandle)?->delete();

    /** @var Statamic\Fields\Blueprint|null $blueprint */
    $blueprint = Blueprint::find('collections.'.$collectionHandle.'.'.$collectionHandle);
    $blueprint?->delete();
    /** @var User|null $cpUser */
    $cpUser = $this->cpUser;
    $cpUser?->delete();
    Stache::refresh();
});

it('renders the product fieldtype (single + multiple) in the CP entry editor without JavaScript errors', function () use ($collectionHandle) {
    /** @var string $email */
    $email = $this->cpEmail;
    /** @var string $password */
    $password = $this->cpPassword;

    $loggedIn = loginToCp($email, $password);
    $page = $loggedIn->navigate('/cp/collections/'.$collectionHandle.'/entries/create/default');

    // The publish form mounts client-side after page load; wait for the first
    // field label before asserting the rest (they render together).
    waitToSee($page, 'Single Product');
    $page->assertSee('Multiple Product');

    assertNoAppJavaScriptErrors($page);
});
