# Custom Assets Fieldtype

## Overview

The custom Assets fieldtype extends Statamic's base Assets fieldtype to add file type restrictions and email attachment functionality. This fieldtype provides all the standard asset management features while adding configuration options to restrict allowed file types and specify whether files should be attached to email notifications.

## Features

- **Full Asset Management**: Inherits all functionality from Statamic's base Assets fieldtype
- **File Type Restrictions**: Configurable options to restrict allowed file types by MIME types or categories
- **File Size Restrictions**: Configurable minimum and maximum file size limits with automatic validation
- **Query Scopes**: When file type restrictions are set, the field adds the `assets_by_file_type` query scope and params so the asset browser can filter by type when scope params are passed (e.g. by a future Statamic mechanism). Until then, only server-side validation applies for the browser.
- **Email Attachment Toggle**: Configurable option to attach files to email notifications
- **Form Integration**: Designed to work with Statamic forms and email notifications

## Configuration

### File Type Restrictions

The fieldtype adds file type restriction controls in a new "File Type Restrictions" section of the field configuration:

#### File Type Restrictions Radio Button
- **Display Name**: "File Type Restrictions"
- **Type**: Radio
- **Default**: "Allow all file types"
- **Options**:
  - "Allow all file types" (`all`) - No restrictions
  - "Specify allowed file types" (`mime_types`) - Restrict by specific MIME types
  - "Specify allowed file type categories" (`mime_categories`) - Restrict by MIME type categories

#### Allowed File Types (when "Specify allowed file types" is selected)
- **Display Name**: "Allowed File Types"
- **Type**: Select (with preset)
- **Preset**: `mime_types`
- **Multiple**: `true`
- **Clearable**: `true`
- **Instructions**: "Select the specific file types that are allowed."

#### Allowed File Type Categories (when "Specify allowed file type categories" is selected)
- **Display Name**: "Allowed File Type Categories"
- **Type**: Select (with preset)
- **Preset**: `mime_categories`
- **Multiple**: `true`
- **Clearable**: `true`
- **Instructions**: "Select the file type categories that are allowed."

#### File Size Restrictions
The fieldtype adds file size restriction controls in the "File Restrictions" section:

##### Minimum File Size
- **Display Name**: "Minimum File Size (MB)"
- **Type**: Float
- **Instructions**: "Set the minimum file size limit for each file uploaded."

##### Maximum File Size
- **Display Name**: "Maximum File Size (MB)"
- **Type**: Float
- **Instructions**: "Set the maximum file size limit for each file uploaded."

**Note**: File sizes are specified in megabytes (MB) and are automatically converted to kilobytes (KB) for Laravel validation. Both fields are optional - you can set only minimum, only maximum, or both values.

### Email Attachment Setting

The fieldtype adds an "Attach to Email" toggle in the "Appearance & Behavior" section of the field configuration:

- **Display Name**: "Attach to Email"
- **Type**: Toggle
- **Default**: `false`
- **Instructions**: "Attach files from this field to email notifications when used in forms. Note: Email notification settings in the form configuration must have 'Attachments' enabled for files to be attached."

**Note**: This configuration field is only available when the Assets fieldtype is used within form blueprints. It will not appear in collection, taxonomy, or other blueprint contexts.

## Usage

### In Blueprints

When creating a blueprint, you can now use the custom Assets fieldtype which will include the email attachment configuration option. The email attachment configuration is only available in form blueprints and will not appear in collection, taxonomy, or other blueprint contexts.

### In Forms

When this fieldtype is used in a form:

1. The "Attach to Email" toggle must be enabled for the field
2. The form's email notification settings must have "Attachments" enabled
3. Files uploaded through this field will be automatically attached to email notifications

### Example Blueprint Configuration

#### Basic Configuration
```yaml
fields:
  -
    handle: documents
    field:
      type: assets
      display: Documents
      instructions: Upload documents that will be attached to email notifications
      config:
        container: files
        max_files: 5
        attach_to_email: true
```

#### With File Type Restrictions
```yaml
fields:
  -
    handle: images
    field:
      type: assets
      display: Images
      instructions: Upload image files only
      config:
        container: images
        max_files: 10
        file_type_restrictions: mime_categories
        mime_categories:
          - image
```

#### With Specific MIME Types
```yaml
fields:
  -
    handle: documents
    field:
      type: assets
      display: Documents
      instructions: Upload PDF and Word documents only
      config:
        container: files
        max_files: 5
        file_type_restrictions: mime_types
        mime_types:
          - application/pdf
          - application/msword
          - application/vnd.openxmlformats-officedocument.wordprocessingml.document
```

