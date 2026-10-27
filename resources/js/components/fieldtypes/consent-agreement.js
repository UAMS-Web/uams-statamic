/**
 * Sanitization helper for the Consent fieldtype's agreement preview.
 *
 * `ConsentFieldtype.vue` binds the agreement body with `v-html`. The value is
 * admin-authored markdown rendered to HTML server-side via Statamic's
 * `Markdown::parse()` (`app/Fieldtypes/Consent.php`), with a raw-markdown config
 * fallback — the same trust profile as Statamic's own markdown fieldtype. This
 * adds defense-in-depth client-side sanitization before the `v-html` sink,
 * mirroring Statamic core's markdown helper (`DOMPurify.sanitize(..., { ADD_ATTR:
 * ['target'] })`), so a stray `<script>`/handler can never reach the DOM even if
 * the server-side rendering or trust assumptions change.
 */

import DOMPurify from 'dompurify';

/**
 * Sanitize the consent-agreement HTML for safe `v-html` binding. Strips
 * `<script>`, inline event handlers, and `javascript:` URLs (and `data:` on
 * navigational `href`s), while preserving ordinary markdown-rendered
 * formatting. `target` is allowed so agreement links may open in a new tab
 * (matches Statamic core). Note DOMPurify still permits a safe-media-type
 * `data:` on `<img src>`, which does not execute script.
 *
 * @param  {string}  html  Server-rendered agreement HTML (or raw-markdown fallback).
 * @return {string}        Sanitized HTML, or `""` for non-string/empty input.
 */
export function sanitizeAgreementHtml(html) {
    if (typeof html !== "string" || html === "") {
        return "";
    }

    return DOMPurify.sanitize(html, { ADD_ATTR: ["target"] });
}

/**
 * Resolve the agreement HTML the component renders, then sanitize it. Prefers
 * the server-rendered `meta.consent_agreement_html`, falling back to the raw
 * `config.consent_agreement`. Keeping the resolve-and-sanitize together here
 * (rather than inline in the Vue computed) makes the exact value bound to
 * `v-html` unit-testable without mounting the CP component.
 *
 * @param  {{ consent_agreement_html?: string } | null | undefined}  meta
 * @param  {{ consent_agreement?: string } | null | undefined}       config
 * @return {string}  Sanitized agreement HTML.
 */
export function resolveAgreementHtml(meta, config) {
    const raw = (meta && meta.consent_agreement_html) || (config && config.consent_agreement) || "";

    return sanitizeAgreementHtml(raw);
}
