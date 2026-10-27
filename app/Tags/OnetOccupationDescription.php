<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\OnetWebServices\OnetOccupationOverviewService;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Antlers tag emitting schema.org `description` text from O*NET occupation
 * overview data for a single O*NET-SOC code parameter.
 *
 * Parameters:
 *   - code: O*NET-SOC code string (e.g. `17-2051.00`)
 *
 * Outputs empty string when the API key is missing, the code is invalid, or
 * the overview has no description.
 *
 * @see OnetOccupationOverviewService
 */
class OnetOccupationDescription extends Tags
{
    /**
     * Statamic-registered tag handle for Antlers.
     *
     * @var string
     */
    protected static $handle = 'onet_occupation_description';

    /**
     * Resolve and return the occupation description or an empty string.
     *
     * @return string Plain description text or empty when omitted.
     */
    public function index(): string
    {
        // ---
        // Read SOC token from tag parameters.
        // ---

        // Get the SOC code from the tag parameters
        /** @var Parameters $parameters */
        $parameters = $this->params;
        $code = $parameters->get('code');

        // Check if the SOC code is a string
        $code = is_string($code)
            ? trim($code)
            : '';

        // Check if the SOC code is empty
        if ($code === '') {
            // Return empty string to signal that the SOC code is empty
            return '';
        }

        // ---
        // Resolve cached or remote overview; omit missing descriptions.
        // ---

        // Fetch the overview data from the API
        $overview = app(OnetOccupationOverviewService::class)->overview(
            $code
        );

        // Check if the overview data is valid
        if (
            $overview === null
            || ! isset($overview['description'])
        ) {
            // Return empty string to signal that the overview data has no description
            return '';
        }

        // Return the description from the overview data
        return $overview['description'];
    }
}
