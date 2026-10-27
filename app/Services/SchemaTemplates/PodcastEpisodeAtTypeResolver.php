<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

use App\Enums\SchemaType;

/**
 * Resolves the JSON-LD `@type` list for an audio entry that belongs to a podcast
 * series, co-typing its own content node as a `PodcastEpisode` (issue UAMS-Web/uams-statamic-archive#1487).
 *
 * A podcast episode is authored on the shared `audio` blueprint (`posts/audio`
 * and `clinical_resources/audio`), whose content node is declared as an
 * `Article`. When the entry's `series` (`audio_series`) term declares the
 * `podcast` subtype (UAMS-Web/uams-statamic-archive#1611), that node should also advertise `PodcastEpisode` so
 * the episode fields (`partOfSeries`, `episodeNumber`, …) describe a genuine
 * `PodcastEpisode` node; a non-podcast recording on the same blueprint must keep
 * its declared `Article` type alone.
 *
 * This mirrors {@see MedicalOrganizationSubtypeAtTypeResolver}: a pure rule that
 * refines a node's declared `@type`, gated by {@see SchemaOutputService} to the
 * context entity's own business node so nested / shared nodes are untouched.
 */
class PodcastEpisodeAtTypeResolver
{
    /**
     * The Schema.org `PodcastEpisode` type appended to a podcast episode's node.
     */
    private const string PODCAST_EPISODE = 'PodcastEpisode';

    /**
     * Root type a node must equal or descend from to be co-typed as a
     * `PodcastEpisode` (itself a `CreativeWork`), so the co-type stays coherent.
     */
    private const string CREATIVE_WORK = 'CreativeWork';

    /**
     * Build the co-typed `@type` list for a podcast episode's content node.
     *
     * Returns the declared types with `PodcastEpisode` appended when the node
     * models a `CreativeWork` and does not already declare `PodcastEpisode`;
     * otherwise an empty list so the caller keeps the entry's declared `@type`.
     *
     * @param  list<string>  $declaredTypes  The node's declared Schema.org types
     * @return list<string> `[...$declaredTypes, 'PodcastEpisode']`, or `[]` to
     *                      keep the declared `@type`
     */
    public function resolve(array $declaredTypes): array
    {
        // Already advertises PodcastEpisode → nothing to add.
        // Needle and haystack are Schema.org type strings; TrueToFalse on the
        // strict flag is equivalent (no non-string loosely equals the needle).
        // @pest-mutate-ignore: TrueToFalse
        if (\in_array(self::PODCAST_EPISODE, $declaredTypes, true)) {
            return [];
        }

        // Only co-type a CreativeWork node; PodcastEpisode is a CreativeWork, so
        // appending it to a non-CreativeWork base would over-claim.
        if (! $this->declaresCreativeWork($declaredTypes)) {
            return [];
        }

        return [...$declaredTypes, self::PODCAST_EPISODE];
    }

    /**
     * Whether at least one declared type is `CreativeWork` or descends from it.
     *
     * @param  list<string>  $declaredTypes  The node's declared Schema.org types
     */
    private function declaresCreativeWork(array $declaredTypes): bool
    {
        foreach ($declaredTypes as $type) {
            if ($type === self::CREATIVE_WORK) {
                return true;
            }

            // ancestorsOf yields [] for an empty or unknown type name, so an
            // unrecognized type simply fails the CreativeWork gate below.
            if (
                // Needle and ancestor list are Schema.org type strings; TrueToFalse
                // on the strict flag is equivalent.
                // @pest-mutate-ignore: TrueToFalse
                \in_array(self::CREATIVE_WORK, SchemaType::ancestorsOf($type), true)
            ) {
                return true;
            }
        }

        return false;
    }
}
