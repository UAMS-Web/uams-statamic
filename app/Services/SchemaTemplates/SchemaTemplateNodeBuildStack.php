<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

/**
 * Tracks schema_objects entry ids currently being built for `schema_template_node`.
 *
 * When a schema property template embeds `{{ schema_template_node id="…" }}` for
 * the same entry id that is already being resolved, emitting a full node again
 * would recurse without bound. The stack detects that case so the service can
 * output only `{"@id":…}` (aligned with graph dedupe once the full node exists).
 */
final class SchemaTemplateNodeBuildStack
{
    /**
     * Entry ids with an in-flight `buildSchemaTemplateNodeJsonForTag` / node build.
     *
     * @var array<int, string>
     */
    private static array $entryIds = [];

    /**
     * Whether an entry id is already being built (re-entrant tag call).
     */
    public static function contains(string $entryId): bool
    {
        return \in_array($entryId, self::$entryIds, true);
    }

    /**
     * Mark an entry id as currently being built.
     */
    public static function push(string $entryId): void
    {
        self::$entryIds[] = $entryId;
    }

    /**
     * Clear the innermost entry id after its build attempt finishes.
     */
    public static function pop(): void
    {
        array_pop(self::$entryIds);
    }
}
