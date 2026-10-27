<?php

declare(strict_types=1);

namespace App\Tags;

use App\Support\Statamic\Tags\TagParam;
use Illuminate\Support\Facades\Vite;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Resolve a source asset path through Vite's manifest to its public URL.
 *
 * Wraps Laravel's `Vite::asset()`, which reads `public/build/manifest.json`
 * in production and the dev-server hot file in development. Lets Antlers
 * templates reference Vite-built assets that aren't entry points — typically
 * fonts, images, or any other dependency a built CSS/JS bundle imports.
 *
 * Usage:
 *   <link rel="preload" href="{{ vite_asset src="resources/fonts/firago/FiraGO-Light.woff2" }}" as="font" type="font/woff2" crossorigin>
 */
class ViteAsset extends Tags
{
    /**
     * @return string Returns the public URL of the asset.
     */
    public function index(): string
    {
        // Get the source asset path from the tag parameters
        /** @var Parameters $params */
        $params = $this->params;
        $src = TagParam::get($params, 'src');

        // Narrow the mixed parameter to a string path for `Vite::asset()`.
        $src = \is_string($src) ? $src : '';

        // Return an empty string if no source is provided.
        if ($src === '' || $src === '0') {
            return '';
        }

        // ---
        // Return the public URL of the asset. This is the same as calling
        // `Vite::asset($src)` in an Antlers template.
        // ---
        return Vite::asset($src);
    }
}
