<?php

declare(strict_types=1);

namespace App\Services\PageBuilder;

use App\Services\ComputedFields\Registration\SideBySideAlignmentComputedsRegistrar;

/**
 * Resolves the effective image side for the Side-by-Side Image & Text page-builder
 * block, honoring its two auto-alternating alignment options (UAMS-Web/uams-statamic-archive#1192).
 *
 * The block's `image_position` field offers four options: the two explicit sides
 * (`left`/`right`) and two auto options (`auto_default_left`/`auto_default_right`).
 * An auto block that does **not** immediately follow another Side-by-Side block
 * renders on its stated default side; an auto block that immediately follows one
 * flips to the opposite of that preceding block's rendered side. A run of
 * consecutive auto blocks therefore zig-zags without the editor hand-alternating.
 *
 * This is a single one-pass over the raw `page_builder` replicator value in stored
 * (render) order. It is a pure function of that array — no entry, request, or
 * Statamic state — so it is exercised directly in isolation. The
 * {@see SideBySideAlignmentComputedsRegistrar}
 * surfaces its output as an entry computed the block partial reads.
 */
final class SideBySideAlignmentResolver
{
    /**
     * The replicator set type of the Side-by-Side Image & Text block.
     */
    public const string SET_TYPE = 'uamsweb_block_image_side_by_side';

    /**
     * Auto option defaulting to image-left on the first block of a run.
     */
    public const string AUTO_DEFAULT_LEFT = 'auto_default_left';

    /**
     * Auto option defaulting to image-right on the first block of a run.
     */
    public const string AUTO_DEFAULT_RIGHT = 'auto_default_right';

    /**
     * The resolved image side for the left arrangement.
     */
    public const string SIDE_LEFT = 'left';

    /**
     * The resolved image side for the right arrangement.
     */
    public const string SIDE_RIGHT = 'right';

    /**
     * Map each Side-by-Side block to its resolved image side, keyed by block id.
     *
     * Walks the raw replicator blocks in stored order. Disabled blocks are skipped
     * as if absent (they are not rendered, so they neither resolve nor break a run);
     * any other non-Side-by-Side block breaks the run (resetting adjacency) so a
     * following auto block falls back to its default side. Only Side-by-Side blocks
     * with a stable `id` appear in the returned map; explicit-alignment blocks are
     * included too so the map is a complete render-order picture, but the partial
     * only consults it for the auto options.
     *
     * @param  iterable<mixed, mixed>  $blocks  The raw `page_builder` replicator value.
     * @return array<string, string> Block id => resolved side (`left`/`right`).
     */
    public function resolve(iterable $blocks): array
    {
        // The rendered image side of the immediately-preceding Side-by-Side block,
        // or null when the previous rendered block was not a Side-by-Side block.
        $previousSide = null;

        $resolved = [];

        foreach ($blocks as $block) {
            // A non-array entry cannot be a block; treat it as run-breaking noise.
            if (! is_array($block)) {
                $previousSide = null;

                continue;
            }

            // Disabled blocks are not rendered, so they do not participate in
            // adjacency at all — skip without touching the running side.
            if (($block['enabled'] ?? true) === false) {
                continue;
            }

            // Any block that is not a Side-by-Side block breaks the run.
            if (($block['type'] ?? null) !== self::SET_TYPE) {
                $previousSide = null;

                continue;
            }

            $position = $block['image_position'] ?? self::SIDE_LEFT;
            $side = $this->resolveSide(is_string($position) ? $position : self::SIDE_LEFT, $previousSide);

            $id = $block['id'] ?? null;
            if (is_string($id) && $id !== '') {
                $resolved[$id] = $side;
            }

            $previousSide = $side;
        }

        return $resolved;
    }

    /**
     * Resolve one block's image side from its stored position and the preceding
     * Side-by-Side block's rendered side.
     *
     * Explicit `left`/`right` pass through unchanged. An auto option uses its
     * default side when not immediately preceded by a Side-by-Side block, and
     * otherwise flips to the opposite of the preceding block's side.
     *
     * @param  string  $position  The block's stored `image_position` value.
     * @param  string|null  $previousSide  The preceding Side-by-Side block's side, or null.
     * @return string The resolved side (`left`/`right`).
     */
    private function resolveSide(string $position, ?string $previousSide): string
    {
        // Explicit alignment renders exactly as authored.
        if ($position === self::SIDE_LEFT || $position === self::SIDE_RIGHT) {
            return $position;
        }

        // Auto: the stated default side for the first block of a run.
        $default = $position === self::AUTO_DEFAULT_RIGHT ? self::SIDE_RIGHT : self::SIDE_LEFT;

        // Not immediately preceded by a Side-by-Side block — use the default.
        if ($previousSide === null) {
            return $default;
        }

        // Immediately follows a Side-by-Side block — flip to the opposite side.
        return $previousSide === self::SIDE_LEFT ? self::SIDE_RIGHT : self::SIDE_LEFT;
    }
}
