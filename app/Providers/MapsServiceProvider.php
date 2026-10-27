<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\CP\Gps\NominatimSearchController;
use App\Services\Locations\CountyRegionMap;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Statamic\Statamic;

/**
 * Registers Control-Panel routes and bindings for map / GPS features.
 *
 * The server-side Nominatim address-search proxy consumed by the GPS fieldtype's
 * picker (App\Http\Controllers\CP\Gps\NominatimSearchController), and the
 * coordinate-to-region derivation services (UAMS-Web/uams-statamic-archive#962).
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
    }

    /**
     * Bootstrap map-related Control-Panel routes.
     */
    public function boot(): void
    {
        $this->bootCpRoutes();
    }

    /**
     * Register the CP route backing the GPS picker's address search.
     */
    protected function bootCpRoutes(): void
    {
        Statamic::pushCpRoutes(function (): void {
            Route::get(
                'gps/nominatim-search',
                NominatimSearchController::class,
            )->name('gps.nominatim-search');
        });
    }
}
