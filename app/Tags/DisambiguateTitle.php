<?php

declare(strict_types=1);

namespace App\Tags;

use App\Listeners\SetCurrentEntryForTitleFormat;
use App\Services\TitleDisambiguator;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Antlers;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Fields\Value as ValueObject;
use Statamic\Tags\Context;
use Statamic\Tags\Tags;

/**
 * Paired tag for use in collection title_format to disambiguate duplicate titles.
 *
 * Wrap the base title content between {{ disambiguate_title }} and
 * {{ /disambiguate_title }}. The tag parses the inner content to get the base
 * title, checks existing entries in the same collection/site, and returns the
 * full title with suffix " (2)", " (3)", etc. when needed. Works for new entries
 * (no id) by resolving collection/site from the entry, route, or current site.
 *
 * Example title_format (schema_objects):
 *   {{ disambiguate_title }}{{ schema.type }} — {{ schema.properties.headline ?? schema.properties.name }}{{ /disambiguate_title }}
 */
class DisambiguateTitle extends Tags
{
    /**
     * The {{ disambiguate_title }} paired tag. Returns the full title (base + suffix when needed).
     */
    public function index(): string
    {
        $content = $this->content;
        // Equivalent for the blank-content half: blank pair content parses to '', which
        // the check below returns. And for || as &&: setContent('') is what makes a tag
        // a single one, so a single tag's content is always ''.
        // @pest-mutate-ignore: BooleanOrToBooleanAnd, EmptyStringToNotEmpty, UnwrapTrim
        if (! $this->isPair || trim($content) === '') {
            return $this->parseContentAsBaseTitle();
        }

        $baseTitle = $this->parseContentAsBaseTitle();
        if ($baseTitle === '') {
            return '';
        }

        $entry = $this->resolveEntry();
        $disambiguator = app(TitleDisambiguator::class);

        if ($entry instanceof Entry && $entry->collection()) {
            $suffix = $disambiguator->suffixForEntry($entry, $baseTitle);

            return $baseTitle.$suffix;
        }

        $collectionHandle = $this->resolveCollectionHandle($entry);
        $siteHandle = $this->resolveSiteHandle($entry);
        // Equivalent for the '' check: resolveCollectionHandle() returns null rather
        // than an empty handle.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        if ($collectionHandle === null || $collectionHandle === '') {
            return $baseTitle;
        }

        $suffix = $disambiguator->suffixForCollectionAndSite(
            $collectionHandle,
            $siteHandle,
            $baseTitle
        );

        return $baseTitle.$suffix;
    }

    /**
     * Parse the tag content (or context) to get the base title string.
     */
    private function parseContentAsBaseTitle(): string
    {
        $content = $this->isPair
            ? $this->content
            : $this->getFallbackBaseFormat();
        // Equivalent without the return: Antlers parses blank content to blank, and the
        // trim below returns ''.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (
            // @pest-mutate-ignore: EmptyStringToNotEmpty, UnwrapTrim
            trim($content) === ''
        ) {
            return '';
        }

        /** @var Context $context */
        $context = $this->context;
        $variables = $context->all();
        $parsed = (string) Antlers::parse($content, $variables);

        return trim($parsed);
    }

    /**
     * When used as a single tag (no pair), we have no content; use a best-effort
     * base from context (e.g., title) so we don't break.
     */
    private function getFallbackBaseFormat(): string
    {
        /** @var Context $context */
        $context = $this->context;
        $title = $context->get('title');
        if ($title instanceof ValueObject) {
            $title = $title->value();
        }

        return \is_string($title) ? $title : '';
    }

    /**
     * Resolve the entry when possible: by context id, or from Blink (current save).
     */
    private function resolveEntry(): ?Entry
    {
        /** @var Context $context */
        $context = $this->context;
        $id = $context->get('id');
        if ($id instanceof ValueObject) {
            $id = $id->value();
        }

        if ($id !== null && $id !== '') {
            $entry = EntryFacade::find($id);

            // Equivalent: this repository's find() returns an Entry or null.
            // @pest-mutate-ignore: InstanceOfToTrue
            return $entry instanceof Entry ? $entry : null;
        }

        return SetCurrentEntryForTitleFormat::currentEntry();
    }

    /**
     * Resolve collection handle from entry, context, or request (e.g., CP URL).
     */
    private function resolveCollectionHandle(?Entry $entry): ?string
    {
        // Unreachable branch: index() returns through suffixForEntry() whenever the
        // entry has a collection, so skipping it changes nothing.
        // @pest-mutate-ignore: InstanceOfToFalse
        if ($entry instanceof Entry && $entry->collection()) {
            /** @var Collection $collection */
            $collection = $entry->collection();
            $handle = $collection->handle();

            return \is_string($handle) ? $handle : null;
        }

        /** @var Context $context */
        $context = $this->context;
        $fromContext = $context->get('collection');
        if ($fromContext instanceof ValueObject) {
            $fromContext = $fromContext->value();
        }

        if (\is_string($fromContext) && $fromContext !== '') {
            return $fromContext;
        }

        $fromRequest = request()->route('collection') ?? request()->input('collection');
        if (\is_string($fromRequest) && $fromRequest !== '') {
            return $fromRequest;
        }

        // CP create form: /cp/collections/{handle}/entries/create
        $segments = request()->segments();
        $collectionsIdx = array_search('collections', $segments, true);
        // request()->segments() is a zero-indexed list, so a found key is an int
        if (\is_int($collectionsIdx) && isset($segments[$collectionsIdx + 1])) {
            $segment = $segments[$collectionsIdx + 1];

            return \is_string($segment) ? $segment : null;
        }

        return null;
    }

    /**
     * Resolve the Statamic site handle for disambiguation scope.
     *
     * Uses only the actual site the entry belongs to (or is being created in).
     * Does not use the blueprint field with handle "site" (e.g. "Source Site" on
     * schema_objects), which is entry data, not the entry's locale/site.
     *
     * Fallback order: entry's site → route param (CP create/store) → current site.
     *
     * @param  Entry|null  $entry  The entry to resolve the site handle for
     * @return string|null The site handle, or null if not found
     */
    private function resolveSiteHandle(
        ?Entry $entry
    ): ?string {
        // Check if the entry has a site
        if ($entry instanceof Entry && $entry->site() !== null) {
            /** @var \Statamic\Sites\Site $entrySite */
            $entrySite = $entry->site();
            $entryHandle = $entrySite->handle();

            // Return the site handle
            return \is_string($entryHandle) ? $entryHandle : null;
        }

        // Get the site from the route parameter
        $fromRoute = request()->route('site');

        // Check if the site is a string and not empty
        if (\is_string($fromRoute) && $fromRoute !== '') {
            // Return the site handle
            return $fromRoute;
        }

        // Get the current site
        /** @var \Statamic\Sites\Site|null $current */
        $current = Site::current();

        // Check if the current site is not null
        if ($current !== null) {
            $currentHandle = $current->handle();

            // Return the site handle
            return \is_string($currentHandle) ? $currentHandle : null;
        }

        // Return null
        return null;
    }
}
