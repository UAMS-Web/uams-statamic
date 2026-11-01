<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Assert;
use Statamic\Auth\File\User as FileStatamicUser;
use Statamic\Facades\Collection;
use Statamic\Facades\Form as FormFacade;
use Statamic\Facades\User;
use Statamic\Forms\Form;
use Tests\Support\StacheIsolatedTestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\withoutMiddleware;
use function Pest\Laravel\withoutVite;

/**
 * Proves the Control Panel form settings screen, now served by the
 * `uams-web/statamic-form-settings` add-on, saves each setting and shows it again
 * when the form is reopened (#263).
 *
 * One setting is taken from each area the add-on adds: notifications, the
 * confirmation, and restrictions. The test goes through the real Control Panel
 * routes, so it also proves the add-on's controller is the one serving them.
 *
 * @command  php artisan test --compact tests/Feature/CP/Forms/FormSettingsAddOnControlPanelTest.php
 */
uses(StacheIsolatedTestCase::class)->group('serial');

/**
 * Per-test forms directory, so the throwaway form never touches resources/forms.
 *
 * @var string Filesystem path to the per-test forms directory.
 */
$cpFormsDir = '';

beforeEach(function () use (&$cpFormsDir): void {
    // Point form definitions at a throwaway directory
    $cpFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($cpFormsDir);
    Config::set('statamic.forms.forms', $cpFormsDir);

    // Post without a CSRF token and render without built assets
    withoutMiddleware(ValidateCsrfToken::class);
    withoutVite();
});

afterEach(function () use (&$cpFormsDir): void {
    // Remove the throwaway forms directory
    if ($cpFormsDir !== '' && File::isDirectory($cpFormsDir)) {
        File::deleteDirectory($cpFormsDir);
    }
});

/**
 * Persist a super user, who holds every permission.
 */
function formSettingsAddOnCpSuperUser(): FileStatamicUser
{
    $user = User::make();
    Assert::assertInstanceOf(FileStatamicUser::class, $user);

    $user->id((string) Str::uuid());
    $user->email('form-settings-super@example.test');
    $user->makeSuper();
    $user->save();

    return $user;
}

/**
 * Resolve a Control Panel route to its URL string.
 *
 * @param  string  $route  Route name under `statamic.cp.`.
 * @param  array<string, mixed>  $params  Route parameters.
 */
function formSettingsAddOnCpRoute(string $route, array $params = []): string
{
    /** @var string $url */
    $url = cp_route($route, $params);

    return $url;
}

it('saves each form setting and shows it again when the form is reopened', function (): void {
    // Create the form the settings screen edits
    $form = makeForm('form_settings_cp_probe');
    $form->title('Settings probe');
    $form->save();

    actingAs(formSettingsAddOnCpSuperUser());

    // Save one setting from each area the add-on adds
    patchJson(formSettingsAddOnCpRoute('forms.update', ['form' => 'form_settings_cp_probe']), [
        'title' => 'Settings probe',
        'submit' => 'Send it',
        'email' => [[
            'name' => 'Team notice',
            'to_type' => 'email',
            'to' => 'team@example.test',
            'cc_type' => 'email',
            'bcc_type' => 'email',
            'reply_to_type' => 'email',
            'subject' => 'New submission',
            'message' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'A form was submitted.']],
            ]],
        ]],
        'confirmation_settings_default' => [
            'confirmation_type' => 'message',
            // A Bard field, so it is sent as the editor's document
            'confirmation_message' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'Thanks for writing.']],
            ]],
        ],
        'limit_entries' => true,
        'limit_entries_count' => 3,
        'limit_entries_period' => 'total',
        'limit_entries_message' => 'This form is full.',
        'require_login_query' => true,
        'require_login_message' => 'Please log in to submit.',
    ])->assertSuccessful();

    // Read the saved form back from disk
    $saved = FormFacade::find('form_settings_cp_probe');
    Assert::assertInstanceOf(Form::class, $saved);

    expect($saved->get('submit'))->toBe('Send it')
        ->and($saved->get('limit_entries'))->toBeTrue()
        ->and($saved->get('limit_entries_count'))->toBe(3)
        ->and($saved->get('limit_entries_message'))->toBe('This form is full.')
        ->and($saved->get('require_login_query'))->toBeTrue()
        ->and($saved->get('require_login_message'))->toBe('Please log in to submit.');

    $confirmation = mustBeArray($saved->get('confirmation_settings_default'), 'confirmation');
    expect($confirmation['confirmation_type'] ?? null)->toBe('message')
        ->and(json_encode($confirmation['confirmation_message'] ?? null))->toContain('Thanks for writing.');

    $email = mustBeArray($saved->email(), 'email');
    $firstEmail = mustBeArray($email[0] ?? null, 'first notification');
    expect($firstEmail['to'] ?? null)->toBe('team@example.test');

    // The confirmation's page picker lists the `pages` collection, which the
    // isolated Stache starts without
    Collection::make('pages')->save();

    // Reopen the settings screen and find the saved values in it
    get(formSettingsAddOnCpRoute('forms.edit', ['form' => 'form_settings_cp_probe']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('values.submit', 'Send it')
            ->where('values.limit_entries_count', 3)
            ->where('values.require_login_message', 'Please log in to submit.')
            ->where('values.confirmation_settings_default.confirmation_message', fn (mixed $message): bool => str_contains((string) json_encode($message), 'Thanks for writing.'))
            ->where('values.email.0.to', 'team@example.test')
            ->etc());
});

it('offers the quiz settings on the form settings screen while scoring is on', function (): void {
    $form = makeForm('form_settings_quiz_probe');
    $form->title('Quiz probe');
    $form->save();

    actingAs(formSettingsAddOnCpSuperUser());

    // The confirmation's page picker lists the `pages` collection, which the
    // isolated Stache starts without
    Collection::make('pages')->save();

    get(formSettingsAddOnCpRoute('forms.edit', ['form' => 'form_settings_quiz_probe']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('blueprint', fn (mixed $blueprint): bool => str_contains((string) json_encode($blueprint), '"quiz_grading"'))
            ->etc());
});
