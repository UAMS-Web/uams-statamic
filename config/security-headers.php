<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Application Security Headers
|--------------------------------------------------------------------------
|
| Configuration consumed by App\Http\Middleware\SecurityHeaders.
|
| Rollout plan (CSP):
|   1. Ship in Report-Only mode (default) so violations are logged via the
|      browser without breaking pages. Watch real-world traffic for reports.
|   2. Tighten the directive lists below until reports are quiet on the
|      pages we care about (front-end, CP, forms, PDF previews).
|   3. Flip SECURITY_CSP_REPORT_ONLY=false to enforce.
|
| HSTS is only emitted on secure (HTTPS) requests and only when explicitly
| enabled per environment — preload is sticky and must be opt-in.
|
*/

return [

    // Master switch; when false, SecurityHeaders skips every header below
    'enabled' => env('SECURITY_HEADERS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Baseline headers
    |--------------------------------------------------------------------------
    | These are cheap, broadly compatible, and applied to every response.
    */

    // X-Content-Type-Options value; set false to omit the header
    'content_type_options' => env('SECURITY_CONTENT_TYPE_OPTIONS', 'nosniff'),

    // Referrer-Policy value sent on every response
    'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),

    // X-Frame-Options value (clickjacking protection for legacy browsers)
    'frame_options' => env('SECURITY_FRAME_OPTIONS', 'SAMEORIGIN'),

    // Permissions-Policy feature allowlist (empty env value omits the header)
    'permissions_policy' => env(
        // Environment variable name for Permissions-Policy
        'SECURITY_PERMISSIONS_POLICY',

        // Default deny-list for sensitive device APIs
        'camera=(), microphone=(), payment=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=()'
    ),

    /*
    |--------------------------------------------------------------------------
    | HSTS — Strict-Transport-Security
    |--------------------------------------------------------------------------
    | Only sent when the request is actually over HTTPS. Disabled by default
    | so local (http://uams-statamic.test) traffic is unaffected. Production
    | should set SECURITY_HSTS_ENABLED=true.
    |
    | preload is intentionally opt-in: once a domain is in browser preload
    | lists it is very slow to remove. Only enable after a long bake at
    | max-age=31536000 with includeSubDomains.
    */

    'hsts' => [
        // Emit Strict-Transport-Security only on HTTPS when true
        'enabled' => env('SECURITY_HSTS_ENABLED', false),

        // max-age directive in seconds (default one year)
        'max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),

        // Append includeSubDomains to the HSTS header when true
        'include_subdomains' => env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', true),

        // Append preload token; enable only after sustained HTTPS rollout
        'preload' => env('SECURITY_HSTS_PRELOAD', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy
    |--------------------------------------------------------------------------
    | Directives are arrays of source expressions. Empty arrays emit a
    | bare directive (e.g. `upgrade-insecure-requests`). Set a directive
    | to `null` to omit it.
    |
    | Per-request nonces
    | ------------------
    | SecurityHeaders generates one nonce per response and injects it into
    | every directive listed under `nonce_directives`. The same value is
    | exposed to templates as `{{ csp_nonce }}` (Antlers) / `{{ $csp_nonce }}`
    | (Blade), so the inline <style>/<script> blocks we control can carry a
    | matching `nonce="..."`. Adding a nonce to a directive makes browsers
    | ignore `'unsafe-inline'` for it, so only nonce-tagged inline content is
    | allowed — that is exactly how `style-src` is tightened below.
    |
    | Remaining `'unsafe-*'` allowances:
    |   - script-src `'unsafe-inline'`: REMOVED (UAMS-Web/uams-statamic-archive#1741). Inline <script> elements
    |     are now nonce-gated, exactly as `style-src` already was. Both inline
    |     blocks we control (layout/header/_google_tag_manager,
    |     people/provider/_podcast) already carry `nonce="{{ csp_nonce }}"`, and
    |     `resources/views/**` emits no inline event-handler attributes, so this
    |     needed no template sweep. JSON-LD is a data block CSP does not gate.
    |   - script-src `'unsafe-eval'`: REMOVED (UAMS-Web/uams-statamic-archive#1859). The front end bundles
    |     `@alpinejs/csp` instead of the standard Alpine 3 build, so every `x-*`
    |     attribute is evaluated by an AST interpreter rather than compiled
    |     through `new Function()`. Public-form field conditions moved with it:
    |     `UAMSWeb\Aether\Forms\AlpineCsp\JsDrivers\AlpineCsp` (the uams-web/aether-alpine-csp-forms
    |     add-on, UAMS-Web/uams-statamic-archive#3073; selected with `js="alpine_csp"`) emits
    |     a `$showField(...)` magic call in place of Statamic's
    |     `Statamic.$conditions.showField(...)`, which the CSP build rejects as a
    |     window global. Nothing else in `resources/js/**` evaluates strings.
    |     One response adds it back: Google's results take it up through
    |     `allowances.google_search` below (UAMS-Web/uams-statamic#412).
    |   - style-src `'unsafe-inline'`: REMOVED. Inline <style> elements are now
    |     nonce-gated. Inline `style="..."` attributes are kept working via
    |     `style-src-attr 'unsafe-inline'`; hardening those (sweeping attribute
    |     styles out of our templates and vendor views) is a follow-up.
    |
    | Control Panel (cp/*): Statamic core emits the CP's inline scripts/styles
    | and needs `'unsafe-eval'` for its Vue tooling. Nonce support for those
    | belongs upstream in `statamic/cms`, so this config does not special-case
    | the CP. While in Report-Only mode the CP's un-nonced inline assets simply
    | surface as violation reports; flipping cp/* to enforce (#33) should be
    | gated on upstream nonce support.
    |
    | Dropping `'unsafe-eval'` (UAMS-Web/uams-statamic-archive#1859) widens that same CP gap rather than
    | creating a new one: the CP now reports an `'unsafe-eval'` violation
    | alongside the inline ones it already reported. Nothing breaks while the
    | policy is Report-Only, and #33 cannot flip cp/* to enforce until upstream
    | ships nonce support regardless. Whether cp/* then gets an `exclude_paths`
    | entry or its own directive set is #33's call, not this config's.
    */

    'csp' => [
        // When false, no Content-Security-Policy header is sent
        'enabled' => env('SECURITY_CSP_ENABLED', true),

        // true = Report-Only header; false = enforcing Content-Security-Policy
        'report_only' => env('SECURITY_CSP_REPORT_ONLY', true),

        // Optional report-uri endpoint for browser violation reports
        'report_uri' => env('SECURITY_CSP_REPORT_URI'),

        // ---
        // Directives that receive the per-request `'nonce-<base64>'` value.
        // SecurityHeaders appends the nonce to each listed directive and
        // exposes the same value to templates as `{{ csp_nonce }}`. Listing
        // an element directive here makes browsers drop `'unsafe-inline'`
        // for it, so only nonce-tagged inline content is allowed.
        // ---
        'nonce_directives' => [
            'script-src',
            'style-src',
        ],

        // ---
        // Path patterns (Str::is) where CSP is NOT applied. Other headers
        // still apply. Use this if a route needs to be excluded entirely.
        // ---
        'exclude_paths' => [
            //
        ],

        // ---
        // Sources added to one response, by name, when its render takes them up
        // through SecurityHeaders::allowOnThisResponse(). For a third-party script
        // that only some renders load, so every other page stays narrower. A
        // directive set to null below stays omitted: nothing here brings it back.
        // ---
        'allowances' => [
            // Google's results for "All UAMS websites" on /search
            // (UAMS-Web/uams-statamic#412): `cse.js` is nonce-tagged by our template,
            // then loads its own scripts and stylesheets, which a nonce cannot cover.
            // Its element calls eval() for each result, so results fail without
            // 'unsafe-eval' once the policy enforces. This response alone gets that.
            // Its three inline <style> elements stay blocked: results render
            // without them, and allowing them would mean dropping the nonce.
            'google_search' => [
                'script-src' => [
                    "'unsafe-eval'",
                    'https://cse.google.com',
                    'https://www.google.com',
                ],
                'style-src' => [
                    'https://www.google.com',
                ],
                'connect-src' => [
                    'https://cse.google.com',
                ],
                'frame-src' => [
                    'https://cse.google.com',
                ],
            ],
        ],

        // CSP directive name => source expression list (null omits a directive)
        'directives' => [
            // Fallback for fetches that do not match a more specific directive
            'default-src' => [
                "'self'",
            ],

            // JavaScript sources. Inline <script> elements are gated by the
            // per-request nonce (injected via nonce_directives), so
            // 'unsafe-inline' is intentionally absent here (UAMS-Web/uams-statamic-archive#1741), and
            // 'unsafe-eval' is absent because the front end runs the
            // @alpinejs/csp build (UAMS-Web/uams-statamic-archive#1859). Both absences are asserted by
            // tests/Feature/Http/SecurityHeadersMiddlewareTest.php so a
            // re-added allowance is a deliberate edit, not silent drift.
            'script-src' => [
                "'self'",
                'https://www.googletagmanager.com',
                'https://www.google-analytics.com',
                'https://*.google-analytics.com',
                'https://maps.googleapis.com',

                // LiveWhale calendar widget: the events feed on the news home
                // page and the `livewhale` page-builder block both load
                // `lwcw.js` from the UAMS calendar host. Surfaced by the UAMS-Web/uams-statamic-archive#1741
                // inline-script audit — the host was never allow-listed, so it
                // reports as a violation today and would break outright when
                // #33 flips CSP to enforcing. The widget needs three
                // directives, not one — see connect-src and style-src below.
                'https://calendar.uams.edu',

                // RadioMD widget (UAMS-Web/uams-statamic-archive#1683): the UAMS Health Talk podcast embed on
                // clinical provider profiles loads easyXDM from the apex host only
                // (`https://radiomd.com/widget/easyXDM.js`). Scoped to the apex on
                // purpose — the frame it then renders needs its own, wider allowance
                // (see frame-src below), but nothing executes a subdomain's script.
                'https://radiomd.com',
            ],

            // Stylesheet element sources. Inline <style>/<link> are gated by
            // the per-request nonce (injected via nonce_directives), so
            // 'unsafe-inline' is intentionally absent here.
            'style-src' => [
                "'self'",
                'https://fonts.googleapis.com',

                // LiveWhale calendar widget (UAMS-Web/uams-statamic-archive#1741): `lwcw.js` appends a
                // <link rel="stylesheet"> for the aggregated widget CSS on this
                // host. A nonce cannot cover it — the element is created by
                // third-party script, not by our templates — so the host is
                // allow-listed instead.
                'https://calendar.uams.edu',
            ],

            // Inline style="..." attribute sources. Nonces cannot cover
            // attribute styles, so these stay on 'unsafe-inline' until the
            // attribute-style sweep (follow-up) lands. Attribute styles are a
            // far weaker vector than inline <style> elements.
            'style-src-attr' => [
                "'unsafe-inline'",
            ],

            // Image sources (assets, data URIs, and remote https images)
            'img-src' => [
                "'self'",
                'data:',
                'blob:',
                'https:',
            ],

            // Web font sources
            'font-src' => [
                "'self'",
                'data:',
                'https://fonts.gstatic.com',
            ],

            // XHR, fetch, WebSocket, and EventSource targets
            'connect-src' => [
                "'self'",
                'https://www.google-analytics.com',
                'https://*.google-analytics.com',
                'https://*.analytics.google.com',
                'https://stats.g.doubleclick.net',
                'https://maps.googleapis.com',

                // LiveWhale calendar widget (UAMS-Web/uams-statamic-archive#1741): both templates request the
                // widget with `format=html`, which takes jQuery's `dataType:
                // 'html'` branch — a cross-origin XHR to
                // `/live/widget/<id>` on this host, not JSONP. Without this the
                // events feed itself is blocked at the #33 enforce flip, so the
                // widget renders empty rather than merely unstyled.
                'https://calendar.uams.edu',
            ],

            // Nested browsing contexts (video embeds, MyChart, Google)
            'frame-src' => [
                "'self'",
                'https://www.youtube.com',
                'https://www.youtube-nocookie.com',
                'https://youtu.be',
                'https://player.vimeo.com',
                'https://*.mychart.com',
                'https://mychart.uamshealth.com',
                'https://www.google.com',

                // RadioMD widget (UAMS-Web/uams-statamic-archive#1683): easyXDM renders the episode list into an
                // <iframe>, so the frame host needs its own allowance alongside the
                // script-src one above.
                'https://radiomd.com',
                'https://*.radiomd.com',
            ],

            // Audio and video elements
            'media-src' => [
                "'self'",
                'https:',
                'data:',
                'blob:',
            ],

            // Dedicated workers and shared workers
            'worker-src' => [
                "'self'",
                'blob:',
            ],

            // Who may embed this site in a frame (complements X-Frame-Options)
            'frame-ancestors' => [
                "'self'",
            ],

            // Restricts URLs usable by base and form-action in the document
            'base-uri' => [
                "'self'",
            ],

            // Targets allowed for form submissions
            'form-action' => [
                "'self'",
            ],

            // Blocks plugins (Flash, Java applets, etc.)
            'object-src' => [
                "'none'",
            ],

            // Upgrade http subresource requests to https (empty = flag only)
            'upgrade-insecure-requests' => [
                //
            ],
        ],
    ],

];