#### With File Size Restrictions
```yaml
fields:
  -
    handle: images
    field:
      type: assets
      display: Images
      instructions: Upload images between 1MB and 10MB
      config:
        container: images
        max_files: 10
        min_file_size: 1.0
        max_file_size: 10.0
```

#### With File Type and Size Restrictions
```yaml
fields:
  -
    handle: documents
    field:
      type: assets
      display: Documents
      instructions: Upload PDF documents between 0.5MB and 5MB
      config:
        container: files
        max_files: 5
        file_type_restrictions: mime_types
        mime_types:
          - application/pdf
        min_file_size: 0.5
        max_file_size: 5.0
```

## Technical Details

### Class Structure

- **Namespace**: `App\Fieldtypes`
- **Base Class**: `Statamic\Fieldtypes\Assets\Assets`
- **Traits Used**: `ConfigSectionWrapper`, `FormBlueprintContext`

### Method Overrides

The fieldtype overrides several methods to add functionality:

#### `configFieldItems()`
Adds file type restriction controls to a new "File Type Restrictions" section and the email attachment configuration field to the existing "Appearance & Behavior" section.

#### `getValidationRules()`
Generates Laravel validation rules based on the file type restrictions and file size restrictions:
- For `mime_types`: Adds `mimetypes` validation rule
- For `mime_categories`: Adds both `mimetypes` and `extensions` validation rules
- For file size restrictions:
  - If only `min_file_size` is set: Adds `min` validation rule
  - If only `max_file_size` is set: Adds `max` validation rule
  - If both are set: Adds `between` validation rule
- File sizes are automatically converted from MB to KB for Laravel validation

#### `getAcceptAttribute()`
Generates the HTML `accept` attribute value from file type restrictions (kept for docs or future use). The CP uses Statamic’s core Assets fieldtype and does not use this for the upload input.

#### `preload()`
Adds the full config to preload. When file type restrictions are set, merges `query_scopes` to include `assets_by_file_type` and adds `query_scope_params` (categories for mime_categories, mime_types for mime_types) so the asset browser can filter when scope params are passed. Does not add `accept` to config (no consumer in the CP).

### Context Detection

The fieldtype uses the `FormBlueprintContext` trait to detect whether it's being configured within a form blueprint context. This allows the email attachment configuration to only appear when the field is used in forms, not in collections or other blueprint types.

### MIME Type Integration

The fieldtype integrates with the `App\Enums\MimeType` enum to:
- Find MIME types by categories using `MimeType::findByCategories()`
- Generate validation rules and accept attributes based on selected MIME types and categories

### Testing

The fieldtype includes comprehensive unit tests that verify:
- The file type restriction configuration fields are properly added
- The file size restriction configuration fields are properly added
- The email attachment configuration field is properly added
- The fieldtype correctly extends the base Assets fieldtype
- The ConfigSectionWrapper trait is properly used
- Validation rules are generated correctly for different restriction types
- File size validation rules are generated correctly for different size configurations
- Accept attributes are generated correctly for browser file filtering
- MIME type enum integration works correctly

## Dependencies

- Statamic CMS
- Laravel Framework
- `App\Fieldtypes\Traits\ConfigSectionWrapper` trait
- `UAMSWeb\Aether\Forms\Settings\Fieldtypes\Traits\FormBlueprintContext` trait (from `uams-web/aether-form-settings`)
- `App\Enums\MimeType` enum

## Notes

- This fieldtype is designed to work with Statamic's form system
- Email attachment functionality requires proper configuration in both the field and the form's email notification settings
- File type restrictions are enforced on the backend via Laravel validation. The CP uses Statamic’s core Assets fieldtype; there is no `accept` on the upload input or client-side drag-drop validation. The asset browser can filter by type when the `assets_by_file_type` scope receives params (e.g. when Statamic or another mechanism passes them).
- File size restrictions are enforced through Laravel validation on the backend
- The fieldtype maintains full compatibility with all existing Assets fieldtype features
- The email attachment configuration is only available in form blueprints and will not appear in other blueprint contexts
- File type and size restrictions are available in all blueprint contexts (collections, taxonomies, forms, etc.)
- File sizes are specified in megabytes (MB) in the configuration but are automatically converted to kilobytes (KB) for Laravel validation
