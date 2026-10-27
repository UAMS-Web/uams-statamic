<?php

declare(strict_types=1);

namespace App\Http\Controllers\CP\SchemaTemplates;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

/**
 * Statamic Control Panel entry for the Schema Templates tools hub.
 *
 * Renders the Inertia landing page that links to site, section, and blueprint
 * defaults plus JSON-LD cache utilities when the authenticated user has the
 * matching abilities.
 */
class SchemaTemplatesIndexController extends CpController
{
    /**
     * Render the Schema Templates index with permission booleans for each tools
     * subsection.
     *
     * @return InertiaResponse Inertia response for the Schema Templates tools hub and permission flags for
     *                         each linked area
     */
    public function index(): InertiaResponse
    {
        // ---
        // Render the Schema Templates index with permission booleans for each tools
        // subsection
        // ---
        /** @var Authorizable|null $user */
        $user = User::current();

        return Inertia::render(
            'app::SchemaTemplatesIndex',
            [
                'canEditSiteDefaults' => $user?->can('edit schema site defaults') ?? false,
                'canEditSectionDefaults' => $user?->can('edit schema section defaults') ?? false,
                'canEditBlueprintDefaults' => $user?->can('edit schema blueprint defaults') ?? false,
                'canManageJsonLdCache' => $user?->can('manage schema json ld cache') ?? false,
            ]
        );
    }
}
