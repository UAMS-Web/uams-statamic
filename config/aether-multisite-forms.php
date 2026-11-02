<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Forms not assigned to the current site
    |--------------------------------------------------------------------------
    |
    | What the {{ form }} tags do with a form that is not assigned to the site
    | the page is rendered for.
    |
    | "empty": render nothing, and log a warning naming the form and the site
    |          once per form and site per request. Suits a shared layout or
    |          partial that renders a form on every site when only some use it.
    | "404":   abort the request with the site's 404 page.
    |
    | A submit to an unassigned form always answers 404, whatever this says.
    |
    | This application keeps "empty" (UAMS-Web/uams-statamic-archive#3066). A form reaches a page only as a
    | block inside an entry's content (the page builder form block and the
    | Bard form set), never from a shared layout. So a form not assigned to
    | the entry's site is one block on an otherwise valid page: "empty" drops
    | that block and logs the mismatch for an editor to fix, while "404"
    | would take the rest of the page down with it.
    |
    */

    'not_assigned' => 'empty',

];
