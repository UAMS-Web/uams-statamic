# Specification: Syndication Feed Output Policy

This document records the standing policy for the UAMS Statamic site's **outbound syndication feeds** (the site's own RSS/Atom output). It exists because the WordPress settings that governed feed output (`Settings → Reading`) are **not present in the WXR export**, so the behavior is a Statamic-side decision rather than a value the importer can carry over.

> **Scope.** This is a policy/decision record, not a feed implementation. No outbound syndication feed exists in the Statamic app today (see [Current state](#current-state)); the policy below is the pinned target for whenever one is built.

## Decisions

### Feed item count (UAMS-Web/uams-statamic-archive#185)

**Fixed at the 10 most-recent items. No per-site (or per-feed) configurability.**

WordPress exposed this as `Settings → Reading → "Syndication feeds show the most recent [N] items"` (the `posts_per_rss` option). There is no demonstrated need for per-site tuning, and the value is absent from the export (see below), so a single sensible fixed default is preferred over speculative configurability.

### Feed content: full text vs. excerpt (UAMS-Web/uams-statamic-archive#186)

**Excerpt-only.** Each feed item carries the entry's excerpt/summary, not its full content.

WordPress exposed this as `Settings → Reading → "For each post in a feed, include [Full text / Excerpt]"` (the `rss_use_excerpt` option). Excerpt-only is lighter for performance and SEO and matches typical syndication consumption (a teaser that links back to the page); there is no demonstrated need for a full-text/excerpt toggle, and the value is absent from the export. Decided jointly with the item count above.

## Why this is a Statamic-side decision (not migrated)

Both controlling WordPress options live in `wp_options`, which the WXR format does not carry — a WXR file exports *content* (posts, pages, terms, comments, and per-post `meta_key`/`meta_value` post-meta), not site options. Verified against the local export corpus at `wordpress-importer-exports/WordPress`:

- `posts_per_rss` (feed item count) — **0 occurrences** across all WXR files.
- `rss_use_excerpt` (feed full-text vs. excerpt) — **0 occurrences** across all WXR files.
- The `<wp:*>` element inventory confirms WXR carries content and post-meta but no `wp_options` block.

So neither setting can be imported; each is decided on the Statamic side and documented here as intentional. (This is the same pattern as UAMS-Web/uams-statamic-archive#187, the search-engine-visibility Reading setting.)

## Current state

- **No outbound syndication feed exists in the Statamic app.** There is no feed route, no collection `feed_url`, and Statamic v6 ships no native feed feature (no core `Feeds` component).
- The existing `{{ rss_feed }}` tag (`app/Tags/RssFeed.php`) and the RSS page-builder block **consume external feeds** for on-page display; they do not publish the site's own feed.

## Application (when an outbound feed is built)

Whenever an outbound feed is implemented — tracked in UAMS-Web/uams-statamic-archive#1042 (the build ticket), naturally alongside the UAMS News surface (UAMS-Web/uams-statamic-archive#76) — apply this policy:

- Limit the feed query to the **10 most-recent** entries.
- Emit each item's **excerpt/summary**, not its full content.

## Non-goals

- Building per-site (or per-feed) configurability now (neither the item count nor the full-text/excerpt mode is exposed as a setting).
- Any importer change — the source values are absent from WXR by construction.

## Revisit trigger

Revisit only if a real feed consumer demonstrates a need for a different item count or for full-text content.

## References

- WordPress: `Settings → Reading` — syndication feed item count (`posts_per_rss`) and feed full-text/excerpt (`rss_use_excerpt`).
- Issues: UAMS-Web/uams-statamic-archive#185 (item count), UAMS-Web/uams-statamic-archive#186 (feed content), UAMS-Web/uams-statamic-archive#187 (search-engine visibility — sibling Reading setting, same "absent from WXR" pattern).
- Outbound feed build — UAMS-Web/uams-statamic-archive#1042 (applies this policy when an outbound feed is built).
- Feed consumers: UAMS-Web/uams-statamic-archive#76 (UAMS News page-builder block reads external feeds).
