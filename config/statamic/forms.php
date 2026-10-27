<?php

declare(strict_types=1);

use Statamic\Forms\Exporters\CsvExporter;
use Statamic\Forms\Exporters\JsonExporter;
use UAMSWeb\FormSettings\Jobs\SendFormNotification;

return [

    /*
    |--------------------------------------------------------------------------
    | Forms Path
    |--------------------------------------------------------------------------
    |
    | Where your form YAML files are stored.
    |
    */

    'forms' => resource_path('forms'),

    /*
    |--------------------------------------------------------------------------
    | Submissions Path
    |--------------------------------------------------------------------------
    |
    | Where your form submissions are stored.
    |
    | Added by studio1902/statamic-peak.
    |
    */

    'submissions' => storage_path('forms'),

    /*
    |--------------------------------------------------------------------------
    | Email View Folder
    |--------------------------------------------------------------------------
    |
    | The folder under resources/views where your email templates are found.
    |
    */

    'email_view_folder' => null,

    /*
    |--------------------------------------------------------------------------
    | Send Email Job
    |--------------------------------------------------------------------------
    |
    | The class name of the job that will be used to send an email.
    |
    | Overridden with the form settings add-on's job (#263), which honors the
    | email config's `from_name` field in the `From:` header (UAMS-Web/uams-statamic-archive#213), routes
    | recipients and gates conditional notifications. With the add-on's
    | `notifications` switch off, that job sends the way Statamic's own does.
    |
    */

    'send_email_job' => SendFormNotification::class,

    /*
    |--------------------------------------------------------------------------
    | Exporters
    |--------------------------------------------------------------------------
    |
    | Here you may define all the available form submission exporters.
    | You may customize the options within each exporter's array.
    |
    */

    'exporters' => [
        'csv' => [
            'class' => CsvExporter::class,
        ],
        'json' => [
            'class' => JsonExporter::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | CSV Export Delimiter
    |--------------------------------------------------------------------------
    |
    | Statamic will use this character as delimiter for csv exports.
    |
    | Added by studio1902/statamic-peak.
    |
    */

    'csv_delimiter' => ',',

    /*
    |--------------------------------------------------------------------------
    | CSV Export Headings
    |--------------------------------------------------------------------------
    |
    | The values to be used in the csv export header rows.
    | Can be the field handle or the field display text.
    |
    | Supported values: "handle", "display"
    |
    | Added by studio1902/statamic-peak.
    |
    */

    'csv_headers' => 'display',

];
