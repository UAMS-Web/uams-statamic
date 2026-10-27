<?php

declare(strict_types=1);

namespace App\Http\Controllers\CP\SchemaTemplates;

use App\Services\SchemaTemplates\SchemaJsonLdOutputCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Statamic\Http\Controllers\CP\CpController;

/**
 * Statamic CP controller for clearing and warming the schema JSON-LD output cache.
 *
 * Presents an Inertia utility page and runs cache bust or warm flows for users who
 * can manage the JSON-LD cache ability.
 */
class SchemaJsonLdCacheController extends CpController
{
    /**
     * Render the JSON-LD cache utility page with config-derived metadata.
     *
     * @return InertiaResponse Inertia page for bust/warm actions and cache state
     */
    public function index(): InertiaResponse
    {
        // Authorize the user to manage the schema JSON-LD cache
        $this->authorize('manage schema json ld cache');

        // Get the schema JSON-LD output cache
        $cache = app(SchemaJsonLdOutputCache::class);

        // Get the last bump metadata
        $lastBump = $cache->lastBumpMeta();

        // Get the configured TTL, narrowing to a numeric scalar before casting
        $ttlConfig = config('schema_templates.json_ld_cache.ttl_seconds', 3600);
        $ttlSeconds = \is_numeric($ttlConfig) ? (int) $ttlConfig : 0;

        // Render the schema JSON-LD cache utility
        return Inertia::render(
            'app::SchemaTemplatesJsonLdCache',
            [
                // This utility manages the page output cache, so report the two
                // switches that gate it: the master switch and the output-cache
                // switch split from it in UAMS-Web/uams-statamic-archive#1842.
                'enabled' => (bool) config(
                    'schema_templates.json_ld_cache.enabled',
                    true
                ) && (bool) config(
                    'schema_templates.json_ld_cache.output_enabled',
                    true
                ),
                'ttlSeconds' => max(
                    0,
                    $ttlSeconds
                ),
                'bust' => $cache->currentBust(),
                'lastBump' => $lastBump,
                'lastWarm' => $cache->lastWarmSummary(),
                'clearUrl' => cp_route('schema-templates.json-ld-cache.clear'),
                'warmUrl' => cp_route('schema-templates.json-ld-cache.warm'),
                'displayTimezone' => config('statamic.system.display_timezone'),
            ]
        );
    }

    /**
     * Bump the cache generation so existing JSON-LD entries are abandoned.
     *
     * @return RedirectResponse Redirect back to the prior CP page after flashing success
     */
    public function clear(): RedirectResponse
    {
        // Authorize the user to manage the schema JSON-LD cache
        $this->authorize('manage schema json ld cache');

        // Bump the schema JSON-LD cache
        app(SchemaJsonLdOutputCache::class)->bump();

        // ---
        // Flash the standard CP success session key
        // (see Statamic\Http\Controllers\Controller::success)
        // ---
        $this->success(__('Schema JSON-LD cache cleared.'));

        // Return a redirect to the previous page
        return back();
    }

    /**
     * Run the warm Artisan command synchronously and flash command output to the
     * session.
     *
     * @param  Request  $request  Incoming request with optional `site` field
     * @return RedirectResponse Redirect back to the prior CP page after flashing success
     */
    public function warm(
        Request $request
    ): RedirectResponse {
        // Authorize the user to manage the schema JSON-LD cache
        $this->authorize('manage schema json ld cache');

        // Set the time limit to 600 seconds
        set_time_limit(600);

        $options = [];

        // Get the site from the request
        $site = $request->input('site');

        // Check if the site is set
        if (
            \is_string($site)
            && $site !== ''
        ) {
            // Add the site to the options
            $options['--site'] = $site;
        }

        // Call the warm console command
        Artisan::call(
            'schema-templates:warm-json-ld-cache',
            $options
        );

        // Get the output from the command
        $output = Artisan::output();

        // Check if the output is not empty
        $message = $output !== ''
            ? trim($output)
            : __('Schema JSON-LD warm completed.');

        // ---
        // Flash the standard CP success session key
        // (see Statamic\Http\Controllers\Controller::success)
        // ---
        $this->success($message);

        // Return a redirect to the previous page
        return back();
    }
}
