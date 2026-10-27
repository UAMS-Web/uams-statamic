<?php

declare(strict_types=1);

/**
 * Application form configuration outside Statamic's `statamic.forms` package
 * config.
 *
 * Notification recipients are role keys, not per-form addresses (UAMS-Web/uams-statamic-archive#2745). Each
 * form YAML names a key with `{{ config:forms.recipients.<key> }}`; each
 * environment sets the matching `FORM_RECIPIENT_*` variable.
 *
 * @see docs/forms/notification-recipients.md
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Form notification recipients
    |--------------------------------------------------------------------------
    |
    | One address (or comma-separated list) per role. A form under
    | resources/forms/ may name one of these keys as `config:forms.recipients.*`
    | or carry a real address set in the control panel: the forms live in the
    | private uams-statamic-forms submodule, so an address there is not
    | public (UAMS-Web/uams-statamic-archive#2818). Placeholders in .env.example are not real mailboxes:
    | set the real values in each environment before deploy.
    |
    */

    'recipients' => [
        'institutional_relations' => env(
            'FORM_RECIPIENT_INSTITUTIONAL_RELATIONS'
        ),
        'mail_processing_center' => env(
            'FORM_RECIPIENT_MAIL_PROCESSING_CENTER'
        ),
        'spread_kindness' => env(
            'FORM_RECIPIENT_SPREAD_KINDNESS'
        ),
    ],

];
