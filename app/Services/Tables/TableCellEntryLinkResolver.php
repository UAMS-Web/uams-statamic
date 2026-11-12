<?php

declare(strict_types=1);

namespace App\Services\Tables;

use App\Modifiers\SanitizeTableCell;
use Statamic\Entries\Entry as EntryItem;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;

/**
 * Resolves `statamic://entry::<id>` links in table-cell HTML to the entry's URL.
 *
 * The importer writes a cell's internal link as an entry reference, and the site
 * treats that reference as the durable form of a link: the reference repointer
 * rewrites it when an entry's id changes. {@see TableCellSanitizer} allows only
 * `http`, `https`, `mailto` and `tel`, so an unresolved reference loses its `href`
 * there. This runs first, on the rendered value only, so the stored cell keeps the
 * reference.
 *
 * A reference to an entry that is missing, absent from the current site, not
 * published, or without a URL loses its `href`, so the cell renders the link text
 * alone and no `statamic://` value reaches the page.
 *
 * @see SanitizeTableCell The render path that calls this before sanitizing.
 */
class TableCellEntryLinkResolver
{
    /**
     * A quoted `href` whose value is an entry reference.
     *
     * Groups: 1 the quote, 2 the entry id, 3 the query and fragment as written,
     * so an `&amp;`-encoded query is carried over untouched.
     */
    private const string ENTRY_HREF = '/\bhref\s*=\s*(["\'])statamic:\/\/entry::([^"\'?#]+)([?#][^"\']*)?\1/i';

    /**
     * Replace every entry-reference `href` in a cell with the entry's URL.
     *
     * @param  mixed  $value  The raw cell value (string, scalar, or null)
     * @return mixed The value with references resolved, or the value unchanged when it is not a string
     */
    public function resolve(
        mixed $value
    ): mixed {
        // Only string cells can carry markup; the sanitizer handles the rest
        if (! \is_string($value) || ! str_contains($value, 'statamic://')) {
            return $value;
        }

        return preg_replace_callback(
            self::ENTRY_HREF,
            $this->replacement(...),
            $value
        ) ?? $value;
    }

    /**
     * The attribute that replaces one matched entry-reference `href`.
     *
     * @param  array<int|string, string>  $match  The regex match: quote, id, and optional query and fragment
     * @return string The `href` pointing at the entry, or an empty string to drop the attribute
     */
    private function replacement(
        array $match
    ): string {
        $url = $this->entryUrl(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5));

        if ($url === null) {
            return '';
        }

        return 'href='.$match[1].e($url).($match[3] ?? '').$match[1];
    }

    /**
     * The URL of a published entry on the current site, or null.
     *
     * @param  string  $id  The entry id from the reference
     * @return string|null The entry's URL, or null when it cannot be linked
     */
    private function entryUrl(
        string $id
    ): ?string {
        $entry = Entry::find($id);

        if (! $entry instanceof EntryItem) {
            return null;
        }

        /** @var \Statamic\Sites\Site $site */
        $site = Site::current();

        // The localization for the site being rendered decides publication.
        $localized = $entry->in($site->handle());

        if (! $localized instanceof EntryItem || $localized->status() !== 'published') {
            return null;
        }

        $url = $localized->url();

        return \is_string($url) && $url !== '' ? $url : null;
    }
}
