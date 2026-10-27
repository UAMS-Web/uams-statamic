<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\CP\SchemaObjectReplicatorDataController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Statamic\Statamic;

/**
 * Registers CP routes and other bootstrap for the schema_object fieldtype
 * (e.g., lazy-load replicator data endpoint).
 */
class SchemaObjectServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap schema_object fieldtype CP routes.
     */
    public function boot(): void
    {
        $this->bootCpRoutes();
    }

    /**
     * Register CP routes for schema_object fieldtype (lazy replicator data).
     */
    protected function bootCpRoutes(): void
    {
        Statamic::pushCpRoutes(function (): void {
            Route::post(
                'schema-object/replicator-data',
                SchemaObjectReplicatorDataController::class
            )->name('schema-object.replicator-data');
        });
    }
}
