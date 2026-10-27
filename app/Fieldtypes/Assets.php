<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Enums\MimeType;
use App\Fieldtypes\Traits\ConfigSectionWrapper;
use App\Fieldtypes\Traits\FieldtypeValidationRules;
use App\Rules\AssetMinimumDimensions;
use App\Scopes\AssetsByFileSize;
use Statamic\Fields\Field;
use Statamic\Fieldtypes\Assets\Assets as BaseAssets;
use UAMSWeb\FormSettings\Fieldtypes\Traits\FormBlueprintContext;

/**
 * Custom Assets fieldtype that extends Statamic's Assets to support email
 * attachment configuration.
 *
 * This fieldtype adds an "Attach to Email" toggle to the config panel, allowing
 * users to specify whether files from this assets field should be attached to
 * email notifications. It uses the ConfigSectionWrapper trait to inject the
 * custom config field into the existing Statamic Assets fieldtype configuration.
 */
class Assets extends BaseAssets
{
    /**
     * Trait for wrapping and extending Statamic fieldtype config sections.
     *
     * Provides helper methods to inject or modify configuration fields in the parent
     * fieldtype's config, allowing for clean extension and organization of config
     * options without duplicating parent logic.
     */
    use ConfigSectionWrapper;

    /**
     * Trait for integrating custom validation rules with Statamic's validation system.
     *
     * Provides the extraRules() method to properly integrate custom validation rules
     * with Statamic's built-in validation system without interfering with the rules()
     * method.
     */
    use FieldtypeValidationRules;

    /**
     * Trait for detecting form blueprint context in fieldtypes.
     *
     * Provides methods to determine if a fieldtype is being configured within a form
     * blueprint context, allowing for conditional configuration based on the
     * blueprint type (forms vs collections vs other blueprint types).
     */
    use FormBlueprintContext;

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        /** @var string $icon */
        $icon = parent::icon();

