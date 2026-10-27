<?php

declare(strict_types=1);

namespace App\Providers;

use App\Console\Commands\RefusedSiteClear;
use Illuminate\Support\ServiceProvider;
use Statamic\Console\Commands\SiteClear;

/**
 * Binds Statamic's destructive `site:clear` command to a refusing subclass (UAMS-Web/uams-statamic-archive#2022).
 *
 * **What this binding actually covers.** The class answers to two command
 * names — `php artisan statamic:site:clear` and `php please site:clear`, the
 * latter because Statamic's `please` application calls
 * `RunsInPlease::removeStatamicGrouping()` to strip the `statamic:` prefix at
 * registration time. **Both names are already covered without this binding**,
 * by Laravel's `ApplicationBuilder::withCommands()` auto-discovery of
 * `app/Console/Commands`: it registers {@see RefusedSiteClear}
 * under the signature it inherits, and because that bootstrapper is registered
 * *after* Statamic's `ConsoleServiceProvider`, Symfony's "later registration
 * wins" rule makes it override Statamic's under both names. `Please\Kernel`
 * overrides `shouldDiscoverCommands()` to return true for itself, so discovery
 * runs there too.
 *
 * Measured, after an earlier version of this docblock claimed the opposite:
 * with the binding removed at source, `app(SiteClear::class)` returns
 * Statamic's real class while `please find('site:clear')` still returns the
 * refusing one.
 *
 * So this binding is **defense in depth**, not the sole protection. What it
 * uniquely covers is every path that resolves the class rather than the name —
 * `app(SiteClear::class)`, `Artisan::call(SiteClear::class)`, a direct
 * `make()` — and it keeps the guard working if the subclass is ever moved out
 * of the auto-discovered directory, which would silently drop the other layer.
 *
 * **Why not a `CommandStarting` listener.** A listener matches on the command
 * *name*, so it would need both strings enumerated and kept in step.
 *
 * The failure directions decide it. If Statamic ever moves or renames the
 * class, this binding fails **loudly** — the `use` statement stops resolving.
 * A listener matched on a command name would simply stop matching, and go on
 * passing invocations through to the real command with nothing reporting that
 * the guard had lapsed. For a guard whose whole job is preventing silent
 * destruction, only the loud failure is acceptable.
 *
 * Registered in `bootstrap/providers.php`. Ordering against Statamic's own
 * `ConsoleServiceProvider` does not matter: commands resolve lazily, so the
 * binding only has to exist by the time one is run.
 */
final class SiteClearGuardServiceProvider extends ServiceProvider
{
    /**
     * Register the refusing implementation over Statamic's.
     */
    #[\Override]
    public function register(): void
    {
        $this->app->bind(SiteClear::class, RefusedSiteClear::class);
    }
}
