<?php

declare(strict_types=1);

namespace App\Tags;

use Statamic\Assets\Asset;
use Statamic\Facades\Asset as AssetFacade;
use Statamic\Facades\GlobalSet;
use Statamic\Globals\Variables;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Returns a JSON array of logo URLs for a site's subject (root,
 * major_entity, sub_entity) for use in schema_object logo property.
 *
 * Avoids Antlers modifier restrictions when parsed from stored content by
 * doing flatten, filter, dedupe, and URL resolution in PHP.
 *
 * Usage:
 *   {{ get_schema_logo_urls }}
 *   {{ get_schema_logo_urls site="default" }}
 *
 * @param  string|null  $site  Optional site handle; when omitted uses current site.
 *
 * Output: JSON string e.g. ["http://.../logo1.svg", "http://.../logo2.svg"]
 */
class GetSchemaLogoUrls extends Tags
{
    private const string GLOBAL_HANDLE = 'uams_multisite_settings';

    private const string LOGO_CONTAINER = 'logos';

    /**
     * Return JSON array of logo URLs for the given or current site subject.
     *
     * @return string JSON array of URL strings
     */
    public function index(): string
    {
        $set = GlobalSet::findByHandle(self::GLOBAL_HANDLE);
        if ($set === null) {
            return $this->jsonEncode([]);
        }

        /** @var Parameters $params */
        $params = $this->params;
        $siteHandle = $params->get('site');
        /** @var Variables|null $vars */
        $vars = \is_string($siteHandle) && $siteHandle !== ''
            ? $set->in($siteHandle)
            : $set->inCurrentSite();
        if ($vars === null) {
            return $this->jsonEncode([]);
        }

        $subject = $vars->get('subject');
        // Equivalent without the return: a non-string subject matches no branch below,
        // so no source is collected and the result is the same empty array.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! \is_string($subject)) {
            return $this->jsonEncode([]);
        }

        $sources = [];
        if ($subject === 'root') {
            $header = $vars->get('global_header_logo');
            $footer = $vars->get('global_footer_logo');
            if (\is_array($header)) {
                $sources[] = $header;
            }

            if (\is_array($footer)) {
                $sources[] = $footer;
            }
        } elseif ($subject === 'major_entity' || $subject === 'sub_entity') {
            $footer = $vars->get('global_footer_logo');
            if (\is_array($footer)) {
                $sources[] = $footer;
            }
        }

        $paths = [];
        foreach ($sources as $group) {
            $paths = array_merge($paths, $this->flattenLogoPaths($group));
        }

        $urls = [];
        foreach ($paths as $path) {
            // Equivalent: flattenLogoPaths() never returns an empty path. This and that
            // check back each other up, so neither mutant alone changes the output.
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            if ($path === '') {
                continue;
            }

            $url = $this->pathToUrl($path);
            // Equivalent: pathToUrl() returns null rather than ''. The two checks back
            // each other up.
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            if ($url !== null && $url !== '') {
                $urls[] = $url;
            }
        }

        $urls = array_values(array_unique($urls));

        return $this->jsonEncode($urls);
    }

    /**
     * Flatten nested logo group (orientation => color => path) to list of paths.
     *
     * @param  array<array-key, mixed>  $group
     * @return array<int, string>
     */
    private function flattenLogoPaths(array $group): array
    {
        $out = [];
        foreach ($group as $value) {
            // Equivalent: index() skips an empty path as well. The two checks back each
            // other up.
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            if (\is_string($value) && $value !== '') {
                $out[] = $value;
            } elseif (\is_array($value)) {
                $out = array_merge($out, $this->flattenLogoPaths($value));
            }
        }

        return $out;
    }

    /**
     * Resolve asset path (relative to container) to full URL.
     *
     * @return string|null Full URL or null if asset not found
     */
    private function pathToUrl(string $path): ?string
    {
        // Build the asset ID using the configured logo container handle
        $id = self::LOGO_CONTAINER.'::'.$path;

        // Find the asset by its container-prefixed ID
        $asset = AssetFacade::find($id);

        // Check if the asset was found
        if ($asset === null) {
            // Return null to signal that the asset was not found
            return null;
        }

        // Resolve the asset URL, preferring absolute URL when available
        $url = $asset instanceof Asset ? $asset->absoluteUrl() : null;

        // Check if the absolute URL is available
        if (
            ! \is_string($url)
            || $url === ''
        ) {
            // Return the relative URL when the absolute URL is not available
            $url = $asset->url();
        }

        // Build the final output URL when a non-empty string URL is available. Equivalent:
        // index() drops an empty URL as well; the two checks back each other up.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $outputUrl = $url !== '' ? $url : null;

        // Unset local variables to free memory
        unset(
            $asset,
            $id,
            $url
        );

        // Return the final output URL
        return $outputUrl;
    }

    /**
     * @param  array<int, string>  $urls
     */
    private function jsonEncode(array $urls): string
    {
        $json = json_encode($urls, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $json !== false ? $json : '[]';
    }
}
