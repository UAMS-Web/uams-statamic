<?php

declare(strict_types=1);

/**
 * Custom fieldtype publish UI and validation attribute labels.
 *
 * The Person Name fieldtype's labels moved with it into `uams-web/aether-people`,
 * which reads them as `aether-people::fieldtypes.person_name.*` (#696).
 *
 * Quantitative Value (`App\Fieldtypes\QuantitativeValue`, `QuantitativeValueFieldtype.vue`):
 * `statamic::fieldtypes.quantitative_value.*`.
 *
 * Partial Date (`UAMSWeb\Aether\Fieldtypes\Fieldtypes\PartialDate`, `PartialDateFieldtype.vue`):
 * `statamic::fieldtypes.partial_date.*`.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Custom Fieldtype Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap attribute placeholders with
    | something more reader friendly such as "Full First Name (Text)" instead of
    | "first_name.text". This simply helps us make messages a little cleaner.
    |
    */

    /*
     * Schema.org Type fieldtype (`App\Fieldtypes\SchemaType`,
     * `SchemaTypeFieldtype.vue`).
     */
    'schema_type.title' => 'Schema Type',

    'schema_type.config.section_heading' => 'Schema Type',
    'schema_type.config.mode_display' => 'Mode',
    'schema_type.config.mode_instructions' => 'Chained: pick immediate subtypes step by step. Single: one combobox of the starting type(s) and every descendant type.',
    'schema_type.config.mode_chained' => 'Chained Drilldown',
    'schema_type.config.mode_single' => 'Single Dropdown',
    'schema_type.config.starting_types_display' => 'Starting Types',
    'schema_type.config.starting_types_instructions' => 'Types allowed as the root of the chain or as roots of the single dropdown. Default is `Thing`.',
    'schema_type.config.clearable_display' => 'Clearable',
    'schema_type.config.clearable_instructions' => 'Allow clearing each dropdown so the placeholder is shown again.',
    'schema_type.config.searchable_display' => 'Searchable',
    'schema_type.config.searchable_instructions' => 'Allow searching dropdown option lists.',
    'schema_type.config.multiple_display' => 'Multiple',
    'schema_type.config.multiple_instructions' => 'Allow selecting more than one type from the schema type dropdown.',
    'schema_type.config.default_display' => 'Default Value',
    'schema_type.config.default_instructions' => 'Default stored value for new entries (string or list of strings matching Multiple).',

    'schema_type.field.multi_typed_entity' => 'Multi Typed Entity',
    'schema_type.field.select_placeholder' => 'Select…',
    'schema_type.field.select_types' => 'Select Types',
    'schema_type.field.select_a_type' => 'Select a Type',
    'schema_type.field.starting_type' => 'Starting Type',
    'schema_type.field.subtype_of' => 'Subtype of :type',
    'schema_type.field.stop_here_use_type' => 'Stop here — use :type',

    /*
     * Quantitative Value fieldtype (`App\Fieldtypes\QuantitativeValue`,
     * `QuantitativeValueFieldtype.vue`).
     */
    'quantitative_value.title' => 'Quantitative Value',

    'quantitative_value.config.section_mode' => 'Mode',
    'quantitative_value.config.mode_display' => 'Mode',
    'quantitative_value.config.mode_option_number' => 'Number',
    'quantitative_value.config.mode_option_duration' => 'Duration',
    'quantitative_value.config.number_mode_display' => 'Number mode',
    'quantitative_value.config.number_mode_option_integer' => 'Integer',
    'quantitative_value.config.number_mode_option_float' => 'Float',

    'quantitative_value.config.unit_code_enabled_display' => 'Unit Code',
    'quantitative_value.config.unit_code_enabled_instructions' => 'The unit of measurement given using the UN/CEFACT Common Code (three characters) or a URL. Other codes than the UN/CEFACT Common Code may be used with a prefix followed by a colon.',
    'quantitative_value.config.unit_code_required_display' => 'Unit Code required',
    'quantitative_value.config.unit_text_enabled_display' => 'Unit Text',
    'quantitative_value.config.unit_text_enabled_instructions' => 'A string or text indicating the unit of measurement. This is useful if you cannot provide a standard unit code.',
    'quantitative_value.config.unit_text_required_display' => 'Unit Text required',

    'quantitative_value.config.section_components' => 'Components',
    'quantitative_value.config.major_components_display' => 'Major components',
    'quantitative_value.config.major_components_instructions' => 'Select at least one: Value, Minimum, and/or Maximum.',
    'quantitative_value.config.major_option_value' => 'Value',
    'quantitative_value.config.major_option_min' => 'Minimum',
    'quantitative_value.config.major_option_max' => 'Maximum',
    'quantitative_value.config.value_required_display' => 'Value required',
    'quantitative_value.config.min_required_display' => 'Minimum required',
    'quantitative_value.config.max_required_display' => 'Maximum required',
    'quantitative_value.config.number_single_storage_display' => 'Single-component storage (number)',
    'quantitative_value.config.number_single_storage_instructions' => 'When only one major component is enabled: nested keeps YAML as a one-key object; flat stores a scalar at the field root.',
    'quantitative_value.config.number_single_storage_option_nested' => 'Nested object',
    'quantitative_value.config.number_single_storage_option_flat' => 'Scalar (flat)',

    'quantitative_value.config.section_duration_units' => 'Duration units',
    'quantitative_value.config.duration_units_display' => 'Time units',
    'quantitative_value.config.duration_units_instructions' => 'Select at least one unit.',
    'quantitative_value.config.duration_unit_years' => 'Years',
    'quantitative_value.config.duration_unit_months' => 'Months',
    'quantitative_value.config.duration_unit_weeks' => 'Weeks',
    'quantitative_value.config.duration_unit_days' => 'Days',
    'quantitative_value.config.duration_unit_hours' => 'Hours',
    'quantitative_value.config.duration_unit_minutes' => 'Minutes',
    'quantitative_value.config.duration_unit_seconds' => 'Seconds',
    'quantitative_value.config.unit_years_required_display' => 'Years required',
    'quantitative_value.config.unit_months_required_display' => 'Months required',
    'quantitative_value.config.unit_weeks_required_display' => 'Weeks required',
    'quantitative_value.config.unit_days_required_display' => 'Days required',
    'quantitative_value.config.unit_hours_required_display' => 'Hours required',
    'quantitative_value.config.unit_minutes_required_display' => 'Minutes required',
    'quantitative_value.config.unit_seconds_required_display' => 'Seconds required',
    'quantitative_value.config.duration_single_unit_presentation_display' => 'Single-unit presentation (duration)',
    'quantitative_value.config.duration_single_unit_presentation_instructions' => 'When only one time unit is enabled: grouped shows Value / Min / Max sections; inline uses compact labels. Stored YAML shape is unchanged.',
    'quantitative_value.config.duration_single_unit_option_grouped' => 'Grouped',
    'quantitative_value.config.duration_single_unit_option_inline' => 'Inline labels',

    'quantitative_value.field.value' => 'Value',
    'quantitative_value.field.minimum' => 'Minimum',
    'quantitative_value.field.maximum' => 'Maximum',
    'quantitative_value.field.unit_code' => 'Unit Code',
    'quantitative_value.field.unit_text' => 'Unit Text',
    'quantitative_value.field.duration_unit.years' => 'Years',
    'quantitative_value.field.duration_unit.months' => 'Months',
    'quantitative_value.field.duration_unit.weeks' => 'Weeks',
    'quantitative_value.field.duration_unit.days' => 'Days',
    'quantitative_value.field.duration_unit.hours' => 'Hours',
    'quantitative_value.field.duration_unit.minutes' => 'Minutes',
    'quantitative_value.field.duration_unit.seconds' => 'Seconds',
    'quantitative_value.field.duration_flat_label_value' => ':unit :label',
    'quantitative_value.field.duration_flat_label_min' => ':label :unit',
    'quantitative_value.field.duration_flat_label_max' => ':label :unit',

    'quantitative_value.validation.duration_subfield' => ':major — :unit',

    'partial_date.title' => 'Partial Date',

    'partial_date.config.section_parts' => 'Date Parts',
    'partial_date.config.included_parts_display' => 'Included Parts',
    'partial_date.config.included_parts_instructions' => 'Which parts editors see and which are persisted as one canonical partial-date string in YAML.',
    'partial_date.config.included.year_only' => 'Year only',
    'partial_date.config.included.year_month' => 'Year and month',
    'partial_date.config.included.year_month_day' => 'Year, month, and day',
    'partial_date.config.included.month_only' => 'Month only',
    'partial_date.config.included.month_day' => 'Month and day',
    'partial_date.config.required_parts_display' => 'Required Parts',
    'partial_date.config.required_parts_instructions_ymd' => 'When this field is marked required in the blueprint, which saved parts must be filled. Only applies to modes that include year, month, and day.',
    'partial_date.config.required_parts_instructions_ym' => 'When this field is marked required, which saved parts must be filled. Only applies when including year and month (no day).',
    'partial_date.config.required_parts_instructions_md' => 'When this field is marked required, which saved parts must be filled. Only applies when including month and day (no year).',
    'partial_date.config.require.year_only' => 'Year only',
    'partial_date.config.require.year_month' => 'Year and month',
    'partial_date.config.require.year_month_day' => 'Year, month, and day',
    'partial_date.config.require.month_only' => 'Month only',
    'partial_date.config.require.month_day' => 'Month and day',
    'partial_date.config.year_default_display' => 'Default Year',
    'partial_date.config.year_default_instructions' => 'Pre-filled year for new entries when the blueprint has no default for this field. Ignored when included parts do not include year.',
    'partial_date.config.year_min_display' => 'Minimum Year',
    'partial_date.config.year_min_instructions' => 'Optional minimum year when year is included.',
    'partial_date.config.year_max_display' => 'Maximum Year',
    'partial_date.config.year_max_instructions' => 'Optional maximum year when year is included.',
    'partial_date.config.month_default_display' => 'Default Month',
    'partial_date.config.month_default_instructions' => 'Pre-filled month for new entries when the blueprint has no default for this field. Ignored when included parts do not include month.',
    'partial_date.config.day_default_display' => 'Default Day',
    'partial_date.config.day_default_instructions' => 'Pre-filled day of month for new entries when the blueprint has no default for this field. Ignored when included parts do not include day.',

    'partial_date.config.section_display' => 'Display',
    'partial_date.config.subfields_layout_display' => 'Layout',
    'partial_date.config.subfields_layout_instructions' => 'Inline shows year, month, and day inputs in a row (wrapping when needed). Stack shows each input full width, one per line.',
    'partial_date.config.subfields_layout_inline' => 'Inline',
    'partial_date.config.subfields_layout_stack' => 'Stack',

    'partial_date.add' => 'Add Date',

    'partial_date.field.year' => 'Year',
    'partial_date.field.month' => 'Month',
    'partial_date.field.day' => 'Day',

    'partial_date.validation.month_range' => 'The month must be between 1 and 12.',
    'partial_date.validation.day_requires_month' => 'Select a month before choosing a day.',
    'partial_date.validation.day_requires_year' => 'Select a year before choosing a day.',
    'partial_date.validation.month_requires_year' => 'Select a year before choosing a month.',
    'partial_date.validation.day_range' => 'The day must be between 1 and :max for the selected month.',
    'partial_date.validation.invalid_shape' => 'The partial date value must be structured data from the publisher.',
    'partial_date.validation.invalid_storage_format' => 'The partial date string is not valid for this field.',
    'partial_date.hierarchy.month_locked_tooltip' => 'Enter a year first, or populate month or day to continue editing.',
    'partial_date.hierarchy.day_locked_tooltip' => 'Pick a month, enter the year alone to set day without month, or keep an existing day value.',

    // GPS / Map Fieldtype

    'gps.title' => 'GPS / Map',

    'gps.config.section_heading' => 'Map Defaults',
    'gps.config.default_latitude_display' => 'Default Latitude',
    'gps.config.default_latitude_instructions' => 'Latitude the picker centers on before a marker is placed.',
    'gps.config.default_longitude_display' => 'Default Longitude',
    'gps.config.default_longitude_instructions' => 'Longitude the picker centers on before a marker is placed.',
    'gps.config.default_zoom_display' => 'Default Zoom',
    'gps.config.default_zoom_instructions' => 'Initial zoom level (0 world – 19 building).',
    'gps.config.enable_address_search_display' => 'Enable Address Search',
    'gps.config.enable_address_search_instructions' => 'Show an OpenStreetMap (Nominatim) address-search box in the picker that geocodes a typed address and drops the pin on the chosen result.',

    'gps.field.address_search_label' => 'Search Address',
    'gps.field.address_search_instructions' => 'Type a street address, then choose a result to drop the pin. You can fine-tune it afterward by dragging the marker.',
    'gps.field.address_search_placeholder' => 'e.g., 4301 W Markham St, Little Rock',
    'gps.field.address_search_searching' => 'Searching…',
    'gps.field.address_search_no_results' => 'No matching addresses found.',
    'gps.field.address_search_error' => 'Address search is unavailable right now. Try again shortly.',
    'gps.field.address_search_attribution' => 'Address search © OpenStreetMap contributors (Nominatim)',

    'gps.field.paste_label' => 'Paste Coordinates',
    'gps.field.paste_instructions' => 'Paste a coordinate to drop a pin on the map below. Decimal, DMS, geo: URI, and UTM formats are accepted.',
    'gps.field.paste_error' => 'Could not read those coordinates. Try decimal, DMS, a geo: URI, or UTM.',
    'gps.field.set' => 'Set',
    'gps.field.map_label' => 'Map',
    'gps.field.map_instructions' => 'Click the map to set the pin. Once placed, drag the pin to move it to a new location.',
    'gps.field.generated_heading' => 'Generated Coordinates',
    'gps.field.generated_description' => 'Set automatically by the map or the paste box above. Read-only.',
    'gps.field.latitude' => 'Latitude',
    'gps.field.longitude' => 'Longitude',
    'gps.field.not_set' => 'Not set',
    'gps.field.reset_view' => 'Reset View',
    'gps.field.center_on_pin' => 'Center on Pin',
    'gps.field.clear' => 'Clear',
    'gps.field.street' => 'Street',
    'gps.field.aerial' => 'Aerial',
];
