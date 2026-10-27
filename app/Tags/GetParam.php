<?php

declare(strict_types=1);

namespace App\Tags;

use App\Support\Forms\FormRequestContext;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Provides a Statamic tag for retrieving URL query parameters.
 *
 * Allows access to URL parameters from the current request, useful for
 * building dynamic content based on query string values.
 */
class GetParam extends Tags
{
    /**
     * The {{ get_param }} tag.
     *
     * Gets the value of a URL parameter from the current URL.
     *
     * Prefers the submit request's query parameters captured at submit time when a
     * form-email render is in progress (so it stays correct when a queue worker
     * renders the email with no submitter request), and falls back to the live
     * request's query string otherwise.
     *
     * Usage: {{ get_param name="parameter_name" }}
     *
     * Parameters:
     *   - name="parameter_name" - The name of the parameter to retrieve
     *
     * @return string|array<array-key, mixed> The value of the parameter or empty string if not found
     */
    public function index(): string|array
    {
        // Get the parameter name from the 'name' parameter
        /** @var Parameters $params */
        $params = $this->params;
        $paramName = $params->get('name');

        // Check if the parameter name was provided
        if (empty($paramName)) {
            // Return empty string if no parameter name provided
            return '';
        }

        // Narrow the requested parameter name to a string key
        $key = is_scalar($paramName) ? (string) $paramName : '';

        // Prefer the captured submit-request query during a form-email render
        $captured = FormRequestContext::active();
        if ($captured instanceof FormRequestContext) {
            // Read the captured parameter, mirroring request()->query() shape (string|array|null)
            $value = $captured->query[$key] ?? null;

            // Return empty string if the captured query lacks this parameter
            return is_string($value) || is_array($value) ? $value : '';
        }

        // Get the parameter value from the current request
        $value = request()->query($key);

        // Return empty string if parameter doesn't exist
        return $value ?? '';
    }
}
