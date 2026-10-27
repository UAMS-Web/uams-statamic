# Canonical URLs for localized entries

Every public page emits a `<link rel="canonical">` tag that tells search engines which URL is the authoritative one for the page's content. This page explains the single editorial decision behind it: the **"Point the canonical URL at the origin entry"** toggle (`canonical_from_origin`) on an entry's **SEO → Meta** tab.

## What canonical does

When the same (or very similar) content lives at more than one URL, search engines pick one as *canonical* and credit it with the ranking; the others are treated as duplicates. Declaring the canonical yourself avoids letting the search engine guess — and avoids two of your own pages competing with each other.

## How the multisite uses it

UAMS multisite "localizations" are **not language translations** — they are audience/topic variants. The same underlying entry can exist on the medicine site, the cancer site, the research site, etc., each with content tuned for that audience. Two patterns follow:

- **Customized localization (the norm).** The localization's content is meaningfully different from its origin. It should be **self-canonical** so it holds its own search ranking. → Leave the toggle **off**.
- **Near-duplicate localization (rarer).** The localization is essentially a copy of its origin with little or no change. It should point its canonical at the origin so the origin keeps all the ranking and the copy doesn't dilute it. → Turn the toggle **on**.

Because customization is the common case, the toggle **defaults to off (self-canonical)**.

## What to do

| Situation | Toggle | Resulting canonical |
|---|---|---|
| An **origin** entry (not copied from another site) | (no effect) | itself |
| A **customized** localization | **off** (default) | itself |
| A **near-duplicate** localization | **on** | its origin entry |

The toggle only matters on a localization. On an origin entry it has no effect — the page is always self-canonical.

### Examples

- *Cancer site's "Lung Cancer Screening" page, rewritten with cancer-center-specific details* → customized → **off** (self-canonical). It should rank on its own for cancer-site searches.
- *Department site that copied the main "Visitor Parking" page verbatim* → near-duplicate → **on** (origin-canonical). Let the main page own that result.

When in doubt, leave it **off** — a customized page wrongly pointed at its origin becomes invisible to search, which is worse than two similar pages both being indexed.

## Where it's implemented

- Field: `canonical_from_origin` on the `uamsweb_entry_seo_meta` fieldset (SEO → Meta).
- Output: [`resources/views/layout/header/_canonical.antlers.html`](../../resources/views/layout/header/_canonical.antlers.html) — emits `origin:permalink` when the entry has an origin **and** the toggle is on, otherwise the entry's own `permalink`.
