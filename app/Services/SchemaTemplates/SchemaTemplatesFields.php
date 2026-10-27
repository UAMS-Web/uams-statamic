<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

use Statamic\Facades\Blueprint;
use Statamic\Fields\Blueprint as FieldsBlueprint;

/**
 * Centralized field config for Schema Templates CP (site, section, blueprint
 * defaults). Returns Statamic blueprint contents for use with PublishForm.
 *
 * Each slot (subject, website, webpage, main content) uses an entries field
 * for the schema_objects collection (no query scopes; all entries listed).
 *
 * @TODO: Make conditional display logic work for the fields.
 */
class SchemaTemplatesFields
{
    /**
     * Blueprint contents for Site Defaults (Tools → Schema Templates → Site Defaults).
     *
     * @return array<string, mixed>
     */
    public static function siteDefaults(): array
    {
        return [
            'tabs' => [
                'main' => [
                    'sections' => [
                        [
                            'display' => __('Schema Output'),
                            'instructions' => __(
                                'Enable JSON-LD and set schema entries for Subject (site subject), Website, Webpage, and Main Content. Section and blueprint levels can override Webpage and Main Content entries.'
                            ),
                            'fields' => [
                                static::enabledField(static::enabledOptionsSite()),
                                static::subjectEntryField(),
                                static::websiteEntryField(),
                                static::webpageEntryField(),
                                static::mainContentEntryField(),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Blueprint contents for Section Defaults (collection/taxonomy).
     *
     * @param  bool  $siteDisabled  When true, inherit_template defaults to false and is read-only
     * @return array<string, mixed>
     */
    public static function sectionDefaults(bool $siteDisabled): array
    {
        return [
            'tabs' => [
                'main' => [
                    'sections' => [
                        [
                            'display' => __('Schema Output'),
                            'instructions' => __(
                                'Override or inherit from Site Defaults. Webpage and Main Content entries cascade to blueprint and entry.'
                            ),
                            'fields' => [
                                static::enabledField(static::enabledOptionsSection()),
                                static::inheritField(
                                    'inherit_webpage_entry',
                                    __('Inherit Webpage Entry From Site Defaults'),
                                    $siteDisabled
                                ),
                                static::webpageEntryField(),
                                static::inheritField(
                                    'inherit_main_content_entry',
                                    __('Inherit Main Content Entry From Site Defaults'),
                                    $siteDisabled
                                ),
                                static::mainContentEntryField(),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Blueprint contents for Blueprint Defaults (section + blueprint).
     *
     * @param  bool  $sectionDisabled  When true, inherit_template defaults to false and is read-only
     * @return array<string, mixed>
     */
    public static function blueprintDefaults(bool $sectionDisabled): array
    {
        return [
            'tabs' => [
                'main' => [
                    'sections' => [
                        [
                            'display' => __('Schema Output'),
                            'instructions' => __(
                                'Override or inherit from Section Defaults. Webpage and Main Content entries combine with Site/Section to build the JSON-LD @graph.'
                            ),
                            'fields' => [
                                static::enabledField(static::enabledOptionsBlueprint()),
                                static::inheritField(
                                    'inherit_webpage_entry',
                                    __('Inherit Webpage Entry From Section Defaults'),
                                    $sectionDisabled
                                ),
                                static::webpageEntryField(),
                                static::inheritField(
                                    'inherit_main_content_entry',
                                    __('Inherit Main Content Entry From Section Defaults'),
                                    $sectionDisabled
                                ),
                                static::mainContentEntryField(),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build a Blueprint instance with the given contents.
     *
     * @param  array<string, mixed>  $contents
     */
    public static function toBlueprint(array $contents): FieldsBlueprint
    {
        /** @var FieldsBlueprint $blueprint */
        $blueprint = Blueprint::make()->setContents($contents);

        return $blueprint;
    }

    /**
     * Enabled options for site level (no inherit).
     *
     * @return array<string, string>
     */
    protected static function enabledOptionsSite(): array
    {
        return [
            'true' => __('Enabled'),
            'false' => __('Disabled'),
        ];
    }

    /**
     * Enabled options for section level (inherit from site).
     *
     * @return array<string, string>
     */
    protected static function enabledOptionsSection(): array
    {
        return [
            'true' => __('Enabled'),
            'false' => __('Disabled'),
            'inherit' => __('Inherit from Site Defaults'),
        ];
    }

    /**
     * Enabled options for blueprint level (inherit from section).
     *
     * @return array<string, string>
     */
    protected static function enabledOptionsBlueprint(): array
    {
        return [
            'true' => __('Enabled'),
            'false' => __('Disabled'),
            'inherit' => __('Inherit from Section Defaults'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function inheritTemplateOptions(): array
    {
        return [
            'true' => __('Inherit'),
            'false' => __('Use Custom Template'),
        ];
    }

    /**
     * @param  array<string, string>  $options
     * @return array{ handle: string, field: array<string, mixed> }
     */
    protected static function enabledField(array $options): array
    {
        $default = \count($options) === 2 ? 'true' : 'inherit';

        return [
            'handle' => 'enabled',
            'field' => [
                'type' => 'button_group',
                'display' => __('Schema Output'),
                'options' => $options,
                'default' => $default,
                'cast_booleans' => 'true',
            ],
        ];
    }

    /**
     * Entries field for a slot: schema_objects collection, no query scopes.
     *
     * @param  string  $handle  Field handle (e.g., subject_entry)
     * @param  string  $display  Field display label
     * @param  string  $instructions  Field instructions
     * @return array{ handle: string, field: array<string, mixed> }
     */
    protected static function slotEntryField(
        string $handle,
        string $display,
        string $instructions
    ): array {
        $collection = config('schema_object.entries_collection', 'schema_objects');

        return [
            'handle' => $handle,
            'field' => [
                'type' => 'entries',
                'display' => $display,
                'instructions' => $instructions,
                'collections' => [$collection],
                'max_items' => 1,
            ],
        ];
    }

    /**
     * Subject (site subject) entry (site only).
     *
     * @return array{ handle: string, field: array<string, mixed> }
     */
    protected static function subjectEntryField(): array
    {
        return static::slotEntryField(
            'subject_entry',
            __('Subject (Site Subject)'),
            __('Select a schema entry for what the site is about (e.g., organization, person).')
        );
    }

    /**
     * Website schema entry (site only).
     *
     * @return array{ handle: string, field: array<string, mixed> }
     */
    protected static function websiteEntryField(): array
    {
        return static::slotEntryField(
            'website_entry',
            __('Website'),
            __('Select a schema entry for the website node.')
        );
    }

    /**
     * Webpage schema entry (site, section, blueprint).
     *
     * @return array{ handle: string, field: array<string, mixed> }
     */
    protected static function webpageEntryField(): array
    {
        return static::slotEntryField(
            'webpage_entry',
            __('Webpage'),
            __('Select a schema entry for the webpage node.')
        );
    }

    /**
     * Main content schema entry (site, section, blueprint).
     *
     * @return array{ handle: string, field: array<string, mixed> }
     */
    protected static function mainContentEntryField(): array
    {
        return static::slotEntryField(
            'main_content_entry',
            __('Main Content'),
            __('Select a schema entry for the main content node.')
        );
    }

    /**
     * Per-parameter inherit button group (section or blueprint).
     *
     * @return array{ handle: string, field: array<string, mixed> }
     */
    protected static function inheritField(
        string $handle,
        string $display,
        bool $parentDisabled
    ): array {
        $options = static::inheritTemplateOptions();

        return [
            'handle' => $handle,
            'field' => [
                'type' => 'button_group',
                'display' => $display,
                'options' => $options,
                'default' => $parentDisabled ? 'false' : 'true',
                'read_only' => $parentDisabled,
                'cast_booleans' => 'true',
            ],
        ];
    }
}
