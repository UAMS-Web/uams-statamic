<?php

declare(strict_types=1);

namespace App\Tags;

use Statamic\Facades\Entry;
use Statamic\Tags\Context;
use Statamic\Tags\Tags;

/**
 * Computes the rendering layout of a calculator entry — which variable is the
 * displayed equation's primary (the formula whose target appears as the
 * caption's LHS), which are referenced by that formula, and which sit outside
 * the equation entirely.
 *
 * Lives as a tag because the analysis needs regex string matching (extracting
 * identifier symbols from the formula source), which Antlers doesn't expose.
 *
 * Reads `parts` from the surrounding scope, returns an array with:
 *   - `primary_handle`: handle of the primary variable
 *   - `primary`: array of one part — the primary variable (the formula's
 *                LHS / caption target)
 *   - `referenced`: array of parts referenced by the primary's formula, in
 *                   part order
 *   - `other`: array of parts neither primary nor referenced
 *   - `caption_html`: pre-prettified HTML for the formula caption (the
 *                     same output a JS engine would build, computed
 *                     server-side so the caption appears on first paint)
 *
 * Visual column mapping is the partial's choice — the tag itself is
 * layout-neutral and just classifies parts by their role in the displayed
 * equation.
 *
 * Usage in Antlers:
 *
 *   {{ calculator_layout }}
 *       {{ primary }}...{{ /primary }}
 *       {{ referenced }}...{{ /referenced }}
 *       {{ other }}...{{ /other }}
 *   {{ /calculator_layout }}
 */
class CalculatorLayout extends Tags
{
    /**
     * Return the layout map for the surrounding calculator entry's parts.
     *
     * @return array{primary_handle: string, primary: list<array<string, mixed>>, referenced: list<array<string, mixed>>, other: list<array<string, mixed>>, caption_html: string}
     */
    public function index(): array
    {
        // The augmented `parts` value reachable through the Antlers context is
        // a collection of fieldtype-wrapped objects, not raw arrays — accessing
        // `$part['formula']` on those would return another wrapped object, not
        // the stored value. Pulling the entry's raw parts off the entry by id
        // gives plain PHP arrays for the strategy work below.
        /** @var Context $context */
        $context = $this->context;
        $entryId = $context->get('id');
        $entryId = \is_string($entryId) || \is_int($entryId) ? $entryId : null;
        /** @var \Statamic\Entries\Entry|null $entry */
        $entry = $entryId ? Entry::find($entryId) : null;
        $rawParts = $entry?->get('parts') ?: [];

        if (empty($rawParts) || ! \is_array($rawParts)) {
            return [
                'primary_handle' => '',
                'primary' => [],
                'referenced' => [],
                'other' => [],
                'caption_html' => '',
            ];
        }

        // The raw parts are plain PHP arrays (see note above); narrow them so the
        // strategy helpers and the per-part offset access below are well-typed.
        /** @var list<array<string, mixed>> $parts */
        $parts = array_values($rawParts);

        $primaryHandle = $this->resolvePrimaryHandle($parts);
        $referenced = $this->referencedHandlesForPrimary($parts, $primaryHandle);

        $primary = [];
        $referencedParts = [];
        $other = [];

        foreach ($parts as $part) {
            $handle = $part['handle'] ?? '';
            if ($handle === $primaryHandle) {
                $primary[] = $part;
            } elseif (\in_array($handle, $referenced, true)) {
                $referencedParts[] = $part;
            } else {
                $other[] = $part;
            }
        }

        return [
            'primary_handle' => $primaryHandle,
            'primary' => $primary,
            'referenced' => $referencedParts,
            'other' => $other,
            'caption_html' => $this->captionHtml($parts, $primaryHandle),
        ];
    }

