<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Form as FormFacade;
use Statamic\Facades\Stache;
use Statamic\Fields\BlueprintRepository;
use Statamic\Forms\Form;
use Statamic\Forms\Submission;
use Statamic\Stache\Stores\Store;
use Tests\TestCase;

use function Pest\Laravel\post;
use function Pest\Laravel\withServerVariables;

uses(TestCase::class);

/**
 * End-to-end coverage for the public-form Replicator (UAMS-Web/uams-statamic-archive#675): render a form with a replicator field
 * through the real `form:create` pipeline (which calls
 * App\Fieldtypes\Replicator::preProcessTagRenderable), and submit the nested payload the template's
 * Alpine engine produces to prove the round-trip and the min-sets / at-least-one-enabled rules.
 *
 * @command php artisan test --compact tests/Feature/Forms/ReplicatorFormRoundTripTest.php
 */
const REPLICATOR_FORM_HANDLE = 'referrals';

$replicatorFormsDir = '';
$replicatorSubsDir = '';

/**
 * Register the form + its replicator blueprint, optionally required with a min-sets floor.
 *
 * @param  array<int, string>  $validate
 */
function makeReplicatorForm(array $validate = [], ?int $minSets = null): void
{
    $config = [
        'type' => 'replicator',
        'display' => 'Referrals',
        'validate' => $validate,
        'sets' => [
            'main' => [
                'sets' => [
                    'contact' => ['display' => 'Contact', 'fields' => [
                        ['handle' => 'name', 'field' => ['type' => 'text', 'display' => 'Name']],
                        ['handle' => 'email', 'field' => ['type' => 'text', 'display' => 'Email']],
                    ]],
                    'note' => ['display' => 'Note', 'fields' => [
                        ['handle' => 'body', 'field' => ['type' => 'textarea', 'display' => 'Body']],
                    ]],
                ],
            ],
        ],
    ];

    if ($minSets !== null) {
        $config['min_sets'] = $minSets;
    }

    app(BlueprintRepository::class)->setFallback('forms.'.REPLICATOR_FORM_HANDLE, fn () => Blueprint::makeFromFields([
        'referrals' => $config,
    ]));

    $form = makeForm(REPLICATOR_FORM_HANDLE);
    $form->title('Referrals');
    $form->save();
}

/** Render the form through the real form:create pipeline and return its HTML. */
function renderReplicatorForm(): string
{
    $viewsDir = storage_path('framework/testing/views-'.uniqid('', true));
    File::ensureDirectoryExists($viewsDir);
    View::addNamespace('replicator_form_test', $viewsDir);
    File::put(
        $viewsDir.'/form.antlers.html',
        '{{ form:create handle="'.REPLICATOR_FORM_HANDLE.'" }}{{ partial:vendor/statamic/forms/fields }}{{ /form:create }}'
    );

    $html = (string) View::make('replicator_form_test::form')->render();

    File::deleteDirectory($viewsDir);

    return $html;
}

/** Count stored submission files, bypassing the Stache cache. */
function replicatorStoredCount(): int
{
    $base = Config::get('statamic.forms.submissions');
    $dir = (is_string($base) ? $base : '').'/'.REPLICATOR_FORM_HANDLE;

    return File::isDirectory($dir) ? count(File::files($dir)) : 0;
}

beforeEach(function () use (&$replicatorFormsDir, &$replicatorSubsDir): void {
    withServerVariables(['REMOTE_ADDR' => '198.51.'.random_int(0, 255).'.'.random_int(1, 254)]);

    $replicatorFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($replicatorFormsDir);
    Config::set('statamic.forms.forms', $replicatorFormsDir);

    $replicatorSubsDir = storage_path('framework/testing/subs-'.uniqid('', true));
    File::ensureDirectoryExists($replicatorSubsDir);
    Config::set('statamic.forms.submissions', $replicatorSubsDir);
    /** @var Store $submissionsStore */
    $submissionsStore = Stache::store('form-submissions');
    $submissionsStore->directory($replicatorSubsDir);
});

