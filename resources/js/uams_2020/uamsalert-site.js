/**
 * Site-bucket detection for the UAMS alert banner (`uamsalert.js`).
 *
 * The shared alert feed categorizes alerts by a coarse "site" bucket
 * (`uams`, `uamshealth`, …). The WordPress original sniffed `<body>` class
 * names the WordPress theme emitted; Statamic emits the resolved bucket as a
 * `data-uams-alert-site` attribute on `<body>` instead (see the
 * `{{ uams_alert_site_key }}` tag and `config/uamsalert.php`). This pure
 * helper reads that attribute so it can be unit-tested without bootstrapping
 * the banner's side-effectful entry script.
 */

/**
 * Default alert bucket when no `data-uams-alert-site` is present — the shared
 * UAMS-wide alert. Mirrors the PHP `config('uamsalert.default')` fallback.
 *
 * @type {string}
 */
export const DEFAULT_SITE_STATUS = 'uams';

/**
 * Resolve the alert bucket for the current page from the `<body>` element's
 * `data-uams-alert-site` attribute, falling back to {@link DEFAULT_SITE_STATUS}.
 *
 * @param {HTMLElement} [body=document.body] The body element to read.
 * @returns {string} The alert bucket key (e.g. `uams`, `uamshealth`).
 */
export function resolveSiteStatus(body = document.body) {
    const status = body && body.dataset ? body.dataset.uamsAlertSite : '';

    // Trim so a blank/whitespace-only attribute falls back to the default
    // bucket rather than producing dead categories like `alert-   `.
    return (status && status.trim()) || DEFAULT_SITE_STATUS;
}
