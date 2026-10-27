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

        // Narrow the mixed handle to a string for safe path building
        $handle = is_scalar($handle) ? (string) $handle : '';

        // Build the form file path
        $formsRoot = config('statamic.forms.forms');
        $formsRoot = is_scalar($formsRoot) ? (string) $formsRoot : '';

        $formPath = $formsRoot.sprintf('/%s.yaml', $handle);

        // Check if the form file exists
        if (! File::exists($formPath)) {
            // Return empty string if file doesn't exist
            return '';
        }

        // Read and parse the YAML file
        /** @var array<string, mixed> $formData */
        $formData = YAML::parse(File::get($formPath));

        // Check if a specific key was requested
        if (! empty($key)) {
            // Narrow the mixed key to a valid array offset
            $key = is_scalar($key) ? (string) $key : '';

            // Return the specific key value or empty string if not found
            $value = $formData[$key] ?? '';

            // Narrow the mixed value to the declared string|array return type
            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                return $value;
            }

            return is_scalar($value) ? (string) $value : '';
        }

        // Otherwise return all form data
        return $formData;
    }

    /**
     * The {{ form_data:heading }} tag.
     *
     * Gets the heading from a form YAML file.
     */
    public function heading(): string
    {
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

        // Narrow the mixed handle to a string for safe path building
        $handle = is_scalar($handle) ? (string) $handle : '';

        // Build the form file path using Statamic configuration
        $formsRoot = config('statamic.forms.forms');
        $formsRoot = is_scalar($formsRoot) ? (string) $formsRoot : '';

        $formPath = $formsRoot.sprintf('/%s.yaml', $handle);

        // Check if the form file exists on disk
        if (! File::exists($formPath)) {
            $this->logMissingFormYaml(
                $handle,
                $formPath
            );

            return '';
        }

        // Read and parse the YAML file content
        /** @var array<string, mixed> $formData */
        $formData = YAML::parse(File::get($formPath));

        // Return heading value or fallback message if property doesn't exist
        $heading = $formData['heading'] ?? 'No heading property found';

        // Narrow the mixed value to the declared string return type
        return is_scalar($heading) ? (string) $heading : 'No heading property found';
    }

    /**
     * The {{ form_data:description }} tag.
     *
     * Gets the description from a form YAML file.
     */
    public function description(): string
    {
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

        // Narrow the mixed handle to a string for safe path building
        $handle = is_scalar($handle) ? (string) $handle : '';

        // Build the form file path using Statamic configuration
        $formsRoot = config('statamic.forms.forms');
        $formsRoot = is_scalar($formsRoot) ? (string) $formsRoot : '';

        $formPath = $formsRoot.sprintf('/%s.yaml', $handle);

        // Check if the form file exists on disk
        if (! File::exists($formPath)) {
            $this->logMissingFormYaml(
                $handle,
                $formPath
            );

            return '';
        }

        // Read and parse the YAML file content
        /** @var array<string, mixed> $formData */
        $formData = YAML::parse(File::get($formPath));

        // Return description value or fallback message if property doesn't exist
        $description = $formData['description'] ?? 'No description property found';

        // Narrow the mixed value to the declared string return type
        return is_scalar($description) ? (string) $description : 'No description property found';
    }

    /**
     * Log a missing form YAML file for operators without exposing paths in templates.
     *
     * @param  string  $handle  The form handle from tag parameters.
     * @param  string  $formPath  Resolved filesystem path that was not found.
     */
    private function logMissingFormYaml(
        string $handle,
        string $formPath
    ): void {
        Log::warning(
            'FormData tag: form YAML file not found.',
            [
                'handle' => $handle,
                'path' => $formPath,
            ]
        );
    }
}
