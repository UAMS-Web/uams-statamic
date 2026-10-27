<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Listeners\AppendOrphanBanner;
use App\Services\OrphanedAncestors\OrphanedAncestorResolver;
use Statamic\Entries\Entry;
use Statamic\Fields\Fieldtype;

/**
 * Read-only Control-Panel banner — the CP-signal half of the universal
 * orphaned-ancestor mechanism (UAMS-Web/uams-statamic-archive#1429), generalizing UAMS-Web/uams-statamic-archive#1077's `KbOrphanBanner`
 * across every routable + hierarchical collection.
 *
 * When the entry being edited is orphaned by an unpublished structural ancestor
 * (so its live page 302s away), this surfaces a warning naming the unpublished
 * ancestor and the redirect destination; otherwise it renders nothing. The
 * orphaned state is computed server-side from the entry via
 * {@see OrphanedAncestorResolver} — there is no request-controlled input.
 *
 * The field is injected onto handled collections' blueprints at resolve time by
 * {@see AppendOrphanBanner}, so it is not user-pickable.
 */
class OrphanBanner extends Fieldtype
{
    /**
     * The categories this fieldtype belongs to in the Statamic fieldtype selector.
     *
     * @var array<string>
     */
    protected $categories = [];

    /**
     * Not user-pickable — injected onto handled collections' blueprints.
     *
     * @var bool
     */
    protected $selectable = false;

    /**
     * Not a form field.
     *
     * @var bool
     */
    protected $selectableInForms = false;

    /**
     * The Vue component (registered as `orphan_banner-fieldtype` in cp.js).
     *
     * @var string
     */
    protected $component = 'orphan_banner';

    /**
     * The icon for this fieldtype.
     */
    #[\Override]
    public function icon(): string
    {
        return 'warning';
    }

    /**
     * This fieldtype has no configuration.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        return [];
    }

    /**
     * Compute the orphaned state of the entry being edited for the Vue banner.
     *
     * @return array{orphaned: bool, redirect_url: string|null, ancestor_title: string|null}
     */
    #[\Override]
    public function preload(): array
    {
        $empty = [
            'orphaned' => false,
            'redirect_url' => null,
            'ancestor_title' => null,
        ];

        $parent = $this->field()?->parent();
        if (! $parent instanceof Entry) {
            return $empty;
        }

        $result = app(OrphanedAncestorResolver::class)->resolve($parent);
        if ($result === null) {
            return $empty;
        }

        return [
            'orphaned' => true,
            'redirect_url' => $result->redirectUrl,
            'ancestor_title' => $result->unpublishedAncestorTitle,
        ];
    }
}
