<?php

declare(strict_types=1);

namespace App\Services\Posts;

use App\Support\Statamic\Modifiers\WhitespaceDelimitedWordMetrics;
use Statamic\Assets\Asset as StatamicAsset;
use Statamic\Entries\Entry;
use Statamic\Facades\Asset;
use Statamic\Facades\Blink;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Term;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Value;
use Statamic\Globals\Variables;
use Statamic\Modifiers\Modify;
use Statamic\Sites\Site;
use Statamic\Statamic;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;

/**
 * Aggregates plain text from posts entries for metrics, following the same field
 * sources as post Antlers (content, multisite boilerplates, segment titles,
 * excerpts, and transcripts). Unlike Antlers display pipelines, this does not
 * apply `sanitize` so apostrophes and ampersands stay as real characters and
 * `word_count_uams` / `WhitespaceDelimitedWordMetrics` tokenization (not
 * Statamic’s `word_count` modifier) so counts align with common paste-based
 * counters. Reading time uses the same token count and default WPM (`200`) as
 * Statamic `read_time`, but whole minutes are `ceil(seconds / 60)` where
 * `seconds` is `ceil(words / wpm * 60)` (not `ceil(words / wpm)` alone).
 * The posts collection computed `reading_time` exposes that minute bucket as
 * seconds (`minutes * 60`), not as a minute count.
 */
class PostsReadingMetricsService
{
    /**
     * A run of valid UTF-8 sequences in the `valid` group, or else any single byte.
     *
     * Read without the `u` flag, so it matches text that is not valid UTF-8. A byte
     * outside the `valid` group starts no valid sequence, and is replaced with a space (UAMS-Web/uams-statamic-archive#2984).
     */
    private const string VALID_UTF8_RUN_OR_BYTE = '/(?<valid>(?:[\x00-\x7F]'
        .'|[\xC2-\xDF][\x80-\xBF]'
        .'|\xE0[\xA0-\xBF][\x80-\xBF]'
        .'|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}'
        .'|\xED[\x80-\x9F][\x80-\xBF]'
        .'|\xF0[\x90-\xBF][\x80-\xBF]{2}'
        .'|[\xF1-\xF3][\x80-\xBF]{3}'
        .'|\xF4[\x80-\x8F][\x80-\xBF]{2})+)|./s';

    /**
     * Word count for a posts entry using whitespace-delimited tokens on aggregated text.
     *
     * @param  Entry  $entry  Post entry
     * @return int Non-negative word count
     */
    public function wordCount(
        Entry $entry
    ): int {
        // Build the same plain-text aggregate used for reading time
        $text = $this->totalTextContent($entry);

        // Count tokens split on Unicode whitespace only
        $count = WhitespaceDelimitedWordMetrics::countWords($text);

        // Unset local variables to free memory
        unset(
            $text
        );

        // Return the count
        return $count;
    }

    /**
     * Estimated reading time in minutes from the same word count as `wordCount`.
     *
     * @param  Entry  $entry  Post entry
     * @return int Minutes, rounded up (zero when there are no words)
     */
    public function readingTimeMinutes(
        Entry $entry
    ): int {
        // Build aggregated plain text for the entry
        $text = $this->totalTextContent($entry);

        // Minutes from true seconds at default WPM (see `WhitespaceDelimitedWordMetrics`)
        $minutes = WhitespaceDelimitedWordMetrics::readingTimeMinutesFromText(
            $text,
            WhitespaceDelimitedWordMetrics::DEFAULT_WORDS_PER_MINUTE
        );

        // Unset local variables to free memory
        unset(
            $text
        );

        // Return the minutes
        return $minutes;
    }

    /**
     * Estimated reading time in seconds, rounded up to whole minutes (as seconds).
     *
     * Uses the same minute bucket as `readingTimeMinutes`, scaled by 60 (e.g.
     * a ~2 s true estimate with a 1-minute bucket returns `60`; ~121 s returns
     * `180` when the bucket is three minutes).
     *
     * @param  Entry  $entry  Post entry
     * @return int Non-negative seconds, multiple of 60 when positive
     */
    public function readingTimeSecondsRoundedUpToWholeMinutes(
        Entry $entry
    ): int {
        // Build aggregated plain text for the entry
        $text = $this->totalTextContent($entry);

        // Scale the minute bucket to seconds for computed `reading_time`
        $seconds = WhitespaceDelimitedWordMetrics::readingTimeSecondsRoundedUpToWholeMinutesFromText(
            $text,
            WhitespaceDelimitedWordMetrics::DEFAULT_WORDS_PER_MINUTE
        );

        // Unset local variables to free memory
        unset(
            $text
        );

        // Return the seconds
        return $seconds;
    }

