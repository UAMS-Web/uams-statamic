<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\Posts\NewsSpringboardService;
use App\Support\Statamic\NewsSectionGrid;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Site;
use Statamic\Fields\Value;
use Statamic\Sites\Site as SiteModel;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Provides the {{ news_home }} tag backing the `news` site's home template (UAMS-Web/uams-statamic-archive#1612).
 *
 * The newsroom home is the `axis=category`, `scope=all`, `zones=none` configuration
 * of the shared news-magazine core (UAMS-Web/uams-statamic-archive#1158): a sticky-first **lead** band over the
 * whole newsroom (no org filter), then category-slot **sections** below it, with
 * page-wide earlier-section-wins dedup so a post in the lead never repeats in a
 * section. It reads the home entry's `sections` grid + `lead_count` and delegates
 * to {@see NewsSpringboardService::resolveMagazine()}.
 *
 * Returns the `lead` posts and resolved `sections` (each with its deduped `posts`
 * and archive-link CTA) plus a `no_results` flag for the template.
 *
 * Usage:
 *   {{ news_home }}
 *     {{ if no_results }} … {{ else }}
 *       {{ lead }} … {{ /lead }}
 *       {{ sections }} <h2>{{ heading }}</h2> {{ posts }} … {{ /posts }} {{ /sections }}
 *     {{ /if }}
 *   {{ /news_home }}
 */
class NewsHome extends Tags
{
    /**
     * The {{ news_home }} tag.
     *
     * @return array<string, mixed>
     */
    public function index(): array
    {
        $sections = NewsSectionGrid::normalize($this->configValue('sections'));
        $leadCount = $this->leadCount();

        /** @var SiteModel $current */
        $current = Site::current();
        $handle = $current->handle();

        $magazine = app(NewsSpringboardService::class)->resolveMagazine(
            [
                // scope=all → the lead and sections span the current (news) site's
                // whole newsroom; the section grid may still narrow per slot.
                'lead_count' => $leadCount,
                'lead_sources' => [],
                'sections' => $sections,
            ],
            // Site::current()->handle() is always a string in Statamic; the
            // non-string arm is defensive only.
            // @pest-mutate-ignore: TernaryNegated, EmptyStringToNotEmpty
            \is_string($handle) ? $handle : ''
        );

        /** @var Collection<int, Entry> $lead */
        $lead = $magazine['lead'];
        $resolvedSections = $magazine['sections'];

        return [
            'lead' => $lead,
            'sections' => $resolvedSections,
            'no_results' => $lead->isEmpty() && ! $this->anySectionHasPosts($resolvedSections),
        ];
    }

    /**
     * The lead-band post count: an explicit `:lead_count` param wins, else the
     * home entry's `lead_count` field, else the magazine default.
     */
    private function leadCount(): int
    {
        $value = $this->configValue('lead_count');

        $count = \is_numeric($value) ? (int) $value : null;

        return $count > 0 ? $count : NewsSpringboardService::DEFAULT_LEAD_COUNT;
    }

    /**
     * Read a config value: an explicit tag param wins, else the rendering entry's
     * same-named field in context (mirrors {@see NewsSpringboard}).
     */
    private function configValue(string $key): mixed
    {
        /** @var Parameters $params */
        $params = $this->params;
        /** @var Context $context */
        $context = $this->context;

        $param = $params->get($key);
        $value = $param ?? $context->get($key);

        // An entry field reaches a tag's context AUGMENTED, so a context read is a
        // Value wrapper rather than the underlying scalar/array. Unwrap here so
        // numeric reads (`lead_count`) are not silently discarded; the `sections`
        // read is unaffected either way (NewsSectionGrid handles both shapes).
        return $value instanceof Value ? $value->value() : $value;
    }

    /**
     * Whether any resolved section still has posts after dedup.
     *
     * @param  array<int, array{heading: ?string, posts: Collection<int, Entry>, category_name: ?string, category_url: ?string}>  $sections
     */
    private function anySectionHasPosts(array $sections): bool
    {
        return \array_any($sections, static fn (array $section): bool => $section['posts']->isNotEmpty());
    }
}
