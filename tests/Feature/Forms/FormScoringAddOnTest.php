<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Statamic\Facades\Blueprint;
use Statamic\Fields\BlueprintRepository;
use Tests\TestCase;

use function Pest\Laravel\post;
use function Pest\Laravel\withServerVariables;

/**
 * Proves quiz and survey forms still work on this site now that scoring, the
 * `quiz` and `survey` tags, and the `quiz` and `likert_scale` fieldtypes come from
 * the `uams-web/statamic-form-settings` add-on (#264).
 *
 * The behavior itself is tested in the add-on's own suite. These tests only prove
 * that the installed add-on works through this site's own form pipeline: the
 * submit route, the confirmation message, and the public form partial.
 *
 * @command  php artisan test --compact tests/Feature/Forms/FormScoringAddOnTest.php
 */
uses(TestCase::class);

$formScoringFormsDir = '';

beforeEach(function () use (&$formScoringFormsDir): void {
    // A unique IP per test keeps each submission in its own form-submit rate-limit bucket
    withServerVariables(['REMOTE_ADDR' => '203.0.'.random_int(0, 255).'.'.random_int(1, 254)]);

    // Point form definitions at a throwaway directory
    $formScoringFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($formScoringFormsDir);
    Config::set('statamic.forms.forms', $formScoringFormsDir);
});

afterEach(function () use (&$formScoringFormsDir): void {
    // Remove the throwaway forms directory
    if ($formScoringFormsDir !== '' && File::isDirectory($formScoringFormsDir)) {
        File::deleteDirectory($formScoringFormsDir);
    }
});

/**
 * Save a form with the given fields and a message confirmation.
 *
 * @param  array<string, array<string, mixed>>  $fields  The form's fields, by handle.
 * @param  string  $message  The confirmation message.
 */
function saveFormScoringForm(string $handle, array $fields, string $message = ''): void
{
    app(BlueprintRepository::class)->setFallback('forms.'.$handle, fn () => Blueprint::makeFromFields($fields));

    $form = makeForm($handle);
    $form->title('Scoring probe');
    $form->set('confirmation_settings_default', [
        'confirmation_type' => 'message',
        'confirmation_message' => $message,
    ]);
    $form->save();
}

/**
 * A quiz question with one correct answer.
 *
 * @return array<string, mixed>
 */
function formScoringQuizField(): array
{
    return [
        'type' => 'quiz',
        'display' => 'Capital of France?',
        'question_fieldtype' => 'radio',
        'options' => [
            ['label' => 'Paris', 'is_correct' => true],
            ['label' => 'London', 'is_correct' => false],
        ],
    ];
}

/**
 * A five-point satisfaction question.
 *
 * @return array<string, mixed>
 */
function formScoringLikertField(): array
{
    return [
        'type' => 'likert_scale',
        'display' => 'How satisfied are you?',
        'scale_type' => 'satisfaction',
        'number_of_points' => 5,
    ];
}

/**
 * Render a form through the partial the site's public pages use.
 */
function renderFormScoringForm(string $handle): string
{
    $viewsDir = storage_path('framework/testing/views-'.uniqid('', true));
    File::ensureDirectoryExists($viewsDir);
    View::addNamespace('form_scoring_test', $viewsDir);
    File::put(
        $viewsDir.'/form.antlers.html',
        '{{ form:create handle="'.$handle.'" }}{{ partial:vendor/statamic/forms/fields }}{{ /form:create }}',
    );

    $html = (string) View::make('form_scoring_test::form')->render();

    File::deleteDirectory($viewsDir);

    return $html;
}

it('scores a quiz submission and renders its results in the confirmation', function (): void {
    $handle = 'quiz_scoring_'.str_replace('.', '', uniqid('', true));
    saveFormScoringForm(
        $handle,
        ['q1' => formScoringQuizField()],
        'You scored {{ quiz:score }}/1 ({{ quiz:percent }}%). {{ quiz }}',
    );

    post('/!/forms/'.$handle, ['q1' => 'Paris'])->assertSessionHasNoErrors();

    $message = mustBeString(session('form.'.$handle.'.success'), 'confirmation message');
    expect($message)->toStartWith('You scored 1/1 (100%).')
        ->and($message)->toContain('Capital of France?')
        ->and($message)->toContain('Paris');
});

it('renders the results of a survey submission in the confirmation', function (): void {
    $handle = 'survey_scoring_'.str_replace('.', '', uniqid('', true));
    saveFormScoringForm(
        $handle,
        ['q1' => formScoringLikertField()],
        'Your survey score: {{ survey:score }}. {{ survey }}',
    );

    post('/!/forms/'.$handle, ['q1' => '4'])->assertSessionHasNoErrors();

    $message = mustBeString(session('form.'.$handle.'.success'), 'confirmation message');
    expect($message)->toStartWith('Your survey score: 4.')
        ->and($message)->toContain('How satisfied are you?');
});

it('renders the quiz and likert_scale fieldtypes on a public form', function (): void {
    $handle = 'scoring_public_'.str_replace('.', '', uniqid('', true));
    saveFormScoringForm($handle, [
        'q1' => formScoringQuizField(),
        'q2' => formScoringLikertField(),
    ]);

    $html = renderFormScoringForm($handle);

    // Each question's own controls, and no answer key on the page
    expect($html)->toContain('name="q1"')
        ->and($html)->toContain('Paris')
        ->and($html)->toContain('London')
        ->and($html)->toContain('name="q2"')
        ->and($html)->not->toContain('is_correct');
});
