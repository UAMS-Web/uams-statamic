<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Support\Forms\ChoiceSubmissionLimits;
use Illuminate\Validation\ValidationException;
use Statamic\Events\FormSubmitted;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Forms\Form;
use UAMSWeb\FormSettings\Listeners\EnforceFormRestrictions;

/**
 * Rejects a form submission server-side when it selects a choice that has reached its per-choice
 * submission cap (UAMS-Web/uams-statamic-archive#292).
 *
 * `Statamic\Events\FormSubmitted` fires inside `FormController::submit()` before the submission is
 * stored, and the controller catches `ValidationException` — so throwing here rejects the POST and
 * surfaces the message through the form's normal error display, enforcing the cap even against a
 * direct POST that bypassed the disabled choice in the rendered form (the race-safe re-check).
 *
 * The decision is delegated to {@see ChoiceSubmissionLimits}, the same source of truth the choice
 * templates use to disable full options at render time, so the gate and the UI never disagree.
 * Registered in `EventServiceProvider::$listen` (UAMS-Web/uams-statamic-archive#3240). The form-level restrictions gate,
 * {@see EnforceFormRestrictions}, listens for the same event but comes from the form settings
 * add-on, which registers it itself.
 */
final class EnforceChoiceSubmissionLimits
{
    public function __construct(private readonly ChoiceSubmissionLimits $limits = new ChoiceSubmissionLimits) {}

    /**
     * Reject the submission when any selected choice is already full.
     *
     * @param  FormSubmitted  $event  The form-submitted event, carrying the submission.
     *
     * @throws ValidationException When a selected choice has reached its cap.
     */
    public function handle(FormSubmitted $event): void
    {
        $form = $event->submission->form();

        if (! $form instanceof Form) {
            return;
        }

        $blueprint = $form->blueprint();

        if (! $blueprint instanceof Blueprint) {
            return;
        }

        $errors = [];

        foreach ($blueprint->fields()->all() as $field) {
            if (! $field instanceof Field) {
                continue;
            }

            if (! $this->limits->enabledFor($field)) {
                continue;
            }

            $handle = $field->handle();

            if (! \is_string($handle)) {
                continue;
            }

            if ($this->limits->selectionHitsFullChoice($form, $field, $event->submission->get($handle))) {
                // Keyed by the field handle so the message renders against that field.
                $errors[$handle] = [$this->message($field)];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * The closed-choice message: a field-configured override, else a sensible default.
     */
    private function message(Field $field): string
    {
        $message = $field->get('choice_limit_message');

        return \is_scalar($message) && trim((string) $message) !== ''
            ? trim((string) $message)
            : __('One or more of your selections is no longer available. Please choose another option.');
    }
}
