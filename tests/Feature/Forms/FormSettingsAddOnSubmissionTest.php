<?php

declare(strict_types=1);

use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Stache;
use Statamic\Fields\BlueprintRepository;
use Statamic\Forms\Form;
use Statamic\Stache\Stores\Store;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

use function Pest\Laravel\post;
use function Pest\Laravel\withServerVariables;

/**
 * Proves the form settings behaviors this site relies on still hold now that they
 * come from the `uams-web/statamic-form-settings` add-on (#3072).
 *
 * Each test submits a real form over HTTP on the sync queue connection, so the
 * add-on's notification job, confirmation middleware and restriction listener all
 * run as they do on a live submission. The add-on's own suite covers each class;
 * this file covers the site's wiring of them.
 *
 * @command  php artisan test --compact tests/Feature/Forms/FormSettingsAddOnSubmissionTest.php
 */
uses(TestCase::class);

/**
 * Per-test forms directory, so the throwaway form never touches resources/forms.
 *
 * @var string Filesystem path to the per-test forms directory.
 */
$addOnFormsDir = '';

/**
 * Per-test submissions directory, so the entry-limit count starts at zero.
 *
 * @var string Filesystem path to the per-test submissions directory.
 */
$addOnSubsDir = '';

/**
 * Unique form handle for the current test.
 *
 * @var string Handle of the throwaway form.
 */
$addOnHandle = '';

beforeEach(function () use (&$addOnFormsDir, &$addOnSubsDir, &$addOnHandle): void {
    // Give each test its own form-submit rate-limit bucket
    withServerVariables(['REMOTE_ADDR' => '198.51.'.random_int(0, 255).'.'.random_int(1, 254)]);

    // Name the throwaway form uniquely so no sibling test's submissions are counted
    $addOnHandle = 'form_settings_addon_'.str_replace('.', '', uniqid('', true));

    // Point form definitions at a throwaway directory
    $addOnFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($addOnFormsDir);
    Config::set('statamic.forms.forms', $addOnFormsDir);

    // Point stored submissions at a throwaway directory
    $addOnSubsDir = storage_path('framework/testing/subs-'.uniqid('', true));
    File::ensureDirectoryExists($addOnSubsDir);
    Config::set('statamic.forms.submissions', $addOnSubsDir);
    /** @var Store $submissionsStore */
    $submissionsStore = Stache::store('form-submissions');
    $submissionsStore->directory($addOnSubsDir);

    // Give the form a blueprint so submitted values are stored and augmented
    app(BlueprintRepository::class)->setFallback('forms.'.$addOnHandle, fn () => Blueprint::makeFromFields([
        'name' => ['type' => 'text', 'display' => 'Name'],
        'department' => ['type' => 'text', 'display' => 'Department'],
    ]));
});

