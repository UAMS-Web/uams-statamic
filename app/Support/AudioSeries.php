<?php

declare(strict_types=1);

namespace App\Support;

use Statamic\Entries\Entry;
use Statamic\Facades\Term;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Value;
use Statamic\Taxonomies\LocalizedTerm;

/**
 * Shared knowledge of the `audio_series` taxonomy and its multi-select `subtype`
 * discriminator (podcast, radio, …) introduced when `podcast_series` was folded
 * into `audio_series` (UAMS-Web/uams-statamic-archive#1611).
 *
 * A podcast is a *subtype* of audio series: an audio entry (`posts/audio` or
 * `clinical_resources/audio`) models a podcast episode when the `audio_series`
 * term selected in its `series` field declares `podcast`. This helper resolves
 * that signal from the entry's raw stored `series` slug + a `Term::find` lookup —
 * the same independent term resolution the schema `schema_thing_from_relationship`
 * tag uses — so it stays correct on a live request AND hermetic under the schema
 * subsystem's synthetic in-memory fixtures (where terms-field augmentation does
 * not hydrate, but a seeded term is still resolvable by id). `subtype` is
 * non-localizable, so the default-locale term value is definitive on every site.
 */
final class AudioSeries
{
    /**
     * The taxonomy that both generic audio series and podcast series live on.
     */
    public const string TAXONOMY = 'audio_series';

    /**
     * The audio entry terms field pointing at the series term (`max_items: 1`).
     */
    public const string SERIES_FIELD = 'series';

    /**
     * The multi-select field on an `audio_series` term declaring its kind(s).
     */
    public const string SUBTYPE_FIELD = 'subtype';

    /**
     * The `subtype` option that turns on the podcast-episode model.
     */
    public const string SUBTYPE_PODCAST = 'podcast';

    /**
     * The `subtype` option marking a radio program.
     */
    public const string SUBTYPE_RADIO = 'radio';

    /**
     * Whether the audio_series term selected in the entry's `series` field
     * declares the given subtype.
     *
     * @param  Entry  $entry  The audio entry whose `series` is inspected
     * @param  string  $subtype  A `subtype` option key (e.g. `podcast`)
     */
    public static function entrySeriesHasSubtype(
        Entry $entry,
        string $subtype
    ): bool {
        // The `series` field is shared with the video collections (→ `video_series`)
        // and `recognition_lists` (→ `recognition_lists_series`); only treat it as
        // an audio series when THIS entry's `series` field actually targets the
        // `audio_series` taxonomy, so a colliding non-audio slug is never resolved
        // to a podcast audio_series term (UAMS-Web/uams-statamic-archive#1611).
        if (! self::entrySeriesTargetsAudioSeries($entry)) {
            return false;
        }

        // Check every audio_series term id the entry's `series` field selects
        foreach (self::seriesTermIds($entry) as $termId) {
            if (\in_array($subtype, self::subtypesForTermId($termId), true)) {
                // Unset local variables to free up memory
                unset(
                    $termId
                );

                // Stop at the first series term that declares the subtype
                return true;
            }
        }

        // No selected series term declares the subtype
        return false;
    }

    /**
     * Whether the entry's `series` field targets the `audio_series` taxonomy on
     * this entry's own blueprint (as opposed to `video_series` on the shared-name
     * video `series` field, or `recognition_lists_series`).
     *
     * @param  Entry  $entry  The entry whose `series` field is inspected
     */
    private static function entrySeriesTargetsAudioSeries(Entry $entry): bool
    {
        $blueprint = $entry->blueprint();
        if (! $blueprint instanceof Blueprint) {
            return false;
        }

        $field = $blueprint->field(self::SERIES_FIELD);
        if (! $field instanceof Field) {
            return false;
        }

        $taxonomies = $field->get('taxonomies');

        return \is_array($taxonomies) && \in_array(self::TAXONOMY, $taxonomies, true);
    }

    /**
     * The `subtype` option keys declared by the `audio_series` term with the
     * given canonical id, or an empty list when it does not resolve.
     *
     * Loads the term independently of field augmentation (so a synthetic
     * in-memory entry that carries only the raw slug still resolves a seeded
     * term). `Term::find` yields a {@see LocalizedTerm}.
     *
     * @param  string  $termId  A canonical `audio_series::<slug>` term id
     * @return list<string> The declared subtype option keys
     */
    public static function subtypesForTermId(string $termId): array
    {
        $term = Term::find($termId);

        // Only a resolved, localized term exposes stored field values here
        if (! $term instanceof LocalizedTerm) {
            return [];
        }

        // Read the raw stored value; a term loaded by id exposes the on-disk value
        // directly (never an augmentation Value container)
        $raw = $term->get(self::SUBTYPE_FIELD);

        // A `select` (multiple) stores a flat list of option-key strings
        if (! is_array($raw)) {
            return [];
        }

        // Keep the non-empty string option keys, re-indexed as a list
        $subtypes = [];
        foreach ($raw as $option) {
            if (\is_string($option) && $option !== '') {
                $subtypes[] = $option;
            }
        }

        // Unset local variables to free up memory
        unset(
            $term,
            $raw
        );

        return $subtypes;
    }

    /**
     * Canonical `audio_series::<slug>` term ids selected in the entry's `series`
     * field (a `max_items: 1` terms field stores a bare slug, but tolerate a
     * list or an already-namespaced id).
     *
     * @param  Entry  $entry  The audio entry
     * @return list<string> Canonical term ids, empty when nothing is selected
     */
    private static function seriesTermIds(Entry $entry): array
    {
        // Read the raw stored value, unwrapping the augmentation Value container
        $raw = $entry->get(self::SERIES_FIELD);
        if ($raw instanceof Value) {
            $raw = $raw->value();
        }

        // Namespace each non-empty bare slug; keep already-qualified ids
        $termIds = [];
        foreach (is_array($raw) ? $raw : [$raw] as $slug) {
            if (! \is_string($slug)) {
                continue;
            }

            if ($slug === '') {
                continue;
            }

            $termIds[] = str_contains($slug, '::')
                ? $slug
                : self::TAXONOMY.'::'.$slug;
        }

        return $termIds;
    }
}
