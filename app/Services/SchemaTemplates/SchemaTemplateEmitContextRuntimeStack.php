<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

/**
 * Holds the active {@see SchemaTemplateEmitContext} for nested Antlers parses
 * where Statamic's tag context does not always retain arbitrary scope keys.
 */
final class SchemaTemplateEmitContextRuntimeStack
{
    /** @var list<SchemaTemplateEmitContext> */
    private static array $stack = [];

    public static function push(SchemaTemplateEmitContext $context): void
    {
        self::$stack[] = $context;
    }

    public static function pop(): void
    {
        if (self::$stack !== []) {
            array_pop(self::$stack);
        }
    }

    public static function peek(): ?SchemaTemplateEmitContext
    {
        if (self::$stack === []) {
            return null;
        }

        return self::$stack[\count(self::$stack) - 1];
    }
}