afterEach(function () use (&$addOnFormsDir, &$addOnSubsDir): void {
    // Remove both throwaway directories
    foreach ([$addOnFormsDir, $addOnSubsDir] as $dir) {
        if ($dir !== '' && File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

/**
 * Build and save the throwaway form with the given settings.
 *
 * @param  string  $handle  Handle of the throwaway form.
 * @param  bool  $store  Whether submissions are stored.
 * @param  array<string, mixed>  $settings  Form settings to set before saving.
 */
function formSettingsAddOnForm(string $handle, bool $store, array $settings): Form
{
    // Make the form and choose whether it stores submissions
    $form = makeForm($handle);
    $form->store($store);

    // Apply each setting, routing `email` through its own accessor
    foreach ($settings as $key => $value) {
        if ($key === 'email') {
            $form->email($value);

            continue;
        }

        $form->set($key, $value);
    }

    $form->save();

    return $form;
}

/**
 * The lowercase TO addresses of every captured notification email.
 *
 * @return list<string>
 */
function formSettingsAddOnSentTo(): array
{
    $transport = mustBe(ArrayTransport::class, Mail::getSymfonyTransport(), 'transport');

    return array_values($transport->messages()
        ->flatMap(function ($sent): array {
            $sent = mustBe(SentMessage::class, $sent, 'sent');
            $message = mustBe(Email::class, $sent->getOriginalMessage(), 'message');

            return array_map(static fn ($address): string => strtolower($address->getAddress()), $message->getTo());
        })
        ->all());
}

/**
 * Count the submission files written for a form, bypassing the Stache cache.
 *
 * @param  string  $handle  Handle of the throwaway form.
 */
function formSettingsAddOnStoredCount(string $handle): int
{
    $base = Config::get('statamic.forms.submissions');
    $dir = (is_string($base) ? $base : '').'/'.$handle;

    return File::isDirectory($dir) ? count(File::files($dir)) : 0;
}

it('sends a notification to the recipient its email routing selects', function () use (&$addOnHandle): void {
    formSettingsAddOnForm($addOnHandle, false, [
        'email' => [[
            'to_type' => 'routing',
            'to_routing' => [
                ['field' => 'department', 'operator' => 'equals', 'value' => 'cardiology', 'email' => 'cardio@example.test'],
                ['field' => 'department', 'operator' => 'equals', 'value' => 'oncology', 'email' => 'onco@example.test'],
            ],
            'subject' => 'Routed',
        ]],
    ]);

    post('/!/forms/'.$addOnHandle, ['name' => 'Pat', 'department' => 'oncology'])->assertRedirect();

    expect(formSettingsAddOnSentTo())->toBe(['onco@example.test']);
});

it('sends a conditional notification only when its condition holds', function (string $department, array $expected) use (&$addOnHandle): void {
    formSettingsAddOnForm($addOnHandle, false, [
        'email' => [
            [
                'to' => 'cardiology@example.test',
                'subject' => 'Cardiology',
                'conditional_logic' => ['if' => ['department' => 'cardiology'], 'always_save' => false],
            ],
            [
                'to' => 'always@example.test',
                'subject' => 'Always',
            ],
        ],
    ]);

    post('/!/forms/'.$addOnHandle, ['name' => 'Pat', 'department' => $department])->assertRedirect();

    expect(formSettingsAddOnSentTo())->toEqualCanonicalizing($expected);
})->with([
    'condition holds' => ['cardiology', ['cardiology@example.test', 'always@example.test']],
    'condition fails' => ['oncology', ['always@example.test']],
]);

it('shows a message confirmation in place of the default success text', function () use (&$addOnHandle): void {
    formSettingsAddOnForm($addOnHandle, false, [
        'confirmation_settings_default' => [
            'confirmation_type' => 'message',
            'confirmation_message' => 'Thank you, {{ name }}!',
        ],
    ]);

    post('/!/forms/'.$addOnHandle, ['name' => 'Pat'])
        ->assertRedirect()
        ->assertSessionHas('form.'.$addOnHandle.'.success', 'Thank you, Pat!');
});

it('follows a redirect confirmation to its URL', function () use (&$addOnHandle): void {
    formSettingsAddOnForm($addOnHandle, false, [
        'confirmation_settings_default' => [
            'confirmation_type' => 'redirect',
            'confirmation_redirect_url' => '/thank-you',
            'confirmation_query_string' => 'ref={{ name }}',
        ],
    ]);

    post('/!/forms/'.$addOnHandle, ['name' => 'Pat'])->assertRedirect('/thank-you?ref=Pat');
});

it('refuses a submission once the entry limit is reached', function () use (&$addOnHandle): void {
    formSettingsAddOnForm($addOnHandle, true, [
        'limit_entries' => true,
        'limit_entries_count' => 1,
        'limit_entries_period' => 'total',
        'limit_entries_message' => 'This form is full.',
    ]);

    // The first submission is within the limit and is stored
    post('/!/forms/'.$addOnHandle, ['name' => 'First'])->assertRedirect();
    expect(formSettingsAddOnStoredCount($addOnHandle))->toBe(1);

    // The second reaches the limit, so it is refused with the message and not stored
    post('/!/forms/'.$addOnHandle, ['name' => 'Second'])
        ->assertSessionHasErrors([$addOnHandle => 'This form is full.'], null, 'form.'.$addOnHandle);
    expect(formSettingsAddOnStoredCount($addOnHandle))->toBe(1);
});

it('refuses an anonymous submission when login is required', function () use (&$addOnHandle): void {
    formSettingsAddOnForm($addOnHandle, true, [
        'require_login_query' => true,
        'require_login_message' => 'Please log in to submit.',
    ]);

    post('/!/forms/'.$addOnHandle, ['name' => 'Guest'])
        ->assertSessionHasErrors([$addOnHandle => 'Please log in to submit.'], null, 'form.'.$addOnHandle);

    expect(formSettingsAddOnStoredCount($addOnHandle))->toBe(0);
});
