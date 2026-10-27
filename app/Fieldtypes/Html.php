<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use Statamic\Fieldtypes\Html as BaseHtml;

/**
 * Application Html fieldtype.
 *
 * Not selectable in form blueprints: the published form partial
 * `resources/views/vendor/statamic/forms/fields/html.antlers.html` emits its
 * value raw (Antlers `{{ }}` does not escape), so exposing it to the non-admin
 * `form_editor` role would open an editor-reachable stored-XSS surface on public
 * forms (UAMS-Web/uams-statamic-archive#869). A super-admin can still add raw HTML via a direct blueprint edit.
 */
class Html extends BaseHtml
{
    /**
     * Indicates whether the fieldtype is selectable in form blueprints.
     *
     * @var bool Indicates whether the fieldtype is selectable in form blueprints
     */
    protected $selectableInForms = false;
}
