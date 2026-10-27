<?php

declare(strict_types=1);

namespace App\Services\LinkHealth;

/**
 * One link found at one precise location in stored content.
 *
 * The locality is the point of this record. Reporting `field: page_builder` is useless to an
 * editor when a blueprint has 97 replicator fields between them — the nested path names the set
 * index, the set handle, and the sub-field, so the reported location can be opened directly.
 */
final readonly class LinkOccurrence
{
    /**
     * @param  ContentItemType  $itemType  Which repository the value was read from.
     * @param  string  $container  Collection handle, global set handle, or taxonomy handle.
     * @param  string  $id  Entry id, global set id, or term id.
     * @param  string  $site  Handle of the site whose localization holds this value.
     * @param  string  $field  Top-level blueprint field handle the value lives under.
     * @param  list<string|int>  $path  Segments below the top-level field: set indexes, set
     *                                  handles, and sub-field handles, outermost first. Empty
     *                                  when the value sits directly on the field.
     * @param  string  $value  The stored value exactly as found, unnormalized.
     * @param  LinkKind  $kind  The classification assigned to this occurrence.
     */
    public function __construct(
        public ContentItemType $itemType,
        public string $container,
        public string $id,
        public string $site,
        public string $field,
        public array $path,
        public string $value,
        public LinkKind $kind,
    ) {}

    /**
     * Render the full location as a single dotted path, for reports and test assertions.
     *
     * @return string The field handle followed by each path segment, e.g. `page_builder.2.cta.url`.
     */
    public function location(): string
    {
        // `implode` casts each segment itself, and `$path` is `list<string|int>` by contract —
        // larastan enforces that at level 10 — so an `array_map(strval(...))` here converts nothing
        // `implode` would not. Mutation testing surfaced it: `UnwrapArrayMap` survived because
        // removing the map cannot change the output for any value the contract admits.
        return implode('.', [$this->field, ...$this->path]);
    }
}
