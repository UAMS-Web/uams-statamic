<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Builds a schema.org fragment (scalar properties + optional PropertyValue
 * identifier) from a single classification or identifier value.
 *
 * Used to merge classifications/identifiers tab values into the schema node.
 */
interface SchemaIdentifierMergerInterface
{
    /**
     * Get the entry key (handle) for the replicator field.
     *
     * @return string Entry key (handle) for the replicator field (e.g., "duns", "naics")
     */
    public function sourceKey(): string;

    /**
     * Build the schema fragment for the given value.
     *
     * May include top-level properties (e.g. duns, taxID, naics, iso6523Code)
     * and an "identifier" key with a single PropertyValue structure. Caller
     * merges fragments and combines all identifier PropertyValues.
     *
     * @param  string  $value  Non-empty value from the replicator (e.g. "122452563")
     * @return array<string, mixed> Fragment to merge into the node; use "identifier" => [@type, name, ...] for PropertyValue
     */
    public function buildFragment(string $value): array;
}
