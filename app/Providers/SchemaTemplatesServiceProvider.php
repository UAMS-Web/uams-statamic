<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\CP\SchemaTemplates\SchemaBlueprintDefaultsController;
use App\Http\Controllers\CP\SchemaTemplates\SchemaJsonLdCacheController;
use App\Http\Controllers\CP\SchemaTemplates\SchemaSectionDefaultsController;
use App\Http\Controllers\CP\SchemaTemplates\SchemaSiteDefaultsController;
use App\Http\Controllers\CP\SchemaTemplates\SchemaTemplatesIndexController;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Statamic\CP\Navigation\NavItem;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Facades\User;
use Statamic\Statamic;

/**
 * Registers Schema Templates under Tools: Site Defaults, Section Defaults,
 * Blueprint Defaults, with permissions and CP routes.
 */
class SchemaTemplatesServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap permissions, CP routes, and nav.
     */
    public function boot(): void
    {
        $this->bootInertiaShares();
        $this->bootPermissions();
        $this->bootCpRoutes();
        Statamic::booted($this->bootNav(...));
    }

    protected function bootInertiaShares(): void
    {
        Inertia::share(
            'canEditSchemaSiteDefaults',
            fn (): bool => $this->currentUserCan('edit schema site defaults')
        );
        Inertia::share(
            'canEditSchemaSectionDefaults',
            fn (): bool => $this->currentUserCan('edit schema section defaults')
        );
        Inertia::share(
            'canEditSchemaBlueprintDefaults',
            fn (): bool => $this->currentUserCan('edit schema blueprint defaults')
        );
        Inertia::share(
            'canManageSchemaJsonLdCache',
            fn (): bool => $this->currentUserCan('manage schema json ld cache')
        );
    }

    /**
     * Whether the current Control Panel user holds the given ability.
     *
     * @param  string  $ability  Permission name to check
     * @return bool True when a user is authenticated and authorized for the ability
     */
    private function currentUserCan(string $ability): bool
    {
        /** @var Authorizable|null $user */
        $user = User::current();

        return $user?->can($ability) ?? false;
    }

    protected function bootPermissions(): void
    {
        Permission::group(
            'schema_templates',
            'Schema Templates',
            function (): void {
                Permission::register('edit schema site defaults')
                    ->label(__('Edit Schema Site Defaults'));
                Permission::register('edit schema section defaults')
                    ->label(__('Edit Schema Section Defaults'));
                Permission::register('edit schema blueprint defaults')
                    ->label(__('Edit Schema Blueprint Defaults'));
                Permission::register('manage schema json ld cache')
                    ->label(__('Manage Schema JSON-LD cache'));
            }
        );
    }

    protected function bootCpRoutes(): void
    {
        Statamic::pushCpRoutes(function (): void {
            Route::prefix('schema-templates')->name('schema-templates.')->group(function (): void {
                Route::get('/', [SchemaTemplatesIndexController::class, 'index'])->name('index');
                Route::get('site-defaults/edit', [SchemaSiteDefaultsController::class, 'edit'])->name('site-defaults.edit');
                Route::patch('site-defaults', [SchemaSiteDefaultsController::class, 'update'])->name('site-defaults.update');
                Route::get('section-defaults', [SchemaSectionDefaultsController::class, 'index'])->name('section-defaults.index');
                Route::get('section-defaults/{sectionType}/{sectionHandle}/edit', [SchemaSectionDefaultsController::class, 'edit'])->name('section-defaults.edit');
                Route::patch('section-defaults/{sectionType}/{sectionHandle}', [SchemaSectionDefaultsController::class, 'update'])->name('section-defaults.update');
                Route::get('blueprint-defaults', [SchemaBlueprintDefaultsController::class, 'index'])->name('blueprint-defaults.index');
                Route::get('blueprint-defaults/{sectionType}/{sectionHandle}/{blueprintHandle}/edit', [SchemaBlueprintDefaultsController::class, 'edit'])->name('blueprint-defaults.edit');
                Route::patch('blueprint-defaults/{sectionType}/{sectionHandle}/{blueprintHandle}', [SchemaBlueprintDefaultsController::class, 'update'])->name('blueprint-defaults.update');
                Route::get('json-ld-cache', [SchemaJsonLdCacheController::class, 'index'])->name('json-ld-cache.index');
                Route::post('json-ld-cache/clear', [SchemaJsonLdCacheController::class, 'clear'])->name('json-ld-cache.clear');
                Route::post('json-ld-cache/warm', [SchemaJsonLdCacheController::class, 'warm'])->name('json-ld-cache.warm');
            });
        });
    }

    protected function bootNav(): void
    {
        Nav::extend(function ($nav): void {
            if (! $this->userHasSchemaTemplatesPermissions()) {
                return;
            }

            /**
             * @var \Statamic\CP\Navigation\Nav $nav
             * @var NavItem $tools
             */
            $tools = $nav->tools(__('Schema Templates')); // @phpstan-ignore method.notFound

            /** @var NavItem $siteDefaults */
            $siteDefaults = $nav->item(__('Site Defaults'))
                ->route('schema-templates.site-defaults.edit');

            /** @var NavItem $sectionDefaults */
            $sectionDefaults = $nav->item(__('Section Defaults'))
                ->route('schema-templates.section-defaults.index');

            /** @var NavItem $blueprintDefaults */
            $blueprintDefaults = $nav->item(__('Blueprint Defaults'))
                ->route('schema-templates.blueprint-defaults.index');

            /** @var NavItem $jsonLdCache */
            $jsonLdCache = $nav->item(__('JSON-LD cache'))
                ->route('schema-templates.json-ld-cache.index');

            // cp_route() returns the Control Panel URL string for the named route
            $toolsUrl = cp_route('schema-templates.index');
            $tools->url(\is_string($toolsUrl) ? $toolsUrl : null);
            $tools->icon('programming-script-code');
            $tools->children([
                $siteDefaults->can('edit schema site defaults'),
                $sectionDefaults->can('edit schema section defaults'),
                $blueprintDefaults->can('edit schema blueprint defaults'),
                $jsonLdCache->can('manage schema json ld cache'),
            ]);
        });
    }

    private function userHasSchemaTemplatesPermissions(): bool
    {
        /** @var \Statamic\Auth\File\User $user */
        $user = User::current();
        if ($user->can('edit schema site defaults')) {
            return true;
        }

        if ($user->can('edit schema section defaults')) {
            return true;
        }

        if ($user->can('edit schema blueprint defaults')) {
            return true;
        }

        return $user->can('manage schema json ld cache');
    }
}