    /**
     * Concatenate all post text used for metrics (mirrors post Antlers templates).
     *
     * Result is memoized per HTTP request via `Blink` so `word_count` and
     * `reading_time` share one aggregation pass.
     *
     * @param  Entry  $entry  Post entry
     * @return string Single-space-collapsed aggregate text
     */
    public function totalTextContent(
        Entry $entry
    ): string {
        // Build a request-scoped cache key for this entry and site
        /** @var Site $site */
        $site = $entry->site();
        $siteHandle = $site->handle();
        $entryId = $entry->id();
        // Concatenation casts a scalar exactly as `(string)` would
        $blinkKey = 'posts_reading_metrics.total_text_content.'
            .(\is_scalar($siteHandle) ? $siteHandle : '')
            .'.'
            .(\is_scalar($entryId) ? $entryId : '');

        // Compute once and reuse for both metrics on the same request
        $text = Blink::once(
            $blinkKey,
            fn (): string => $this->buildTotalTextContentUncached($entry)
        );

        // Return the memoized aggregate as a string
        return \is_string($text) ? $text : '';
    }

    /**
     * Build aggregated plain text without request-level caching.
     *
     * @param  Entry  $entry  Post entry
     * @return string Single-space-collapsed aggregate text
     */
    private function buildTotalTextContentUncached(Entry $entry): string
    {
        // Initialize fragment list for join
        $parts = [];

        // Add main bard `content` as plain text
        $parts[] = $this->bardPlainText($entry->get('content'));

        // Add series boilerplate from the related series term when the display toggle is on
        if ($this->truthyToggle($entry->get('series_boilerplate_display_query'))) {
            $parts[] = $this->bardPlainText(
                $this->seriesBoilerplateRaw($entry)
            );
        }

        // Add site boilerplate from the multisite global when the behavior toggle is on
        if ($this->truthyToggle($entry->get('boilerplate_query'))) {
            $parts[] = $this->bardPlainText(
                $this->multisiteBoilerplateRaw($entry)
            );
        }

        // Read replicator segments when present
        $segmentsRaw = $entry->get('segments');

        // Skip segment processing when not an array or empty
        if (
            ! \is_array($segmentsRaw)
            || $segmentsRaw === []
        ) {
            return $this->collapseWhitespaceJoined($parts);
        }

        // Replicator data is a zero-indexed list of rows. flattenSegmentRows() walks it with
        // foreach and never reads a key, so the reindexing cannot change the result.
        // @pest-mutate-ignore: UnwrapArrayValues
        /** @var array<int, mixed> $segmentsList */
        $segmentsList = array_values($segmentsRaw);

        // Normalize nested replicator rows to leaf segment sets
        $flatSegments = $this->flattenSegmentRows($segmentsList);

        // Count segments for conditional title and excerpt inclusion
        $segmentCount = \count($flatSegments);

        // Walk each segment and append transcript (and optional title or excerpt)
        foreach ($flatSegments as $segment) {
            // Skip replicator rows when the enabled field is false
            if (($segment['enabled'] ?? true) === false) {
                continue;
            }

            // Resolve primary asset when the segment references `file`
            $segmentAsset = $this->resolveSegmentPrimaryAsset($segment);

            // When more than one segment, add title and excerpt like Antlers
            if ($segmentCount > 1) {
                // Convert the title field value to a string or null
                $segmentTitle = $this->scalarString($segment['title'] ?? null);

                // Convert the title_override field value to a string or null
                $titleOverride = $this->scalarString($segment['title_override'] ?? null);

                // Convert the title field value from the asset to a string or null
                $fileTitle = $segmentAsset instanceof StatamicAsset
                    ? $this->scalarString($segmentAsset->get('title'))
                    : null;

                // Resolve the title like the templates (first non-empty source)
                $chosenTitle = $this->firstFilledString(
                    $segmentTitle,
                    $titleOverride,
                    $fileTitle
                );

                // Append tag-stripped title as plain text for metrics when present
                if ($chosenTitle !== null) {
                    $parts[] = $this->stripTagsPlainForMetrics($chosenTitle);
                }

                // Convert the excerpt field value to a string or null
                $segmentExcerpt = $this->scalarString($segment['excerpt'] ?? null);

                // Convert the excerpt_override field value to a string or null
                $excerptOverride = $this->scalarString($segment['excerpt_override'] ?? null);

                // Convert the excerpt field value from the asset to a string or null
                $fileExcerpt = $segmentAsset instanceof StatamicAsset
                    ? $this->scalarString($segmentAsset->get('excerpt'))
                    : null;

                // Resolve the excerpt like the templates (first non-empty source)
                $chosenExcerpt = $this->firstFilledString(
                    $segmentExcerpt,
                    $excerptOverride,
                    $fileExcerpt
                );

                // Append tag-stripped excerpt as plain text for metrics when present
                if ($chosenExcerpt !== null) {
                    $parts[] = $this->stripTagsPlainForMetrics($chosenExcerpt);
                }
            }

            // Append transcript from asset or inline segment field
            $this->appendSegmentTranscriptToParts(
                $parts,
                $segment,
                $segmentAsset
            );
        }

        // Collapse the parts and return the collapsed text as a string
        return $this->collapseWhitespaceJoined($parts);
    }