        return $icon;
    }

    /**
     * Extend the config to add additional configuration fields available for this
     * fieldtype in the blueprint.
     *
     * Adds the following config fields:
     *   - File type restriction controls in a new 'File Type Restrictions' section
     *   - Email attachment toggle in the 'Appearance' section (form context only)
     *
     * @return array<int, mixed> The configuration field items
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        // Get parent configuration
        /** @var array<array<string, mixed>> $parentConfig */
        $parentConfig = parent::configFieldItems();

        // Initialize array for fields to add to existing sections
        $fieldsToAdd = [];

        // Initialize array for new sections
        $newSections = [];

        // Add file type restriction fields to a new 'File Type Restrictions' section
        $newSections[] = [
            'display' => __('File Restrictions'),
            'fields' => [
                'file_type_restrictions_section' => [
                    'display' => __('File Type'),
                    'type' => 'subsection',
                ],
                'file_type_restrictions' => [
                    'display' => __('File Type Restrictions'),
                    'instructions' => __('Choose how to restrict allowed file types.'),
                    'type' => 'radio',
                    'options' => [
                        'all' => __('Allow all file types'),
                        'mime_types' => __('Specify allowed file types'),
                        'mime_categories' => __('Specify allowed file type categories'),
                    ],
                    'default' => 'all',
                ],
                'mime_types' => [
                    'display' => __('Allowed File Types'),
                    'instructions' => __('Select the specific file types that are allowed.'),
                    'type' => 'select',
                    'preset' => 'mime_types',
                    'multiple' => true,
                    'clearable' => true,
                    'if' => [
                        'file_type_restrictions' => 'mime_types',
                    ],
                ],
                'mime_categories' => [
                    'display' => __('Allowed File Type Categories'),
                    'instructions' => __('Select the file type categories that are allowed.'),
                    'type' => 'select',
                    'preset' => 'mime_categories',
                    'multiple' => true,
                    'clearable' => true,
                    'if' => [
                        'file_type_restrictions' => 'mime_categories',
                    ],
                ],
                'file_size_restrictions_section' => [
                    'display' => __('File Size'),
                    'type' => 'subsection',
                ],
                'min_file_size' => [
                    'display' => __('Minimum File Size'),
                    'instructions' => __('Set the minimum file size **in megabytes** for each file uploaded.'),
                    'type' => 'float',
                ],
                'max_file_size' => [
                    'display' => __('Maximum File Size'),
                    'instructions' => __('Set the maximum file size **in megabytes** for each file uploaded.'),
                    'type' => 'float',
                ],
                'image_dimensions_section' => [
                    'display' => __('Image Dimensions'),
                    'type' => 'subsection',
                    'instructions' => __('Minimum pixel dimensions for uploaded **images**. Only enforced when the field is restricted to images (the browser reads image dimensions with `getimagesize`).'),
                    'if' => [
                        'file_type_restrictions' => 'not all',
                    ],
                ],
                'min_width' => [
                    'display' => __('Minimum Width'),
                    'instructions' => __('Reject uploaded images narrower than this **width in pixels**. Leave blank for no minimum.'),
                    'type' => 'integer',
                    'if' => [
                        'file_type_restrictions' => 'not all',
                    ],
                ],
                'min_height' => [
                    'display' => __('Minimum Height'),
                    'instructions' => __('Reject uploaded images shorter than this **height in pixels**. Leave blank for no minimum.'),
                    'type' => 'integer',
                    'if' => [
                        'file_type_restrictions' => 'not all',
                    ],
                ],
            ],
        ];

        // Check if the fieldtype is being configured within a Form blueprint context.
        // Section key must match Statamic v6 base Assets: "Appearance".
        if ($this->isFormBlueprintContext()) {
            // Add email attachment field to the end of Appearance section
            $fieldsToAdd[__('Appearance')] = [
                'attach_to_email' => [
                    'display' => __('Attach to Email'),
                    'instructions' => __('Attach files from this field to email notifications. Note: Email notification settings in the form configuration must have "Attachments" enabled for files to be attached.'),
                    'type' => 'toggle',
                    'default' => false,
                ],
            ];
        }

        // Add fields to existing sections using the trait method
        /** @var array<int, mixed> $result */
        $result = $this->addFieldsToExistingSections(
            $fieldsToAdd,
            $parentConfig
        );

        // Loop through new sections and add them to the result array
        foreach ($newSections as $section) {
            // Add the new section to the result
            $result[] = $section;
        }

        // Clean up variables to free up memory
        unset(
            $newSections
        );

        // Return the result
        return $result;
    }

    /**
     * Provide additional validation rules for this fieldtype. Applies file type
     * restrictions and file size restrictions based on configuration.
     *
     * @param  mixed  $value  The value to validate
     * @param  array<string, mixed>  $config  The field config
     * @return array<int, mixed> Array of validation rules (Laravel format)
     */
    public function getValidationRules(
        $value,
        array $config = []
    ): array {
        // Start with base validation rules from parent
        /** @var array<int, mixed> $rules */
        $rules = parent::rules();

        // Get the file type restriction setting
        $restrictionType = $config['file_type_restrictions'] ?? 'all';

        // Check if the restriction type is MIME types
        if ($restrictionType === 'mime_types') {
            // Get the allowed MIME types
            /** @var array<int, string> $allowedMimeTypes */
            $allowedMimeTypes = (array) ($config['mime_types'] ?? []);

            // Check if there are allowed MIME types
            if (! empty($allowedMimeTypes)) {
                // ---
                // Generate the MIME types validation rule
                // (e.g., 'mimetypes:image/jpeg,image/png,image/gif')
                // ---
                $mimetypesRule = 'mimetypes:'.\implode(
                    ',',
                    $allowedMimeTypes
                );

                // Add the MIME types validation rule to the rules array
                $rules[] = $mimetypesRule;

            }
        } elseif ($restrictionType === 'mime_categories') { // Check if the restriction type is MIME categories
            // Get the allowed MIME categories
            /** @var array<int, string> $allowedCategories */
            $allowedCategories = (array) ($config['mime_categories'] ?? []);

            // Check if there are allowed MIME categories
            if (! empty($allowedCategories)) {
                // Get all MIME types for the selected categories (e.g., ['image', 'video'])
                $mimeTypes = MimeType::findByCategories($allowedCategories);

                // Initialize array for allowed MIME types
                $allowedMimeTypes = [];

                // Initialize array for allowed extensions
                $allowedExtensions = [];

                // Loop through the MIME types
                foreach ($mimeTypes as $mimeType) {
                    // Add MIME types to the allowed MIME types array
                    $allowedMimeTypes[] = $mimeType->mimeType();

                    // Add extensions to the allowed extensions array
                    $allowedExtensions = \array_merge(
                        $allowedExtensions,
                        $mimeType->extensions()
                    );
                }

                // Check if there are allowed MIME types
                if ($allowedMimeTypes !== []) {
                    // ---
                    // Generate the MIME types validation rule
                    // (e.g., 'mimetypes:image/jpeg,image/png,image/gif')
                    // ---
                    $mimetypesRule = 'mimetypes:'.\implode(
                        ',',
                        $allowedMimeTypes
                    );

                    // Add the MIME types validation rule to the rules array
                    $rules[] = $mimetypesRule;

                }

                // Check if there are allowed extensions
                if ($allowedExtensions !== []) {
                    // Generate the extensions validation rule (e.g., 'extensions:jpg,jpeg,png,gif')
                    $extensionsRule = 'extensions:'.\implode(
                        ',',
                        $allowedExtensions
                    );

                    // Add the extensions validation rule to the rules array
                    $rules[] = $extensionsRule;

                }

                // Clean up variables to free up memory
                unset(
                    $mimeTypes,
                    $allowedMimeTypes,
                    $allowedExtensions
                );
            }
        }

        // Get the minimum file size (e.g., 1 MB)
        $minFileSizeMBRaw = $config['min_file_size'] ?? null;
        // A numeric string multiplies the same as its float below (RemoveDoubleCast is equivalent)
        // @pest-mutate-ignore: RemoveDoubleCast
        $minFileSizeMB = is_numeric($minFileSizeMBRaw) ? (float) $minFileSizeMBRaw : null;

        // ---
        // If the minimum file size is set, convert it to kilobytes for validation
        // (1 MB = 1000 KB)
        // ---
        $minFileSizeKB = ($minFileSizeMB !== null) ? (int) ($minFileSizeMB * 1000) : null;

        // Get the maximum file size (e.g., 10 MB)
        $maxFileSizeMBRaw = $config['max_file_size'] ?? null;
        // A numeric string multiplies the same as its float below (RemoveDoubleCast is equivalent)
        // @pest-mutate-ignore: RemoveDoubleCast
        $maxFileSizeMB = is_numeric($maxFileSizeMBRaw) ? (float) $maxFileSizeMBRaw : null;

        // ---
        // If the maximum file size is set, convert it to kilobytes for validation
        // (1 MB = 1000 KB)
        // ---
        $maxFileSizeKB = ($maxFileSizeMB !== null) ? (int) ($maxFileSizeMB * 1000) : null;

        // Check if both min and max are set
        if ($minFileSizeKB !== null && $maxFileSizeKB !== null) {
            // Add the between validation rule (e.g., 'between:1000,10000')
            $rules[] = sprintf('between:%d,%d', $minFileSizeKB, $maxFileSizeKB);
        } elseif ($minFileSizeKB !== null) { // Check if only min is set
            // Add the min validation rule (e.g., 'min:1000')
            $rules[] = 'min:'.$minFileSizeKB;
        } elseif ($maxFileSizeKB !== null) { // Check if only max is set
            // Add the max validation rule (e.g., 'max:10000')
            $rules[] = 'max:'.$maxFileSizeKB;
        }

        // Reject undersized images by their STORED dimension meta (Asset::width()/
        // height()) rather than Laravel's `dimensions` rule, which re-reads the
        // upload with getimagesize(). Reading meta enforces the minimum on
        // SELECTION as well as upload, and is HEIC-safe: an asset the image driver
        // could not measure is skipped, never false-rejected (UAMS-Web/uams-statamic-archive#1299). Still
        // image-only — only added when the field is restricted to images
        // exclusively, so a minimum on a mixed field can never reject a non-image
        // (UAMS-Web/uams-statamic-archive#192 guard). Video minimum dimensions remain out of scope.
        $minWidth = is_numeric($config['min_width'] ?? null) ? max(0, (int) $config['min_width']) : 0;
        $minHeight = is_numeric($config['min_height'] ?? null) ? max(0, (int) $config['min_height']) : 0;

        if (($minWidth > 0 || $minHeight > 0) && $this->restrictsToImagesOnly($config)) {
            $rules[] = new AssetMinimumDimensions(
                $minWidth,
                $minHeight,
                $this->configuredContainers($config),
            );
        }

        // Clean up variables to free up memory
        unset(
            $restrictionType,
            $minFileSizeMBRaw,
            $maxFileSizeMBRaw,
            $minFileSizeMB,
            $maxFileSizeMB,
            $minFileSizeKB,
            $maxFileSizeKB,
            $minWidth,
            $minHeight
        );

        // Return the merged rules
        return $rules;
    }

    /**
     * Whether this field is restricted to image uploads exclusively, so the
     * image-only Laravel `dimensions` rule can be applied without ever
     * false-rejecting a non-image upload.
     *
     * True only when every allowed type is an image — the `image` MIME category
     * (and nothing else), or `mime_types` that are all `image/*`. An unrestricted
     * (`all`) field also accepts non-images, so it returns false.
     *
     * @param  array<string, mixed>  $config
     */
    protected function restrictsToImagesOnly(array $config): bool
    {
        $restrictionType = $config['file_type_restrictions'] ?? 'all';

        if ($restrictionType === 'mime_categories') {
            // array_diff() and the emptiness check ignore keys (UnwrapArrayValues is equivalent)
            // @pest-mutate-ignore: UnwrapArrayValues
            $categories = array_values(array_filter(array_map(
                static fn ($category): string => is_scalar($category) ? (string) $category : '',
                (array) ($config['mime_categories'] ?? [])
            )));

            return $categories !== [] && array_diff($categories, ['image']) === [];
        }

        if ($restrictionType === 'mime_types') {
            // array_reduce() and the emptiness check ignore keys (UnwrapArrayValues is equivalent)
            // @pest-mutate-ignore: UnwrapArrayValues
            $mimeTypes = array_values(array_filter(
                (array) ($config['mime_types'] ?? []),
                is_string(...)
            ));

            return $mimeTypes !== [] && array_reduce(
                $mimeTypes,
                static fn (bool $carry, string $mimeType): bool => $carry && str_starts_with($mimeType, 'image/'),
                true
            );
        }

        return false;
    }

    /**
     * The container handle(s) configured for this field, normalized to a list of
     * non-empty strings. Used to resolve bare-path asset references in
     * {@see AssetMinimumDimensions}. Tolerates a single handle or an array.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    protected function configuredContainers(array $config): array
    {
        return array_values(array_filter(
            array_map(
                static fn ($container): string => is_string($container) ? $container : '',
                (array) ($config['container'] ?? []),
            ),
            static fn (string $container): bool => $container !== '',
        ));
    }

    /**
     * The validation rules applied to this field's own value.
     *
     * Statamic's {@see Field::rules()} merges a fieldtype's `rules()` into the
     * field's own handle bucket, so rules returned here actually validate the
     * field on save (unlike `extraRules()`, a `[handle => rules]` map, whose flat
     * list keys rules under numeric "phantom" fields Laravel never validates).
     *
     * Only ASSET-AWARE rule OBJECTS are wired here — currently the minimum image
     * dimensions rule, which resolves the selected asset reference(s) and reads
     * their stored meta. The raw Laravel string rules `getValidationRules()` also
     * builds (`mimetypes:`, `extensions:`, file-size `between|min|max`) are FILE
     * rules: against an Assets field's value — an array of asset REFERENCES, never
     * an UploadedFile — `mimetypes`/`extensions` fail `isValidFileInstance` and
     * size rules count array items, so wiring them here would reject every valid
     * selection. They stay unwired pending an asset-aware reimplementation (UAMS-Web/uams-statamic-archive#1301);
     * `getValidationRules()` keeps building them for callers/tests only.
     *
     * @return array<int, mixed> Laravel validation rules for this field
     */
    #[\Override]
    public function rules(): array
    {
        /** @var Field $field */
        $field = $this->field();

        /** @var array<string, mixed> $config */
        $config = $field->config();

        /** @var array<int, mixed> $baseRules */
        $baseRules = parent::rules();

        return array_merge($baseRules, $this->assetReferenceRules($config));
    }

    /**
     * The subset of {@see getValidationRules()} safe to apply to an Assets field's
     * value (an array of asset references): the asset-aware rule OBJECTS, which
     * resolve references themselves. Raw Laravel string rules are excluded — they
     * expect an UploadedFile and reject a reference array (see {@see rules()}).
     *
     * `getValidationRules()` prepends {@see parent::rules()}, which currently
     * returns only string rules, so this yields exactly our custom rule objects.
     * If a future base `rules()` ever returns a rule OBJECT, dedupe it against
     * `parent::rules()` here — it is already merged in {@see rules()}, so it would
     * otherwise be wired twice.
     *
     * @param  array<string, mixed>  $config
     * @return array<int, object>
     */
    protected function assetReferenceRules(array $config): array
    {
        // rules(), the only caller, renumbers through array_merge() (UnwrapArrayValues is equivalent)
        // @pest-mutate-ignore: UnwrapArrayValues
        return array_values(array_filter(
            $this->getValidationRules(null, $config),
            is_object(...),
        ));
    }

    /**
     * No extra (other-field) rules. The asset-aware custom rules validate THIS
     * field and are returned from {@see rules()} so they key to the field's own
     * handle. `extraRules()` is a `[handle => rules]` map for OTHER fields; a flat
     * list here mis-keys under numeric phantom fields (dead), and a rule OBJECT
     * here throws in {@see Field::addNullableRule()}.
     *
     * @return array<int, mixed>
     */
    #[\Override]
    public function extraRules(): array
    {
        return [];
    }

    /**
     * Get the accept attribute value for the file input to restrict file types in the
     * browser.
     *
     * @param  array<string, mixed>  $config  The field config
     * @return ?string The accept attribute value or null if no restrictions
     */
    public function getAcceptAttribute(
        array $config = []
    ): ?string {
        // Get the file type restriction setting
        $restrictionType = $config['file_type_restrictions'] ?? 'all';

        // Check if no restrictions are set
        // Falling through collects no accept values and returns null too (RemoveEarlyReturn is equivalent)
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($restrictionType === 'all') {
            // Return null (allow all file types)
            return null;
        }

        // Initialize array for accept values
        /** @var array<int, string> $acceptValues */
        $acceptValues = [];

        // If the restriction type is MIME types, add the MIME types accept values
        if ($restrictionType === 'mime_types') {
            // Get the allowed MIME types
            /** @var array<int, string> $allowedMimeTypes */
            $allowedMimeTypes = (array) ($config['mime_types'] ?? []);

            // If there are allowed MIME types, add the MIME types accept values
            if (! empty($allowedMimeTypes)) {
                // Add the MIME types to the accept values array
                $acceptValues = [
                    ...$allowedMimeTypes,
                ];

            }
        } elseif ($restrictionType === 'mime_categories') { // Check if the restriction type is MIME categories
            // Get the allowed MIME categories
            /** @var array<int, string> $allowedCategories */
            $allowedCategories = (array) ($config['mime_categories'] ?? []);

            // Check if there are allowed MIME categories
            if (! empty($allowedCategories)) {
                // Loop through allowed categories to build accept values
                foreach ($allowedCategories as $category) {
                    // Add the MIME categories accept values to the accept values array
                    // (e.g., image/*, message/*)
                    $acceptValues[] = $category.'/*';
                }

                // Clean up variables to free up memory
                unset(
                    $allowedCategories,
                    $category
                );
            }
        }

        // Clean up variables to free up memory
        unset(
            $restrictionType
        );

        // Return comma-separated accept values or null if empty
        return empty($acceptValues) ? null : \implode(
            ',',
            $acceptValues
        );
    }

    /**
     * Preload config data for the Vue component.
     *
     * This method provides the form fields to the Vue component so it can populate
     * the field selection dropdowns. It merges preset config overrides into the
     * config array so the Vue component always receives the expanded config,
     * regardless of what is in the YAML.
     *
     * It follows the same pattern as the Input trait's preload() method.
     *
     * When file type restrictions are set, adds encoded file-type query_scopes
     * so the asset browser can filter by file type without param transport.
     *
     * @return array<string, mixed> Preload data
     */
    #[\Override]
    public function preload(): array
    {
        // Get the base preload data from parent
        /** @var array<string, mixed> $preload */
        $preload = parent::preload();

        // Get the field config
        /** @var array<string, mixed> $config */
        $config = $this->config();

        // Add encoded query scopes when file type restrictions are set.
        $this->applyFileTypeQueryScopeToConfig($config);

        // Add encoded query scopes when file size restrictions are set.
        $this->applyFileSizeQueryScopeToConfig($config);

        // ---
        // Add the full config to preload (following the Input trait pattern). This makes
        // all config properties available as meta.config in the Vue component.
        // ---
        $preload['config'] = $config;

        // Clean up variables to free up memory
        unset($config);

        // Return the preload data
        return $preload;
    }

    /**
     * Add encoded file-type query scopes to config when file type restrictions
     * are set, so the asset browser can filter by file type without
     * query-scope params transport.
     *
     * @param  array<string, mixed>  $config  Field config (modified in place)
     */
    protected function applyFileTypeQueryScopeToConfig(array &$config): void
    {
        // Initialize the restriction type from field config.
        $restrictionTypeRaw = $config['file_type_restrictions'] ?? 'all';
        $restrictionType = \is_string($restrictionTypeRaw) ? $restrictionTypeRaw : 'all';

        // Check if file type restrictions are disabled.
        if ($restrictionType === 'all') {
            // Return early
            return;
        }

        // Initialize the base scope handle for file type filtering.
        $scopeHandle = 'assets_by_file_type';

        // Build dynamic encoded scope handles from file type restrictions.
        $encodedScopeHandles = $this->encodedFileTypeScopeHandles(
            $config,
            $restrictionType
        );

        // ---
        // Ensure file-type scopes are in the query scopes list.
        // ---

        // ???
        $existing = \array_filter((array) ($config['query_scopes'] ?? []));

        // Normalize remaining scope handles to strings for the unique merge below.
        /** @var array<int, string> $existing */
        $existing = \array_map(
            static fn ($scope): string => is_scalar($scope) ? (string) $scope : '',
            $existing
        );

        // ???
        $queryScopes = \array_values(\array_unique([
            ...$existing,
            $scopeHandle,
            ...$encodedScopeHandles,
        ]));

        // ???
        $config['query_scopes'] = $queryScopes;

        // Clean up variables to free up memory.
        unset(
            $existing,
            $queryScopes,
            $restrictionTypeRaw,
            $restrictionType,
            $scopeHandle,
            $encodedScopeHandles
        );
    }

    /**
     * Add encoded file-size query scopes to config when a size bound is set, so
     * the asset browser can filter by file size without query-scope params
     * transport (see {@see AssetsByFileSize} for the UAMS-Web/uams-statamic-archive#487 limitation).
     *
     * @param  array<string, mixed>  $config  Field config (modified in place)
     */
    protected function applyFileSizeQueryScopeToConfig(array &$config): void
    {
        // Build the encoded min/max size scope handles from the field config.
        $encodedScopeHandles = $this->encodedFileSizeScopeHandles($config);

        // Check if neither a min nor a max size bound is configured.
        if ($encodedScopeHandles === []) {
            // Return early when there is no size bound to encode.
            return;
        }

        // Initialize the base scope handle for file size filtering.
        $scopeHandle = 'assets_by_file_size';

        // Normalize any existing query scopes to a string list for the merge.
        $existing = \array_filter((array) ($config['query_scopes'] ?? []));

        /** @var array<int, string> $existing */
        $existing = \array_map(
            static fn ($scope): string => is_scalar($scope) ? (string) $scope : '',
            $existing
        );

        // Merge the base handle + encoded bound handles, deduped.
        $queryScopes = \array_values(\array_unique([
            ...$existing,
            $scopeHandle,
            ...$encodedScopeHandles,
        ]));

        // Write the merged query scopes back to the config.
        $config['query_scopes'] = $queryScopes;

        // Clean up variables to free up memory.
        unset(
            $existing,
            $queryScopes,
            $scopeHandle,
            $encodedScopeHandles
        );
    }

    /**
     * Build encoded file-size query scope handles from the field's
     * `min_file_size` / `max_file_size` config (megabytes).
     *
     * Encoded handles use this structure (bytes; 1 MB = 1,000,000 bytes, matching
     * the server-side validation's decimal MB convention):
     * - assets_by_file_size_min_{bytes}
     * - assets_by_file_size_max_{bytes}
     *
     * @param  array<string, mixed>  $config
     * @return array<int, string>
     */
    protected function encodedFileSizeScopeHandles(array $config): array
    {
        // Initialize the handle prefix for encoded file size scopes.
        $prefix = 'assets_by_file_size_';

        // Initialize the encoded handles collection.
        $encodedScopeHandles = [];

        // Resolve the configured minimum size (megabytes) when numeric.
        $minMbRaw = $config['min_file_size'] ?? null;
        if (is_numeric($minMbRaw)) {
            // Convert megabytes to bytes (decimal MB) for the encoded bound.
            // A numeric string multiplies the same as its float (RemoveDoubleCast is equivalent)
            // @pest-mutate-ignore: RemoveDoubleCast
            $minBytes = (int) ((float) $minMbRaw * 1_000_000);

            // Encode only a meaningful (positive) lower bound.
            if ($minBytes > 0) {
                $encodedScopeHandles[] = $prefix.'min_'.$minBytes;
            }
        }

        // Resolve the configured maximum size (megabytes) when numeric.
        $maxMbRaw = $config['max_file_size'] ?? null;
        if (is_numeric($maxMbRaw)) {
            // Convert megabytes to bytes (decimal MB) for the encoded bound.
            // A numeric string multiplies the same as its float (RemoveDoubleCast is equivalent)
            // @pest-mutate-ignore: RemoveDoubleCast
            $maxBytes = (int) ((float) $maxMbRaw * 1_000_000);

            // Encode only a meaningful (positive) upper bound.
            if ($maxBytes > 0) {
                $encodedScopeHandles[] = $prefix.'max_'.$maxBytes;
            }
        }

        // Clean up variables to free up memory.
        unset(
            $prefix,
            $minMbRaw,
            $maxMbRaw
        );

        // Return the encoded size scope handles.
        return $encodedScopeHandles;
    }

    /**
     * Build encoded file-type query scope handles from field restrictions.
     *
     * Encoded handles use this structure:
     * - assets_by_file_type_{category}
     * - assets_by_file_type_{mime_type_enum_case_in_lower_snake}
     *
     * @param  array<string, mixed>  $config
     * @return array<int, string>
     */
    protected function encodedFileTypeScopeHandles(
        array $config,
        string $restrictionType
    ): array {
        // Initialize handle prefix for encoded file type scopes.
        $prefix = 'assets_by_file_type_';

        // Initialize encoded handles collection.
        $encodedScopeHandles = [];

        // Check if restrictions are MIME categories.
        if ($restrictionType === 'mime_categories') {
            // Extract configured category values.
            $categories = (array) ($config['mime_categories'] ?? []);

            // Build encoded handles for each configured category.
            foreach ($categories as $category) {
                // Check if category value is not a valid string.
                if (! \is_string($category)) {
                    // Skip the category
                    continue;
                }

                if ($category === '') {
                    // Skip the category
                    continue;
                }

                // Add encoded category scope handle.
                $encodedScopeHandles[] = $prefix.strtolower($category);
            }

            // Clean up category variables to free up memory.
            unset($categories);
        } elseif ($restrictionType === 'mime_types') {
            // Extract configured MIME type values.
            $mimeTypes = (array) ($config['mime_types'] ?? []);

            // Build encoded handles for each configured MIME type.
            foreach ($mimeTypes as $mimeType) {
                // Check if MIME type value is not a valid string.
                if (! \is_string($mimeType)) {
                    // Skip the MIME type
                    continue;
                }

                // An empty string finds no MIME type below either (EmptyStringToNotEmpty is equivalent)
                // @pest-mutate-ignore: EmptyStringToNotEmpty
                if ($mimeType === '') {
                    // Skip the MIME type
                    continue;
                }

                // Resolve MIME type enum case by MIME type string.
                $mimeTypeCase = MimeType::findByMimeType($mimeType);

                // Check if MIME type enum case could not be resolved.
                if (! $mimeTypeCase instanceof MimeType) {
                    // Skip the MIME type
                    continue;
                }

                // Add encoded MIME type scope handle from enum case value.
                $encodedScopeHandles[] = $prefix.strtolower($mimeTypeCase->value);
            }

            // Clean up MIME type variables to free up memory.
            unset($mimeTypes);
        }

        // Dedupe and normalize encoded handles.
        // The only caller dedupes and renumbers the merged scope list again
        // @pest-mutate-ignore: UnwrapArrayUnique, UnwrapArrayValues
        $encodedScopeHandles = array_values(array_unique($encodedScopeHandles));

        // Clean up variables to free up memory.
        unset(
            $prefix,
            $restrictionType
        );

        // Return encoded file type scope handles.
        return $encodedScopeHandles;
    }
}
