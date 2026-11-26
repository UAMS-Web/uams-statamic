<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Locations\CountyRegionMap;
use App\Support\Http\OutboundUrlEgressGuard;
use Illuminate\Support\ServiceProvider;
use UAMSWeb\Aether\Fieldtypes\Contracts\EgressGuard;

/**
 * Registers container bindings for map / GPS features.
 *
 * The GPS picker's address search is served by the shared fieldtype add-on at the Control Panel
 * route `gps/nominatim-search`; this provider binds the egress guard that endpoint fetches through
 * to the site's {@see OutboundUrlEgressGuard}. It also memoizes the coordinate-to-region map
 * (UAMS-Web/uams-statamic-archive#962).
 */
class MapsServiceProvider extends ServiceProvider
{
    /**
     * Register container bindings.
     */
    #[\Override]
    public function register(): void
    {
        // Memoize the county -> region map for the whole process: a bulk import
        // saving many locations builds it from the taxonomy once, not per entry.
        $this->app->singleton(CountyRegionMap::class);

        // Route the add-on's address-search fetch through the site's SSRF guard.
        // The add-on offers its own guard with `bindIf`, so this binding wins
        // whichever provider registers first.
        $this->app->bind(EgressGuard::class, OutboundUrlEgressGuard::class);
    }
}
