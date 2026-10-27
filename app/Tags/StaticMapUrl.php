<?php

declare(strict_types=1);

namespace App\Tags;

use Illuminate\Support\Facades\Config;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Build the no-JS static-map image URL for the `partials/map` <noscript> fallback (UAMS-Web/uams-statamic-archive#993).
 *
 * Substitutes a coordinate into the `maps.viewer.static_url` template's `{lat}` / `{lng}` /
 * `{zoom}` placeholders. The substitution lives in PHP because Antlers' `replace` modifier
 * cannot interpolate a variable into the template string.
 *
 * Returns an empty string — so the consumer renders no image and never leaks a key — when
 * `static_url` is unconfigured (the default) or the coordinate is missing / non-numeric.
 *
 * Usage:
 *   {{ static_map_url :lat="coordinate:lat" :lng="coordinate:lng" :zoom="$map_zoom" }}
 *
 * Parameters:
 *   - lat  (required): the coordinate latitude; must be numeric.
 *   - lng  (required): the coordinate longitude; must be numeric.
 *   - zoom (optional): the framed zoom; falls back to `maps.viewer.default_zoom`.
 */
class StaticMapUrl extends Tags
{
    /**
     * Antlers handle for this tag.
     *
     * @var string
     */
    protected static $handle = 'static_map_url';

    /**
     * Resolve the static-map image URL for the current coordinate.
     *
     * @return string The substituted URL, or empty when off / coordinate invalid
     */
    public function index(): string
    {
        // Read the operator-configured template; off (empty) by default
        $template = Config::get('maps.viewer.static_url');

        if (! \is_string($template) || trim($template) === '') {
            return '';
        }

        /** @var Parameters $params */
        $params = $this->params;

        $lat = $params->get('lat');
        $lng = $params->get('lng');

        // A missing / non-numeric coordinate renders no image (no broken markup, no key leak)
        if (! \is_numeric($lat) || ! \is_numeric($lng)) {
            return '';
        }

        // Substitute the coordinate. Casting through float canonicalizes the numeric value
        // (a direct caller's stray whitespace / exponent string collapses to a clean number)
        // and guarantees a URL-safe token; the zoom falls back to the configured viewer
        // default when the caller passes none.
        return strtr($template, [
            '{lat}' => (string) (float) $lat,
            '{lng}' => (string) (float) $lng,
            '{zoom}' => (string) $this->resolveZoom($params->get('zoom')),
        ]);
    }

    /**
     * Resolve the framed zoom: the passed value when numeric, else the viewer default.
     *
     * @param  mixed  $zoom  The caller-supplied zoom parameter
     */
    private function resolveZoom(mixed $zoom): int
    {
        if (\is_numeric($zoom)) {
            return (int) $zoom;
        }

        $default = Config::get('maps.viewer.default_zoom');

        return \is_numeric($default) ? (int) $default : 16;
    }
}
