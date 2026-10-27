<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\Posts\NewsSpringboardService;
use App\Support\Statamic\NewsSectionGrid;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteModel;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Provides the {{ news_springboard }} tag backing the news-magazine springboard
 * template (UAMS-Web/uams-statamic-archive#1158).
 *
 * Reads the springboard entry's `sections` grid — each row a category slot
 * (heading + `post_categories` category + item count) — and delegates to
 * {@see NewsSpringboardService}, which threads a page-wide accumulator so a post
 * surfaced in an earlier section never repeats in a later one. Returns the
 * resolved sections (each with its deduped `posts` and archive-link CTA) under
 * the `sections` alias for the template to render.
 *
 * Usage:
 *   {{ news_springboard }}
 *     {{ if no_results }} … {{ else }}
 *       {{ sections }} <h2>{{ heading }}</h2> {{ posts }} … {{ /posts }} {{ /sections }}
 *     {{ /if }}
 *   {{ /news_springboard }}
 */
class NewsSpringboard extends Tags
{
    /**
     * The {{ news_springboard }} tag.
     *
     * @return array<string, mixed>
     */
    public function index(): array
    {
        $sections = NewsSectionGrid::normalize($this->sectionsInput());

        /** @var SiteModel $current */
        $current = Site::current();
        $handle = $current->handle();

        $resolved = app(NewsSpringboardService::class)->resolveSections(
            $sections,
            \is_string($handle) ? $handle : ''
        );

        return [
            'sections' => $resolved,
            'no_results' => $resolved === [],
        ];
    }

    /**
     * The `sections` grid value: an explicit `:sections` param (used by tests)
     * falls back to the rendering entry's `sections` field in context.
     */
    private function sectionsInput(): mixed
    {
        /** @var Parameters $params */
        $params = $this->params;
        /** @var Context $context */
        $context = $this->context;

        $param = $params->get('sections');

        return $param ?? $context->get('sections');
    }
}
