<?php

declare(strict_types=1);

namespace App\Tags;

use App\Support\Navigation\SubsectionResolver;
use Statamic\Entries\Entry;
use Statamic\Fields\Value;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * `{{ subsection :id="id" }}` — resolves the current page's subsection (UAMS-Web/uams-statamic-archive#52).
 *
 * Returns whether the page is within a subsection and, if so, the subsection
 * root's id, URL, page-tree URI, and title. Feeds the header sub-brand
 * (`_brand.antlers.html`) and the subsection-scoped primary navigation
 * (`desktop.antlers.html`, and the mobile nav in UAMS-Web/uams-statamic-archive#53). The page is identified by
 * the `:id` param, falling back to the current entry's `id` in context.
 *
 * @return array{active: bool, id: string|null, url: string|null, uri: string|null, title: string|null}
 */
class Subsection extends Tags
{
    /**
     * Resolve the current page's subsection root for the templates.
     *
     * @return array{active: bool, id: string|null, url: string|null, uri: string|null, title: string|null}
     */
    public function index(): array
    {
        $entryId = $this->resolveEntryId();

        $root = $entryId !== null
            ? app(SubsectionResolver::class)->resolveRoot($entryId)
            : null;

        if (! $root instanceof Entry) {
            return [
                'active' => false,
                'id' => null,
                'url' => null,
                'uri' => null,
                'title' => null,
            ];
        }

        return [
            'active' => true,
            'id' => $this->stringOrNull($root->id()),
            'url' => $this->stringOrNull($root->url()),
            'uri' => $this->stringOrNull($root->uri()),
            'title' => $this->stringOrNull($root->value('title')),
        ];
    }

    /**
     * Resolve the page's entry id from the `:id` param, else the context `id`.
     */
    private function resolveEntryId(): int|string|null
    {
        /** @var Parameters $params */
        $params = $this->params;

        $param = $this->normalizeId($params->get('id'));
        if ($param !== null) {
            return $param;
        }

        /** @var Context $context */
        $context = $this->context;
        $contextId = $context->get('id');

        if ($contextId instanceof Value) {
            $contextId = $contextId->value();
        }

        return $this->normalizeId($contextId);
    }

    /**
     * Narrow a raw id value to a non-empty id, or null.
     */
    private function normalizeId(mixed $value): int|string|null
    {
        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Narrow an untyped entry accessor value to a non-empty string, or null.
     */
    private function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
