<?php

declare(strict_types=1);

/**
 * Person Name fieldtype — publish UI and validation attribute labels.
 *
 * Keys are referenced from `App\Fieldtypes\PersonName::extraValidationAttributes()`
 * and `PersonNameFieldtype.vue` via `__('statamic::fieldtypes.person_name.field.*')`.
 *
 * Quantitative Value (`App\Fieldtypes\QuantitativeValue`, `QuantitativeValueFieldtype.vue`):
 * `statamic::fieldtypes.quantitative_value.*`.
 *
 * Partial Date (`App\Fieldtypes\PartialDate`, `PartialDateFieldtype.vue`):
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

    // Person Name Fieldtype

    'person_name.field.display_format_instructions' => 'Choose how the name should be displayed.',
    'person_name.field.first_name_phonetic_text' => 'Full First Name (Phonetic Text)',
    'person_name.field.first_name_text' => 'Full First Name (Text)',
    'person_name.field.last_name_phonetic_text' => 'Full Last Name (Phonetic Text)',
    'person_name.field.last_name_text' => 'Full Last Name (Text)',
    'person_name.field.middle_name_phonetic_text' => 'Full Middle Name (Phonetic Text)',
    'person_name.field.middle_name_text' => 'Full Middle Name (Text)',
    'person_name.field.generational_suffix' => 'Generational Suffix',
    'person_name.field.nickname_phonetic_text' => 'Nickname (Phonetic Text)',
    'person_name.field.nickname_text' => 'Nickname (Text)',
    'person_name.field.no_generational_suffix' => 'No Generational Suffix',
    'person_name.field.no_middle_name' => 'No Middle Name',
    'person_name.field.no_nickname' => 'No Nickname',
    'person_name.field.phonetic_alert_heading' => 'Phonetic Text Transcription Instructions',

    /*
    | Shared IPA help (PhoneticTextInstructions; PersonName; TextPhoneticPair).
    */
    'phonetic_instructions.alert_heading' => 'Phonetic Text Transcription Instructions',
    'phonetic_instructions.p1' => 'Enter the pronunciation using International Phonetic Alphabet (IPA) symbols.',
    'phonetic_instructions.p2_html' => 'Enter the <strong>symbols</strong> only. Do not include forward slashes (<code>/ /</code>) or brackets (<code>[ ]</code>), as these will be added automatically.',
    'phonetic_instructions.p3_html' => 'Example: <code>təˈmeɪtoʊ</code> — stored without wrapping slashes; presentation may add them.',
    'phonetic_instructions.p4_html' => '<strong>Note:</strong> Do not use "phonetic respelling" (e.g., <code>tuh-MAY-toh</code>).',
    'phonetic_instructions.p5_html' => 'If you are unsure of the correct symbols, use a tool like <a href="https://tophonetics.com/" target="_blank" rel="noopener noreferrer">toPhonetics</a> / <a href="https://ipa-reader.com/" target="_blank" rel="noopener noreferrer">IPA Reader</a> or an IPA keyboard like <a href="https://ipa.typeit.org/full/" target="_blank" rel="noopener noreferrer">https://ipa.typeit.org/full/</a>.',
    'person_name.field.preferred_display_format' => 'Preferred Display Format',
    'person_name.checklist.before_display_format' => 'Before you can select a display format, complete the following fields:',
    'person_name.checklist.first_name' => 'First name',
    'person_name.checklist.generational_suffix' => 'Generational suffix (or mark that there is no generational suffix)',
    'person_name.checklist.last_name' => 'Last name',
    'person_name.checklist.middle' => 'Middle name (or mark that there is no middle name)',
    'person_name.checklist.nickname' => 'Nickname (or mark that there is no nickname)',

    // Text Phonetic Pair Fieldtype

    'text_phonetic_pair.config.section_heading' => 'Text & phonetic pair',
    'text_phonetic_pair.config.text_label_display' => 'Text label',
    'text_phonetic_pair.config.text_label_instructions' => 'Label shown above the plain text input. Leave empty to use the default.',
    'text_phonetic_pair.config.phonetic_label_display' => 'Phonetic label',
    'text_phonetic_pair.config.phonetic_label_instructions' => 'Label shown for the IPA phonetic input. Leave empty to use the default.',
    'text_phonetic_pair.config.quote_straight_on_text_display' => 'Straight quotes on text',
    'text_phonetic_pair.config.quote_straight_on_text_instructions' => 'Show straight double quotes as decorative prepend/append on the text field (not stored).',
    'text_phonetic_pair.config.split_inputs_display' => 'Split layout (md+)',
    'text_phonetic_pair.config.split_inputs_instructions' => 'When enabled, text and phonetic inputs sit side by side from the medium breakpoint; when disabled, they stack full width.',
    'text_phonetic_pair.config.border_display' => 'Bordered container',
    'text_phonetic_pair.config.border_instructions' => 'Wrap the pair in the same bordered card shell as a Group field.',
    'text_phonetic_pair.config.body_class_display' => 'Body class',
    'text_phonetic_pair.config.body_class_instructions' => 'Optional Tailwind / utility classes for the inner wrapper (default: vertical spacing).',
    'text_phonetic_pair.config.require_text_display' => 'Require text',
    'text_phonetic_pair.config.require_text_instructions' => 'When enabled, the text value must be filled (or the field is marked required in the blueprint).',
    'text_phonetic_pair.config.require_phonetic_display' => 'Require phonetic text',
    'text_phonetic_pair.config.require_phonetic_instructions' => 'When enabled, the phonetic (IPA) value must be filled.',
    'text_phonetic_pair.config.include_phonetic_instructions_display' => 'Include phonetic text instructions',
    'text_phonetic_pair.config.include_phonetic_instructions_instructions' => 'When enabled, the IPA help alert is shown above the pair. When disabled, that alert is hidden.',
    'text_phonetic_pair.field.text' => 'Text',
    'text_phonetic_pair.field.phonetic_text' => 'Phonetic text',
    'text_phonetic_pair.field.default_text_label' => 'Text',
    'text_phonetic_pair.field.default_phonetic_label' => 'Phonetic text (IPA)',

    // Role Title fieldtype

    'role_title.config.section_heading' => 'Role Title Options',
    'role_title.config.enabled_configurations_display' => 'Enabled Title Configurations',
    'role_title.config.enabled_configurations_instructions' => 'Choose which title configuration modes editors can use. At least one must remain selected.',
    'role_title.config.default_configuration_display' => 'Default Configuration',
    'role_title.config.default_configuration_instructions' => 'Configuration selected for new entries when the field is empty.',
    'role_title.config.border_display' => 'Bordered Container',
    'role_title.config.border_instructions' => 'Wrap the field in the same bordered card shell as a Group field.',
    'role_title.config.scope_unit_row_link_display' => 'Scope Unit Must Match Row Field',
    'role_title.config.scope_unit_row_link_instructions' => 'Handle of the sibling field in the same row that links this role\'s organizational unit (for example `department` or `service_line`). When both are set, the scope\'s linked unit must be the same entry, so a row cannot link one unit and name a different one in its title.',
    'role_title.config.enabled_title_structures_display' => 'Enabled Title Structures',
    'role_title.config.enabled_title_structures_instructions' => 'Choose whether editors use the structured composer, an unstructured title with occupation metadata, or both. At least one must remain selected.',
    'role_title.config.default_title_structure_display' => 'Default Title Structure',
    'role_title.config.default_title_structure_instructions' => 'Structure selected for new entries when the field is empty.',
    'role_title.config.required_unstructured_subfields_display' => 'Required Unstructured Subfields',
    'role_title.config.required_unstructured_subfields_instructions' => 'When the field is marked required in the blueprint, the selected unstructured segments must be filled when editors choose the unstructured title structure. By default only the title text is required.',

    'role_title.structure.structured' => 'Structured',
    'role_title.structure.unstructured' => 'Unstructured',

    'role_title.configuration.faculty' => 'Faculty Role Title',
    'role_title.configuration.academic_leadership' => 'Academic Leadership Role Title',
    'role_title.configuration.institutional_leadership' => 'Institutional Leadership Role Title',
    'role_title.configuration.clinical_leadership' => 'Clinical Leadership Role Title',

    'role_title.field.configuration' => 'Title Configuration',
    'role_title.field.mode' => 'Title Structure',
    'role_title.field.composed_value' => 'Title Text',
    'role_title.field.unstructured.occupation_same_as' => 'Occupation — Reference Webpages',
    'role_title.field.unstructured.occupation_isco_08' => 'Occupation — ISCO-08 Codes',
    'role_title.field.unstructured.occupation_onet_soc' => 'Occupation — O*NET-SOC Codes',
    'role_title.field.unstructured.occupational_field_same_as' => 'Occupational Field — Reference Webpages',

    'role_title.unstructured.occupation_isco_08_instructions' => 'Add one row per ISCO-08 code. Use the classification picker from major group down to the narrowest level that applies; the stored code identifies the occupation (for example in schema.org metadata).',
    'role_title.unstructured.occupation_onet_soc_instructions' => 'Add one row per O\*NET-SOC occupation code. Use the standard format: two digits, a hyphen, four digits, a period, then two digits (for example 11-1011.00). Browse codes and titles on <a href="https://www.onetonline.org/" target="_blank" rel="noopener noreferrer">O\*NET Online</a>.',
    'role_title.unstructured.onet_soc_code' => 'O*NET-SOC Code',
    'role_title.unstructured.reference_webpages' => 'Reference Webpage',
    'role_title.unstructured.reference_webpages_instructions' => 'Reference webpages (e.g., Wikipedia article) or URI-identified RDF class from an external vocabulary (e.g., Wikidata entry, Library of Congress Name Authority File) that unambiguously indicates the occupation\'s identity.',
    'role_title.unstructured.reference_webpages_instructions_occupational_field' => 'Reference webpages (e.g., Wikipedia article) or URI-identified RDF class from an external vocabulary (e.g., Wikidata entry, Library of Congress Name Authority File) that unambiguously indicates the occupational field\'s identity.',
    'role_title.unstructured.replicator_row_isco_08' => 'ISCO-08 Occupation Classification',
    'role_title.unstructured.replicator_row_onet_soc' => 'O*NET-SOC Occupation Code',
    'role_title.unstructured.replicator_row_occupation_same_as' => 'Occupation Reference Webpage',
    'role_title.unstructured.replicator_row_occupational_field_same_as' => 'Occupational Field Reference Webpage',
    'role_title.unstructured.add_row_isco_08' => 'Add ISCO-08 Code',
    'role_title.unstructured.add_row_onet_soc' => 'Add O*NET-SOC Code',
    'role_title.unstructured.add_row_occupation_same_as' => 'Add Reference Webpage',
    'role_title.unstructured.add_row_occupational_field_same_as' => 'Add Reference Webpage',

    /*
     * Structural field labels (`role_title.field.*`): section headings, checklist.
     */
    'role_title.field.scope_qualifier' => 'Scope Qualifier',
    'role_title.field.hierarchical_modifier' => 'Hierarchical Modifier',
    'role_title.field.role' => 'Role',
    'role_title.field.object' => 'Object',
    'role_title.field.positional_qualifier' => 'Positional Qualifier',
    'role_title.field.scope_joiner' => 'Scope Joiner',
    'role_title.field.scope_article' => 'Scope Article',
    'role_title.field.scope_unit' => 'Scope Unit',
    'role_title.field.scope_name' => 'Scope Name',
    'role_title.field.honorific' => 'Honorific',

    /*
     * Scope unit link validation (UAMS-Web/uams-statamic-archive#2146).
     */
    'role_title.validation.scope_unit_not_found' => 'The linked scope unit does not exist or is not an organizational unit.',
    'role_title.validation.scope_unit_unnamed' => 'The linked scope unit has no public label, so it cannot name a title. Give the unit a public label first.',
    'role_title.validation.scope_unit_object' => 'A linked scope unit names the unit already, so it cannot be combined with an object. Clear the object or the scope unit.',
    'role_title.validation.scope_joiner_requires_article' => 'A typed scope that uses "in" must use the article "the", or link a scope unit instead.',
    'role_title.validation.scope_unit_row_mismatch' => 'The scope unit must be the same organizational unit this role links.',
    'role_title.field.toggle_options' => 'Title Options',

    /*
     * Scope suffix lines: one phrase per preposition, with and without the article
     * (`_the`: a linked unit, or a typed scope whose article is `the`), so locales can
     * vary phrasing independently of the joiner radio labels below (UAMS-Web/uams-statamic-archive#2546).
     */
    'role_title.compose.scope_for' => 'for :name',
    'role_title.compose.scope_for_the' => 'for the :name',
    'role_title.compose.scope_of' => 'of :name',
    'role_title.compose.scope_of_the' => 'of the :name',
    'role_title.compose.scope_in' => 'in :name',
    'role_title.compose.scope_in_the' => 'in the :name',

    /*
     * Checkbox/radio option text (`role_title.field.option.*`): lowercase for strings
     * used in composed title (other than proper nouns); Title Case only for options
     * that are only used in the control panel.
     */
    // Sentinel options
    'role_title.option.none_scope_qualifier' => 'No Scope Qualifier',
    'role_title.option.none_hierarchical_modifier' => 'No Hierarchical Modifier',
    'role_title.option.none_object' => 'No Object',
    'role_title.option.none_positional_qualifier' => 'No Positional Qualifier',
    'role_title.option.none_role' => 'No Role',
    'role_title.option.none_honorific' => 'No Honorific',
    'role_title.option.none_scope_article' => 'No Article',
    // title_options
    'role_title.field.option.adjunct' => 'adjunct',
    'role_title.field.option.co_prefix' => 'co-',
    'role_title.field.option.distinguished' => 'distinguished',
    'role_title.field.option.emeritus' => 'emeritus',
    'role_title.field.option.emerita' => 'emerita',
    'role_title.field.option.executive' => 'executive',
    'role_title.field.option.interim' => 'interim',
    'role_title.field.option.regional' => 'regional',
    'role_title.field.option.senior' => 'senior',
    'role_title.field.option.visiting' => 'visiting',
    // scope_qualifier
    'role_title.field.option.clinical' => 'clinical',
    'role_title.field.option.research' => 'research',
    // hierarchical_modifier
    'role_title.field.option.assistant' => 'assistant',
    'role_title.field.option.associate' => 'associate',
    // object
    'role_title.field.option.department' => 'department',
    'role_title.field.option.division' => 'division',
    'role_title.field.option.fellowship_program' => 'fellowship program',
    'role_title.field.option.medical_student_clerkship' => 'medical student clerkship',
    'role_title.field.option.residency_program' => 'residency program',
    'role_title.field.option.section' => 'section',
    'role_title.field.option.training' => 'training',
    // positional_qualifier
    'role_title.field.option.deputy' => 'deputy',
    'role_title.field.option.vice' => 'vice',
    // role
    'role_title.field.option.chair' => 'chair',
    'role_title.field.option.chairman' => 'chairman',
    'role_title.field.option.chairperson' => 'chairperson',
    'role_title.field.option.chairwoman' => 'chairwoman',
    'role_title.field.option.chancellor' => 'chancellor',
    'role_title.field.option.chief_communications_officer' => 'chief communications officer',
    'role_title.field.option.chief_executive_officer' => 'chief executive officer',
    'role_title.field.option.chief_financial_officer' => 'chief financial officer',
    'role_title.field.option.chief_human_resources_officer' => 'chief human resources officer',
    'role_title.field.option.chief_information_officer' => 'chief information officer',
    'role_title.field.option.chief_legal_counsel' => 'chief legal counsel',
    'role_title.field.option.chief_legal_officer' => 'chief legal officer',
    'role_title.field.option.chief_marketing_officer' => 'chief marketing officer',
    'role_title.field.option.chief_of_staff' => 'chief of staff',
    'role_title.field.option.chief_operating_officer' => 'chief operating officer',
    'role_title.field.option.chief_people_and_culture_officer' => 'chief people and culture officer',
    'role_title.field.option.chief_strategy_officer' => 'chief strategy officer',
    'role_title.field.option.chief_technology_officer' => 'chief technology officer',
    'role_title.field.option.dean' => 'dean',
    'role_title.field.option.director' => 'director',
    'role_title.field.option.administrator' => 'administrator',
    'role_title.field.option.medical_director' => 'medical director',
    'role_title.field.option.nursing_director' => 'nursing director',
    'role_title.field.option.service_line' => 'service line',
    'role_title.field.option.general_counsel' => 'general counsel',
    'role_title.field.option.instructor' => 'instructor',
    'role_title.field.option.lecturer' => 'lecturer',
    'role_title.field.option.president' => 'president',
    'role_title.field.option.professor' => 'professor',
    'role_title.field.option.provost' => 'provost',
    'role_title.field.option.public_relations_officer' => 'public relations officer',
    // scope_joiner
    'role_title.field.option.for' => 'for',
    'role_title.field.option.of' => 'of',
    'role_title.field.option.in' => 'in',
    // scope_article
    'role_title.field.option.the' => 'the',

    'role_title.checklist.before_compose' => 'Before the role title can be composed, please set a value for each required field.',

    // ISCO-08 fieldtype

    'isco_08.title' => 'ISCO-08 Occupation Classification Code',

    'isco_08.field.major_group' => 'Major Group',
    'isco_08.field.sub_major_group' => 'Sub-Major Group',
    'isco_08.field.minor_group' => 'Minor Group',
    'isco_08.field.unit_group' => 'Unit Group',
    'isco_08.field.value' => 'Resolved ISCO-08 code (stored, not shown in the field UI)',

    'isco_08.option.none' => 'None — No finer group applies; keep the selection at the previous level',

    'isco_08.validation.not_array' => 'The ISCO-08 field must be an object.',
    'isco_08.validation.required_major' => 'Select a major group.',
    'isco_08.validation.invalid_major' => 'The selected major group is not a valid ISCO-08 code.',
    'isco_08.validation.required_sub_major' => 'Select a sub-major group or choose None.',
    'isco_08.validation.invalid_sub_major' => 'The selected sub-major group does not belong to this major group.',
    'isco_08.validation.required_minor' => 'Select a minor group or choose None.',
    'isco_08.validation.invalid_minor' => 'The selected minor group does not belong to this sub-major group.',
    'isco_08.validation.required_unit' => 'Select a unit group or choose None.',
    'isco_08.validation.invalid_unit' => 'The selected unit group does not belong to this minor group.',

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
