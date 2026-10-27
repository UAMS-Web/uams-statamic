<?php

declare(strict_types=1);

namespace Tests\Feature\Views;

use Statamic\Entries\Entry;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * A configurable stand-in for the {{ uams_news }} tag, so the News block's format
 * branches render hermetically (no posts collection / content submodule) with
 * fixture items under the caller's `as` alias. Set the static fixtures per test.
 *
 * Complements {@see StubUamsNewsTag} (the no-results variant): this one drives the
 * item-rendering branches (image slot, per-item category, byline meta, fallback).
 */
class ConfigurableNewsItemsTag extends Tags
{
    /**
     * The fixture items each iterated under the `as` alias.
     *
     * The entry arm is not a test-only convenience: production's
     * `UamsNews::result()` returns `array<int, Entry>`, and a real entry supplies the
     * deferred relation `Value` that an array fixture omits or flattens.
     *
     * Real entries are necessary but NOT sufficient to reach the empty-relation
     * fall-through: the block must also be rendered through a `{{ partial: }}`, because
     * `Antlers::parse()` on a source string runs untrusted and silently reduces the
     * relation. Both conditions took UAMS-Web/uams-statamic-archive#2031 two attempts to get right — see the note on
     * `renderNewsBlock()`.
     *
     * @var array<int, array<string, mixed>|Entry>
     */
    public static array $items = [];

    /** The category-CTA name (block-level). */
    public static ?string $categoryName = null;

    /** The category-CTA url (block-level; null until UAMS-Web/uams-statamic-archive#1145). */
    public static ?string $categoryUrl = null;

    /**
     * Reset the fixtures between tests so state never leaks across cases.
     */
    public static function reset(): void
    {
        self::$items = [];
        self::$categoryName = null;
        self::$categoryUrl = null;
    }

    /**
     * @return array<string, mixed>
     */
    public function index(): array
    {
        /** @var Parameters $params */
        $params = $this->params;
        /** @var string $as */
        $as = $params->get('as', 'news_item');

        return [
            $as => self::$items,
            'no_results' => self::$items === [],
            'total_results' => \count(self::$items),
            'category_name' => self::$categoryName,
            'category_url' => self::$categoryUrl,
        ];
    }
}
