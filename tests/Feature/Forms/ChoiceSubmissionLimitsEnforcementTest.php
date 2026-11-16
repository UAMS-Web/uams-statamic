<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Stache;
use Statamic\Fields\BlueprintRepository;
use Statamic\Forms\Form;
use Statamic\Stache\Stores\Store;
use Tests\TestCase;

use function Pest\Laravel\post;
use function Pest\Laravel\withServerVariables;

/**
 * Proves per-choice submission limits are enforced server-side at submit time (UAMS-Web/uams-statamic-archive#292): a POST
 * selecting a choice that has reached its cap is rejected (not stored), with the closed-choice
 * message — even against a direct POST that bypassed the disabled option in the rendered form.
 * An unlimited choice on the same field, and any choice below its cap, still submit.
 *
 * Each test uses a unique form handle so the live submission count never sees a sibling test's
 * submissions in the shared store.
 *
 * @covers \App\Listeners\EnforceChoiceSubmissionLimits
 * @covers \App\Support\Forms\ChoiceSubmissionLimits
 *
 * @command  php artisan test --compact tests/Feature/Forms/ChoiceSubmissionLimitsEnforcementTest.php
 */
uses(TestCase::class);

$choiceLimitFormsDir = '';
$choiceLimitSubsDir = '';
$choiceLimitHandle = '';

beforeEach(function () use (&$choiceLimitFormsDir, &$choiceLimitSubsDir, &$choiceLimitHandle): void {
    // Unique IP per test → each test's posts get their own form-submit rate-limit bucket.
    withServerVariables(['REMOTE_ADDR' => '198.51.'.random_int(0, 255).'.'.random_int(1, 254)]);

    $choiceLimitHandle = 'choice_limit_probe_'.str_replace('.', '', uniqid('', true));

    $choiceLimitFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($choiceLimitFormsDir);
    Config::set('statamic.forms.forms', $choiceLimitFormsDir);

    $choiceLimitSubsDir = storage_path('framework/testing/subs-'.uniqid('', true));
    File::ensureDirectoryExists($choiceLimitSubsDir);
    Config::set('statamic.forms.submissions', $choiceLimitSubsDir);
    /** @var Store $submissionsStore */
    $submissionsStore = Stache::store('form-submissions');
    $submissionsStore->directory($choiceLimitSubsDir);

    // A select field capped at one submission for "morning" ("evening" unlimited), plus a
    // checkboxes field capped at one for "am" to exercise the array-selection path.
    app(BlueprintRepository::class)->setFallback('forms.'.$choiceLimitHandle, fn () => Blueprint::makeFromFields([
        'slot' => [
            'type' => 'select',
            'display' => 'Slot',
            'options' => ['morning' => 'Morning', 'evening' => 'Evening'],
            'enable_choice_limits' => true,
            'choice_limits' => [
                ['choice' => 'morning', 'limit' => 1],
            ],
            'choice_limit_message' => 'That slot is full.',
        ],
        'shifts' => [
            'type' => 'checkboxes',
            'display' => 'Shifts',
            'options' => ['am' => 'AM', 'pm' => 'PM'],
            'enable_choice_limits' => true,
            'choice_limits' => [
                ['choice' => 'am', 'limit' => 1],
            ],
            'choice_limit_message' => 'That shift is full.',
        ],
    ]));
});

