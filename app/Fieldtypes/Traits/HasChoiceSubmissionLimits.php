<?php

declare(strict_types=1);

namespace App\Fieldtypes\Traits;

use App\Listeners\EnforceChoiceSubmissionLimits;
use App\Support\Forms\ChoiceSubmissionLimits;
use Statamic\Fields\Field;
use Statamic\Forms\Form;

/**
 * Adds per-choice submission limits to a choice fieldtype (radio / checkboxes / select) — the
 * Statamic analog of the Gravity Forms per-choice "Limit Submissions" perk (UAMS-Web/uams-statamic-archive#292, approach A).
 *
 * Provides the additive blueprint config ({@see self::choiceSubmissionLimitConfigField()}) that
 * leaves the field's flat options map untouched, and merges the currently-full option values into
 * the field's renderable data as `full_choices` so the choice template can disable + flag them.
 * Server-side enforcement of the same full set lives in {@see EnforceChoiceSubmissionLimits}.
 */
trait HasChoiceSubmissionLimits
{
    /**
     * The additive per-choice-limit configuration fields, injected into a choice fieldtype's config.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function choiceSubmissionLimitConfigField(): array
    {
        return [
            'enable_choice_limits' => [
                'display' => __('Limit Submissions Per Choice'),
                'instructions' => __('Cap how many submissions may select each choice. A choice becomes unavailable once its limit is reached.'),
                'type' => 'toggle',
                'default' => false,
            ],
            'choice_limits' => [
                'display' => __('Per-Choice Limits'),
                'instructions' => __('Add a row per capped choice. The "Choice Value" is the option key; leave a choice out to keep it unlimited.'),
                'type' => 'grid',
                'mode' => 'stacked',
                'add_row' => __('Add Limit'),
                'if' => ['enable_choice_limits' => 'equals true'],
                'fields' => [
                    [
                        'handle' => 'choice',
                        'field' => [
                            'type' => 'text',
                            'display' => __('Choice Value'),
                            'instructions' => __('The option value (key) to cap.'),
                            'validate' => ['required'],
                        ],
                    ],
                    [
                        'handle' => 'limit',
                        'field' => [
                            'type' => 'integer',
                            'display' => __('Limit'),
                            'min' => 1,
                            'validate' => ['required'],
                        ],
                    ],
                ],
            ],
            'choice_limit_message' => [
                'display' => __('Full-Choice Message'),
                'instructions' => __('Shown when a submission selects a choice that has reached its limit.'),
                'type' => 'text',
                'default' => 'One or more of your selections is no longer available. Please choose another option.',
                'if' => ['enable_choice_limits' => 'equals true'],
            ],
        ];
    }

    /**
     * The option values that have reached their cap, for the field's current form.
     *
     * Empty in any non-form context (e.g. a collection entry field) or when limits are not
     * enabled, so the query only runs for a form field that opted in.
     *
     * @return list<string>
     */
    protected function fullChoicesForRender(): array
    {
        $field = $this->field();
        $form = $field?->form();

        if (! $field instanceof Field || ! $form instanceof Form) {
            return [];
        }

        return (new ChoiceSubmissionLimits)->fullChoices($form, $field);
    }
}
