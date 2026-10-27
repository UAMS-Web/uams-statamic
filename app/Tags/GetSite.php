<?php

declare(strict_types=1);

namespace App\Tags;

use Statamic\Facades\Site;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Fetch a site variable for a specific site (or current site).
 *
 * Parameters:
 *   - handle (required): The handle of the site to query (e.g., "default")
 *   - var (optional): The variable to query (e.g., "name")
 *
 * Usage:
 *   {{ get_site handle="default" var="name" }}  — returns the name of the site
 *   {{ get_site handle="default" }}  — returns the site as tag pair context
 */
class GetSite extends Tags
{
    /**
     * Handle for the tag.
     *
     * @var string
     */
    protected static $handle = 'get_site';

    /**
     * Default method when tag is used as single or pair.
     *
     * @return mixed The site variable value, the site object, null, or []
     */
    public function index(): mixed
    {
        /** @var Parameters $params */
        $params = $this->params;

        // Get the handle from the parameters (may be string or Site object from context)
        $handle = $params['handle'] ?? null;

        // Get the variable from the parameters
        $var = $params['var'] ?? null;

        // Normalize handle: Antlers can pass a Site object (e.g. source_site ?? site in schema context)
        $handleKey = $this->normalizeSiteHandle($handle);

        // Check if the handle is not provided
        if ($handleKey === null || $handleKey === '') {
            unset(
                $handle,
                $handleKey,
                $var
            );

            return null;
        }

        // Find the site by the handle
        $site = Site::get($handleKey);

        // Check if the site is not found
        if (! $site) {
            unset(
                $handle,
                $handleKey,
                $site
            );

            // Return null if the variable is provided, otherwise return an empty array
            return $var ? null : [];
        }

        // Check if the variable is provided (a non-string `var` cannot name a
        // property, so it falls through to returning the site itself)
        if (
            \is_string($var)
            && $var !== ''
        ) {
            // Return the augmented variable from the site (e.g., name, url, locale)
            $value = $site->{$var};

            unset(
                $handle,
                $handleKey,
                $var,
                $site
            );

            return $value;
        }

        unset(
            $handle,
            $handleKey,
            $var
        );

        // Return the site
        return $site;
    }

    /**
     * Normalize handle param to a string key for Site::get().
     * In schema/content Antlers context, handle may be a Site object (e.g. source_site ?? site).
     *
     * @param  mixed  $handle  String handle or Statamic\Sites\Site instance
     * @return string|null Site handle string or null if not resolvable
     */
    private function normalizeSiteHandle(mixed $handle): ?string
    {
        if (\is_string($handle) && $handle !== '') {
            return $handle;
        }

        if (\is_object($handle)) {
            if (method_exists($handle, 'handle')) {
                $key = $handle->handle();

                return \is_string($key) ? $key : null;
            }

            if (property_exists($handle, 'handle') && \is_string($handle->handle)) {
                return $handle->handle;
            }
        }

        return null;
    }
}