    /**
     * Render the formula caption HTML server-side so it's visible on first
     * paint, avoiding the Alpine-boot flash where a JS engine would otherwise
     * populate the caption text after page load.
     *
     * Format: `<pretty-formula> = <strong>primary-label</strong>` —
     * expression first, target last to match the visual column order
     * (inputs on the left, computed result on the right).
     *
     * Splits the formula into identifier and non-identifier segments. An
     * identifier that matches a part handle becomes `<strong>label</strong>`;
     * other identifiers (mathjs function names like `floor` or `sqrt`) pass
     * through. Non-identifier segments have their operators substituted
     * (`*` → `×`, `/` → `÷`). Crucially, *every* segment — labels, identifiers,
     * and operator/number/punctuation residue alike — is HTML-escaped before it
     * lands in the `{{ caption_html | raw }}` output, so a formula containing
     * `<`, `>`, `"` or `=` cannot inject markup; only the `<strong>` wrappers
     * this method adds itself remain literal.
     *
     * @param  list<array<string, mixed>>  $parts
     */
    protected function captionHtml(array $parts, string $primaryHandle): string
    {
        $formula = '';
        $primaryLabel = '';
        $labels = [];

        foreach ($parts as $part) {
            $handle = \is_string($part['handle'] ?? null) ? $part['handle'] : '';
            if ($handle === '') {
                continue;
            }

            $label = $part['label'] ?? $handle;
            $labels[$handle] = \is_string($label) ? $label : $handle;
            if ($handle === $primaryHandle) {
                $formula = $this->extractFormula($part);
                $primaryLabel = \is_string($part['label'] ?? null) ? $part['label'] : $handle;
            }
        }

        if ($formula === '') {
            return '';
        }

        // Split the formula into alternating non-identifier and identifier
        // segments (even indices = operators/numbers/punctuation, odd = identifiers).
        // Every segment is HTML-escaped before it lands in the raw-rendered
        // caption, so a formula containing `<`, `>`, `"` or `=` cannot inject
        // markup; only the `<strong>` wrappers we add ourselves stay literal.
        $segments = preg_split(
            '/([a-zA-Z_]\w*)/',
            $formula,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        if ($segments === false) {
            $segments = [];
        }

        $pretty = '';
        foreach ($segments as $index => $segment) {
            if ($index % 2 === 1) {
                // Identifier: a known part handle becomes a bold label; anything
                // else (mathjs function names like `floor`/`sqrt`) passes through.
                $pretty .= isset($labels[$segment])
                    ? '<strong>'.htmlspecialchars($labels[$segment], ENT_QUOTES, 'UTF-8').'</strong>'
                    : htmlspecialchars($segment, ENT_QUOTES, 'UTF-8');

                continue;
            }

            // Non-identifier: substitute operators (`*` → `×`, `/` → `÷`) then
            // escape so any stray markup characters in the source are inert.
            $pretty .= htmlspecialchars(
                strtr($segment, ['*' => '×', '/' => '÷']),
                ENT_QUOTES,
                'UTF-8'
            );
        }

        return $pretty.' = <strong>'.htmlspecialchars($primaryLabel, ENT_QUOTES, 'UTF-8').'</strong>';
    }

    /**
     * Pick the primary variable. Prefers the part with `primary: true`; falls
     * back to the sole formula-having part when no explicit primary is set
     * (validator guarantees one of these resolves for any saved entry, but the
     * fallback also handles preview / draft states gracefully).
     *
     * @param  list<array<string, mixed>>  $parts
     */
    protected function resolvePrimaryHandle(array $parts): string
    {
        foreach ($parts as $part) {
            if (! empty($part['primary'])) {
                return \is_string($part['handle'] ?? null) ? $part['handle'] : '';
            }
        }

        $withFormula = array_filter($parts, fn (array $p): bool => $this->extractFormula($p) !== '');
        if (\count($withFormula) === 1) {
            $first = reset($withFormula);

            return \is_string($first['handle'] ?? null) ? $first['handle'] : '';
        }

        // Last-resort fallback so a misconfigured entry still renders.
        return \is_string($parts[0]['handle'] ?? null) ? $parts[0]['handle'] : '';
    }

    /**
     * Identifiers appearing in the primary's formula that match a sibling
     * part handle. Function names (floor, sqrt) and numeric constants are
     * filtered out implicitly because they don't match any handle.
     *
     * @param  list<array<string, mixed>>  $parts
     * @return list<string>
     */
    protected function referencedHandlesForPrimary(array $parts, string $primaryHandle): array
    {
        $primaryFormula = '';
        foreach ($parts as $part) {
            if (($part['handle'] ?? '') === $primaryHandle) {
                $primaryFormula = $this->extractFormula($part);
                break;
            }
        }

        if ($primaryFormula === '') {
            return [];
        }

        preg_match_all('/[a-zA-Z_]\w*/', $primaryFormula, $matches);
        $identifiers = array_unique($matches[0]);
        $partHandles = array_filter(array_map(fn (array $p): string => \is_string($p['handle'] ?? null) ? $p['handle'] : '', $parts));

        return array_values(array_intersect($identifiers, $partHandles));
    }

    /**
     * Read the formula's code string out of a part. The code fieldtype stores
     * `{ code, mode }`; tolerate a bare string for forward compatibility.
     *
     * @param  array<string, mixed>  $part
     */
    protected function extractFormula(array $part): string
    {
        $formula = $part['formula'] ?? null;
        if (\is_array($formula)) {
            $formula = $formula['code'] ?? '';
        }

        return \is_string($formula) ? trim($formula) : '';
    }
}
