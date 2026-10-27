<?php

declare(strict_types=1);

namespace App\Modifiers;

use App\Support\AugmentedBardHtml;
use Statamic\Modifiers\Modifier;
use Stringable;

/**
 * Injects a string immediately after the first opening HTML tag of a chosen
 * type in augmented Bard HTML (not raw ProseMirror JSON).
 *
 * Use on augmented {@code content} in a tag pair, e.g.
 * {@code {{ content scope="block" | ensure_left_bard('LITTLE ROCK, Ark. — ') }}}
 * or with an explicit element:
 * {@code {{ content scope="block" | ensure_left_bard('Note: ', 'div') }}}.
 *
 * Supports:
 *
 * - Full-document HTML string (Bard without sets)
 * - List of {@see Values} rows (Bard with sets; mutates the first
 *   {@code type === 'text'} row)
 * - List of associative arrays (e.g. already-reduced loop rows)
 */
class EnsureLeftBard extends Modifier
{
    /**
     * Inject text after the first opening tag of the given type in Bard HTML.
     *
     * @param  mixed  $value  Augmented Bard: string, list of rows, or arrays
     * @param  array<int|string, mixed>  $params  {@code [0]} text to inject, {@code [1]} element name
     *                                            (default {@code p})
     * @param  array<string, mixed>  $context  Unused Statamic modifier context
     * @return mixed Same shape as input where possible (string or list of rows)
     */
    public function index(
        $value,
        array $params,
        array $context
    ): mixed {
        // ---
        // Resolve injected text and target element from modifier parameters
        // ---

        // Get the injected text from the parameters (scalar/Stringable-safe coercion)
        $injectedParam = $params[0] ?? '';
        $injected = is_scalar($injectedParam) || $injectedParam instanceof Stringable
            ? (string) $injectedParam
            : '';

        // Get the target element from the parameters
        $element = $params[1] ?? 'p';

        // Delegate to the shared Bard HTML helper
        return AugmentedBardHtml::transformFirstTextBlock(
            $value,
            $injected,
            $element,
            AugmentedBardHtml::injectAfterFirstOpeningTag(...)
        );
    }
}
