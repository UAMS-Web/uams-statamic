<?php

declare(strict_types=1);

namespace App\Tags;

use Statamic\Contracts\Query\Builder as QueryBuilder;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Value;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * `{{ posts_category_link_url }}` — the URL of a `posts_category_link` page's
 * selected post-category archive (UAMS-Web/uams-statamic-archive#220).
 *
 * In primary-navigation generation a `posts_category_link` page links to a
 * `post_categories` collection entry's archive URL rather than to its own
 * permalink. This tag resolves the selected category (the `category` field) to
 * its URL; it reads the value from the `:handle` param, falling back to the
 * `category` value in the current context (the nav-item scope). Returns an empty
 * string for a normal page (no `category`), so the caller can fall back to the
 * page's own URL — mirroring {@see ChildSiteUrl} for UAMS-Web/uams-statamic-archive#219.
 */
class PostsCategoryLinkUrl extends Tags
{
    /**
     * The structured collection a category link targets.
     */
    private const string CATEGORY_COLLECTION = 'post_categories';

    /**
     * Resolve the selected category entry's URL, or an empty string.
     */
    public function index(): string
    {
        /** @var Parameters $params */
        $params = $this->params;

        $value = $params->get('handle');

        if ($value === null) {
            /** @var Context $context */
            $context = $this->context;
            $value = $context->get('category');
        }

        $entry = $this->resolveEntry($value);

        // Only ever link a post_categories entry — never some other collection's
        // entry, even if a future blueprint reuses a `category` field elsewhere.
        if (! $entry instanceof Entry || $entry->collectionHandle() !== self::CATEGORY_COLLECTION) {
            return '';
        }

        $url = $entry->url();

        return \is_string($url) ? $url : '';
    }

    /**
     * Normalize a `category` value — an augmented Entry, an id string, an
     * unexecuted query builder, or a collection/array of any of these — to the
     * first resolvable category Entry.
     */
    private function resolveEntry(mixed $value): ?Entry
    {
        // An `entries` field can arrive wrapped in a Value.
        if ($value instanceof Value) {
            $value = $value->value();
        }

        // A multi-item entries field augments to an unexecuted query builder, so
        // run it before its entries can be read. (`method_exists` narrows the
        // empty Builder marker interface so `get()` type-checks.)
        if ($value instanceof QueryBuilder && method_exists($value, 'get')) {
            $value = $value->get();
        }

        if ($value instanceof Entry) {
            return $value;
        }

        if (\is_string($value) && $value !== '') {
            $entry = EntryFacade::find($value);

            return $entry instanceof Entry ? $entry : null;
        }

        if (is_iterable($value)) {
            foreach ($value as $item) {
                $resolved = $this->resolveEntry($item);

                if ($resolved instanceof Entry) {
                    return $resolved;
                }
            }
        }

        return null;
    }
}
