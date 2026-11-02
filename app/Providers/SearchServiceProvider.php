<?php

declare(strict_types=1);

namespace App\Providers;

use App\Search\AreaOfExpertiseSectionProvider;
use App\Search\AreaOfExpertiseSuggestionPresenter;
use App\Search\PrivateEntryVisibility;
use App\Search\SecurityHeadersResponseAllowance;
use Illuminate\Support\ServiceProvider;
use Statamic\Facades\Search;
use UAMSWeb\Aether\Search\Contracts\EntryVisibility;
use UAMSWeb\Aether\Search\Contracts\ResponseAllowance;
use UAMSWeb\Aether\Search\Contracts\SuggestionPresenter;

/**
 * Registers the app's custom search searchables providers and the Aether Search seams.
 *
 * {@see AreaOfExpertiseSectionProvider} synthesizes one search document per populated
 * Area-of-Expertise routed section subpage (UAMS-Web/uams-statamic-archive#1527); it is wired into the `default`
 * (All results) index via the `clinical_expertise_section:*` searchables key in
 * `config/statamic/search.php`.
 *
 * The add-on binds its own default for each seam only when the application has not, and
 * it does so while booting, so these bindings belong in {@see register()}.
 */
final class SearchServiceProvider extends ServiceProvider
{
    /**
     * Bind this application's rules to the seams the add-on calls back into.
     */
    #[\Override]
    public function register(): void
    {
        $this->app->bind(EntryVisibility::class, PrivateEntryVisibility::class);
        $this->app->bind(SuggestionPresenter::class, AreaOfExpertiseSuggestionPresenter::class);
        $this->app->bind(ResponseAllowance::class, SecurityHeadersResponseAllowance::class);
    }

    /**
     * Register the custom searchable providers with Statamic's search subsystem.
     */
    public function boot(): void
    {
        Search::registerSearchableProvider(AreaOfExpertiseSectionProvider::class);
    }
}
