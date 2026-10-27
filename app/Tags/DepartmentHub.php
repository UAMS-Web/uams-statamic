<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\ComputedFields\DepartmentHub\DepartmentHubPeopleComputedValues;
use App\Services\Posts\NewsSpringboardService;
use App\Support\Multisite\DepartmentHubResolver;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Site;
use Statamic\Fields\Value;
use Statamic\Sites\Site as SiteModel;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Provides the {{ department_hub }} tag backing the Departmental News Hub template
 * (UAMS-Web/uams-statamic-archive#1602).
 *
 * The hub is the `axis=type`, `scope={the site's org}`, `zones=department`
 * configuration of the shared news-magazine core. It resolves the current site's
 * `department_organization` (UAMS-Web/uams-statamic-archive#1556) and, when the site opts in, returns the
 * org-filtered, sticky-first lead + per-blueprint type sections (offset dedup,
 * {@see NewsSpringboardService::resolveDepartmentMagazine()}) and the derived
 * leadership/people zone ({@see DepartmentHubPeopleComputedValues}, UAMS-Web/uams-statamic-archive#1557).
 *
 * A site with no `department_organization` is not a hub: `is_hub` is false so the
 * template degrades to the news-only base rung (the UAMS-Web/uams-statamic-archive#179 posts listing), with no
 * people zone. Org-filtering is invariant on the enriched rung.
 *
 * Usage:
 *   {{ department_hub }}
 *     {{ if is_hub }}
 *       {{ lead }} … {{ /lead }}
 *       {{ sections }} <h2>{{ heading }}</h2> {{ posts }} … {{ /posts }} {{ /sections }}
 *       {{ if has_people }}{{ leadership }} … {{ /leadership }}{{ /if }}
 *     {{ else }} {{# degrade — base-rung posts listing #}} {{ /if }}
 *   {{ /department_hub }}
 */
class DepartmentHub extends Tags
{
    /**
     * The {{ department_hub }} tag.
     *
     * @return array<string, mixed>
     */
    public function index(): array
    {
        /** @var SiteModel $current */
        $current = Site::current();
        $handle = $current->handle();
        $handle = \is_string($handle) ? $handle : '';

        $organization = app(DepartmentHubResolver::class)->organization($handle);

        // Not an opt-in hub site — the template degrades to the base-rung listing.
        if (! $organization instanceof Entry) {
            return [
                'is_hub' => false,
                'has_people' => false,
                'no_results' => true,
                'lead' => collect(),
                'sections' => [],
                'leadership' => collect(),
            ];
        }

        $orgId = $organization->id();
        $organizationIds = \is_string($orgId) && $orgId !== '' ? [$orgId] : [];

        $magazine = app(NewsSpringboardService::class)->resolveDepartmentMagazine(
            ['lead_count' => $this->leadCount()],
            $organizationIds,
            $handle
        );

        /** @var Collection<int, Entry> $lead */
        $lead = $magazine['lead'];
        $sections = $magazine['sections'];

        // The derived leadership spine (UAMS-Web/uams-statamic-archive#1557); the fuller roster is gated on the audit.
        $leadership = app(DepartmentHubPeopleComputedValues::class)->resolveLeadership($organization);

        return [
            'is_hub' => true,
            'organization' => $organization,
            'lead' => $lead,
            'sections' => $sections,
            'leadership' => $leadership,
            'has_people' => $leadership->isNotEmpty(),
            'no_results' => $lead->isEmpty() && ! $this->anySectionHasPosts($sections),
        ];
    }

    /**
     * The lead-band post count: an explicit `:lead_count` param wins, else the hub
     * entry's `lead_count` field, else the magazine default.
     */
    private function leadCount(): int
    {
        /** @var Parameters $params */
        $params = $this->params;
        /** @var Context $context */
        $context = $this->context;

        $value = $params->get('lead_count') ?? $context->get('lead_count');

        // An entry field reaches a tag's context AUGMENTED, so the context read is a
        // Value wrapper, not a scalar — `is_numeric()` on it is false and the
        // editor's setting would be silently discarded. Unwrap first (the same
        // handling NewsSectionGrid applies to the sibling `sections` read).
        if ($value instanceof Value) {
            $value = $value->value();
        }

        return \is_numeric($value) && (int) $value > 0
            ? (int) $value
            : NewsSpringboardService::DEFAULT_LEAD_COUNT;
    }

    /**
     * Whether any resolved type section has posts.
     *
     * @param  array<int, array{type: string, heading: ?string, posts: Collection<int, Entry>}>  $sections
     */
    private function anySectionHasPosts(array $sections): bool
    {
        return \array_any($sections, static fn (array $section): bool => $section['posts']->isNotEmpty());
    }
}
