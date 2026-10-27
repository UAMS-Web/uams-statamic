/**
 * Sanitization helpers for the UAMS alert banner (`uamsalert.js`).
 *
 * The alert banner renders a remote JSONP feed (`www.uams.edu/web/alert/data`)
 * whose `content` / `title` / `URL` fields are attacker-influenced if the feed
 * is ever compromised or served over a downgraded (plain-HTTP) connection. This
 * module isolates the pure, DOM-free string logic so it can be unit-tested
 * without bootstrapping the banner's side-effectful entry script.
 *
 * `content` is genuine rich HTML (formatted WordPress post body), so it is run
 * through DOMPurify rather than coerced to text. `title` is plain text (also
 * used in a `title=""` attribute) and is rendered with `textContent` by the
 * caller. `URL` is scheme-validated here before it reaches an `href`.
 */

import DOMPurify from 'dompurify';

/**
 * The WordPress "read more" teaser marker embedded in feed `content`. Content
 * before the marker is shown inline; its presence enables the "Read More" link.
 *
 * @type {string}
 */
export const NOTEASER_MARKER = '<!--noteaser-->';

/**
 * Fallback href used when a feed URL fails scheme validation.
 *
 * @type {string}
 */
export const SAFE_URL_FALLBACK = '#';

/**
 * Sanitize rich feed HTML for safe assignment to `innerHTML`. Restricted to the
 * HTML profile (no SVG / MathML) — an alert banner never needs them — which
 * strips `<script>`, inline event handlers, and `javascript:`/`data:` URLs.
 *
 * The banner renders only ordinary WordPress post-body formatting, so on top of
 * the profile we forbid interactive/form tags and the `style` attribute. Those
 * are script-free vectors the HTML profile otherwise permits: a banner-top
 * `<form><input type=password>` is survivable credential-phishing, and a
 * `style="position:fixed;inset:0"` overlay is a clickjacking / UI-redress
 * surface (or a `background:url(...)` tracking beacon). The banner never needs
 * either, so they are surplus attack surface under the compromised-feed model.
 *
 * The DOMPurify default export binds to the ambient `window` (the real browser
 * at runtime, jsdom under Vitest), so no explicit window wiring is needed here.
 *
 * @param  {string}  html  Untrusted feed HTML.
 * @return {string}        Sanitized HTML, or `""` for non-string input.
 */
export function sanitizeAlertHtml(html) {
    if (typeof html !== "string" || html === "") {
        return "";
    }

    return DOMPurify.sanitize(html, {
        USE_PROFILES: { html: true },
        FORBID_TAGS: ["form", "input", "button", "textarea", "select", "option", "label"],
        FORBID_ATTR: ["style"],
    });
}

/**
 * Split feed `content` at the teaser marker. Mirrors the legacy behavior of
 * showing only the pre-marker body and exposing a "Read More" link — but does
 * the split on the raw string *before* sanitization, since DOMPurify strips
 * HTML comments (including the marker) and would otherwise defeat the check.
 *
 * @param  {string}  html  Raw feed `content`.
 * @return {{ visible: string, hasMore: boolean }}
 */
export function splitTeaser(html) {
    const str = typeof html === "string" ? html : "";
    const index = str.indexOf(NOTEASER_MARKER);

    if (index === -1) {
        return { visible: str, hasMore: false };
    }

    return { visible: str.slice(0, index), hasMore: true };
}

/**
 * Validate a feed URL before it is assigned to an `href`. Allows `http(s)`,
 * protocol-relative, root-relative, and anchor URLs; rejects every other scheme
 * (`javascript:`, `data:`, `vbscript:`, …) by returning the safe fallback.
 *
 * The scheme is isolated as the portion before the first `/`, `?`, or `#`, then
 * reduced to its bare scheme alphabet (`a-z0-9+.-` and the `:` terminator). That
 * strip removes any character a browser disregards when resolving the scheme —
 * whitespace, tabs, control characters — so obfuscation like `java\tscript:`
 * collapses to `javascript:` and is rejected.
 *
 * @param  {string}  url  Untrusted feed URL.
 * @return {string}       The original URL when safe, otherwise `"#"`.
 */
export function safeAlertUrl(url) {
    if (typeof url !== "string") {
        return SAFE_URL_FALLBACK;
    }

    const raw = url.trim();

    if (raw === "") {
        return SAFE_URL_FALLBACK;
    }

    const beforePath = raw.split(/[/?#]/, 1)[0];
    const scheme = beforePath.replace(/[^a-z0-9+.:-]/gi, "").toLowerCase();
    const schemeMatch = scheme.match(/^([a-z][a-z0-9+.-]*):/);

    if (schemeMatch && schemeMatch[1] !== "http" && schemeMatch[1] !== "https") {
        return SAFE_URL_FALLBACK;
    }

    return raw;
}
