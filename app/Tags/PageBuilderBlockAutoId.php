<?php

declare(strict_types=1);

namespace App\Tags;

use Illuminate\Support\Arr;
use Statamic\Fields\Value;
use Statamic\Tags\Context;
use Statamic\Tags\Tags;

/**
 * Resolves the stable `module-...` id for UAMS page builder blocks.
 *
 * Mirrors Antlers: `block:id ?? id ?? (($uamsweb_block_i ?? 0) + 1)` so callers
 * can assign once in their scope (including slot markup) without repeating the
 * expression. Nested partials cannot do this because partial rendering does not
 * bubble variable assignments to the parent.
 */
class PageBuilderBlockAutoId extends Tags
{
    /**
     * Emit the full stable `module-...` id for the current block context.
     *
     * @return string The full id (e.g., `module-m87i0wxv`)
     */
    public function index(): string
    {
        // Get the context
        /** @var Context $context */
        $context = $this->context;
        $context = $context->all();

        // Get the block index, defaulting to 0 when absent or non-numeric
        $blockIndex = Arr::get($context, 'uamsweb_block_i') ?? 0;
        $blockIndex = is_numeric($blockIndex) ? (int) $blockIndex : 0;

        // Get the suffix value
        $suffix = $this->unwrap(Arr::get($context, 'block.id'))
            ?? $this->unwrap(Arr::get($context, 'id'))
            ?? ($blockIndex + 1);

        // Narrow the suffix to a string for interpolation. RemoveStringCast stays
        // untested under PCOV when the suffix is already a string (the covering
        // path runs; the cast node is not attributed).
        // @pest-mutate-ignore: RemoveStringCast
        $suffixString = is_scalar($suffix) ? (string) $suffix : '';

        // Create the result string
        $result = 'module-'.$suffixString;

        // Unset the context and suffix variables
        unset(
            $context,
            $blockIndex,
            $suffix,
            $suffixString
        );

        // Return the result
        return $result;
    }

    /**
     * Unwrap a Statamic Value instance for use in the id expression.
     *
     * @param  mixed  $value  Raw value from context
     * @return mixed The underlying scalar or object, or null
     */
    private function unwrap(
        mixed $value
    ): mixed {
        // Check if the value is a Statamic Value instance
        if ($value instanceof Value) {
            // If it is, return the underlying scalar or object
            return $value->value();
        }

        // Otherwise, return the raw value
        return $value;
    }
}
