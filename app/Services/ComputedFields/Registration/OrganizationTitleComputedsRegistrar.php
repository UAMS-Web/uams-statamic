<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Services\Organizations\OrganizationPublicLabelService;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;

/**
 * Registers the `organizations` computeds that expose an entry's effective
 * public label and its generated title (UAMS-Web/uams-statamic-archive#2125).
 *
 * The title is computed at read time rather than written on save. That is
 * viable here because this repo's local search driver indexes computed fields
 * — `people` and `clinical_providers` already index four of them — and the
 * `organizations` index reads the non-computed inputs directly, so an editor
 * can still find an entry by its Workday name, its Workday ID or its label.
 */
final readonly class OrganizationTitleComputedsRegistrar
{
    /**
     * @param  OrganizationPublicLabelService  $publicLabel  Resolves the own-or-inherited label and composes the title.
     */
    public function __construct(
        private OrganizationPublicLabelService $publicLabel
    ) {}

    /**
     * Register the `resolved_public_label` and `title` computeds.
     */
    public function register(): void
    {
        // Alias the injected service for the computed callbacks.
        $service = $this->publicLabel;

        // The effective public label — own, else the nearest non-inheriting
        // ancestor's. Display only: identity is unaffected, so a post tagged to
        // a deep unit stays tagged to that unit.
        Collection::computed(
            'organizations',
            'resolved_public_label',
            fn (Entry $entry, mixed $_stored): ?string => $service->resolvePublicLabel($entry)
        );

        // `Workday name (ID) — public label`, dropping the trailing segment when
        // the label is absent or merely repeats the Workday name, and falling
        // back to the label then the authored title when there is no Workday name.
        Collection::computed(
            'organizations',
            'title',
            fn (Entry $entry, mixed $_stored): string => $service->composeTitle($entry)
        );
    }
}
