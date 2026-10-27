<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use Statamic\Facades\Markdown;
use Statamic\Fields\Fieldtype;

/**
 * Statamic fieldtype that renders a configured consent agreement (Markdown parsed to
 * HTML) and captures the user's acceptance.
 */
class Consent extends Fieldtype
{
    /**
     * Indicates whether the fieldtype can have a default value.
     *
     * @var bool Indicates whether the fieldtype can have a default value
     */
    protected $defaultable = false;

    /**
     * The name of the Vue component to use for this fieldtype.
     *
     * @var string The name of the Vue component to use for this fieldtype
     */
    protected $component = 'consent';

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'fieldtype-checkboxes';
    }

    /**
     * Defines the configuration fields available for this fieldtype in the blueprint.
     *
     * @return array<int, array<string, mixed>> The configuration field items
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        return [
            [
                // Settings for the consent field
                'display' => __('Consent Options'),
                'fields' => [
                    'checkbox_label' => [
                        'display' => __('Checkbox Label'),
                        'instructions' => __('Set the label for the checkbox.'),
                        'type' => 'text',
                        'default' => 'I agree',
                        'placeholder' => 'e.g., I agree',
                    ],
                    'consent_agreement' => [
                        'type' => 'markdown',
                        'display' => __('Consent Agreement'),
                        'instructions' => __('The consent field will display this text to the user and will store this agreement text with the form entry in order to track what the user has consented to.'),
                        'buttons' => [
                            'bold',
                            'italic',
                            'unorderedlist',
                            'orderedlist',
                            'quote',
                            'link',
                        ],
                        'automatic_links' => true,
                    ],
                ],
            ],
        ];
    }

    /**
     * Preload config data for the Vue component.
     *
     * @return array<string, mixed> Preload data
     */
    #[\Override]
    public function preload(): array
    {
        // Get the field configuration
        /** @var array<string, mixed> $config */
        $config = $this->config();

        // Extract the consent agreement markdown text from the configuration
        $agreementMarkdown = $config['consent_agreement'] ?? '';

        // Parse the markdown text to HTML for display in the Vue component
        $agreementHtml = Markdown::parse(\is_string($agreementMarkdown) ? $agreementMarkdown : '');

        // Merge the configuration with the parsed HTML agreement
        return \array_merge(
            $config,
            [
                'consent_agreement_html' => $agreementHtml,
            ]
        );
    }
}
