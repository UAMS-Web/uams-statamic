<?php

declare(strict_types=1);

namespace App\Tags;

use Statamic\Facades\Antlers;
use Statamic\Tags\Tags;

/**
 * The "On this page" jump-links nav on the Find-a-Doc profile pages (UAMS-Web/uams-statamic#1575).
 *
 * A pair tag around a page's sections. It renders them, collects each `<h2>` that carries a
 * `data-jump-link` attribute, in page order, and shows a nav linking to them when there are
 * at least two, as Find-a-Doc's `$jump_link_count_min` does. The list is therefore built from
 * the sections the page actually rendered, and no view restates a section's own condition.
 *
 * The attribute's value is the link text, falling back to the heading's text when empty. A
 * heading with no `id` gets one derived from that label, made unique within the content, so
 * every link reaches its heading. The nav markup is the shared `partials/jump_links` partial,
 * placed where the content holds the `<!--jump-links-->` marker, or before the content when it
 * holds none.
 *
 * Usage: `{{ jump_links }} ... <!--jump-links--> ... {{ /jump_links }}`, with an optional
 * `min` parameter for the threshold (default 2).
 */
class JumpLinks extends Tags
{
    /**
     * The marker the nav replaces.
     */
    public const string MARKER = '<!--jump-links-->';

    /**
     * The fewest links that show the nav, as on Find-a-Doc.
     */
    private const int DEFAULT_MINIMUM = 2;

    /**
     * Render the wrapped sections with the jump-links nav when enough of them rendered.
     *
     * @return string The rendered content, with ids added and the nav placed.
     */
    public function index(): string
    {
        // Render the sections the tag wraps
        $content = (string) $this->parse();

        // Collect the marked headings, giving each a unique id
        [$linked, $links] = $this->collect($content);

        // Below the threshold, drop the marker and return the sections as they rendered
        $minimum = $this->params->int('min', self::DEFAULT_MINIMUM);
        if (\count($links) < $minimum) {
            return str_replace(self::MARKER, '', $content);
        }

        $content = $linked;

        // Render the nav through the shared partial
        $nav = (string) Antlers::parse('{{ partial:jump_links :links="links" }}', ['links' => $links]);

        // Put the nav at the first marker, or before the content when there is none
        $at = strpos($content, self::MARKER);
        if ($at === false) {
            return $nav.$content;
        }

        // Drop any further markers after placing the nav at the first
        $before = substr($content, 0, $at);
        $after = str_replace(self::MARKER, '', substr($content, $at + \strlen(self::MARKER)));

        return $before.$nav.$after;
    }

    /**
     * Find the marked headings, add any missing ids, and list the links.
     *
     * @param  string  $content  The rendered sections.
     * @return array{0: string, 1: list<array{id: string, label: string}>}
     */
    private function collect(string $content): array
    {
        // The ids already in the content, so a generated one never collides
        preg_match_all('/\sid="([^"]*)"/i', $content, $existing);
        $taken = array_fill_keys($existing[1], true);

        // Walk each marked heading in page order
        $links = [];
        $content = (string) preg_replace_callback(
            '/<h2\b([^>]*\sdata-jump-link="([^"]*)"[^>]*)>(.*?)<\/h2>/is',
            function (array $match) use (&$links, &$taken): string {
                // Take the label from the attribute, else from the heading text
                $label = trim(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5));
                if ($label === '') {
                    $label = trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($match[3]), ENT_QUOTES | ENT_HTML5)));
                }

                // A heading with nothing to call it is left out
                if ($label === '') {
                    return $match[0];
                }

                // Use the heading's own id, or add a unique one
                $attributes = $match[1];
                if (preg_match('/\sid="([^"]+)"/i', $attributes, $id) === 1) {
                    $headingId = $id[1];
                } else {
                    $headingId = $this->uniqueId($label, $taken);
                    $attributes .= ' id="'.$headingId.'"';
                }

                $links[] = ['id' => $headingId, 'label' => $label];

                return '<h2'.$attributes.'>'.$match[3].'</h2>';
            },
            $content
        );

        return [$content, $links];
    }

    /**
     * An id derived from a label, not yet used in the content.
     *
     * @param  string  $label  The link text.
     * @param  array<string, true>  $taken  The ids in use, updated with the new one.
     */
    private function uniqueId(string $label, array &$taken): string
    {
        // Slug the label under a prefix, so it cannot start with a digit
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($label)), '-');
        $base = 'jump-'.($slug === '' ? 'section' : $slug);

        // Number it until it is free
        $candidate = $base;
        for ($n = 2; isset($taken[$candidate]); $n++) {
            $candidate = $base.'-'.$n;
        }

        $taken[$candidate] = true;

        return $candidate;
    }
}
