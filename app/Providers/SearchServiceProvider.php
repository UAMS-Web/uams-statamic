<?php

declare(strict_types=1);

namespace App\Providers;

use App\Search\AreaOfExpertiseSectionProvider;
use Illuminate\Support\ServiceProvider;
use Statamic\Facades\Search;

/**
 * Registers the app's custom search searchables providers.
 *
 * {@see AreaOfExpertiseSectionProvider} synthesizes one search document per populated
 * Area-of-Expertise routed section subpage (UAMS-Web/uams-statamic-archive#1527); it is wired into the `default`
 * (All results) index via the `clinical_expertise_section:*` searchables key in
 * `config/statamic/search.php`.
 */
final class SearchServiceProvider extends ServiceProvider
{
    /**
     * Register the custom searchable providers with Statamic's search subsystem.
     */
    public function boot(): void
    {
        Search::registerSearchableProvider(AreaOfExpertiseSectionProvider::class);
    }
}
