<?php

declare(strict_types=1);

namespace App\Tags;

use App\Support\Forms\SubmittedFieldDisplay;
use Illuminate\Support\Collection;
use Statamic\Fields\Value;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Emulates Gravity Forms {all_fields} merge tag.
 *
 * Displays a preformatted HTML table containing all of the submitted values.
 *
 * Each value cell renders through App\Support\Forms\SubmittedFieldDisplay, so the table shows
 * every field the notification's field list shows, the same way: option labels, asset URLs,
 * replicator set blocks (UAMS-Web/uams-statamic-archive#2055) and escaped text (UAMS-Web/uams-statamic-archive#3149).
 *
 * Uses parameter-based approach for multiple options.
 *
 * Usage: {{ all_fields }}
 *
 * Parameters:
 *   - show_value="true" - Shows actual values instead of labels
 *   - show_empty="true" - Shows fields with no submitted values
 *   - hide_hidden="true" - Hides hidden field types
 */
class AllFields extends Tags
{
    /**
     * Returns a preformatted HTML table containing all of the submitted values.
     *
     * @return string The preformatted HTML table containing all of the submitted values
     */
    public function index(): string
    {
        // Narrow the tag context to its concrete bag for typed access
        /** @var Context $context */
        $context = $this->context;

        // Get the fields from the context
        /** @var Collection<array-key, mixed> $fields */
        $fields = $context->get(
            'fields',
            collect()
        );

        // Check if the fields collection is empty
        if ($fields->isEmpty()) {
            // Return empty string if no fields are available
            return '';
        }

        // ---
        // Get parameters for controlling field display
        // ---

        // Narrow the tag parameters to their concrete bag for typed access
        /** @var Parameters $params */
        $params = $this->params;

        // Get the show_value parameter to control value vs. label display
        $showValue = $params->get(
            'show_value',
            false
        );

        // Get the show_empty parameter to control empty field display
        $showEmpty = $params->get(
            'show_empty',
            false
        );

        // Get the hide_hidden parameter to control hidden field display
        $hideHidden = $params->get(
            'hide_hidden',
            false
        );

        // ---
        // Build the HTML table similar to Gravity Forms
        // ---

        // Initialize the HTML table with styling
        $html = '<table style="width: 100%; border-collapse: collapse; margin: 20px 0; border: 1px solid #ddd;">';

        // Add table header
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th style="padding: 12px; border: 1px solid #ddd; background-color: #f8f9fa; font-weight: bold; text-align: left; width: 30%;">Field</th>';
        $html .= '<th style="padding: 12px; border: 1px solid #ddd; background-color: #f8f9fa; font-weight: bold; text-align: left; width: 70%;">Value</th>';
        $html .= '</tr>';
        $html .= '</thead>';

        // Add table body
        $html .= '<tbody>';

        // Loop through each field to build table rows
        foreach ($fields as $rawField) {
            // ---
            // Extract field properties with fallbacks
            // ---

            // Normalize the field to an array so offsets are safely accessible
            $field = \is_array($rawField) ? $rawField : [];

            // Extract the display label or fall back to handle if display is missing
            $rawDisplay = $field['display'] ?? $field['handle'] ?? '';
            $display = \is_scalar($rawDisplay) ? (string) $rawDisplay : '';

            // Extract the field value, unwrapping an augmented value for the emptiness check
            $rawValue = $field['value'] ?? '';
            $value = $rawValue instanceof Value ? $rawValue->value() : $rawValue;

            // Extract the field type: the mailable writes `fieldtype`, older callers `type`. It is
            // only compared with `hidden`, so any other fallback string behaves as the empty one
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            $fieldType = $field['fieldtype'] ?? $field['type'] ?? '';

            // Determine if this field is a hidden field type
            $isHidden = $fieldType === 'hidden';

            // ---
            // Skip hidden fields if hide_hidden parameter is used
            // ---

            // Check if hidden fields should be skipped and this field is hidden
            if (
                $hideHidden
                && $isHidden
            ) {
                // Skip this field and continue to the next iteration
                continue;
            }

            // ---
            // Skip empty values unless show_empty parameter is used
            // ---

            // Check if empty values should be skipped and this field has no value
            if (
                SubmittedFieldDisplay::isEmpty($value)
                && ! $showEmpty
            ) {
                // Skip this field and continue to the next iteration
                continue;
            }

            // ---
            // Handle value vs. label display
            // ---

            // Use the stored value if show_value is enabled, otherwise the field's display HTML,
            // which the resolver has already escaped
            $displayValue = $showValue
                ? strtr(htmlspecialchars($this->getActualValue($field)), ['{' => '&#123;', '}' => '&#125;'])
                : SubmittedFieldDisplay::html($field);

            // Build table row with proper HTML escaping
            $html .= '<tr>';
            $html .= '<td style="padding: 12px; border: 1px solid #ddd; font-weight: bold; width: 30%;">';
            $html .= htmlspecialchars($display); // Escape HTML special characters in the display label for safe output
            $html .= '</td>';
            $html .= '<td style="padding: 12px; border: 1px solid #ddd; width: 70%;">';
            $html .= $displayValue;
            $html .= '</td>';
            $html .= '</tr>';
        }

        // Close the table body and table elements
        $html .= '</tbody>';

        // Return the complete HTML table
        return $html.'</table>';
    }

    /**
     * Get the actual value for multi-input fields.
     *
     * For fields with options (checkboxes, radio buttons, dropdowns), returns the
     * option key instead of the display label.
     *
     * For other field types, returns the value as-is.
     *
     * @param  array<array-key, mixed>  $field  The field data array containing value, type, and options
     * @return string The actual value to display
     */
    private function getActualValue(
        array $field
    ): string {
        // ---
        // Extract field properties
        // ---

        // Extract the field value or use empty string as fallback, unwrapping an augmented
        // value so an option field yields its stored key. Stringable values coerce to string.
        $rawValue = $field['value'] ?? '';
        $rawValue = $rawValue instanceof Value ? $rawValue->value() : $rawValue;

        // For other field types, return the value as-is
        return \is_scalar($rawValue) || $rawValue instanceof \Stringable
            ? (string) $rawValue
            : '';
    }
}
