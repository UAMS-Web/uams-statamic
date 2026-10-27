<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use Statamic\Fields\Fieldtype;

/**
 * CP-only config fieldtype: a select of the OTHER `date` fields in the blueprint
 * being edited.
 *
 * It backs {@see Date}'s `earliest_date_field` / `latest_date_field` boundary
 * config selects (UAMS-Web/uams-statamic-archive#1393). Core cannot populate those selects server-side, because
 * the config schema is built in ISOLATION from the blueprint's other fields —
 * `Statamic\Http\Controllers\CP\Fields\FieldsController::edit()` constructs a
 * synthetic single-field blueprint from only the field's own config values, so a
 * server-side `configFieldItems()` has no way to reach the sibling date fields.
 *
 * The whole feature is therefore client-side: the Vue component
 * `resources/js/components/fieldtypes/DateFieldSelectFieldtype.vue` injects the
 * blueprint builder's sibling-field list and renders the options (option derivation
 * lives in the pure, unit-tested `resources/js/date-field-options.js`). This class
 * exists only so `type: date_field_select` resolves to a registered fieldtype and
 * routes to that component; it stores a plain field-handle string and needs no
 * server-side value handling.
 */
class DateFieldSelect extends Fieldtype
{
    /**
     * Not offered in the fieldtype selector — this is a config-only field for the
     * Date fieldtype's boundary settings, never placed directly in a blueprint.
     *
     * @var bool Whether the fieldtype is selectable in collection blueprints
     */
    protected $selectable = false;

    /**
     * Not offered in the form-blueprint fieldtype selector either.
     *
     * @var bool Whether the fieldtype is selectable in form blueprints
     */
    protected $selectableInForms = false;
}
