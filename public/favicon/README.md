# Favicon

Browser tab and home-screen icons for the front-end site.

## Files

| File | Purpose | Referenced by |
|---|---|---|
| `favicon.svg` | Primary favicon for modern browsers. Single 1:1 SVG, ~1.7KB after SVGO. Scales to any size (16px tab through 512px PWA). | `<link rel="icon" type="image/svg+xml">` |
| `favicon.ico` | Legacy fallback for browsers without SVG-favicon support and bookmark caches. Multi-resolution ICO (16×16 + 32×32). | `<link rel="icon" sizes="any">` |
| `favicon-180x180.png` | iOS / iPadOS home-screen icon. Used when users "Add to Home Screen" from Safari. | `<link rel="apple-touch-icon">` |
| `../favicon.ico` | Duplicate of the .ico at the **public root** — see *Why two .ico files?* below. | Implicit browser request to `/favicon.ico` |

## Where these are referenced

Layout partial: [resources/views/layout/header/favicon.antlers.html](../../resources/views/layout/header/favicon.antlers.html)

Included in the site `<head>` from [resources/views/layout.antlers.html](../../resources/views/layout.antlers.html):

```html
<link rel="icon" href="/favicon/favicon.ico" sizes="any">
<link rel="icon" type="image/svg+xml" href="/favicon/favicon.svg">
<link rel="apple-touch-icon" href="/favicon/favicon-180x180.png">
```

That's the entire favicon markup. No IE-era tile metadata, no PWA manifest, no multi-size `<link>` ladder.

## Why two .ico files?

There's a copy at `public/favicon/favicon.ico` **and** at `public/favicon.ico` (root). Both have identical bytes. Reasons:

- **`/favicon/favicon.ico`** is what our `<link rel="icon">` tag points at. Used by browsers without SVG-favicon support.
- **`/favicon.ico`** (root) is the 1999-era conventional URL. RSS readers, scrapers, bookmark caches, web crawlers, and various automated tools still probe `/favicon.ico` directly regardless of what's in HTML. Also acts as a safety net for pages that don't include the layout partial — Statamic error pages ([resources/views/errors/](../../resources/views/errors/)) and the password-prompt page don't include the favicon partial, so the browser falls back to the implicit `/favicon.ico` request on those pages.

If the icon changes, **update both files**.

## Why we don't reference `/favicon.ico` directly in markup

The markup points at `/favicon/favicon.ico`, not `/favicon.ico`. This is a **macOS Herd** local-dev workaround.

On macOS, Herd's bundled nginx config (`~/Library/Application Support/Herd/config/nginx/herd.conf`, lines 21–22) contains exact-match `location` blocks for `/favicon.ico` and `/robots.txt`:

```nginx
location = /favicon.ico { access_log off; log_not_found off; }
location = /robots.txt  { access_log off; log_not_found off; }
```

These blocks have **no `try_files` directive**, so nginx tries to serve those URLs from `root /;` (the filesystem root, not the site's `public/`). The file doesn't exist there, so nginx 404s. Then `error_page 404 = server.php` does an internal redirect that lets Valet's PHP find and serve the actual `public/favicon.ico` content — but nginx preserves the original 404 status. Result: the correct file body comes back with a 404 status code.

**Windows Herd does not carry these blocks.** Its `herd.conf` (`%USERPROFILE%\.config\herd\config\nginx\herd.conf`) has no `location = /favicon.ico` and no `location = /robots.txt`, so a direct `/favicon.ico` request is served normally there. The markup still points at `/favicon/favicon.ico` because it has to be correct on both platforms — the indirection costs nothing on Windows and avoids the 404 noise on macOS.

**This is local-dev only.** Production nginx config won't have these vestigial Valet blocks, and `/favicon.ico` returns a clean 200.

To avoid the dev console noise, we reference the .ico at `/favicon/favicon.ico` instead. That URL goes through the normal `location /` rewrite to `server.php`, which serves the file with a proper 200.

## Updating the favicon

### Replacing the SVG (preferred)

1. Open the source artwork in Illustrator (square 1:1 artboard, e.g. 100×100).
2. **File → Export → Export As → SVG**, with these settings:
   - **Styling:** Presentation Attributes
   - **Font:** Convert to Outlines
   - **Images:** Embed (only if raster is present — favicon ideally has none)
   - **Object IDs:** Minimal
   - **Decimal:** 1 (or 2 if you see artifacts)
   - **Minify:** true
   - **Responsive:** true
3. Save to `resources/favicon/favicon.svg` (or wherever convenient).
4. Run SVGO to strip remaining Illustrator-isms:
   ```bash
   npx svgo path/to/favicon.svg
   ```
5. Sanity-check the output:
   - Root `<svg>` has `viewBox="0 0 N N"` (1:1 ratio) and **no** `width`/`height` attributes.
   - No `<text>` elements (should all be `<path>`).
   - No `xmlns:xlink`, no `<title>`/`<desc>`.
   - File under 2KB.
6. Replace `public/favicon/favicon.svg`.

### Design constraints for the SVG

- **1:1 aspect ratio.** Every consumer renders favicons in a square slot.
- **~10% safe margin** around the artwork. iOS/Android can apply a rounded mask; PWA maskable icons require this safe zone.
- **Legible at 16×16.** That's the smallest size browsers actually render at. Test by scaling to 16px in a browser — if it turns to mush, simplify the artwork (drop fine detail, thicken strokes, ditch gradients).
- **Survives dark-mode swap.** If the design uses `@media (prefers-color-scheme: dark)` to swap fills, make sure the silhouette works against Safari/Chrome's dark tab background (~`#292a2d`). The current design sidesteps this by putting a white logomark on a solid crimson background — the crimson backdrop provides its own contrast against any tab strip.

### Replacing the .ico

Use any multi-resolution ICO generator. A 16×16 + 32×32 combined ICO is sufficient. Update **both** copies:

- `public/favicon.ico` (root)
- `public/favicon/favicon.ico`

### Replacing the 180×180 PNG

Export a 180×180 PNG of the same artwork. Keep file size under 10KB (PNG-8 with a small palette is usually fine for two-color logos).

## What's intentionally NOT here

Don't re-add these without a deliberate reason:

- **IE-era tile metadata** (`msapplication-TileColor`, `msapplication-TileImage`, `msapplication-config`, `browserconfig.xml`). Internet Explorer 11 was retired June 2022; Windows 8 reached EOL January 2023. Dead code.
- **Multi-size `<link rel="icon">` ladder** (`favicon-32x32.png`, `favicon-96x96.png`, `favicon-128x128.png`, etc.). The `.ico` already contains 16/32, and the SVG handles every size above that. The extra PNG files were standard in the 2010s but add no value when SVG favicons work everywhere.
- **Multiple `apple-touch-icon` sizes.** Modern iOS scales a single 180×180 PNG correctly across all device classes.
- **`site.webmanifest` / PWA install icons.** Out of scope until the site actually offers PWA features.
- **`theme-color` meta tag.** Different concern (browser chrome theming, not favicon).
