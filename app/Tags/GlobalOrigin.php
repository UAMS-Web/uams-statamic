<?php

declare(strict_types=1);

namespace App\Tags;

use App\Support\Forms\FormRequestContext;
use Statamic\Contracts\Data\Localization;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Globals\Variables;
use Statamic\Sites\Site as SiteObject;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Provides a Statamic tag for retrieving global set origin information.
 *
 * Handles the {{ global_origin }} tag in Antlers templates, allowing retrieval
 * of site origin data from global sets in both recursive and non-recursive modes.
 */
class GlobalOrigin extends Tags
{
    /**
     * Handles the {{ global_origin }} tag in Antlers templates.
     *
     * This method processes the tag based on provided parameters and returns either:
     *   - A string representing the immediate origin site handle (non-recursive mode).
     *   - An array of ancestor site objects (recursive mode), each containing 'title',
     *     'url', and 'subject'.
     *
     * Parameters:
     *   - handle (required): The handle of the global set to query
     *     (e.g., "uams_multisite_settings").
     *   - in (optional): The site handle to query; defaults to the current site if not
     *     specified.
     *   - recursive (optional): Boolean flag; if true, returns an array of ancestor
     *     sites up to but not beyond a root-level brand; defaults to false.
     *
     * Returns:
     *   - String: The immediate origin site handle if recursive is false or omitted.
     *   - Array: An array of site objects (with 'title', 'url', and 'subject') from the outermost
     *     ancestor (root-level brand) to just before the current site if recursive is
     *     true and the current site is not a root; returns an empty array if the
     *     current site is a root-level brand.
     *   - Empty string or array: If no global set or origin is found, returns ''
     *     (non-recursive) or [] (recursive).
     *
     * @return string|array<int, array<string, string|null>> The origin site handle or array of ancestor sites
     */
    public function index(): string|array
    {
        // ---
        // Retrieve the 'handle' parameter, which specifies the global set to query
        // (e.g., "uams_multisite_settings")
        // ---
        /** @var Parameters $params */
        $params = $this->params;

        $globalHandle = $params->get('handle');

        // ---
        // If no handle is provided, return an empty string as a safe default for
        // non-recursive mode or an empty array for recursive mode to avoid errors
        // downstream
        // ---

        // Check if the global handle parameter was provided
        if (! $globalHandle) {
            // Return empty string if no handle provided
            return '';
        }

        // ---
        // Get the site handle to query; use the provided 'in' parameter if specified,
        // otherwise default to the submit-time origin site (during a form-email render)
        // or the live current site's handle
        // ---
        /** @var SiteObject $currentSite */
        $currentSite = Site::current();

        // Prefer the origin site captured at submit time during a form-email render,
        // so the default site resolves to the submitter's site, not the worker's
        $captured = FormRequestContext::active();
        $defaultSiteHandle = $captured instanceof FormRequestContext && $captured->siteHandle !== ''
            ? $captured->siteHandle
            : $currentSite->handle();

        $siteHandle = $params->get(
            'in',
            $defaultSiteHandle
        );

        // ---
        // Determine if recursive mode is enabled; defaults to false if not specified,
        // triggering string output instead of an array
        // ---
        $recursive = $params->bool(
            'recursive',
            false
        );

        // ---
        // Fetch the global set for the specified handle and site; this contains the
        // site's settings, including the 'subject' field and origin data
        // ---
        /** @var Variables|null $global */
        $global = GlobalSet::find($globalHandle)?->in($siteHandle);

        // ---
        // If no global set is found for the given handle and site, return an empty string
        // (non-recursive) or empty array (recursive) as a fallback
        // ---

        // Check if the global set was found
        if (! $global) {
            // Return empty array for recursive mode or empty string for non-recursive mode
            return $recursive ? [] : '';
        }

        // Check if recursive mode is enabled
        if ($recursive) {
            // ---
            // Recursive mode: Build an array of ancestor sites from the outermost root-level
            // brand to just before the current site
            // ---

            // Check if the current site is designated as a root-level brand
            if ($global->get('subject') === 'root') {
                // Return empty array since root sites have no ancestors in breadcrumbs
                return [];
            }

            // Initialize an empty array to store the final ordered list of ancestor sites
            $origins = [];

            // Start with the current global set object to traverse its origin chain
            $current = $global;

            // ---
            // Temporary array to collect origins in reverse order (innermost to outermost)
            // during traversal
            // ---
            $tempOrigins = [];

            // ---
            // Traverse the origin chain until no further origin exists or a root-level brand
            // is encountered
            // ---
            while ($current->origin()) {
                // ---
                // Get the origin of the current global set, which is either a Variables object
                // (Localization) or null
                // ---
                /** @var Variables|null $origin */
                $origin = $current->origin();

                // ---
                // Check if the origin is a valid Localization object, representing a site in the
                // multisite configuration
                // ---
                if ($origin instanceof Localization) {
                    // Extract the site handle (locale) from the origin, identifying the ancestor site
                    $locale = $origin->locale();

                    // ---
                    // Retrieve site data from Statamic's Site facade using the locale to access its
                    // name and URL
                    // ---
                    /** @var SiteObject|null $siteData */
                    $siteData = Site::get($locale);

                    // ---
                    // If site data exists, add it to the temporary array with its title, URL, and
                    // subject for breadcrumb and sub-brand use
                    // ---

                    // Check if site data was retrieved successfully
                    if ($siteData) {
                        // Fetch the global set for this origin site to read its 'subject' value
                        /** @var Variables|null $originGlobal */
                        $originGlobal = GlobalSet::find($globalHandle)?->in($locale);

                        // Determine the origin site's subject (e.g., "root"), or null if unavailable
                        $subjectValue = $originGlobal?->get('subject');
                        $originSubject = \is_scalar($subjectValue) ? (string) $subjectValue : null;

                        // Narrow the site's display name to a string, defaulting to '' if non-scalar
                        $siteName = $siteData->name();
                        $siteTitle = \is_scalar($siteName) ? (string) $siteName : '';

                        // Narrow the site's full URL to a string, defaulting to '' if non-scalar
                        $siteUrl = $siteData->absoluteUrl();
                        $siteAbsoluteUrl = \is_scalar($siteUrl) ? (string) $siteUrl : '';

                        // Add the site data to the temporary origins array
                        $tempOrigins[] = [
                            'title' => $siteTitle, // The site's display name (e.g., "University of Arkansas for Medical Sciences")
                            'url' => $siteAbsoluteUrl, // The site's full URL (e.g., "http://uams-statamic.test")
                            'subject' => $originSubject, // The site's brand subject (e.g., "root", "major_entity", "sub_entity")
                        ];

                        // Check if the origin site exists and is designated as a root-level brand
                        if ($originSubject === 'root') {
                            // Stop adding ancestors, as this is the outermost root-level brand
                            break;
                        }
                    }

                    // Move to the next origin in the chain (the parent of the current origin)
                    $current = $origin;
                } else { // Otherwise (if the origin isn't a Localization object)
                    // Stop the loop
                    break;
                }
            }

            // ---
            // Reverse the temporary array to order ancestors from outermost (e.g., "default")
            // to innermost (e.g., immediate parent)
            // ---
            $origins = \array_reverse($tempOrigins);

            // ---
            // Return the array of ancestor sites, which will be empty if no ancestors exist
            // or if recursion stopped early
            // ---
            return $origins;
        }

        // Otherwise
        // ---
        // Non-recursive mode: Return the immediate origin site handle as a string
        // ---
        // ---
        // Get the immediate origin of the global set, which is either a Variables object
        // or null
        // ---
        /** @var Variables|null $origin */
        $origin = $global->origin();
        // ---
        // If an origin exists and is a Localization object, return its site handle
        // (locale); otherwise, return an empty string so the declared string|array
        // return type holds for origin-less / root brands
        // ---
        $localeValue = $origin instanceof Localization ? $origin->locale() : '';

        // ---
        // Return the result, which will be a string (e.g., "default") or an empty string
        // if no origin is set
        // ---
        return \is_scalar($localeValue) ? (string) $localeValue : '';
    }
}
