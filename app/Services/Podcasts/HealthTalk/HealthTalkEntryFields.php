<?php

declare(strict_types=1);

namespace App\Services\Podcasts\HealthTalk;

use Illuminate\Support\Str;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fieldtypes\Bard\Augmentor;

/**
 * Builds the feed-owned field values of a UAMS Health Talk `audio` entry (UAMS-Web/uams-statamic-archive#1803).
 *
 * These are the only fields a sync writes: title, show notes, duration,
 * episode number, transcript, the speaker provenance and the one `remote_audio`
 * segment row. Every other field, including the other segment rows, belongs to
 * editors. Vendor HTML is converted through the destination blueprint's own
 * Bard fields, so the stored value is what the control panel would store.
 */
final readonly class HealthTalkEntryFields
{
    /**
     * The segment set the feed owns.
     */
    public const string AUDIO_SET = 'remote_audio';

    /**
     * Link schemes that survive the show-notes conversion.
     */
    private const array SAFE_LINK_SCHEMES = ['http', 'https', 'mailto'];

    /**
     * The node and mark types each converted field keeps, matching its editor's buttons.
     *
     * The conversion itself is not scoped to a field: Statamic's Augmentor
     * registers every Bard extension whatever the field's buttons, so a table,
     * an `h1`, a code block or a `set` in vendor HTML would otherwise survive
     * into a field whose editor cannot show it -- and a `set` with no attributes
     * throws when it is augmented. Neither list names `image` or `set`, and
     * neither carries content, so both are dropped whole.
     *
     * @var array<string, array{nodes: list<string>, marks: list<string>}>
     */
    private const array ALLOWED = [
        'show_notes' => [
            'nodes' => ['paragraph', 'text', 'hardBreak', 'heading', 'bulletList', 'orderedList', 'listItem', 'horizontalRule'],
            'marks' => ['bold', 'italic', 'link', 'strike', 'superscript', 'subscript', 'code'],
        ],
        'episode_transcript' => [
            'nodes' => ['paragraph', 'text', 'hardBreak'],
            'marks' => ['bold', 'italic', 'superscript', 'subscript'],
        ],
    ];

    /**
     * Node types that hold inline content.
     */
    private const array INLINE_NODES = ['text', 'hardBreak'];

    /**
     * Node types whose children must be blocks.
     */
    private const array BLOCK_CONTAINERS = ['listItem'];

    /**
     * @param  Blueprint  $blueprint  The destination blueprint, whose Bard fields convert vendor HTML
     */
    public function __construct(
        private Blueprint $blueprint
    ) {}

    /**
     * The fields for a destination collection blueprint.
     *
     * @throws HealthTalkSourceException When it does not exist
     */
    public static function for(
        string $collection,
        string $blueprint
    ): self {
        $resolved = Collection::findByHandle($collection)?->entryBlueprint($blueprint);

        if (! $resolved instanceof Blueprint) {
            throw new HealthTalkSourceException(\sprintf('The %s/%s blueprint does not exist.', $collection, $blueprint));
        }

        return new self($resolved);
    }

    /**
     * The fields a sync writes on every create or change, and never any other.
     *
     * @return array<string, mixed>
     */
    public function feedOwned(
        HealthTalkFeedItem $item,
        HealthTalkItemPage $page
    ): array {
        return [
            'title' => $item->title,
            'show_notes' => $this->prosemirror('show_notes', $item->showNotesHtml),
            'episode_duration' => $item->duration,
            'episode_number' => $item->episodeNumber,
            'episode_transcript' => $page->transcriptHtml === null ? null : $this->prosemirror('episode_transcript', $page->transcriptHtml),
            'source_speaker_names' => \array_map(static fn (HealthTalkSpeaker $speaker): string => $speaker->name, $page->speakers),
            'source_speaker_slugs' => \array_map(static fn (HealthTalkSpeaker $speaker): string => $speaker->providerSlug ?? '', $page->speakers),
        ];
    }

    /**
     * The speakers an entry recorded when its item page was last read.
     *
     * Names and slugs are stored as parallel lists, so credits can be resolved
     * again on every run without refetching the page.
     *
     * @return list<HealthTalkSpeaker>
     */
    public function storedSpeakers(
        Entry $entry
    ): array {
        $names = $entry->get('source_speaker_names');
        $slugs = $entry->get('source_speaker_slugs');
        $slugs = \is_array($slugs) ? \array_values($slugs) : [];

        $speakers = [];

        foreach (\is_array($names) ? \array_values($names) : [] as $index => $name) {
            if (! \is_string($name)) {
                continue;
            }

            if ($name === '') {
                continue;
            }

            $slug = $slugs[$index] ?? null;
            $speakers[] = new HealthTalkSpeaker($name, \is_string($slug) && $slug !== '' ? $slug : null);
        }

        return $speakers;
    }

    /**
     * The segments with the feed's audio row set, leaving every other row as it was.
     *
     * @param  array<mixed>  $segments  The entry's current rows
     * @return list<array<mixed>>
     */
    public function segments(
        array $segments,
        HealthTalkFeedItem $item
    ): array {
        $rows = [];
        $found = false;

        foreach ($segments as $row) {
            if (! \is_array($row)) {
                continue;
            }

            if (! $found && ($row['type'] ?? null) === self::AUDIO_SET) {
                $found = true;

                if ($item->audioUrl === null) {
                    continue;
                }

                $row['url'] = $item->audioUrl;
            }

            $rows[] = $row;
        }

        if (! $found && $item->audioUrl !== null) {
            \array_unshift($rows, [
                'id' => Str::random(8),
                'type' => self::AUDIO_SET,
                'enabled' => true,
                'url' => $item->audioUrl,
            ]);
        }

        return $rows;
    }

    /**
     * The URL in an entry's feed-owned audio row, or null.
     */
    public function storedAudioUrl(
        Entry $entry
    ): ?string {
        $segments = $entry->get('segments');

        foreach (\is_array($segments) ? $segments : [] as $row) {
            if (\is_array($row) && ($row['type'] ?? null) === self::AUDIO_SET) {
                return \is_string($row['url'] ?? null) ? $row['url'] : null;
            }
        }

        return null;
    }

    /**
     * Vendor HTML as the named Bard field's stored value.
     *
     * The conversion drops script, event attributes and `javascript:` links.
     * The result is then held to the field's own node and mark types: images
     * and sets are removed, any other node the field's editor lacks is unwrapped
     * into its text, and a link outside http, https and mailto loses its mark.
     *
     * @return list<array<mixed>>|null
     */
    public function prosemirror(
        string $handle,
        string $html
    ): ?array {
        if (\trim(\strip_tags($html)) === '') {
            return null;
        }

        $field = $this->blueprint->field($handle);

        $allowed = self::ALLOWED[$handle] ?? null;

        // Every handle in ALLOWED is a field of the audio blueprint, so a missing field is
        // unreachable there and InstanceOfToTrue cannot be killed.
        // @pest-mutate-ignore: InstanceOfToTrue
        if (! $field instanceof Field || $allowed === null) {
            return null;
        }

        $document = new Augmentor($field->fieldtype())->renderHtmlToProsemirror($html);
        // The converter always returns a document array, so only the second test can decide this.
        // @pest-mutate-ignore: BooleanAndToBooleanOr
        $content = \is_array($document) && \is_array($document['content'] ?? null) ? $document['content'] : [];
        $nodes = $this->asBlocks($this->allowedNodes($content, $allowed));

        return $nodes === [] ? null : $nodes;
    }

    /**
     * Nodes held to a field's allowed types: dropped nodes go, unknown ones are
     * replaced by their children, and marks are filtered.
     *
     * @param  array<mixed>  $nodes
     * @param  array{nodes: list<string>, marks: list<string>}  $allowed
     * @return list<array<mixed>>
     */
    private function allowedNodes(
        array $nodes,
        array $allowed
    ): array {
        $kept = [];

        foreach ($nodes as $node) {
            if (! \is_array($node)) {
                continue;
            }

            $type = $node['type'] ?? null;

            if (! \is_string($type)) {
                continue;
            }

            $children = \is_array($node['content'] ?? null) ? $this->allowedNodes($node['content'], $allowed) : [];

            if (! self::isIn($type, $allowed['nodes'])) {
                \array_push($kept, ...$children);

                continue;
            }

            if ($type === 'text') {
                // The converter never emits a text node without a string, or with an empty one, so
                // neither guard's value can be told apart by a test.
                // @pest-mutate-ignore: EmptyStringToNotEmpty
                $text = \is_string($node['text'] ?? null) ? $node['text'] : '';

                // @pest-mutate-ignore: EmptyStringToNotEmpty
                if ($text === '') {
                    continue;
                }

                $marks = $this->allowedMarks(\is_array($node['marks'] ?? null) ? $node['marks'] : [], $allowed['marks']);
                $kept[] = $marks === [] ? ['type' => 'text', 'text' => $text] : ['type' => 'text', 'text' => $text, 'marks' => $marks];

                continue;
            }

            if ($type === 'heading') {
                // The converter always sets an integer level, and HTML has no level above 6, so the
                // default and the upper clamp are guards no input can reach.
                // @pest-mutate-ignore: BooleanAndToBooleanOr, DecrementInteger, IncrementInteger
                $level = \is_array($node['attrs'] ?? null) && \is_int($node['attrs']['level'] ?? null) ? $node['attrs']['level'] : 2;
                // @pest-mutate-ignore: IncrementInteger
                $level = \min(6, $level);
                $node['attrs'] = [...(\is_array($node['attrs'] ?? null) ? $node['attrs'] : []), 'level' => \max(2, $level)];
            }

            if (\array_key_exists('content', $node)) {
                $node['content'] = self::isIn($type, self::BLOCK_CONTAINERS) ? $this->asBlocks($children) : $children;
            }

            $kept[] = $node;
        }

        return $kept;
    }

    /**
     * Marks of the allowed types, with links outside the safe schemes removed.
     *
     * @param  array<mixed>  $marks
     * @param  list<string>  $allowed
     * @return list<array<mixed>>
     */
    private function allowedMarks(
        array $marks,
        array $allowed
    ): array {
        $kept = [];

        foreach ($marks as $mark) {
            if (! \is_array($mark)) {
                continue;
            }

            if (! self::isIn($mark['type'] ?? null, $allowed)) {
                continue;
            }

            if ($mark['type'] === 'link' && ! $this->isSafeHref(\is_array($mark['attrs'] ?? null) ? ($mark['attrs']['href'] ?? null) : null)) {
                continue;
            }

            $kept[] = $mark;
        }

        return $kept;
    }

    /**
     * A block-level list: each run of inline nodes left behind by unwrapping becomes a paragraph.
     *
     * @param  list<array<mixed>>  $nodes
     * @return list<array<mixed>>
     */
    private function asBlocks(
        array $nodes
    ): array {
        $blocks = [];
        $inline = [];

        foreach ($nodes as $node) {
            if (self::isIn($node['type'] ?? null, self::INLINE_NODES)) {
                $inline[] = $node;

                continue;
            }

            if ($inline !== []) {
                $blocks[] = ['type' => 'paragraph', 'content' => $inline];
                $inline = [];
            }

            $blocks[] = $node;
        }

        if ($inline !== []) {
            $blocks[] = ['type' => 'paragraph', 'content' => $inline];
        }

        return $blocks;
    }

    /**
     * Whether a link target uses an allowed scheme.
     */
    private function isSafeHref(
        mixed $href
    ): bool {
        if (! \is_string($href)) {
            return false;
        }

        $scheme = \parse_url(\trim($href), PHP_URL_SCHEME);

        return \is_string($scheme) && self::isIn(\strtolower($scheme), self::SAFE_LINK_SCHEMES);
    }

    /**
     * Whether a value is one of a list of type or scheme names, compared strictly.
     *
     * @param  list<string>  $list
     */
    private static function isIn(
        mixed $value,
        array $list
    ): bool {
        // Every list here holds non-numeric names, where loose and strict comparison agree, so
        // TrueToFalse on the strict flag cannot be killed.
        // @pest-mutate-ignore: TrueToFalse
        return \in_array($value, $list, true);
    }
}
