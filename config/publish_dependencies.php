<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Disabled collections
    |--------------------------------------------------------------------------
    |
    | The opt-in publish-dependency mechanism (UAMS-Web/uams-statamic-archive#1593) applies to any collection
    | whose blueprint declares a `publish_depends_on` entries field. List a
    | collection handle here to opt it out of BOTH the front-end redirect and the
    | Control Panel banner even when it declares the field — an escape hatch for a
    | collection that keeps the field for reference but should never be
    | render-suppressed by it.
    |
    | The default (empty) leaves every opted-in collection covered; a collection
    | that never declares the field is unaffected regardless.
    |
    */

    'disabled_collections' => [
        //
    ],

];
