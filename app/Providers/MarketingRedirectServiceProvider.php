<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\CP\Redirects\MarketingRedirectController;
use App\Services\Redirects\MarketingRedirectStore;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Statamic\CP\Navigation\NavItem;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Facades\User;
use Statamic\Statamic;

/**
 * Wires up the marketing-redirect utility (UAMS-Web/uams-statamic-archive#1355) and its approval workflow
 * (UAMS-Web/uams-statamic-archive#1357): the `create marketing redirects` and `approve marketing redirects`
 * permissions, the Control Panel routes behind the {@see MarketingRedirectController}
 * screens, and Tools nav items — a creation utility for requesters and a review
 * queue (labelled with its pending-request count) for approvers.
 *
 * The utility writes to the shared `redirects` global (served on every site via
 * the UAMS-Web/uams-statamic-archive#1110 read-time merge) and is the safe, staff-only increment the #1105
 * trust-boundary decision settled on — no public creation path, and a created
 * redirect stays pending until an approver promotes it.
 */
class MarketingRedirectServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the redirect permissions, CP routes, and nav items.
     */
    public function boot(): void
    {
        $this->bootPermissions();
        $this->bootCpRoutes();
        $this->bootNav();
    }

    /**
     * Register the dedicated marketing-redirect permissions.
     */
    protected function bootPermissions(): void
    {
        Permission::group('redirects', 'Redirects', function (): void {
            Permission::register('create marketing redirects')
                ->label(__('Create marketing redirects'));
            Permission::register('approve marketing redirects')
                ->label(__('Approve marketing redirects'));
        });
    }

    /**
     * Register the create screen, review queue, and their endpoints.
     */
    protected function bootCpRoutes(): void
    {
        Statamic::pushCpRoutes(function (): void {
            Route::prefix('redirects/marketing')->name('marketing-redirects.')->group(function (): void {
                Route::get('/', [MarketingRedirectController::class, 'create'])->name('create');
                Route::post('/', [MarketingRedirectController::class, 'store'])->name('store');
                Route::get('review', [MarketingRedirectController::class, 'index'])->name('review');
                Route::post('{redirect}/approve', [MarketingRedirectController::class, 'approve'])->name('approve');
                Route::post('{redirect}/reject', [MarketingRedirectController::class, 'reject'])->name('reject');
            });
        });
    }

    /**
     * Add Tools nav items scoped to each user's redirect permissions.
     */
    protected function bootNav(): void
    {
        Nav::extend(function ($nav): void {
            /** @var \Statamic\Auth\File\User|null $user */
            $user = User::current();

            if ($user === null) {
                return;
            }

            // The creation utility, for users who can request redirects.
            if ($user->can(MarketingRedirectController::PERMISSION)) {
                /**
                 * @var \Statamic\CP\Navigation\Nav $nav
                 * @var NavItem $item
                 */
                $item = $nav->tools(__('Marketing Redirects')); // @phpstan-ignore method.notFound
                $item->route('marketing-redirects.create');
                $item->icon('hyperlink');
            }

            // The review queue, for approvers — surfacing the pending count (UAMS-Web/uams-statamic-archive#1357).
            if ($user->can(MarketingRedirectController::APPROVE_PERMISSION)) {
                // Read the pending count once so approvers see outstanding work in the nav.
                $pending = app(MarketingRedirectStore::class)->pendingCount();

                $label = $pending > 0
                    ? sprintf('%s (%d)', __('Review Redirects'), $pending)
                    : __('Review Redirects');

                /**
                 * @var \Statamic\CP\Navigation\Nav $nav
                 * @var NavItem $reviewItem
                 */
                $reviewItem = $nav->tools($label); // @phpstan-ignore method.notFound
                $reviewItem->route('marketing-redirects.review');
                $reviewItem->icon('flag');
            }
        });
    }
}
