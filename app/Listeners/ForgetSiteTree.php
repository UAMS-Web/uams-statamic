<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Support\Multisite\SiteTreeCache;
use Statamic\Events\GlobalSetDeleted;
use Statamic\Events\GlobalSetSaved;
use Statamic\Events\GlobalVariablesDeleted;
use Statamic\Events\GlobalVariablesSaved;
use Statamic\Events\StacheCleared;
use Statamic\Events\StacheWarmed;

/**
 * Forgets the cached multisite tree search reads when a global set, its variables or the Stache
 * changes (#480).
 *
 * The cache's fingerprint already catches a changed file; these events cover a change the Stache
 * makes without one, such as a refresh after a deploy. See {@see SiteTreeCache}.
 */
class ForgetSiteTree
{
    /**
     * Forget the cached tree, whatever the event.
     *
     * @param  GlobalSetSaved|GlobalSetDeleted|GlobalVariablesSaved|GlobalVariablesDeleted|StacheCleared|StacheWarmed  $event  The change, which only names the moment to forget.
     */
    public function handle(GlobalSetSaved|GlobalSetDeleted|GlobalVariablesSaved|GlobalVariablesDeleted|StacheCleared|StacheWarmed $event): void
    {
        SiteTreeCache::forget();
    }
}
