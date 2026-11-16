<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Article Bard Extra Buttons
    |--------------------------------------------------------------------------
    |
    | Keeps the Attributes button in the form settings screen's Article Bard
    | toolbar, matching the site's own Bard fields. The add-on defaults to
    | none.
    |
    */

    'article_bard_extra_buttons' => ['bts_attributes'],

    /*
    |--------------------------------------------------------------------------
    | Quiz Scoring
    |--------------------------------------------------------------------------
    |
    | The site's forms score quizzes, so this stays on whatever the add-on's
    | default becomes. The add-on's URL placeholder is set in `lang/en.json`,
    | because the add-on has no setting for it. Every other key keeps the
    | add-on's default.
    |
    */

    'quiz_scoring' => (bool) env('FORM_SETTINGS_QUIZ_SCORING', true),

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Turns on submission data, which the add-on leaves off: the `_*` metadata
    | saved with each submission and the submitter's request context carried
    | to queued notification emails, which the `ip`, `referrer`, `get_param`
    | and `global_origin` tags read (#265). Every other switch keeps the
    | add-on's default. All five are listed because this array replaces the
    | add-on's whole `features` array rather than merging into it, so a switch
    | left out would ignore its environment variable.
    |
    */

    'features' => [
        'control_panel' => (bool) env('FORM_SETTINGS_CONTROL_PANEL', true),
        'notifications' => (bool) env('FORM_SETTINGS_NOTIFICATIONS', true),
        'confirmations' => (bool) env('FORM_SETTINGS_CONFIRMATIONS', true),
        'restrictions' => (bool) env('FORM_SETTINGS_RESTRICTIONS', true),
        'submission_data' => (bool) env('FORM_SETTINGS_SUBMISSION_DATA', true),
    ],

];