    /**
     * Append transcript text for one segment (file transcript vs inline transcript).
     *
     * @param  array<int, string>  $parts  Mutable fragment list
     * @param  array<string, mixed>  $segment  Replicator row
     * @param  StatamicAsset|null  $segmentAsset  Resolved asset when `file` is set
     */
    private function appendSegmentTranscriptToParts(
        array &$parts,
        array $segment,
        ?StatamicAsset $segmentAsset
    ): void {
        // Check whether the segment row has a `file` field value
        if ($this->segmentHasFileField($segment)) {
            // Check if the segment asset is not null
            if ($segmentAsset instanceof StatamicAsset) {
                // Convert the transcript field value from the asset to plain text
                $parts[] = $this->bardPlainText(
                    $segmentAsset->get('transcript')
                );
            }
        } else {
            // Use inline segment transcript when there is no file reference
            $parts[] = $this->bardPlainText(
                $segment['transcript'] ?? null
            );
        }
    }

    /**
     * Whether the segment row defines a non-empty `file` reference.
     *
     * @param  array<string, mixed>  $segment  Replicator row
     */
    private function segmentHasFileField(
        array $segment
    ): bool {
        // Check if the file key exists in the segment
        if (! \array_key_exists('file', $segment)) {
            // Return false to signal that the segment does not have a file field
            return false;
        }

        // Get the file field value from the segment
        $file = $segment['file'];

        // Check if the file is null or an empty string
        if (
            $file === null
            || $file === ''
        ) {
            // Return false to signal that the segment does not have a file field
            return false;
        }

        // Check if the file is an empty array
        // Return true to signal that the segment has a file field
        // Return false to signal that the segment does not have a file field
        return ! \is_array($file) || $file !== [];
    }

    /**
     * Resolve the primary asset for a segment `file` value (e.g. audio container).
     *
     * @param  array<string, mixed>  $segment  Replicator row
     * @return StatamicAsset|null Asset or null when not resolvable
     */
    private function resolveSegmentPrimaryAsset(
        array $segment
    ): ?StatamicAsset {
        // Exit when there is no file reference
        if (! $this->segmentHasFileField($segment)) {
            return null;
        }

        // Get the file field value from the segment
        $file = $segment['file'];

        // Check if the file is an array
        if (\is_array($file)) {
            // Use the first path when the field stores a list
            $file = $file[0] ?? null;
        }

        // Check if the file is a non-empty string
        if (
            ! \is_string($file)
            || $file === ''
        ) {
            // Return null to signal that the segment does not have a file field
            return null;
        }

        // Resolve by full Statamic asset ID when already qualified
        $resolved = str_contains($file, '::')
            ? Asset::find($file)
            : Asset::find('audio::'.$file);

        // Return concrete asset instances only (contract omits `get()` for static analysis)
        return $resolved instanceof StatamicAsset ? $resolved : null;
    }