afterEach(function () use (&$choiceLimitFormsDir, &$choiceLimitSubsDir): void {
    foreach ([$choiceLimitFormsDir, $choiceLimitSubsDir] as $dir) {
        if ($dir !== '' && File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

/**
 * Count the submission YAML files actually written for the handle, bypassing the Stache cache.
 */
function choiceLimitStoredCount(string $handle): int
{
    $base = Config::get('statamic.forms.submissions');
    $dir = (is_string($base) ? $base : '').'/'.$handle;

    return File::isDirectory($dir) ? count(File::files($dir)) : 0;
}

it('allows the first submission of a capped choice, then rejects the next server-side', function () use (&$choiceLimitHandle): void {
    $form = makeForm($choiceLimitHandle);
    $form->store(true);
    $form->save();

    // First "morning" is within the cap of 1 → stored.
    post('/!/forms/'.$choiceLimitHandle, ['slot' => 'morning'])->assertRedirect();
    expect(choiceLimitStoredCount($choiceLimitHandle))->toBe(1);

    // Second "morning" hits the cap → rejected with the message, not stored.
    post('/!/forms/'.$choiceLimitHandle, ['slot' => 'morning'])
        ->assertSessionHasErrors(['slot' => 'That slot is full.'], null, 'form.'.$choiceLimitHandle);
    expect(choiceLimitStoredCount($choiceLimitHandle))->toBe(1);
});

it('still accepts an uncapped choice on the same field after another choice is full', function () use (&$choiceLimitHandle): void {
    $form = makeForm($choiceLimitHandle);
    $form->store(true);
    $form->save();

    // Fill "morning".
    post('/!/forms/'.$choiceLimitHandle, ['slot' => 'morning'])->assertRedirect();

    // "evening" has no configured limit → still accepted.
    post('/!/forms/'.$choiceLimitHandle, ['slot' => 'evening'])->assertRedirect();
    expect(choiceLimitStoredCount($choiceLimitHandle))->toBe(2);
});

it('rejects a checkboxes submission whose array selection includes a full choice', function () use (&$choiceLimitHandle): void {
    $form = makeForm($choiceLimitHandle);
    $form->store(true);
    $form->save();

    // First submission fills the "am" shift (array selection).
    post('/!/forms/'.$choiceLimitHandle, ['shifts' => ['am']])->assertRedirect();
    expect(choiceLimitStoredCount($choiceLimitHandle))->toBe(1);

    // A later array selection that includes the full "am" is rejected, even alongside a free "pm".
    post('/!/forms/'.$choiceLimitHandle, ['shifts' => ['am', 'pm']])
        ->assertSessionHasErrors(['shifts' => 'That shift is full.'], null, 'form.'.$choiceLimitHandle);
    expect(choiceLimitStoredCount($choiceLimitHandle))->toBe(1);

    // Selecting only the free "pm" still succeeds.
    post('/!/forms/'.$choiceLimitHandle, ['shifts' => ['pm']])->assertRedirect();
    expect(choiceLimitStoredCount($choiceLimitHandle))->toBe(2);
});

it('rejects a direct POST of a full choice that bypassed the disabled option', function () use (&$choiceLimitHandle): void {
    $form = makeForm($choiceLimitHandle);
    $form->store(true);
    $form->save();

    // Pre-existing submission fills the "morning" cap.
    $existing = $form->makeSubmission();
    $existing->data(['slot' => 'morning']);
    $existing->save();

    // A direct POST re-selecting the full choice is still rejected server-side.
    post('/!/forms/'.$choiceLimitHandle, ['slot' => 'morning'])
        ->assertSessionHasErrors(['slot' => 'That slot is full.'], null, 'form.'.$choiceLimitHandle);
    expect(choiceLimitStoredCount($choiceLimitHandle))->toBe(1);
});

it('reports the form\'s restriction when the restriction and a full choice both refuse', function () use (&$choiceLimitHandle): void {
    $form = makeForm($choiceLimitHandle);
    $form->store(true);
    $form->save();

    // Fill the capped choice while the form is still open
    post('/!/forms/'.$choiceLimitHandle, ['slot' => 'morning'])->assertRedirect();

    // Close the form to guests, so the next "morning" is refused twice over
    $form->merge(['require_login_query' => true, 'require_login_message' => 'Sign in first.']);
    $form->save();

    // The add-on's listener runs first, so the reader sees the restriction, not the full choice
    post('/!/forms/'.$choiceLimitHandle, ['slot' => 'morning'])
        ->assertSessionHasErrors([$choiceLimitHandle => 'Sign in first.'], null, 'form.'.$choiceLimitHandle)
        ->assertSessionDoesntHaveErrors(['slot'], null, 'form.'.$choiceLimitHandle);
    expect(choiceLimitStoredCount($choiceLimitHandle))->toBe(1);
});
