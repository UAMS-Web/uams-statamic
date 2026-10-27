<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Collection;
use Statamic\Sites\Site;
use Statamic\Sites\Sites;
use Tests\CreatesApplication;

/**
 * A test-only {@see Sites} that hydrates `resources/sites.yaml` once per PHP
 * process and reuses the resulting {@see Site} collection across
 * every per-test app boot.
 *
 * Statamic hydrates the multisite config the first time `Site::all()` is
 * resolved — which happens in `Statamic\Stache\ServiceProvider::boot()` on every
 * fresh per-test app. For this app's 255-site `resources/sites.yaml` that parse +
 * per-site Antlers resolve (`{{ config:app:url }}` → an absolute URL) costs
 * ~280ms, re-paid on every test's app boot (UAMS-Web/uams-statamic-archive#1582). The sites are read-only
 * global config, identical for every test — `config('app.url')` is fixed for the
 * process, so every app resolves byte-identical URLs — so hydrating them once and
 * injecting the cached collection is pure win with no cross-test state leak.
 *
 * Only the hydrated collection is cached, not the whole `Sites` instance: a fresh
 * `CachedSites` is built per app, so the mutable per-request state a `Sites`
 * carries (`$current`, `$currentUrlCallback`) starts at its defaults every time
 * and cannot bleed between tests. The cached `Site` objects themselves hold only
 * resolved scalars/arrays (handle, config, rawConfig, isDefault) — no app or
 * container reference — which is what makes the read-only share safe (verified in
 * UAMS-Web/uams-statamic-archive#1582; app-reuse stays rejected per UAMS-Web/uams-statamic-archive#1121). `CreatesApplication` rebinds
 * `Sites::class` to this class in a `booting()` callback so the Stache boot
 * resolves the caching instance.
 *
 * NOT `final`: because this is bound as the `Sites::class` singleton, it is the
 * root the `Site` facade resolves — so `Site::shouldReceive(...)` (Laravel's
 * facade mock) does `Mockery::mock(get_class($root))`, i.e. `Mockery::mock(self)`.
 * Mockery cannot subclass a `final` class, so marking this `final` breaks every
 * test that mocks the `Site` facade. Leave it extendable.
 */
class CachedSites extends Sites
{
    /**
     * The hydrated site collection, captured on the first boot in this process
     * and injected into every later boot. Null until the first hydration.
     *
     * @var Collection<string, Site>|null
     */
    private static ?Collection $hydratedSites = null;

    /**
     * How many times `resources/sites.yaml` has actually been hydrated in this
     * process. Stays at 1 once cached; a value above 1 means the per-process
     * cache regressed to a per-method hydration. Read by the bootstrap-cost guard
     * ({@see CreatesApplication::sitesHydrationCount()}).
     */
    private static int $hydrationCount = 0;

    public function __construct()
    {
        if (self::$hydratedSites !== null) {
            // Reuse: inject the already-hydrated collection, skipping the parse +
            // Antlers resolve of all 255 sites. `$current` / `$currentUrlCallback`
            // stay at their fresh per-instance defaults.
            $this->sites = self::$hydratedSites;

            return;
        }

        // First boot in this process: hydrate normally, then capture the resolved
        // collection so every later boot reuses it.
        parent::__construct();

        /** @var Collection<string, Site> $sites */
        $sites = $this->sites;
        self::$hydratedSites = $sites;
        self::$hydrationCount++;
    }

    /**
     * The number of real hydrations of `resources/sites.yaml` paid in this
     * process. 1 once hydrated; a value above 1 signals a cache regression.
     *
     * @return int Hydration count; 1 once the process has hydrated, 0 before.
     */
    public static function hydrationCount(): int
    {
        return self::$hydrationCount;
    }
}
