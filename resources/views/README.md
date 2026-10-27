# `resources/views` — template authoring contracts

Conventions that apply across the templates in this directory. Each section is a contract: if you author a template that triggers it, you **must** follow the rule, or the page will misbehave once the relevant policy is enforced.

---

## Content-Security-Policy nonce on inline `<style>` / `<script>`

The app sends a Content-Security-Policy (see [`../../config/security-headers.php`](../../config/security-headers.php) and [`../../app/Http/Middleware/SecurityHeaders.php`](../../app/Http/Middleware/SecurityHeaders.php)). `style-src` **and** `script-src` are **nonce-gated** — neither allows `'unsafe-inline'`. A fresh nonce is generated per request and injected into the policy header; the same value is exposed to templates as `{{ csp_nonce }}` (an Antlers tag, see [`../../app/Tags/CspNonce.php`](../../app/Tags/CspNonce.php)) and as `{{ $csp_nonce }}` in Blade.

### The rule

Any inline `<style>` **or** `<script>` block **you author in this directory must carry the nonce**:

```antlers
<style nonce="{{ csp_nonce }}">
    /* ...rules, including dynamic Antlers values... */
</style>

<script nonce="{{ csp_nonce }}">
    /* ...inline JS... */
</script>
```

Without the `nonce` attribute the browser blocks the block (or, while we remain in Report-Only mode, files a violation report instead of applying it). This is why nonces — not hashes — are used: most of our inline `<style>` blocks interpolate Antlers values (block IDs, Glide URLs), so their bytes change per render and a static hash could never match.

### What does **not** need the nonce

- **External stylesheets / scripts** — `<link rel="stylesheet" href="...">` and `<script src="...">` are governed by the source allow-list, not the nonce. Vite-built assets (`{{ vite }}`) and CDN assets are already covered.
- **Inline `style="..."` attributes** — these are governed by `style-src-attr`, which still permits `'unsafe-inline'` today. The nonce **cannot** be applied to an attribute, only to a `<style>` element. Prefer a class over an inline attribute where practical, but existing attribute styles keep working.
- **`<script type="application/ld+json">`** — JSON-LD is a data block, not executable JS, so CSP does not gate it.

### What nonces cannot cover

- **Inline event-handler attributes** (`onclick="..."`) and **`javascript:` URLs** — a nonce applies to an element, not an attribute, so these are blocked outright once `script-src` enforces. `resources/views/**` emits none today; keep it that way. Bind behavior through Alpine or an external module instead.
- **`'unsafe-eval'`** — **removed** ([#1859](https://github.com/UAMS-Web/uams-statamic-archive/issues/1859)). The front end runs `@alpinejs/csp`, which evaluates `x-*` attributes with an AST interpreter instead of `new Function()`, so nothing on a public page may generate code at runtime.

### Writing Alpine under the CSP build

`@alpinejs/csp` accepts a deliberately small expression grammar in `x-*` attributes. **This applies to attributes only** — a JS module under `resources/js/` is ordinary code and none of it applies there. Anything outside the grammar throws at evaluation time, and because every public-form field is wrapped in `<template x-if="…">`, a throw usually presents as a form that renders its heading and Submit button with no inputs.

- **No window globals.** An identifier is resolved only from the Alpine scope stack, so `Statamic`, `window`, `document`, `localStorage`, `JSON` and `Math` all throw `Undefined variable`. Reach them from a module and expose the result as an `Alpine.magic()`: the `$showField` magic in the `uams-web/statamic-alpine-csp-forms` add-on's `resources/js/conditions/showFieldMagic.js` is the worked example.
- **No template literals, arrow functions, `??`, or optional chaining.** The supported nodes are `Literal`, `Identifier`, `Member`, `Call`, `Binary`, `Unary`, `Update`, `Conditional`, `Assignment`, `Array` and `Object`. `&&` and `||` are fine; they fold into `Binary`.
- **No `x-html`.** The CSP build replaces the directive with a hard error.
- **Every `x-data` component name must be registered** with `Alpine.data()` before `Alpine.start()`, and every handle an `x-model` binds must be seeded into the scope. There is no window fallback to mask an omission.

### External script hosts

`<script src="...">` is governed by the `script-src` source allow-list, not the nonce. **If you add a template that loads a script from a new host, add that host to `script-src`** — otherwise it reports as a violation now and breaks when enforcement lands. `SecurityHeadersMiddlewareTest` asserts the hosts the views currently need.

### Source of truth

`csp.nonce_directives` in [`../../config/security-headers.php`](../../config/security-headers.php) lists exactly which directives receive the nonce. The `style-src` half shipped in [#668](https://github.com/UAMS-Web/uams-statamic-archive/issues/668) and the `script-src` half in [#1741](https://github.com/UAMS-Web/uams-statamic-archive/issues/1741); the Report-Only → enforce flip is [#667](https://github.com/UAMS-Web/uams-statamic-archive/issues/667).
