<?php

declare(strict_types=1);

use Statamic\Facades\Taxonomy;

if (! function_exists('ensureAudioSeriesTaxonomyRegistered')) {
    /**
     * Guarantee the `audio_series` taxonomy is resolvable so a term loaded via
     * `Term::find` / `Term::query` always has a non-null `taxonomy()` on the schema
     * render path (`defaultLocale()`, `title()`, `termMatchesTaxonomy`, …).
     *
     * `audio_series` is defined only in the `content/` submodule and is touched by a
     * handful of tests, so its taxonomies Stache store is often cold. On each per-test
     * app refresh the taxonomies and terms stores hydrate independently, so the
     * taxonomies store can transiently miss `audio_series` while the terms store still
     * resolves a term; `Term::taxonomy()`'s `Blink::once("taxonomy-audio_series", …)`
     * then memoizes that null for the rest of the test, crashing the render with
     * `Call to a member function sites() on null` (UAMS-Web/uams-statamic-archive#1611). Saving pins the taxonomy
     * into the store's in-memory items so the miss can never form.
     *
     * Idempotent and content-safe: the guard skips the write on the warm path (whenever
     * the taxonomy already resolves), and the reconstructed config is byte-identical to
     * `content/taxonomies/audio_series.yaml` (`title: 'Audio Series'` + `sites: [default]`),
     * so a cold-path write leaves no net submodule diff. Keep those two values in sync
     * with that file if it ever changes.
     */
    function ensureAudioSeriesTaxonomyRegistered(): void
    {
        // Warm path: the taxonomy already resolves, so nothing to register or write.
        if (Taxonomy::findByHandle('audio_series') !== null) {
            return;
        }

        // Cold path: pin the taxonomy into the store so `taxonomy()` is never null.
        /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
        $taxonomy = Taxonomy::make('audio_series');
        $taxonomy->title('Audio Series');
        $taxonomy->sites(['default']);
        $taxonomy->save();
    }
}
