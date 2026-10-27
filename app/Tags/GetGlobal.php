<?php

declare(strict_types=1);

namespace App\Tags;

use Statamic\Facades\GlobalSet;
use Statamic\Facades\Parse;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;
use Statamic\Fields\Value;
use Statamic\Globals\Variables;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Fetch a global set variable for a specific site (or current site).
 *
 * Usage:
 *   {{ get_global handle="uams_multisite_settings" var="subject" site="health" }}
 *   {{ get_global handle="uams_multisite_settings" var="copyright_notice" }}
 *   {{ get_global handle="uams_multisite_settings" site="health" }}
 *   Without `var`, returns the full set as tag pair context (last line).
 */
class GetGlobal extends Tags
{
    /**
     * Handle for the tag.
     *
     * @var string
     */
    protected static $handle = 'get_global';

    /**
     * Default method when tag is used as single or pair.
     *
     * @return mixed Field value, resolved string, full set array, null, or empty array
     */
    public function index(): mixed
    {
        // Narrow the tag parameters to their concrete Parameters type
        /** @var Parameters $params */
        $params = $this->params;

        // Get the handle from the parameters
        $handle = $params->get('handle');

        // Get the variable from the parameters
        $var = $params->get('var');

        // Get the site from the parameters
        $site = $params->get('site');

        // Check if the handle is not provided
        if (! $handle) {
            // Return null
            return null;
        }

        // Narrow the requested set handle to a string
        $setHandle = \is_scalar($handle) ? (string) $handle : '';

        // Find the global set by the handle
        $set = GlobalSet::findByHandle($setHandle);

        // Check if the global set is not found
        if (! $set) {
            // Return null if the variable is provided, otherwise return an empty array
            return $var ? null : [];
        }

        // Narrow the requested site handle to a string
        $siteHandle = \is_scalar($site) ? (string) $site : '';

        // Get the variables from the global set
        /** @var Variables $variables */
        $variables = $site
            ? $set->in($siteHandle)
            : $set->inCurrentSite();

        // Check if the variable is provided
        if (
            $var !== null
            && $var !== ''
        ) {
            // Narrow the requested variable handle to a string
            $varHandle = \is_scalar($var) ? (string) $var : '';

            // Read the requested global field value
            $value = $variables->get($varHandle);

            // Check whether the field should resolve Antlers content
            if (! $this->fieldUsesAntlers($set, $varHandle)) {
                // Return the original value when Antlers is not enabled
                return $value;
            }

            // Unwrap Statamic Value objects to their stored value
            $rawValue = $value instanceof Value
                ? $value->value()
                : $value;

            // Check if the raw value is not a string
            if (! \is_string($rawValue)) {
                // Return the original value when it is not parseable text
                return $value;
            }

            // Trim surrounding whitespace from the template string
            $template = trim($rawValue);

            // Check if the template is empty
            if ($template === '') {
                // Return the original value when there is no template content
                return $value;
            }

            // Initialize an empty Antlers context and rely on global tags/variables
            $context = [];

            // Parse the Antlers template with global variables context
            $parsed = Parse::template(
                $template,
                $context,
                [],
                false
            );

            // Normalize Antlers parser output to a scalar string value
            $result = (string) $parsed;

            // Return the scalar resolved value
            return $result;
        }

        // Return all the variables from the global set (resolved via the
        // Variables `__call`/augmentation seam, not a declared method)
        return $variables->all(); // @phpstan-ignore method.notFound
    }

    /**
     * Check whether a global field is configured to parse Antlers in content.
     *
     * @param  mixed  $set  Global set instance with optional blueprint API
     * @param  string  $fieldHandle  Requested field handle from tag params
     * @return bool True when field exists and has `antlers: true`
     */
    protected function fieldUsesAntlers(
        mixed $set,
        string $fieldHandle
    ): bool {
        // Check if the field handle is empty
        if ($fieldHandle === '') {
            // Return false when there is no field handle to inspect
            return false;
        }

        // Attempt to read the field's antlers configuration from blueprint metadata
        try {
            // Concrete global sets expose the blueprint API; narrow the type via
            // PHPDoc rather than a runtime method_exists guard, which would
            // exclude the contract-based test doubles used in coverage.
            /** @var \Statamic\Globals\GlobalSet $globalSet */
            $globalSet = $set;

            // Read the blueprint for the global set
            /** @var Blueprint|null $blueprint */
            $blueprint = $globalSet->blueprint();

            // Check whether the requested field exists on the blueprint
            if (
                $blueprint === null
                || ! $blueprint->hasField($fieldHandle)
            ) {
                // Return false when the requested field is not defined
                return false;
            }

            // Read the field instance from the blueprint
            /** @var Field|null $field */
            $field = $blueprint->field($fieldHandle);

            // Check if field metadata is unavailable. Equivalent: without this return,
            // fieldtype() on null throws an Error, which the catch below turns into false.
            // @pest-mutate-ignore: RemoveEarlyReturn
            if ($field === null) {
                // Return false when the field cannot be inspected
                return false;
            }

            // Read the fieldtype instance for the field
            /** @var Fieldtype|null $fieldtype */
            $fieldtype = $field->fieldtype();

            // Check if the fieldtype is unavailable. Equivalent: without this return,
            // config() on null throws an Error, which the catch below turns into false.
            // @pest-mutate-ignore: RemoveEarlyReturn
            if ($fieldtype === null) {
                // Return false when antlers configuration cannot be read
                return false;
            }

            // Read the field's antlers configuration value
            $antlersEnabled = $fieldtype->config('antlers');
        } catch (\Throwable $throwable) {
            // Unset local variables to free memory
            unset($throwable);

            // Return false when metadata access fails
            return false;
        }

        // Return true only when antlers is explicitly enabled
        return $antlersEnabled === true;
    }
}
