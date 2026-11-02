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

];
