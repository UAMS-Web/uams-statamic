<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Form as FormFacade;
use Statamic\Fields\BlueprintRepository;
use Statamic\Forms\Form;
use Statamic\Forms\Submission;
use Tests\TestCase;

use function Pest\Laravel\post;

uses(TestCase::class);

/**
 * End-to-end coverage for the `product` fieldtype on a real Statamic form:
 * render the public form through the actual `form:create` pipeline, submit it
 * over HTTP, and assert the stored submission carries the authoritative,
 * server-recomputed `line_total` for each product.
 *
 * The blueprint mirrors real Gravity Forms exports under
 * `D:\GitHub Repos\wordpress-importer-exports\GravityForms\`, as the
 * wordpress-importer maps them onto this fieldtype:
 *   - Single Product (`inputType: singleproduct`) with a positive base price and
 *     a negative "credit" base price — both occur on the IDHI **e-Link Quote**
 *     form (`gravityforms-export_idhi-uams-edu_e-link.json`).
 *   - Multiple Product Choices (`inputType: radio`) with priced options — the
 *     "Product Name" field on `gravityforms-export_communications-uams-edu_web.json`
 *     ($42.13 / $13.42 / $0.42).
 *
 * Mutation: 100% on App\Fieldtypes\Product (204 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Forms/ProductFormRoundTripTest.php
 */

/** Form handle under test. */
const PRODUCT_FORM_ROUND_TRIP_HANDLE = 'product_quote';

/**
 * Isolated form-definition directory so the throwaway form never touches
 * resources/forms.
 *
 * @var string Filesystem path to the per-test forms directory.
 */
$productFormRoundTripFormsDir = '';

/**
 * Isolated submissions directory so the throwaway submission never touches
 * storage/forms.
 *
 * @var string Filesystem path to the per-test submissions directory.
 */
$productFormRoundTripSubmissionsDir = '';

beforeEach(function () use (&$productFormRoundTripFormsDir, &$productFormRoundTripSubmissionsDir): void {
    // Isolate the form-definition and submission storage so the test never
    // writes the throwaway form / submission into the repo's resources/forms
    // or storage/forms.
    $productFormRoundTripFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    $productFormRoundTripSubmissionsDir = storage_path('framework/testing/submissions-'.uniqid('', true));
    File::ensureDirectoryExists($productFormRoundTripFormsDir);
    File::ensureDirectoryExists($productFormRoundTripSubmissionsDir);
    Config::set('statamic.forms.forms', $productFormRoundTripFormsDir);
    Config::set('statamic.forms.submissions', $productFormRoundTripSubmissionsDir);

    // Give the form an in-memory blueprint with product fields shaped the
    // way the importer emits them (single + negative-price single + multiple).
    app(BlueprintRepository::class)->setFallback('forms.'.PRODUCT_FORM_ROUND_TRIP_HANDLE, fn () => Blueprint::makeFromFields([
        'first_year_cost' => [
            'type' => 'product',
            'display' => 'Telemedicine Cart (first year cost)',
            'selection_type' => 'single',
            'price' => 11000,
        ],
        'high_risk_ob_credit' => [
            'type' => 'product',
            'display' => 'Program Participation Telemedicine Cart - High-Risk OB',
            'selection_type' => 'single',
            'price' => -2500,
        ],
        'product_name' => [
            'type' => 'product',
            'display' => 'Product Name',
            'selection_type' => 'multiple',
            'options' => [
                ['key' => 'First Choice', 'price' => 42.13],
                ['key' => 'Second Choice', 'price' => 13.42],
                ['key' => 'Third Choice', 'price' => 0.42],
            ],
        ],
    ]));

    // Persist the form so the submission route resolves it.
    $form = makeForm(PRODUCT_FORM_ROUND_TRIP_HANDLE);
    $form->title('Product Quote');
    $form->save();
});

