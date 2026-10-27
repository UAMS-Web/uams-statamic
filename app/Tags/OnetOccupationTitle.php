<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\OnetWebServices\OnetOccupationOverviewService;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Antlers tag emitting schema.org `name` text from O*NET occupation overview
 * data for a single O*NET-SOC code parameter.
 *
 * Parameters:
 *   - code: O*NET-SOC code string (e.g. `17-2051.00`)
 *
 * Outputs empty string when the API key is missing, the code is invalid, or
 * the overview cannot be loaded.
 *
 * @see OnetOccupationOverviewService
 */
class OnetOccupationTitle extends Tags
{
    /**
     * Statamic-registered tag handle for Antlers.
     *
     * @var string
     */
    protected static $handle = 'onet_occupation_title';

    /**
     * Resolve and return the occupation title or an empty string.
     *
     * @return string Plain title text or empty when omitted.
     */
    public function index(): string
    {
        // ---
        // Read SOC token from tag parameters.
        // ---

        // Get the SOC code from the tag parameters
        /** @var Parameters $params */
        $params = $this->params;
        $code = $params->get('code');

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
        // Resolve cached or remote overview.
        // ---

        // Fetch the overview data from the API
        $overview = app(OnetOccupationOverviewService::class)->overview(
            $code
        );

        // Check if the overview data is valid
        if ($overview === null) {
            // Return empty string to signal that the overview data is null
            return '';
        }

        // Return the title from the overview data
        return $overview['title'];
    }
}