    /**
     * Flatten nested video or audio segment groups to leaf set rows.
     *
     * @param  array<int, mixed>  $segments  Top-level replicator data
     * @return array<int, array<string, mixed>> Leaf rows with `type` per set
     */
    private function flattenSegmentRows(
        array $segments
    ): array {
        // Initialize output list
        /** @var array<int, array<string, mixed>> $flat */
        $flat = [];

        // Walk each top-level row
        foreach ($segments as $row) {
            // Skip rows when the row is not an array
            if (! \is_array($row)) {
                continue;
            }

            // Get the type field value from the row
            $type = $row['type'] ?? null;

            // ---
            // Unwrap video group sets into child rows
            // ---

            // Check if the type is a new video segment group
            if ($type === 'new_video_segment_group') {
                // Loop through the youtube and vimeo keys in the row
                foreach (['youtube', 'vimeo'] as $setKey) {
                    // Check if the set key is empty or not an array
                    if (empty($row[$setKey])) {
                        // Skip the set key when it is empty or not an array
                        continue;
                    }

                    if (! \is_array($row[$setKey])) {
                        // Skip the set key when it is empty or not an array
                        continue;
                    }

                    // Loop through the children
                    foreach ($row[$setKey] as $child) {
                        // Check if the child is an array
                        if (\is_array($child)) {
                            // Add the child to the flat array
                            /** @var array<string, mixed> $child */
                            $flat[] = $child;
                        }
                    }
                }

                // Skip the rest of the loop
                continue;
            }

            // ---
            // Unwrap audio group sets into child rows
            // ---

            // Check if the type is a new audio segment group
            if ($type === 'new_audio_segment_group') {
                // Read the local_file children, the group's only set key
                $localFiles = $row['local_file'] ?? null;

                // Check if the children are a list (an empty one adds nothing)
                if (\is_array($localFiles)) {
                    // Loop through the children
                    foreach ($localFiles as $child) {
                        // Check if the child is an array
                        if (\is_array($child)) {
                            // Add the child to the flat array
                            /** @var array<string, mixed> $child */
                            $flat[] = $child;
                        }
                    }
                }

                // Skip the rest of the loop
                continue;
            }

            // Keep already-flat rows as-is
            /** @var array<string, mixed> $row */
            $flat[] = $row;
        }

        // Return the flat array
        return $flat;
    }

    /**
     * Raw `boilerplate` value from `uams_multisite_settings` for the entry site.
     *
     * @param  Entry  $entry  Post entry
     * @return mixed Field raw value or null
     */
    private function multisiteBoilerplateRaw(
        Entry $entry
    ): mixed {
        // Resolve the effective boilerplate term (per-post selection, else the
        // site default) and read its Bard content localized to the entry site.
        return $this->localizedTermFieldValue(
            $this->multisiteBoilerplateTermId($entry),
            $entry,
            'boilerplate'
        );
    }

    /**
     * Resolve the effective general-boilerplate term id for a post: the per-post
     * `boilerplate` selection when set, otherwise the site default boilerplate
     * selected on the `uams_multisite_settings` global for the entry site.
     *
     * @param  Entry  $entry  Post entry
     * @return string|null Canonical term id, or null when neither resolves
     */
    private function multisiteBoilerplateTermId(
        Entry $entry
    ): ?string {
        // A per-post selection on the entry overrides the site default — but only
        // when it still resolves. A dangling slug (the picked term was deleted)
        // augments to null in the template, whose ternary then renders the site
        // default; mirror that here so metrics never undercount vs. the page.
        $selected = $this->termIdFromField($entry, 'boilerplate');

        if ($selected !== null && Term::find($selected) !== null) {
            return $selected;
        }

        // Fall back to the site default boilerplate term from the multisite global.
        $global = GlobalSet::findByHandle('uams_multisite_settings');

        // Return null when the global is missing
        if ($global === null) {
            return null;
        }

        // Load variables for the entry's site (globals inherit from the origin)
        /** @var Site $site */
        $site = $entry->site();
        /** @var Variables $variables */
        $variables = $global->in($site->handle());

        // Build a canonical id from the stored slug for the boilerplates taxonomy
        return $this->termId($variables->get('boilerplate'), 'boilerplates');
    }

    /**
     * Raw `boilerplate` value from the entry's related series taxonomy term.
     *
     * Mirrors the post Antlers templates, which source the series boilerplate
     * from `series:boilerplate` (the selected `*_series` term) — not the
     * `uams_multisite_settings` global. Returns null when no series is selected
     * or the resolved term has no boilerplate.
     *
     * @param  Entry  $entry  Post entry
     * @return mixed Raw Bard field value or null
     */
    private function seriesBoilerplateRaw(
        Entry $entry
    ): mixed {
        // Read the selected series term's boilerplate, localized to the entry site.
        return $this->localizedTermFieldValue(
            $this->termIdFromField($entry, 'series'),
            $entry,
            'boilerplate'
        );
    }

