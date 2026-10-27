<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel Core Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    // ---
    // Laravel Core Validation Language Lines
    // ---

    // 'accepted' => 'The :attribute field must be accepted.',
    // 'accepted_if' => 'The :attribute field must be accepted when :other is :value.',
    // 'active_url' => 'The :attribute field must be a valid URL.',
    // 'after' => 'The :attribute field must be a date after :date.',
    // 'after_or_equal' => 'The :attribute field must be a date after or equal to :date.',
    // 'alpha' => 'The :attribute field must only contain letters.',
    // 'alpha_dash' => 'The :attribute field must only contain letters, numbers, dashes, and underscores.',
    // 'alpha_num' => 'The :attribute field must only contain letters and numbers.',
    // 'array' => 'The :attribute field must be an array.',
    // 'ascii' => 'The :attribute field must only contain single-byte alphanumeric characters and symbols.',
    // 'before' => 'The :attribute field must be a date before :date.',
    // 'before_or_equal' => 'The :attribute field must be a date before or equal to :date.',
    // 'between' => [
    //     'array' => 'The :attribute field must have between :min and :max items.',
    //     'file' => 'The :attribute field must be between :min and :max kilobytes.',
    //     'numeric' => 'The :attribute field must be between :min and :max.',
    //     'string' => 'The :attribute field must be between :min and :max characters.',
    // ],
    // 'boolean' => 'The :attribute field must be true or false.',
    // 'can' => 'The :attribute field contains an unauthorized value.',
    // 'confirmed' => 'The :attribute field confirmation does not match.',
    'contains' => 'The :attribute field is missing a required value.',
    // 'current_password' => 'The password is incorrect.',
    // 'date' => 'The :attribute field must be a valid date.',
    // 'date_equals' => 'The :attribute field must be a date equal to :date.',
    // 'date_format' => 'The :attribute field must match the format :format.',
    // 'decimal' => 'The :attribute field must have :decimal decimal places.',
    // 'declined' => 'The :attribute field must be declined.',
    // 'declined_if' => 'The :attribute field must be declined when :other is :value.',
    // 'different' => 'The :attribute field and :other must be different.',
    // 'digits' => 'The :attribute field must be :digits digits.',
    // 'digits_between' => 'The :attribute field must be between :min and :max digits.',
    // 'dimensions' => 'The :attribute field has invalid image dimensions.',
    // 'distinct' => 'The :attribute field has a duplicate value.',
    // 'doesnt_end_with' => 'The :attribute field must not end with one of the following: :values.',
    // 'doesnt_start_with' => 'The :attribute field must not start with one of the following: :values.',
    // 'email' => 'The :attribute field must be a valid email address.',
    // 'ends_with' => 'The :attribute field must end with one of the following: :values.',
    // 'enum' => 'The selected :attribute is invalid.',
    // 'exists' => 'The selected :attribute is invalid.',
    'extensions' => 'The :attribute field must have one of the following extensions: :values.',
    // 'file' => 'The :attribute field must be a file.',
    // 'filled' => 'The :attribute field must have a value.',
    // 'gt' => [
    //     'array' => 'The :attribute field must have more than :value items.',
    //     'file' => 'The :attribute field must be greater than :value kilobytes.',
    //     'numeric' => 'The :attribute field must be greater than :value.',
    //     'string' => 'The :attribute field must be greater than :value characters.',
    // ],
    // 'gte' => [
    //     'array' => 'The :attribute field must have :value items or more.',
    //     'file' => 'The :attribute field must be greater than or equal to :value kilobytes.',
    //     'numeric' => 'The :attribute field must be greater than or equal to :value.',
    //     'string' => 'The :attribute field must be greater than or equal to :value characters.',
    // ],
    'hex_color' => 'The :attribute field must be a valid hexadecimal color.',
    // 'image' => 'The :attribute field must be an image.',
    // 'in' => 'The selected :attribute is invalid.',
    // 'in_array' => 'The :attribute field must exist in :other.',
    // 'integer' => 'The :attribute field must be an integer.',
    // 'ip' => 'The :attribute field must be a valid IP address.',
    // 'ipv4' => 'The :attribute field must be a valid IPv4 address.',
    // 'ipv6' => 'The :attribute field must be a valid IPv6 address.',
    // 'json' => 'The :attribute field must be a valid JSON string.',
    'list' => 'The :attribute field must be a list.',
    // 'lowercase' => 'The :attribute field must be lowercase.',
    // 'lt' => [
    //     'array' => 'The :attribute field must have less than :value items.',
    //     'file' => 'The :attribute field must be less than :value kilobytes.',
    //     'numeric' => 'The :attribute field must be less than :value.',
    //     'string' => 'The :attribute field must be less than :value characters.',
    // ],
    // 'lte' => [
    //     'array' => 'The :attribute field must not have more than :value items.',
    //     'file' => 'The :attribute field must be less than or equal to :value kilobytes.',
    //     'numeric' => 'The :attribute field must be less than or equal to :value.',
    //     'string' => 'The :attribute field must be less than or equal to :value characters.',
    // ],
    // 'mac_address' => 'The :attribute field must be a valid MAC address.',
    // 'max' => [
    //     'array' => 'The :attribute field must not have more than :max items.',
    //     'file' => 'The :attribute field must not be greater than :max kilobytes.',
    //     'numeric' => 'The :attribute field must not be greater than :max.',
    //     'string' => 'The :attribute field must not be greater than :max characters.',
    // ],
    // 'max_digits' => 'The :attribute field must not have more than :max digits.',
    // 'mimes' => 'The :attribute field must be a file of type: :values.',
    // 'mimetypes' => 'The :attribute field must be a file of type: :values.',
    // 'min' => [
    //     'array' => 'The :attribute field must have at least :min items.',
    //     'file' => 'The :attribute field must be at least :min kilobytes.',
    //     'numeric' => 'The :attribute field must be at least :min.',
    //     'string' => 'The :attribute field must be at least :min characters.',
    // ],
    // 'min_digits' => 'The :attribute field must have at least :min digits.',
    // 'missing' => 'The :attribute field must be missing.',
    // 'missing_if' => 'The :attribute field must be missing when :other is :value.',
    // 'missing_unless' => 'The :attribute field must be missing unless :other is :value.',
    // 'missing_with' => 'The :attribute field must be missing when :values is present.',
    // 'missing_with_all' => 'The :attribute field must be missing when :values are present.',
    // 'multiple_of' => 'The :attribute field must be a multiple of :value.',
    // 'not_in' => 'The selected :attribute is invalid.',
    // 'not_regex' => 'The :attribute field format is invalid.',
    // 'numeric' => 'The :attribute field must be a number.',
    'password' => [
        'letters' => 'The :attribute field must contain at least one letter.',
        'mixed' => 'The :attribute field must contain at least one uppercase and one lowercase letter.',
        'numbers' => 'The :attribute field must contain at least one number.',
        'symbols' => 'The :attribute field must contain at least one symbol.',
        'uncompromised' => 'The given :attribute has appeared in a data leak. Please choose a different :attribute.',
    ],
    // 'present' => 'The :attribute field must be present.',
    'present_if' => 'The :attribute field must be present when :other is :value.',
    'present_unless' => 'The :attribute field must be present unless :other is :value.',
    'present_with' => 'The :attribute field must be present when :values is present.',
    'present_with_all' => 'The :attribute field must be present when :values are present.',
    // 'prohibited' => 'The :attribute field is prohibited.',
    // 'prohibited_if' => 'The :attribute field is prohibited when :other is :value.',
    'prohibited_if_accepted' => 'The :attribute field is prohibited when :other is accepted.',
    'prohibited_if_declined' => 'The :attribute field is prohibited when :other is declined.',
    // 'prohibited_unless' => 'The :attribute field is prohibited unless :other is in :values.',
    // 'prohibits' => 'The :attribute field prohibits :other from being present.',
    // 'regex' => 'The :attribute field format is invalid.',
    // 'required' => 'The :attribute field is required.',
    // 'required_array_keys' => 'The :attribute field must contain entries for: :values.',
    // 'required_if' => 'The :attribute field is required when :other is :value.',
    // 'required_if_accepted' => 'The :attribute field is required when :other is accepted.',
    'required_if_declined' => 'The :attribute field is required when :other is declined.',
    // 'required_unless' => 'The :attribute field is required unless :other is in :values.',
    // 'required_with' => 'The :attribute field is required when :values is present.',
    // 'required_with_all' => 'The :attribute field is required when :values are present.',
    // 'required_without' => 'The :attribute field is required when :values is not present.',
    // 'required_without_all' => 'The :attribute field is required when none of :values are present.',
    // 'same' => 'The :attribute field must match :other.',
    // 'size' => [
    //     'array' => 'The :attribute field must contain :size items.',
    //     'file' => 'The :attribute field must be :size kilobytes.',
    //     'numeric' => 'The :attribute field must be :size.',
    //     'string' => 'The :attribute field must be :size characters.',
    // ],
    // 'starts_with' => 'The :attribute field must start with one of the following: :values.',
    // 'string' => 'The :attribute field must be a string.',
    // 'timezone' => 'The :attribute field must be a valid timezone.',
    // 'ulid' => 'The :attribute field must be a valid ULID.',
    // 'unique' => 'The :attribute has already been taken.',
    // 'uploaded' => 'The :attribute failed to upload.',
    // 'uppercase' => 'The :attribute field must be uppercase.',
    // 'url' => 'The :attribute field must be a valid URL.',
    // 'uuid' => 'The :attribute field must be a valid UUID.',

    // ---
    // Statamic Core Overrides to Laravel Core Validation Language Lines
    // ---

    'accepted' => 'Must be accepted.',
    'accepted_if' => 'Must be accepted when :other is :value.',
    'active_url' => 'This is not a valid URL.',
    // 'after' => 'Must be a date after :date.',
    // 'after_or_equal' => 'Must be a date after or equal to :date.',
    'alpha' => 'May only contain letters.',
    'alpha_dash' => 'May only contain letters, numbers, dashes and underscores.',
    'alpha_num' => 'May only contain letters and numbers.',
    'array' => 'Must be an array.',
    'ascii' => 'Must only contain single-byte alphanumeric characters and symbols.',
    // 'before' => 'Must be a date before :date.',
    // 'before_or_equal' => 'Must be a date before or equal to :date.',
    'between' => [
        'array' => 'Must have between :min and :max items.',
        'file' => 'Must be between :min and :max kilobytes.',
        'numeric' => 'Must be between :min and :max.',
        'string' => 'Must be between :min and :max characters.',
    ],
    'boolean' => 'Must be true or false.',
    'can' => 'Contains an unauthorized value.',
    'confirmed' => 'Confirmation does not match.',
    'current_password' => 'The password is incorrect.',
    'date' => 'Not a valid date.',
    // 'date_equals' => 'Must be a date equal to :date.',
    'date_format' => 'Does not match the format :format.',
    'decimal' => 'Must have :decimal decimal places.',
    'declined' => 'Must be declined.',
    'declined_if' => 'Must be declined when :other is :value.',
    'different' => 'This field and :other must be different.',
    'digits' => 'Must be :digits digits.',
    'digits_between' => 'Must be between :min and :max digits.',
    'dimensions' => 'Invalid image dimensions.',
    'distinct' => 'This field has a duplicate value.',
    'doesnt_end_with' => 'Must not end with one of the following: :values.',
    'doesnt_start_with' => 'Must not start with one of the following: :values.',
    'email' => 'Must be a valid email address.',
    'ends_with' => 'Must end with :values',
    'enum' => 'The selected :attribute is invalid.',
    'exists' => 'This is invalid.',
    'file' => 'Must be a file.',
    'filled' => 'Must have a value.',
    'gt' => [
        'array' => 'Must have more than :value items.',
        'file' => 'Must be greater than :value kilobytes.',
        'numeric' => 'Must be greater than :value.',
        'string' => 'Must be greater than :value characters.',
    ],
    'gte' => [
        'array' => 'Must have :value items or more.',
        'file' => 'Must be greater than or equal :value kilobytes.',
        'numeric' => 'Must be greater than or equal :value.',
        'string' => 'Must be greater than or equal :value characters.',
    ],
    'image' => 'Must be an image.',
    'in' => 'This is invalid.',
    'in_array' => 'This field does not exist in :other.',
    'integer' => 'Must be an integer.',
    'ip' => 'Must be a valid IP address.',
    'ipv4' => 'Must be a valid IPv4 address.',
    'ipv6' => 'Must be a valid IPv6 address.',
    'json' => 'Must be a valid JSON string.',
    'lowercase' => 'Must be lowercase.',
    'lt' => [
        'array' => 'Must have less than :value items.',
        'file' => 'Must be less than :value kilobytes.',
        'numeric' => 'Must be less than :value.',
        'string' => 'Must be less than :value characters.',
    ],
    'lte' => [
        'array' => 'Must not have more than :value items.',
        'file' => 'Must be less than or equal :value kilobytes.',
        'numeric' => 'Must be less than or equal :value.',
        'string' => 'Must be less than or equal :value characters.',
    ],
    'mac_address' => 'Must be a valid MAC address.',
    'max' => [
        'array' => 'May not have more than :max items.',
        'file' => 'May not be greater than :max kilobytes.',
        'numeric' => 'May not be greater than :max.',
        'string' => 'May not be greater than :max characters.',
    ],
    'max_digits' => 'Must not have more than :max digits.',
    'mimes' => 'Must be a file of type: :values.',
    'mimetypes' => 'Must be a file of type: :values.',
    'min' => [
        'array' => 'Must have at least :min items.',
        'file' => 'Must be at least :min kilobytes.',
        'numeric' => 'Must be at least :min.',
        'string' => 'Must be at least :min characters.',
    ],
    'min_digits' => 'Must have at least :min digits.',
    'missing' => 'Must be missing.',
    'missing_if' => 'Must be missing when :other is :value.',
    'missing_unless' => 'Must be missing unless :other is :value.',
    'missing_with' => 'Must be missing when :values is present.',
    'missing_with_all' => 'Must be missing when :values are present.',
    'multiple_of' => 'Must be a multiple of :value.',
    'not_in' => 'This is invalid.',
    'not_regex' => 'Format is invalid.',
    'numeric' => 'Must be a number.',
    'present' => 'Must be present.',
    'prohibited' => 'Prohibited.',
    'prohibited_if' => 'Prohibited when :other is :value.',
    'prohibited_unless' => 'Prohibited unless :other is in :values.',
    'prohibits' => 'Prohibits :other from being present.',
    'regex' => 'Format is invalid.',
    'required' => 'This field is required.',
    'required_array_keys' => 'Must contain entries for: :values.',
    'required_if' => 'This field is required when :other is :value.',
    'required_if_accepted' => 'This field is required when :other is accepted.',
    'required_unless' => 'This field is required unless :other is in :values.',
    'required_with' => 'This field is required when :values is present.',
    'required_with_all' => 'This field is required when :values is present.',
    'required_without' => 'This field is required when :values is not present.',
    'required_without_all' => 'This field is required when none of :values are present.',
    'same' => 'This field and :other must match.',
    'size' => [
        'array' => 'Must contain :size items.',
        'file' => 'Must be :size kilobytes.',
        'numeric' => 'Must be :size.',
        'string' => 'Must be :size characters.',
    ],
    'starts_with' => 'Must start with :values',
    'string' => 'Must be a string.',
    'timezone' => 'Must be a valid zone.',
    'ulid' => 'Must be a valid ULID.',
    'unique' => 'This value has already been taken.',
    'uploaded' => 'Failed to upload.',
    'uppercase' => 'Must be uppercase.',
    'url' => 'Must be a valid URL.',
    'uuid' => 'Must be a valid UUID.',

    // ---
    // UAMS Web Overrides to Laravel Core Validation Language Lines
    // ---

    'after' => [
        'date' => 'Must be a date after :date.',
        'time' => 'Must be a time after :date.',
        'datetime' => 'Must be a date and time after :date.',
    ],
    'after_or_equal' => [
        'date' => 'Must be a date after or equal to :date.',
        'time' => 'Must be a time after or equal to :date.',
        'datetime' => 'Must be a date and time after or equal to :date.',
    ],
    'before' => [
        'date' => 'Must be a date before :date.',
        'time' => 'Must be a time before :date.',
        'datetime' => 'Must be a date and time before :date.',
    ],
    'before_or_equal' => [
        'date' => 'Must be a date before or equal to :date.',
        'time' => 'Must be a time before or equal to :date.',
        'datetime' => 'Must be a date and time before or equal to :date.',
    ],
    'date_equals' => [
        'date' => 'Must be a date equal to :date.',
        'time' => 'Must be a time equal to :date.',
        'datetime' => 'Must be a date and time equal to :date.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Statamic Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may validation messages for the custom rules provided by Statamic.
    |
    */

    // ---
    // Statamic Core Custom Statamic Validation Language Lines
    // ---

    'arr_fieldtype' => 'This is invalid.',
    'handle' => 'Must contain only lowercase letters and numbers with underscores as separators.',
    'handle_starts_with_number' => 'Cannot start with a number.',
    'slug' => 'Must contain only letters and numbers with dashes or underscores as separators.',
    'code_fieldtype_rulers' => 'This is invalid.',
    'composer_package' => 'Must be a valid composer package name (eg. hasselhoff/kung-fury).',
    'date_fieldtype_date_required' => 'Date is required.',
    'date_fieldtype_end_date_invalid' => 'Not a valid end date.',
    'date_fieldtype_end_date_required' => 'End date is required.',
    'date_fieldtype_only_single_mode_allowed' => 'You can only use "Single" mode when the field handle is date.',
    'date_fieldtype_start_date_invalid' => 'Not a valid start date.',
    'date_fieldtype_start_date_required' => 'Start date is required.',
    'date_fieldtype_time_required' => 'Time is required.',
    // 'duplicate_field_handle' => 'A field with a handle of :handle already exists.',
    'duplicate_uri' => 'Duplicate URI :value',
    'email_available' => 'A user with this email already exists.',
    'fieldset_imported_recursively' => 'Fieldset :handle is being imported recursively.',
    'one_site_without_origin' => 'At least one site must not have an origin.',
    'options_require_keys' => 'All options must have keys.',
    'origin_cannot_be_disabled' => 'Cannot select a disabled origin.',
    'parent_cannot_be_itself' => 'Cannot be its own parent.',
    'parent_causes_root_children' => 'This would cause the root page to have children.',
    'parent_exceeds_max_depth' => 'This would exceed the maximum depth.',
    'reserved' => 'This is a reserved word.',
    'reserved_field_handle' => 'Field with a handle of :handle is a reserved word.',
    'unique_entry_value' => 'This value has already been taken.',
    'unique_form_handle' => 'This value has already been taken.',
    'unique_term_value' => 'This value has already been taken.',
    'unique_user_value' => 'This value has already been taken.',
    'unique_uri' => 'This URI has already been taken.',
    'time' => 'Not a valid time.',
    'asset_current_filename' => 'This is the current filename.',
    'asset_file_exists' => 'A file already exists with this name.',
    'asset_file_exists_same_content' => 'A file already exists with this name and has the same content. You may want to delete this rather than rename it.',
    'asset_file_exists_different_content' => 'A file already exists with this name but has different content. You may want to replace the other file with this one instead.',

    // ---
    // Statamic Peak Starter Kit (studio1902/statamic-peak) Overrides to Statamic Core
    // Custom Statamic Validation Language Lines
    // ---

    'duplicate_field_handle' => 'Field with a handle of :handle cannot be used more than once.',

    // ---
    // UAMS Web Custom Statamic Validation Language Lines
    // ---

    'ahaid' => 'The value must be a valid American Hospital Association Hospital Identifier with exactly seven digits (e.g., \'1234567\').',
    'ascii_identifier' => [
        'invalid_format' => 'The :attribute can only contain letters (A–Z), numbers, and underscores (_), and must start with a letter or an underscore. For example, \'weight_kg\' is valid, but \'weight-kg\' and \'1weight\' are not.',
        'invalid_type' => 'The :attribute must be a single value, not a list.',
    ],
    'asset_validation_satisfied' => [
        'default' => 'The selected asset is invalid.',
        'unsatisfied' => 'The selected asset does not satisfy validation requirements.',
    ],
    'board_certification_term' => [
        'not_found' => 'The selected certification term (:id) could not be found.',
        'wrong_taxonomy' => 'The selected term (:id) must belong to the certifications taxonomy (found: :taxonomy).',
        'wrong_query_flag' => 'The selected certification must be a board certification.',
    ],
    'other_certification_term' => [
        'not_found' => 'The selected certification term (:id) could not be found.',
        'wrong_taxonomy' => 'The selected term (:id) must belong to the certifications taxonomy (found: :taxonomy).',
        'wrong_query_flag' => 'The selected certification must not be a board certification.',
    ],
    'specialty_taxonomy_term_type' => [
        'invalid_rule_config' => 'The specialty taxonomy term rule uses unknown parameters ":alias". Use the same token as in `query_scopes` (e.g., `specialty`, `subspecialty_acgme`, `medical_research_specialty`) or a comma form such as `specialty,medical`, `subspecialty,non-medical`, or shorthand `medical_specialty`.',
        'not_found' => 'The selected specialty taxonomy term (:id) could not be found.',
        'wrong_taxonomy' => 'The selected term (:id) is not in the taxonomy required for this rule (found: :taxonomy).',
        'wrong_type' => 'The selected term (:id) has specialty/subspecialty type ":type" but must have type ":expected" for this rule.',
        'wrong_medical_query' => 'The selected term (:id) must :negationbe flagged as a medical research specialty or subspecialty (found: :actual).',
    ],
    'professional_development_activity_term' => [
        'not_found' => 'The selected professional or leadership development activity term (:id) could not be found.',
        'wrong_taxonomy' => 'The selected term (:id) must belong to the professional and leadership development activities taxonomy (found: :taxonomy).',
        'wrong_type' => 'The selected term (:id) has type ":type" but must have type ":expected" for this rule.',
    ],
    'leadership_development_activity_term' => [
        'not_found' => 'The selected professional or leadership development activity term (:id) could not be found.',
        'wrong_taxonomy' => 'The selected term (:id) must belong to the professional and leadership development activities taxonomy (found: :taxonomy).',
        'wrong_type' => 'The selected term (:id) has type ":type" but must have type ":expected" for this rule.',
    ],
    'not_top_level_organization' => [
        'top_level' => ':title is at the top of the organizations tree, so it would cover nearly everyone. Choose the college, institute, service line or department instead.',
    ],
    'credential_type_certification' => [
        'not_found' => 'The selected credential term (:id) could not be found.',
        'wrong_taxonomy' => 'The selected term (:id) must belong to the credentials taxonomy (found: :taxonomy).',
        'wrong_ctdl_ceterms_type' => 'The selected credential term (:id) must have CTDL ceterms_type ":expected" before evaluating credential subtype (found: :root).',
        'wrong_credential_type' => 'The selected credential term (:id) has CTDL type ":type", which this certification\'s kind does not accept (accepted: :expected).',
    ],
    'cms_specialty_code' => 'The value must be a valid Centers for Medicare & Medicaid Services Specialty Code with exactly one uppercase alphanumeric character followed by one digit (e.g., \'A1\' or \'12\').',
    'cage_code' => 'The value must be a valid Commercial and Government Entity (CAGE) code: one digit, three characters (excluding I and O), then one digit (e.g., \'1QJY4\').',
    'css_selector_syntax' => 'The :attribute is not valid CSS selector syntax.',
    'decimal_valid' => 'The value must be a valid decimal number.',
    'duns_number' => 'The value must be a valid Dun & Bradstreet Data Universal Numbering System number with exactly nine digits (e.g., \'123456789\').',
    'epic_dep_id' => 'The value must be a valid UAMS Health Epic Department (DEP) ID consisting of one or more digits (e.g., \'1\', \'123\', \'10502\').',
    'global_location_number' => [
        'format' => 'The Global Location Number must be a 13-digit number.',
        'invalid' => 'The provided Global Location Number is not valid.',
    ],
    'google_business_profile_cid' => 'The value must be a valid Google CID with 14 to 20 digits (e.g., \'12345678901234\' or \'12345678901234567890\').',
    'google_scholar_author_id' => 'The value must be a valid Google Scholar Author ID with exactly 12 characters, using only hyphens, underscores, digits or letters (e.g., \'ABC123-def45\' or \'xyz_789-0123\').',
    'google_tag_manager_container_id' => 'The value must be a valid Google Tag Manager Container ID starting with \'GTM-\' followed by six to eight uppercase alphanumeric characters (e.g., \'GTM-ABC123\', \'GTM-1234ABC\' or \'GTM-ABCDEFGH\').',
    'gs1_modulo_10' => [
        'format' => 'The value must be a 13-digit number for GS1 modulo-10 validation.',
        'check_digit_failed' => 'The value failed GS1 modulo-10 check digit validation.',
    ],
    'health_care_provider_taxonomy_code' => 'The value must be a valid code from the Health Care Provider Taxonomy Code Set beginning with three digits, followed by two alphanumeric characters, four digits and \'X\' (e.g., \'123AB4567X\').',
    'international_phonetic_alphabet' => [
        'invalid' => 'The :attribute is not valid International Phonetic Alphabet (IPA) notation.',
        'delimiters_disallowed' => 'The :attribute must not include IPA delimiters (slashes or square brackets). Use bare transcription, or allow delimiters in the validation rule.',
    ],
    'isco_08_code' => 'The value must be a valid ISCO-08 Code with one to four digits (e.g., \'1234\').',
    'isic_rev4_code' => 'The value must be a valid ISIC Revision 4 Code, either a single uppercase letter from \'A\' to \'U\', or two to four digits (e.g., \'A\' or \'1234\').',
    'ipeds_id' => 'The value must be a valid Integrated Postsecondary Education Data System (IPEDS) ID with exactly six digits (e.g., \'106397\').',
    'iso6523_code' => 'The value must be a valid ISO 6523 code with exactly four digits, a colon, and one to 35 uppercase alphanumeric characters, optionally followed by another colon and one to 35 uppercase alphanumeric characters (e.g., \'1234:ABC123\' or \'5678:XYZ789:DEF456\').',
    'lat_lng' => [
        'comma_separated' => 'The value must be a comma-separated pair of latitude and longitude (e.g., "37.7749,-122.4194").',
    ],
    'latitude' => 'The value must be a decimal number between -90.0 and 90.0.',
    'legal_entity_identifier' => 'The value must be a valid Legal Entity Identifier with exactly 18 uppercase alphanumeric characters followed by exactly two digits (e.g., \'123456789ABCDEFGHI12\').',
    'likert_scale' => [
        'not_on_scale' => 'Choose a rating from the scale.',
        'row_label_required' => 'Each row label must have a value.',
        'unknown_row' => 'Rate only the rows this question lists.',
    ],
    'longitude' => 'The value must be a decimal number between -180.0 and 180.0.',
    'luhn' => [
        'format' => 'The value must contain only numeric digits for Luhn algorithm validation.',
        'check_failed' => 'The value failed Luhn algorithm validation.',
    ],
    'month' => [
        'after_or_equal' => 'The month must be after or equal to :min.',
        'before_or_equal' => 'The month must be before or equal to :max.',
    ],
    'naics_code' => 'The value must be a valid North American Industry Classification System code with two to six digits (e.g., \'12\' or \'123456\').',
    'name' => 'You can only use English letters (including diacritics like \'é\', \'ü\', \'ç\'), spaces, periods (restricted to initials like \'J.R.\'), and other common punctuation found in names like dashes and apostrophes.',
    'npi' => [
        'format' => 'The value must be 10 digits (e.g., \'1234567890\').',
        'invalid' => 'The provided NPI is not valid.',
        'api_error' => 'Unable to validate NPI due to an API error. Please try again later.',
        'not_found' => 'The provided NPI does not exist in the National Plan and Provider Enumeration System registry.',
    ],
    'onet_soc_occupation_code' => 'The value must be a valid O*Net-SOC Occupation Code beginning with two digits, followed by a hyphen, four digits, a period and two digits (e.g., \'11-1011.00\', \'13-2011.01\').',
    'ope_id' => 'The value must be a valid Office of Postsecondary Education (OPE) ID with exactly eight digits (e.g., \'00110900\').',
    'orcid' => 'The value must be a valid ORCID with four groups of exactly four characters, separated by hyphens, where the first three groups have four digits and the last group has three digits followed by a digit or \'X\' (e.g., \'1234-5678-9012-3456\' or \'1234-5678-9012-345X\').',
    'pubmed' => [
        'pmid' => [
            'format' => 'The value must be a valid PubMed Identifier starting with a non-zero digit followed by up to seven additional digits (e.g., \'1\', \'123\', \'12345678\').',
            'unverified' => 'The value is not a valid PubMed Identifier or could not be verified due to an API error.',
        ],
        'pubmed_author_id' => 'The value must be a valid PubMed Author Identifier. If using the last name plus initials, the value must begin with the author\'s last name followed by a space and the initials without punctuation (e.g., "o\'brien ja" or "de vries ja"). If using the full name, the value must be the author\'s name in natural order (e.g., "john a o\'brien" or "john a de vries") or inverted order without punctuation other than an optional comma after the last name (e.g., "o\'brien john a" or "de vries, john a").',
    ],
    'quiz_answer' => [
        'array' => 'The quiz answers must be an array.',
        'not_an_option' => 'Choose an answer from the ones this question offers.',
        'marked_correct' => [
            'exactly_one' => 'Exactly one answer must be marked as correct.',
            'at_least_one' => 'At least one answer must be marked as correct.',
        ],
    ],
    'replicator' => [
        'at_least_one_enabled_set' => 'At least one set must be enabled.',
        'min_sets' => [
            'one' => 'At least one set is required.',
            'multiple' => 'At least :min sets are required.',
        ],
        'min_sets_instructions' => 'Set a minimum number of sets.',
    ],
    'researcher_id' => 'The value must be a valid Web of Science ResearcherID with one to three uppercase letters, a hyphen, exactly four digits, another hyphen, and a four-digit year starting with \'19\' or \'20\' (e.g., \'ABC-1234-2023\' or \'Z-5678-1990\').',
    'rxcui' => 'The value must be a valid RxNorm concept unique identifier consisting of one or more digits (e.g., \'1\', \'123\', \'123456789\').',
    'scopus_author_id' => 'The value must be a valid Scopus Author ID starting with a non-zero digit followed by nine to 11 additional digits, with a total length of 10 to 12 digits (e.g., \'1234567890\' or \'567890123456\').',
    'sms_short_code' => 'The value must be a valid U.S. SMS short code consisting of five or six digits, with the first digit being 0 or any digit from 2 to 9 (e.g., \'01234\', \'012345\', \'23456\', \'234567\').',
    'subfields' => [
        'any' => [
            'one_subfield' => 'At least one subfield of :attribute must have a value.',
        ],
        'list' => [
            'one_of_subfields' => 'At least one of the following subfields of :attribute must have a value: :subfields.',
        ],
    ],
    'taxpayer_identification_number' => 'The value must be a valid U.S. Employer Identification Number starting with two digits, followed by a hyphen, and then seven digits (e.g., \'12-3456789\').',
    'dep_in_declared_list' => [
        'parent_empty' => 'Declare the location\'s Department (DEP) IDs in General Details before assigning them to a scheduling widget.',
        'not_declared' => 'The Department (DEP) ID ":dep" is not one of the location\'s declared Department (DEP) IDs. Add it in General Details first.',
    ],
    'unique_identifier' => [
        'collision' => 'The identifier ":value" is already used as the ":other" of ":entry". Each identifier must be unique, and these identifiers share one numbering space, so the same value cannot appear in two of them. Identifiers are compared ignoring capitalization and surrounding spaces.',
        'within_entry' => 'The identifier ":value" is already entered in this entry\'s ":other" field. These identifiers are issued from different numbering spaces, so a value appearing in two of them means one was entered in the wrong field. Identifiers are compared ignoring capitalization and surrounding spaces.',
    ],
    'unique_identifier_list' => [
        'collision' => 'The identifier ":value" is already used by ":entry". Each identifier must be unique across all entries, and is compared ignoring capitalization and surrounding spaces.',
        'within_entry' => 'The identifier ":value" is listed more than once. Remove the duplicate row. Identifiers are compared ignoring capitalization and surrounding spaces.',
    ],
    'terms_subset_of' => [
        'parent_empty' => 'The ":parent" field is empty and must include one or more selections before you can set terms here.',
        'not_subset' => 'Each selection in the ":attribute" field must be among those selected in the ":parent" field.',
    ],
    'uei' => 'The value must be a valid Federal/SAM.gov Unique Entity ID (UEI) with exactly 12 alphanumeric characters (e.g., \'J1XYZ12345A1\').',
    'ticker_symbol' => 'The value must be a valid NASDAQ or NYSE ticker symbol with one to five uppercase letters, optionally followed by a period and a suffix that is either a single uppercase letter or a recognized code (\'PK\', \'SC\' or \'NM\') (e.g., \'AAPL\', \'BRK.A\', \'XYZ.PK\').',
    'entry_schema_type_in' => [
        'not_found' => 'The selected entry (:id) could not be found.',
        'not_allowed' => 'The selected entry has schema type ":type". It must be one of: :allowed.',
    ],
    'schema_type' => [
        'chained_incomplete' => 'Continue the type chain, or choose “Stop here” on the next step when more specific types are available.',
    ],
    'veterans_affairs' => [
        'station_id' => 'The value must be a valid U.S. Department of Veterans Affairs Substation Identifier (STA3N) with exactly three digits (e.g., \'123\').',
        'station_substation_id' => 'The value must be a valid U.S. Department of Veterans Affairs Station Identifier (STA3N) or Substation Identifier (STA6A) with exactly three digits, optionally followed by three uppercase alphanumeric characters (e.g., \'123\', \'123AB\' or \'123AB1\').',
        'substation_id' => 'The value must be a valid U.S. Department of Veterans Affairs Substation Identifier (STA6A) with exactly three digits followed by three uppercase alphanumeric characters (e.g., \'123ABC\' or \'123456\').',
    ],
    'week' => [
        'after_or_equal' => 'The week must be after or equal to :min.',
        'before_or_equal' => 'The week must be before or equal to :max.',
    ],
    'xml_valid' => 'The URL must return valid XML content.',
    'xpath_syntax' => 'The :attribute is not valid XPath syntax.',
    'url_feed' => [
        'valid' => 'The value must be a valid Atom or RSS feed URL (e.g., \'https://example.com/feed.atom\' or \'https://example.com/feed.xml\').',
        'atom' => [
            'not_accessible' => 'The URL must be accessible and return a valid Atom feed.',
            'root_feed' => 'The URL must point to a valid Atom feed with a root `<feed>` element.',
            'atom_namespace' => 'The URL must point to a valid Atom feed in the Atom namespace (http://www.w3.org/2005/Atom).',
            'required_elements' => 'The Atom feed must contain the required elements: `<title>`, `<id>`, and `<updated>`.',
        ],
        'rss' => [
            'not_accessible' => 'The URL must be accessible and return a valid RSS feed.',
            'channel_elements' => 'The RSS feed must contain a `<channel>` element with `<title>` and `<link>` elements.',
            'rss_1_0_elements' => 'The RSS 1.0 feed must contain either a `<channel>` or `<item>` element.',
            'root_rss' => 'The URL must point to a valid RSS feed with a root `<rss>` element (RSS 2.0) or `<rdf:RDF>` element (RSS 1.0).',
        ],
    ],
    'url_tri_profile' => 'The TriProfile URL must follow the format \'https://uams-triprofiles.uams.edu/profiles/display/XXXX\', where \'XXXX\' is a numeric string (e.g., \'https://uams-triprofiles.uams.edu/profiles/display/12345\').',
    'url_first_party' => 'The value must be a UAMS URL on a UAMS-owned domain such as \'uams.edu\' or \'uamshealth.com\' (or one of its subdomains, e.g., \'https://www.uams.edu/...\').',
    'url_non_first_party' => 'The value must be an external, non-UAMS URL. UAMS-owned domains such as \'uams.edu\' and \'uamshealth.com\' (and their subdomains) are not allowed here.',
    'url_box_shared_static' => [
        'audio' => 'The value must be a valid Box.com HTTPS direct link under \'/shared/static/\' with an audio file extension that maps to a known MIME type (e.g., \'https://app.box.com/shared/static/example.wav\').',
        'base' => 'The value must be a valid Box.com HTTPS direct link under \'/shared/static/\' with a file extension that maps to a known MIME type (e.g., \'https://app.box.com/shared/static/example.mp4\').',
        'video' => 'The value must be a valid Box.com HTTPS direct link under \'/shared/static/\' with a video file extension that maps to a known MIME type (e.g., \'https://app.box.com/shared/static/example.mp4\').',
    ],
    'url_video' => [
        'uams' => 'The value must be a valid YouTube or Vimeo video URL, or a Box.com HTTPS direct link under \'/shared/static/\' with a video extension. Examples: \'https://www.youtube.com/watch?v=dQw4w9WgXcQ\', \'https://youtu.be/dQw4w9WgXcQ\', \'https://vimeo.com/123456789\', or \'https://app.box.com/shared/static/yourfile.mp4\'.',
        'vimeo' => 'The value must be a valid Vimeo video URL in the format \'https://vimeo.com/VIDEO_ID\' (e.g., \'https://vimeo.com/123456789\').',
        'youtube' => 'The value must be a valid YouTube video URL in the format \'https://www.youtube.com/watch?v=VIDEO_ID\' or \'https://youtu.be/VIDEO_ID\' (e.g., \'https://www.youtube.com/watch?v=dQw4w9WgXcQ\').',
    ],
    'zip_code' => 'The value must be a valid U.S. ZIP Code beginning with five digits, optionally followed by a hyphen and four digits (e.g., \'12345\' or \'12345-6789\').',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    // 'custom' => [
    //     'attribute-name' => [
    //         'foo' => 'bar',
    //     ],
    // ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap attribute place-holders
    | with something more reader friendly such as E-Mail Address instead
    | of "email". This simply helps us make messages a little cleaner.
    |
    */

    // ---
    // Laravel Lang: Attributes Core Custom Validation Attributes
    // ---
    //
    // Quantitative Value (`quantitative_value`): nested keys use dotted paths
    // like `{handle}.value` / `{handle}.unit_code`. Friendly labels come from
    // `statamic::fieldtypes.quantitative_value.field.*` and
    // `statamic::fieldtypes.quantitative_value.validation.duration_subfield`
    // in `lang/vendor/statamic/en/fieldtypes.php` via the fieldtype’s
    // `extraValidationAttributes()`, not from this `attributes` map.

    'attributes' => [
        'address' => 'address',
        'affiliate_url' => 'affiliate URL',
        'age' => 'age',
        'amount' => 'amount',
        'announcement' => 'announcement',
        'area' => 'area',
        'audience_prize' => 'audience prize',
        'audience_winner' => 'audience winner',
        'available' => 'available',
        'birthday' => 'birthday',
        'body' => 'body',
        'city' => 'city',
        'company' => 'company',
        'compilation' => 'compilation',
        'concept' => 'concept',
        'conditions' => 'conditions',
        'content' => 'content',
        'contest' => 'contest',
        'country' => 'country',
        'cover' => 'cover',
        'created_at' => 'created at',
        'creator' => 'creator',
        'currency' => 'currency',
        'current_password' => 'current password',
        'customer' => 'customer',
        'date' => 'date',
        'date_of_birth' => 'date of birth',
        'dates' => 'dates',
        'day' => 'day',
        'deleted_at' => 'deleted at',
        'description' => 'description',
        'display_type' => 'display type',
        'district' => 'district',
        'duration' => 'duration',
        'email' => 'email',
        'excerpt' => 'excerpt',
        'filter' => 'filter',
        'finished_at' => 'finished at',
        'first_name' => 'first name',
        'gender' => 'gender',
        'grand_prize' => 'grand prize',
        'group' => 'group',
        'hour' => 'hour',
        'image' => 'image',
        'image_desktop' => 'desktop image',
        'image_main' => 'main image',
        'image_mobile' => 'mobile image',
        'images' => 'images',
        'is_audience_winner' => 'is audience winner',
        'is_hidden' => 'is hidden',
        'is_subscribed' => 'is subscribed',
        'is_visible' => 'is visible',
        'is_winner' => 'is winner',
        'items' => 'items',
        'key' => 'key',
        'last_name' => 'last name',
        'lesson' => 'lesson',
        'line_address_1' => 'line address 1',
        'line_address_2' => 'line address 2',
        'login' => 'login',
        'message' => 'message',
        'middle_name' => 'middle name',
        'minute' => 'minute',
        'mobile' => 'mobile',
        'month' => 'month',
        'name' => 'name',
        'national_code' => 'national code',
        'number' => 'number',
        'password' => 'password',
        'password_confirmation' => 'password confirmation',
        'phone' => 'phone',
        'photo' => 'photo',
        'portfolio' => 'portfolio',
        'postal_code' => 'postal code',
        'preview' => 'preview',
        'price' => 'price',
        'product_id' => 'product ID',
        'product_uid' => 'product UID',
        'product_uuid' => 'product UUID',
        'promo_code' => 'promo code',
        'province' => 'province',
        'quantity' => 'quantity',
        'reason' => 'reason',
        'recaptcha_response_field' => 'recaptcha response field',
        'referee' => 'referee',
        'referees' => 'referees',
        'reject_reason' => 'reject reason',
        'remember' => 'remember',
        'restored_at' => 'restored at',
        'result_text_under_image' => 'result text under image',
        'role' => 'role',
        'rule' => 'rule',
        'rules' => 'rules',
        'second' => 'second',
        'sex' => 'sex',
        'shipment' => 'shipment',
        'short_text' => 'short text',
        'size' => 'size',
        'skills' => 'skills',
        'slug' => 'slug',
        'specialization' => 'specialization',
        'started_at' => 'started at',
        'state' => 'state',
        'status' => 'status',
        'street' => 'street',
        'student' => 'student',
        'subject' => 'subject',
        'tag' => 'tag',
        'tags' => 'tags',
        'teacher' => 'teacher',
        'terms' => 'terms',
        'test_description' => 'test description',
        'test_locale' => 'test locale',
        'test_name' => 'test name',
        'text' => 'text',
        'time' => 'time',
        'title' => 'title',
        'type' => 'type',
        'updated_at' => 'updated at',
        'user' => 'user',
        'username' => 'username',
        'value' => 'value',
        'winner' => 'winner',
        'work' => 'work',
        'year' => 'year',
    ],
];
