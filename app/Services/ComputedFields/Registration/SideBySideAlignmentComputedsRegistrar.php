<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Services\PageBuilder\SideBySideAlignmentResolver;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;

/**
 * Registers the `side_by_side_alignments` computed on every collection that hosts
 * the shared `page_builder` replicator, so the Side-by-Side Image & Text block can
 * render its auto-alternating alignment options (UAMS-Web/uams-statamic-archive#1192).
 *
 * The computed is a render-order map of Side-by-Side block id => resolved image
 * side (`left`/`right`), produced by {@see SideBySideAlignmentResolver} from the
 * entry's raw `page_builder` value. The block partial reads this map by its own
 * block id to resolve the two auto options; explicit `left`/`right` blocks ignore
 * it and render exactly as authored.
 *
 * Only these four collections import the replicator `page_builder` fieldset (the
 * one whose set list contains `uamsweb_block_image_side_by_side`); the Find-a-Doc
 * profile collections instead expose a Bard `page_builder`, which cannot hold the
 * block, so they are deliberately excluded.
 *
 * @see SideBySideAlignmentResolver the pure one-pass this exposes
 */
final class SideBySideAlignmentComputedsRegistrar
{
    /**
     * The computed-field handle the Side-by-Side block partial reads.
     */
    public const string HANDLE = 'side_by_side_alignments';

    /**
     * Collections whose `page_builder` is the replicator that can host the
     * Side-by-Side Image & Text block.
     *
     * @var list<string>
     */
    private const array COLLECTIONS = [
        'pages',
        'reusable_content',
        'clinical_locations',
        'clinical_expertise',
    ];

    public function __construct(private readonly SideBySideAlignmentResolver $resolver) {}

    /**
     * Register the alignment-map computed on each page-builder-hosting collection.
     */
    public function register(): void
    {
        foreach (self::COLLECTIONS as $collection) {
            Collection::computed(
                $collection,
                self::HANDLE,
                // Resolve with value() (not get()) so a localization without its own
                // page_builder copy inherits the origin's blocks and alternates the
                // same way the template renders them.
                fn (Entry $entry, mixed $_stored): array => $this->resolveFor($entry->value('page_builder')),
            );
        }
    }

    /**
     * Resolve the alignment map from a raw page_builder value, tolerating a
     * non-iterable (absent/empty) field.
     *
     * @return array<string, string> Block id => resolved side (`left`/`right`).
     */
    private function resolveFor(mixed $pageBuilder): array
    {
        if (! is_iterable($pageBuilder)) {
            return [];
        }

        return $this->resolver->resolve($pageBuilder);
    }
}
