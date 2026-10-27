<?php

declare(strict_types=1);

namespace Tests\Feature\Views;

use Statamic\Tags\Tags;

/**
 * A no-results stand-in for the {{ uams_news }} tag, so NewsBlockTest renders the
 * block template hermetically (no posts collection / content submodule) while
 * still exercising the block-field capture lines that run before the tag.
 */
class StubUamsNewsTag extends Tags
{
    /**
     * @return array<string, mixed>
     */
    public function index(): array
    {
        return ['no_results' => true, 'news_item' => []];
    }
}