    /**
     * Load a taxonomy term by id, localize it to the entry site, and return one of
     * its field values — the shared resolution behind the series and multisite
     * boilerplates.
     *
     * @param  string|null  $termId  Canonical term id, or null
     * @param  Entry  $entry  Post entry (for site localization)
     * @param  string  $field  Term field handle to read
     * @return mixed Raw field value, or null when the term or localization is absent
     */
    private function localizedTermFieldValue(
        ?string $termId,
        Entry $entry,
        string $field
    ): mixed {
        // Nothing selected — no value to read
        if ($termId === null) {
            return null;
        }

        // Load the term from the taxonomy repository
        $term = Term::find($termId);

        // Localize the term to the entry site for per-site content
        /** @var Site $site */
        $site = $entry->site();

        // Reuse an already-localized term, else localize the base term to the site
        if ($term instanceof LocalizedTerm) {
            $localized = $term;
        } elseif ($term instanceof TaxonomyTerm) {
            $localized = $term->in($site->handle());
        } else {
            return null;
        }

        // Return null when the term has no localization in the entry site
        if (! $localized instanceof LocalizedTerm) {
            return null;
        }

        // Return the raw field value for plain-text extraction
        return $localized->value($field);
    }

    /**
     * Resolve the selected taxonomy term id from a single-taxonomy `terms` field
     * on the entry (e.g. `series`, `boilerplate`).
     *
     * A `max_items: 1` terms field stores a bare slug string, so the taxonomy
     * namespace is read from the entry blueprint to build a canonical
     * `taxonomy::slug` id.
     *
     * @param  Entry  $entry  Post entry
     * @param  string  $fieldHandle  The terms field handle to resolve
     * @return string|null Canonical term id, or null when nothing is selected
     */
    private function termIdFromField(
        Entry $entry,
        string $fieldHandle
    ): ?string {
        // Resolve the field's taxonomy namespace from the entry blueprint
        $taxonomy = $this->fieldTaxonomyHandle($entry, $fieldHandle);

        // Without a taxonomy handle a bare slug cannot be namespaced
        if ($taxonomy === null) {
            return null;
        }

        // Build a canonical id from the stored value and the taxonomy handle
        return $this->termId($entry->get($fieldHandle), $taxonomy);
    }

    /**
     * Build a canonical `taxonomy::slug` term id from a raw single-term field value.
     *
     * @param  mixed  $raw  Stored value (bare slug, `taxonomy::slug`, or single-element list)
     * @param  string  $taxonomy  Taxonomy handle used to namespace a bare slug
     * @return string|null Canonical term id, or null when empty
     */
    private function termId(
        mixed $raw,
        string $taxonomy
    ): ?string {
        // Unwrap a single-element list when stored as an array
        if (\is_array($raw)) {
            $raw = $raw[0] ?? null;
        }

        // Treat null and empty string as no selection
        if (
            ! \is_string($raw)
            || $raw === ''
        ) {
            return null;
        }

        // Keep values that already include the taxonomy namespace
        if (str_contains($raw, '::')) {
            return $raw;
        }

        // Namespace the bare slug with the taxonomy handle
        return sprintf('%s::%s', $taxonomy, $raw);
    }

    /**
     * Read a terms field's first configured taxonomy handle from the entry blueprint.
     *
     * @param  Entry  $entry  Post entry
     * @param  string  $fieldHandle  The terms field handle
     * @return string|null First configured taxonomy handle, or null when absent
     */
    private function fieldTaxonomyHandle(
        Entry $entry,
        string $fieldHandle
    ): ?string {
        // Resolve the entry blueprint (fluent getter is loosely typed)
        $blueprint = $entry->blueprint();

        // Return null when no concrete blueprint resolves
        if (! $blueprint instanceof Blueprint) {
            return null;
        }

        // Resolve the field definition from the blueprint
        $field = $blueprint->field($fieldHandle);

        // Return null when the blueprint has no such field
        if (! $field instanceof Field) {
            return null;
        }

        // Read the configured taxonomies for the field
        $taxonomies = $field->config()['taxonomies'] ?? null;

        // Accept a list of taxonomy handles and take the first
        if (\is_array($taxonomies)) {
            $first = $taxonomies[0] ?? null;

            return \is_string($first) ? $first : null;
        }

        // Accept a single taxonomy handle string
        return \is_string($taxonomies) ? $taxonomies : null;
    }

