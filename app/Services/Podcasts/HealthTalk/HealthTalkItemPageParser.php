<?php

declare(strict_types=1);

namespace App\Services\Podcasts\HealthTalk;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Reads a UAMS Health Talk item page into its speakers and transcript (UAMS-Web/uams-statamic-archive#1803).
 *
 * The page's speaker block is a `featured_speaker` label followed by an `<h6>`
 * of names -- `|`-separated on a panel -- then biographies, some carrying a
 * `uamshealth.com/provider/<slug>/` link. On a panel the link names its
 * speaker (`Learn more about Lexie Vaughn, MD`), so a slug is credited only to
 * the speaker its link text names, never to the panel as a whole.
 *
 * The transcript is the `transcriptbox` element, less its own
 * `Transcription:` heading and the repeated episode title.
 */
final readonly class HealthTalkItemPageParser
{
    /**
     * Parse one item page.
     *
     * @param  string  $html  Raw page body
     */
    public function parse(
        string $html
    ): HealthTalkItemPage {
        $xpath = $this->xpath($html);

        return new HealthTalkItemPage(
            speakers: $this->speakers($xpath),
            transcriptHtml: $this->transcript($xpath)
        );
    }

    /**
     * An XPath over the page, parsed as UTF-8 with parser noise suppressed.
     */
    private function xpath(
        string $html
    ): DOMXPath {
        $document = new DOMDocument;
        // Collecting libxml's errors keeps real-world markup from raising PHP warnings; a test
        // without Laravel's error handler cannot observe the difference.
        // @pest-mutate-ignore: TrueToFalse
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new DOMXPath($document);
    }

    /**
     * The featured speakers, each with the provider slug its link names.
     *
     * @return list<HealthTalkSpeaker>
     */
    private function speakers(
        DOMXPath $xpath
    ): array {
        $label = $this->nodes($xpath, '//span[contains(concat(" ", normalize-space(@class), " "), " featured_speaker ")]')[0] ?? null;

        // Without a label the heading query below runs from the document root, where `following::`
        // selects nothing, so skipping this return reaches the same empty result.
        // @pest-mutate-ignore: InstanceOfToTrue, RemoveEarlyReturn
        if (! $label instanceof DOMNode) {
            return [];
        }

        $heading = $this->nodes($xpath, 'following::h6[1]', $label)[0] ?? null;

        if (! $heading instanceof DOMNode) {
            return [];
        }

        $names = \array_values(\array_filter(
            \array_map(self::clean(...), \explode('|', $heading->textContent)),
            static fn (string $name): bool => $name !== ''
        ));

        $links = $this->providerLinks($xpath, $heading);

        return \array_map(
            fn (string $name): HealthTalkSpeaker => new HealthTalkSpeaker(
                $name,
                $this->slugFor($name, $links, \count($names))
            ),
            $names
        );
    }

    /**
     * Provider links in the speaker block, as link text to slug.
     *
     * @return list<array{text: string, slug: string}>
     */
    private function providerLinks(
        DOMXPath $xpath,
        DOMNode $heading
    ): array {
        $links = [];

        // Anchors after the names and before the transcript belong to the speakers. A link
        // inside the transcript has the box as an ancestor rather than a preceding node, so
        // both axes are excluded.
        $box = 'div[contains(concat(" ", normalize-space(@class), " "), " transcriptbox ")]';
        $anchors = $this->nodes(
            $xpath,
            'following::a[contains(@href, "uamshealth.com/provider/")][not(ancestor::'.$box.')][not(preceding::'.$box.')]',
            $heading
        );

        foreach ($anchors as $anchor) {
            // The expression selects only `a` elements, so this guard only narrows the type.
            // @pest-mutate-ignore: InstanceOfToTrue
            if (! $anchor instanceof DOMElement) {
                continue;
            }

            if (\preg_match('~uamshealth\.com/provider/([a-z0-9-]+)/?~i', $anchor->getAttribute('href'), $matches) !== 1) {
                continue;
            }

            $links[] = ['text' => self::clean($anchor->textContent), 'slug' => \strtolower($matches[1])];
        }

        return $links;
    }

    /**
     * The slug a speaker's link names: the only link for a lone speaker, or the
     * link whose text names this speaker on a panel.
     *
     * @param  list<array{text: string, slug: string}>  $links
     */
    private function slugFor(
        string $name,
        array $links,
        int $speakerCount
    ): ?string {
        // array_unique keeps the first key, 0, so the list is already indexed from zero here.
        // @pest-mutate-ignore: UnwrapArrayValues
        $slugs = \array_values(\array_unique(\array_column($links, 'slug')));

        if ($speakerCount === 1) {
            return \count($slugs) === 1 ? $slugs[0] : null;
        }

        foreach ($links as $link) {
            if (\mb_stripos($link['text'], $name) !== false) {
                return $link['slug'];
            }
        }

        return null;
    }

    /**
     * The transcript's HTML, or null when the page has none.
     */
    private function transcript(
        DOMXPath $xpath
    ): ?string {
        $box = $this->nodes($xpath, '//div[contains(concat(" ", normalize-space(@class), " "), " transcriptbox ")]')[0] ?? null;

        // Past this guard a missing box reads as an empty transcript, which is null too; only
        // Laravel's error handler, absent from the parser tests, would turn it into an exception.
        // @pest-mutate-ignore: InstanceOfToTrue, RemoveEarlyReturn
        if (! $box instanceof DOMElement) {
            return null;
        }

        $html = '';

        foreach ($box->childNodes as $child) {
            // A node read from the page always has its document, and saving it fails only on an
            // internal error, so neither fallback can be reached from a test.
            // @pest-mutate-ignore: RemoveNullSafeOperator, EmptyStringToNotEmpty
            $html .= $box->ownerDocument?->saveHTML($child) ?? '';
        }

        // Drop the box's own heading and the episode title the page repeats above the text
        $html = \preg_replace('~^\s*<strong>\s*Transcription:\s*</strong>\s*(<br\s*/?>)?~i', '', $html) ?? $html;
        $html = \preg_replace('~^[^<]*(<br\s*/?>\s*)+~', '', $html) ?? $html;

        // libxml drops whitespace-only text between elements, so text that is blank once tags are
        // stripped is already empty; the trim is a guard the parser tests cannot tell apart.
        // @pest-mutate-ignore: UnwrapTrim
        $text = \trim(\strip_tags($html));

        return $text === '' ? null : \trim($html);
    }

    /**
     * The nodes an expression selects, or none when it is malformed.
     *
     * @return list<DOMNode>
     */
    private function nodes(
        DOMXPath $xpath,
        string $expression,
        ?DOMNode $context = null
    ): array {
        $result = $xpath->query($expression, $context);

        // Every expression is a constant this class wrote, so `false` cannot be reached from a test.
        // @pest-mutate-ignore: FalseToTrue
        if ($result === false) {
            return [];
        }

        $nodes = [];

        foreach ($result as $node) {
            // An XPath result holds only nodes, so this guard only narrows the type.
            // @pest-mutate-ignore: InstanceOfToTrue
            if ($node instanceof DOMNode) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * Whitespace-collapsed text with non-breaking spaces normalized.
     */
    private static function clean(
        string $text
    ): string {
        return \trim(\preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? $text);
    }
}