afterEach(function () use (&$productFormRoundTripFormsDir, &$productFormRoundTripSubmissionsDir): void {
    // Remove the isolated dirs and the default submissions location. Statamic
    // resolves the submissions path at boot, so the runtime Config::set in
    // setUp doesn't redirect submission writes — they land in the default
    // storage/forms/<handle>, which we clean up here so nothing leaks to disk.
    $dirs = [
        $productFormRoundTripFormsDir,
        $productFormRoundTripSubmissionsDir,
        storage_path('forms/'.PRODUCT_FORM_ROUND_TRIP_HANDLE),
    ];

    foreach ($dirs as $dir) {
        if ($dir !== '' && File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

it('renders the public form product fields through the form pipeline', function (): void {
    // Render the form the way the front-end does, through Statamic's
    // tag-aware Antlers view engine: the form:create tag sets up the field
    // scope (incl. each fieldtype's extraRenderableFieldData), and the
    // project's fields partial renders each field's template.
    $viewsDir = storage_path('framework/testing/views-'.uniqid('', true));
    File::ensureDirectoryExists($viewsDir);
    View::addNamespace('product_form_test', $viewsDir);
    File::put(
        $viewsDir.'/form.antlers.html',
        '{{ form:create handle="'.PRODUCT_FORM_ROUND_TRIP_HANDLE.'" }}{{ partial:vendor/statamic/forms/fields }}{{ /form:create }}'
    );

    $html = (string) View::make('product_form_test::form')->render();

    File::deleteDirectory($viewsDir);

    // Single product: base price (positive + negative) rendered, with a
    // quantity input and the hidden line-total input feeding the Total seam.
    expect($html)->toContain('$11,000.00');
    expect($html)->toContain('-$2,500.00');
    expect($html)->toContain('name="first_year_cost[quantity]"');
    expect($html)->toContain('name="first_year_cost[line_total]"');
    expect($html)->toContain('data-product-line-total-input');

    // Multiple choice: a priced option chooser.
    expect($html)->toContain('name="product_name[key]"');
    expect($html)->toContain('First Choice ($42.13)');
    expect($html)->toContain('Second Choice ($13.42)');
    expect($html)->toContain('Third Choice ($0.42)');
});

it('round-trips a submission with server-recomputed line totals', function (): void {
    $form = mustBe(Form::class, FormFacade::find(PRODUCT_FORM_ROUND_TRIP_HANDLE), 'form');

    // Submit the form over HTTP with the composite product payloads the
    // public template produces: `{handle}[quantity]` / `{handle}[key]`.
    // The hidden `line_total` is intentionally tampered to prove the server
    // ignores it and recomputes from config.
    $response = post('/!/forms/'.PRODUCT_FORM_ROUND_TRIP_HANDLE, [
        'first_year_cost' => ['quantity' => '2', 'line_total' => '999999'],
        'high_risk_ob_credit' => ['quantity' => '1'],
        'product_name' => ['key' => 'Second Choice', 'quantity' => '3'],
    ]);

    $response->assertRedirect();

    // Read the stored submission the fieldtype's process() produced.
    $submission = $form->submissions()->last();
    expect($submission)->not->toBeNull('A submission should have been stored.');
    $submission = mustBe(Submission::class, $submission, 'submission');
    $data = $submission->data();

    // Single product: 11000 × 2 = 22000, recomputed (not the tampered value).
    expect(data_get($data, 'first_year_cost.selection_type'))->toBe('single');
    expect(data_get($data, 'first_year_cost.quantity'))->toBe(2);
    expect(data_get($data, 'first_year_cost.line_total'))->toBe(22000.0);

    // Negative-price single product: -2500 × 1 = -2500.
    expect(data_get($data, 'high_risk_ob_credit.line_total'))->toBe(-2500.0);

    // Multiple choice: the selected option's price drives the line total —
    // 13.42 × 3 = 40.26.
    expect(data_get($data, 'product_name.selection_type'))->toBe('multiple');
    expect(data_get($data, 'product_name.key'))->toBe('Second Choice');
    expect(data_get($data, 'product_name.quantity'))->toBe(3);
    expect(data_get($data, 'product_name.line_total'))->toBe(40.26);
});