    /**
     * Bard (or string) to plain text using Statamic `bard_text`.
     *
     * @param  mixed  $raw  Stored field value
     */
    private function bardPlainText(
        mixed $raw
    ): string {
        // Run bard_text modifier
        /** @var Modify<array-key, mixed> $modify */
        $modify = Statamic::modify($raw);
        $text = $modify->modify(
            'bard_text',
            []
        );

        // Return the text as a string. bard_text returns a string or throws, so the
        // fallback is never reached.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        return \is_string($text) ? $text : '';
    }

    /**
     * Strip HTML tags and collapse whitespace for segment title or excerpt metrics.
     *
     * @return string Plain text without `sanitize` (avoids `&#039;`-style entities
     *                that inflate `word_count`)
     */
    private function stripTagsPlainForMetrics(
        string $value
    ): string {
        // Remove HTML-like markup from the value
        $stripped = strip_tags($value);

        // Trim and collapse runs of whitespace to a single space
        return $this->collapseWhitespace($stripped);
    }

    /**
     * Join non-empty fragments and collapse whitespace to a single space.
     *
     * @param  array<int, string>  $parts  Text fragments
     */
    private function collapseWhitespaceJoined(
        array $parts
    ): string {
        // Filter out empty strings. An empty part joins as extra spaces, which the trim and
        // collapse below remove, so the filter cannot change the result.
        // @pest-mutate-ignore: UnwrapArrayFilter, EmptyStringToNotEmpty
        $nonEmpty = array_filter(
            $parts,
            static fn (string $p): bool => $p !== ''
        );

        // Join on space
        $joined = implode(' ', $nonEmpty);

        // Trim and collapse runs of whitespace
        return $this->collapseWhitespace($joined);
    }

    /**
     * Trim and collapse runs of whitespace to a single space, counting a byte that
     * is not valid UTF-8 as whitespace.
     *
     * The `/u` collapse returns null on text that is not valid UTF-8, which once
     * emptied the whole value (UAMS-Web/uams-statamic-archive#2984). Replacing each invalid byte with a space first
     * keeps the valid words and adds none.
     */
    private function collapseWhitespace(
        string $value
    ): string {
        // Check whether the value holds a byte that is not valid UTF-8
        if (! mb_check_encoding($value, 'UTF-8')) {
            // Replace each invalid byte with a space and keep every valid run. The `valid`
            // group is absent from a match it took no part in. The pattern has no `u` flag,
            // so it cannot fail on the bytes and the cast only narrows the type.
            // @pest-mutate-ignore: RemoveStringCast
            $value = (string) preg_replace_callback(
                self::VALID_UTF8_RUN_OR_BYTE,
                static fn (array $match): string => $match['valid'] ?? ' ',
                $value
            );
        }

        // Trim, then collapse runs of whitespace. The value is valid UTF-8 here, so the
        // pattern cannot fail and the fallback is never reached.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        return preg_replace('/\s+/u', ' ', trim($value)) ?? '';
    }

    /**
     * Interpret Statamic true/false and similar stored values as boolean.
     *
     * @param  mixed  $value  Raw toggle value
     */
    private function truthyToggle(
        mixed $value
    ): bool {
        // Parse string booleans when needed
        if (\is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        // Return the value as a boolean
        return (bool) $value;
    }

    /**
     * First non-empty of the given optional strings, or null when all are absent.
     *
     * Mirrors the Antlers `??` coalescence used by the post segment templates
     * (`title_override ?? file:title`): an empty string is treated as absent —
     * so a filled later source wins over a stored-but-empty earlier one — while
     * any other string (including whitespace, matching Antlers) is kept.
     *
     * @param  string|null  ...$candidates  Title or excerpt sources in template order
     */
    private function firstFilledString(
        ?string ...$candidates
    ): ?string {
        // Return the first value that exists and is not the empty string
        foreach ($candidates as $candidate) {
            if (
                $candidate !== null
                && $candidate !== ''
            ) {
                return $candidate;
            }
        }

        // Return null when every source is null or empty
        return null;
    }

    /**
     * Coerce a field value to string or null for segment title or excerpt checks.
     *
     * @param  mixed  $value  Raw value (may be Statamic Value)
     */
    private function scalarString(
        mixed $value
    ): ?string {
        // Return null when the value is null. A null also reaches the final return null, so
        // this only shortens the path.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($value === null) {
            return null;
        }

        // Unwrap Statamic Value when the value is an instance of Value
        if ($value instanceof Value) {
            $value = $value->value();
        }

        // Accept scalar strings and numbers
        if (
            \is_string($value)
            || \is_int($value)
            || \is_float($value)
        ) {
            return (string) $value;
        }

        // Return null when the value is not a scalar string or number
        return null;
    }
}
