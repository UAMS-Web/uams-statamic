<?php

declare(strict_types=1);

namespace App\Tags;

use App\Support\Statamic\Tags\TagParam;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Statamic\Facades\YAML;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Provides Statamic tags for accessing form data from YAML files.
 *
 * Allows retrieval of custom form data, headings, and descriptions from form
 * configuration files stored in the Statamic forms directory.
 *
 * Usage:
 *   - {{ form_data handle="form_handle" key="property_name" }}
 *   - {{ form_data:heading handle="form_handle" }}
 *   - {{ form_data:description handle="form_handle" }}
 */
class FormData extends Tags
{
    /**
     * The {{ form_data }} tag.
     *
     * Gets custom data from a form YAML file.
     *
     * Usage: {{ form_data handle="form_handle" key="property_name" }}
     *
     * @return string|array<string, mixed> An empty string, a single requested
     *                                     key value, or the full parsed form data.
     */
    public function index(): string|array
    {
        // Narrow the untyped Tags::$params bag to its concrete Collection type
        /** @var Parameters $params */
        $params = $this->params;

        // Get the form handle from the 'handle' parameter
        $handle = TagParam::get(
            $params,
            'handle'
        );

        // Get the specific key to retrieve
        $key = TagParam::get(
            $params,
            'key'
        );

        // Check if form handle parameter was provided
        if (empty($handle)) {
            // Return empty string if no handle provided
            return '';
        }

        // Build the form file path, or null when the handle cannot name a form
        $formPath = $this->formYamlPath($handle);

        // Check if the form file exists
        if ($formPath === null || ! File::exists($formPath)) {
            // Return empty string if file doesn't exist
            return '';
        }

        // Read and parse the YAML file
        /** @var array<string, mixed> $formData */
        $formData = YAML::parse(File::get($formPath));

        // Return all form data when no specific key was requested
        if (empty($key)) {
            return $formData;
        }

        // A key that is not a scalar names no property
        if (! is_scalar($key)) {
            return '';
        }

        // Return the specific key value or empty string if not found
        $value = $formData[(string) $key] ?? '';

        // Narrow the mixed value to the declared string|array return type
        if (is_array($value)) {
            /** @var array<string, mixed> $value */
            return $value;
        }

        // `YAML::parse()` yields only scalars, arrays and null, and `??` already turned null
        // into '', so the fallback is never reached (EmptyStringToNotEmpty is equivalent)
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * The {{ form_data:heading }} tag.
     *
     * Gets the heading from a form YAML file.
     */
    public function heading(): string
    {
        return $this->formProperty(
            'heading',
            'No heading property found'
        );
    }

    /**
     * The {{ form_data:description }} tag.
     *
     * Gets the description from a form YAML file.
     */
    public function description(): string
    {
        return $this->formProperty(
            'description',
            'No description property found'
        );
    }

    /**
     * Read one property of the form the `handle` parameter names.
     *
     * @param  string  $property  The form YAML property to read
     * @param  string  $fallback  The text returned when the property is missing or not a scalar
     * @return string The property, the fallback, an error message, or an empty string
     */
    private function formProperty(
        string $property,
        string $fallback
    ): string {
        // Narrow the untyped Tags::$params bag to its concrete Collection type
        /** @var Parameters $params */
        $params = $this->params;

        // Get the form handle from the parameters
        $handle = TagParam::get(
            $params,
            'handle'
        );

        // Check if form handle parameter was provided
        if (empty($handle)) {
            // Return error message if no handle provided
            return 'No handle provided';
        }

        // Build the form file path, or null when the handle cannot name a form
        $formPath = $this->formYamlPath($handle);

        // Check if the form file exists on disk
        if ($formPath === null || ! File::exists($formPath)) {
            $this->logMissingFormYaml(
                $handle,
                $formPath
            );

            return '';
        }

        // Read and parse the YAML file content
        /** @var array<string, mixed> $formData */
        $formData = YAML::parse(File::get($formPath));

        // Return the property value or the fallback if it doesn't exist
        $value = $formData[$property] ?? $fallback;

        // Narrow the mixed value to the declared string return type
        return is_scalar($value) ? (string) $value : $fallback;
    }

    /**
     * Build the path of the YAML file a form handle names.
     *
     * @param  mixed  $handle  The non-empty form handle from tag parameters
     * @return string|null The path, or null when the handle is not a scalar or the forms
     *                     directory is not configured as a string
     */
    private function formYamlPath(
        mixed $handle
    ): ?string {
        // Only a scalar handle can name a form file
        if (! is_scalar($handle)) {
            return null;
        }

        // Read the forms directory from Statamic configuration
        $formsRoot = config('statamic.forms.forms');

        // Without a forms directory no form file can be found
        if (! \is_string($formsRoot)) {
            return null;
        }

        return $formsRoot.sprintf('/%s.yaml', $handle);
    }

    /**
     * Log a missing form YAML file for operators without exposing paths in templates.
     *
     * @param  mixed  $handle  The form handle from tag parameters.
     * @param  string|null  $formPath  Resolved filesystem path that was not found, or null when
     *                                 the handle or the forms directory could not build one.
     */
    private function logMissingFormYaml(
        mixed $handle,
        ?string $formPath
    ): void {
        Log::warning(
            'FormData tag: form YAML file not found.',
            [
                'handle' => is_scalar($handle) ? (string) $handle : $handle,
                'path' => $formPath,
            ]
        );
    }
}