afterEach(function () use (&$replicatorFormsDir, &$replicatorSubsDir): void {
    foreach ([$replicatorFormsDir, $replicatorSubsDir, storage_path('forms/'.REPLICATOR_FORM_HANDLE)] as $dir) {
        if ($dir !== '' && File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

it('renders a blank set template per set type with placeholder-indexed subfield names', function (): void {
    makeReplicatorForm();
    $html = renderReplicatorForm();

    // A cloneable <template> per set type, driven by the Alpine engine.
    expect($html)->toContain('data-replicator-template="contact"');
    expect($html)->toContain('data-replicator-template="note"');

    // Subfields render at the placeholder index the engine rewrites on Add.
    expect($html)->toContain('name="referrals[REPLICATORSETINDEX][name]"');
    expect($html)->toContain('name="referrals[REPLICATORSETINDEX][email]"');
    expect($html)->toContain('name="referrals[REPLICATORSETINDEX][body]"');

    // Per-set type + enabled inputs (paired hidden 0 + checkbox 1).
    expect($html)->toContain('name="referrals[REPLICATORSETINDEX][type]"');
    expect($html)->toContain('name="referrals[REPLICATORSETINDEX][enabled]"');

    // An "Add Set" control per type + the Alpine engine binding. The controls are identified by
    // `data-replicator-add-type` and driven by a delegated listener; they used to carry
    // `@click="addSet('<type>')"`, which the CSP build never bound, so clicking them did nothing
    // (UAMS-Web/uams-statamic-archive#2056). Asserting the data attribute pins the mechanism that actually works.
    expect($html)->toContain('data-replicator-add-type="contact"');
    expect($html)->toContain('data-replicator-add-type="note"');
    expect($html)->not->toContain('@click');
    expect($html)->toContain('x-data="replicatorField()"');
});

it('round-trips a multi-set submission, coercing enabled to booleans', function (): void {
    makeReplicatorForm();

    // The payload the reindexed Alpine engine posts: sequential rows, each with flat subfields.
    post('/!/forms/'.REPLICATOR_FORM_HANDLE, [
        'referrals' => [
            ['type' => 'contact', 'enabled' => '1', 'name' => 'Dr. Ada', 'email' => 'ada@uams.edu'],
            ['type' => 'note', 'enabled' => '0', 'body' => 'Follow up next week'],
        ],
    ])->assertRedirect();

    expect(replicatorStoredCount())->toBe(1);

    $form = mustBe(Form::class, FormFacade::find(REPLICATOR_FORM_HANDLE), 'form');
    $submission = mustBe(Submission::class, $form->submissions()->last(), 'submission');
    $rows = $submission->data()['referrals'];

    expect($rows)->toHaveCount(2);
    expect(data_get($rows, '0.type'))->toBe('contact');
    expect(data_get($rows, '0.name'))->toBe('Dr. Ada');
    expect(data_get($rows, '0.email'))->toBe('ada@uams.edu');
    expect(data_get($rows, '0.enabled'))->toBeTrue();
    expect(data_get($rows, '1.type'))->toBe('note');
    expect(data_get($rows, '1.body'))->toBe('Follow up next week');
    // The toggled-off set persists as a real boolean false, not the '0' string.
    expect(data_get($rows, '1.enabled'))->toBeFalse();
});

it('rejects a submission below the minimum sets when the field is required', function (): void {
    makeReplicatorForm(validate: ['required'], minSets: 2);

    // Only one set, below the min of 2 → rejected, not stored.
    post('/!/forms/'.REPLICATOR_FORM_HANDLE, [
        'referrals' => [
            ['type' => 'contact', 'enabled' => '1', 'name' => 'Solo', 'email' => 'solo@uams.edu'],
        ],
    ])->assertSessionHasErrors('referrals', null, 'form.'.REPLICATOR_FORM_HANDLE);

    expect(replicatorStoredCount())->toBe(0);
});

it('rejects a required submission whose only sets are disabled', function (): void {
    makeReplicatorForm(validate: ['required']);

    // Two sets, both toggled off → no enabled set → rejected.
    post('/!/forms/'.REPLICATOR_FORM_HANDLE, [
        'referrals' => [
            ['type' => 'contact', 'enabled' => '0', 'name' => 'A', 'email' => 'a@uams.edu'],
            ['type' => 'note', 'enabled' => '0', 'body' => 'b'],
        ],
    ])->assertSessionHasErrors('referrals', null, 'form.'.REPLICATOR_FORM_HANDLE);

    expect(replicatorStoredCount())->toBe(0);
});

it('accepts a required submission with at least one enabled set', function (): void {
    makeReplicatorForm(validate: ['required']);

    post('/!/forms/'.REPLICATOR_FORM_HANDLE, [
        'referrals' => [
            ['type' => 'contact', 'enabled' => '1', 'name' => 'A', 'email' => 'a@uams.edu'],
            ['type' => 'note', 'enabled' => '0', 'body' => 'b'],
        ],
    ])->assertRedirect();

    expect(replicatorStoredCount())->toBe(1);
});

it('renders existing-value rows at concrete indices with their subfield values', function (): void {
    makeReplicatorForm();

    // A form-field template test rendering the same collection the re-render path uses (old input
    // and a bound value share the row-building branch). The disabled set's `enabled` is coerced to
    // a boolean so its checkbox is not re-checked — see ReplicatorFieldtypeTest for the coercion
    // datasets; the old-input source of the same branch is exercised end to end by a real POST above.
    $html = (string) Antlers::parse(
        File::get(resource_path('views/vendor/statamic/forms/fields/replicator.antlers.html')),
        [
            'handle' => 'referrals',
            'replicator_index_token' => 'REPLICATORSETINDEX',
            'replicator_min_sets' => 0,
            'replicator_set_templates' => [],
            'replicator_rows' => [
                ['type' => 'contact', 'enabled' => true, 'index' => 0, 'fields' => []],
                ['type' => 'note', 'enabled' => false, 'index' => 1, 'fields' => []],
            ],
        ],
        true,
    );

    // The enabled set's checkbox is checked; the disabled set's is NOT.
    expect($html)->toContain('name="referrals[0][enabled]" value="1" checked');
    expect($html)->not->toContain('name="referrals[1][enabled]" value="1" checked');
    // Each row carries its type + concrete-index inputs.
    expect($html)->toContain('name="referrals[0][type]"');
    expect($html)->toContain('name="referrals[1][type]"');
});
