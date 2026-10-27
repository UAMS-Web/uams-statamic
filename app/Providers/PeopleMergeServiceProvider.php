<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\CP\People\MergeController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Statamic\Facades\Permission;
use Statamic\Statamic;

/**
 * Wires up the duplicate-people merge feature (UAMS-Web/uams-statamic-archive#838): the `merge people entries`
 * permission and the Control Panel routes behind the {@see MergeController}
 * reconciliation screen. The entry point is the `MergePeople` bulk action on the
 * people collection, so no nav item is registered.
 */
class PeopleMergeServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the merge permission and CP routes.
     */
    public function boot(): void
    {
        $this->bootPermissions();
        $this->bootCpRoutes();
    }

    /**
     * Register the dedicated merge permission.
     */
    protected function bootPermissions(): void
    {
        Permission::group('people', 'People', function (): void {
            Permission::register('merge people entries')
                ->label(__('Merge duplicate people entries'));
        });
    }

    /**
     * Register the reconciliation screen + preview/commit endpoints.
     */
    protected function bootCpRoutes(): void
    {
        Statamic::pushCpRoutes(function (): void {
            Route::prefix('people/merge')->name('people-merge.')->group(function (): void {
                Route::get('/', [MergeController::class, 'show'])->name('show');
                Route::post('preview', [MergeController::class, 'preview'])->name('preview');
                Route::post('commit', [MergeController::class, 'commit'])->name('commit');
            });
        });
    }
}
