<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\KnowledgeBase\KnowledgeBaseMountUriRefresher;
use Illuminate\Support\ServiceProvider;

/**
 * Wires up per-knowledge-base mounting (UAMS-Web/uams-statamic-archive#826).
 *
 * Mounting itself is implemented by `App\Entries\KnowledgeBaseEntry` (the live
 * `mount_prefix` route token) and `App\Stache\CollectionEntriesStore` (so every
 * read path uses that class). The old-URL-to-mounted-URL permanent redirect is
 * handled in the 404 template via the `{{ knowledge_base_mount_redirect }}`
 * tag, mirroring the project's `{{ find_redirect_rule }}` flow. This provider
 * registers the shared URI refresher that keeps mounted knowledge base URIs in
 * step with their mount-parent pages.
 */
class KnowledgeBaseServiceProvider extends ServiceProvider
{
    /**
     * Register knowledge base mounting services.
     *
     * The URI refresher is shared so its per-request re-entrancy guard spans
     * every listener invocation and the entry save hook.
     */
    #[\Override]
    public function register(): void
    {
        // Share a single mounted-knowledge-base URI refresher across the request.
        $this->app->singleton(KnowledgeBaseMountUriRefresher::class);
    }
}
