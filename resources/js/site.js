// ---------------------------------------
// Alpine.js for Frontend Forms
// ---------------------------------------

/**
 * This file sets up Alpine.js for reactive form functionality in Statamic.
 *
 * Alpine.js Integration with Statamic's Conditional Form Logic:
 *
 * 1. ALPINE.JS SETUP
 *    - Alpine.js provides reactive data binding and DOM manipulation
 *    - Enables real-time form field updates without page refresh
 *    - Handles x-model, x-if, and other reactive directives
 *
 * 2. THE CONDITIONS EVALUATOR (UAMS-Web/uams-statamic-archive#1956)
 *    - resources/js/conditions/ owns the field-conditions evaluator outright:
 *      * showField(conditions, values, fieldPath) - Evaluates field conditions
 *      * It ships inside THIS bundle, so nothing loads from vendor at runtime.
 *    - Alpine.js and the evaluator work together:
 *      * Alpine.js handles DOM reactivity and user interactions
 *      * the evaluator answers "should this field be visible right now"
 *    - It replaced Statamic's helpers.js, which layout.antlers.html used to load
 *      as a script tag. That bundle decided every comparison with eval(), the
 *      last 'unsafe-eval' consumer on a public page and so the blocker on #33.
 *
 * 3. CONDITIONAL FORM FIELD FLOW
 *    - User interacts with form field (e.g., selects dropdown option)
 *    - Alpine.js updates form state via x-model binding
 *    - Statamic's show_field logic re-evaluates conditions
 *    - Alpine.js updates DOM to show/hide fields based on conditions
 *
 * 4. TEMPLATE INTEGRATION
 *    - Form templates use: {{ form:create :in="form" js="alpine_csp" }}
 *      (the uams-web/statamic-alpine-csp-forms add-on; see 6 below)
 *    - Field templates use: <template x-if="{{ show_field }}">
 *    - Field inputs use: x-model="form.{{ handle }}"
 *
 * 5. EXAMPLE WORKFLOW
 *    - Blueprint defines: if: select_field: 'equals foo'
 *    - User selects "foo" from dropdown
 *    - Alpine.js updates: form.select_field = "foo"
 *    - Statamic evaluates: show_field = $showField(conditions, $data, 'handle')
 *    - Alpine.js shows/hides field based on show_field result
 *
 * 6. THE CSP BUILD (UAMS-Web/uams-statamic-archive#1859)
 *    - `@alpinejs/csp` interprets x-* attributes instead of compiling them
 *      through new Function(), which is what lets script-src drop
 *      'unsafe-eval'. Its interpreter resolves identifiers only from the
 *      Alpine scope stack, so the stock driver's
 *      Statamic.$conditions.showField(...) throws on every field.
 *    - The uams-web/statamic-alpine-csp-forms add-on (UAMS-Web/uams-statamic-archive#3073) owns both halves:
 *      its `alpine_csp` driver emits $showField(...) instead, and its
 *      front-end script, loaded by the layout ahead of this bundle, registers
 *      the magic on `alpine:init`, which is why `window.Alpine` is set below
 *      before `Alpine.start()`.
 *
 * Files Involved:
 * - vendor/uams-web/statamic-alpine-csp-forms: the driver and the evaluator
 * - resources/js/conditions/: custom conditions the evaluator reads from
 *   window.StatamicCustomConditions
 * - resources/views/vendor/statamic/forms/fields.antlers.html: Uses x-if="{{ show_field }}"
 * - resources/views/vendor/statamic/forms/fields/*.antlers.html: Use x-model="form.{{ handle }}"
 *
 * Troubleshooting:
 * - "Alpine isn't loading" / public forms render without any input fields /
 *   DevTools shows `504 Outdated Optimize Dep`: Vite's `node_modules/.vite/deps`
 *   cache is stale (common after `npm ci`, lockfile-touching branch switches,
 *   or dep upgrades while the dev server is running). Fix and full diagnostic:
 *   docs/runbooks/vite-stale-optimize-dep-cache.md
 */

// Import Alpine.js for reactive functionality.
//
// This is the CSP build (`@alpinejs/csp`), not the standard `alpinejs` one: it
// evaluates every `x-*` attribute through an AST interpreter instead of
// `new Function()`, which is what lets `script-src` drop `'unsafe-eval'`
// (UAMS-Web/uams-statamic-archive#1859). The two constraints that buys are (a) attribute expressions may only
// use the supported node set — no template literals, arrow functions, `??` or
// optional chaining — and (b) they may not reference window globals, which is
// why the conditions evaluator is reached through the `$showField` magic below
// rather than named in the attribute. Neither constraint applies to modules
// such as this one.
import Alpine from '@alpinejs/csp';

// Import the custom Alpine component for checkbox groups with a "required" validation rule.
import { registerCheckboxRequiredGroup } from './checkbox-required-group.js';

// Public-form Signature fieldtype engine. Captures a drawn signature on a
// <canvas> via signature_pad (dynamically imported only when a signature field
// is present) and writes the data URL to the field's hidden input, with an
// accessible typed-name + attestation fallback. Mirrors the CP
// SignatureFieldtype.vue value shape.
import { registerSignatureField } from './signature-field.js';

// Public-form Replicator engine. Clones a blank set template on "Add Set", clamps removal at
// `min_sets`, and reindexes each set's field names to their DOM position on submit so the nested
// payload matches Statamic\Fieldtypes\Replicator::process().
import { registerListField } from './list-field.js';
import { registerReplicatorField, registerReplicatorSet } from './replicator-field.js';

// Accessible lightbox for the Image Gallery Bard set (UAMS-Web/uams-statamic-archive#188): prev/next, keyboard,
// swipe, and focus management over the server-rendered gallery grid.
import { registerGalleryLightbox } from './gallery-lightbox.js';

// Lite-YouTube facade for the video Bard set (UAMS-Web/uams-statamic-archive#224): a lightweight thumbnail +
// accessible play trigger that swaps in the real player iframe on activation.
import { registerLiteVideo } from './lite-video.js';

// Public-form calculation engine. Mirrors the CP's `calculation_*` Vue
// reactivity for public forms, importing the same pure math/text/format
// helpers as `CustomInput.vue` via `resources/js/lib/calculation.js`.
import { registerCalculationEngine } from './calculation-engine.js';

// Public-form PersonName engine. Mirrors the CP's reactive display_format
// recomputation, importing the same `buildDisplayFormatOptions` JS module
// as `PersonNameFieldtype.vue` via
// `resources/js/components/fieldtypes/person-name/personNameDisplayFormats.js`.
import { registerPersonNameEngine } from './person-name-engine.js';

// Composite-variable split engine. Projects a single composite value (e.g.,
// decimal pounds) into two adjacent display sub-inputs (lb, oz) and
// recomposes the composite when either sub-input is edited.
import { registerCompositeSplitEngine } from './composite-split-engine.js';

// Calculator reset-button engine. Clears every variable input within a
// calculator scope when the reset button is clicked.
import { registerCalculator } from './calculator.js';
import { registerCalculatorResetEngine } from './calculator-reset-engine.js';

// Cross-calculator value sync. Mirrors variables that share a handle and
// have opted in via `sync_across_calculators` across every calculator on
// the page (e.g. entering `weight_kg` in one calculator populates it in
// every other calculator's `weight_kg` field).
import { registerCalculatorSyncEngine } from './calculator-sync-engine.js';

// Public-form option pricing engine. Sums each option field's selected
// price(s), writes the result to the hidden `[line_total]` input, and
// dispatches `product:price-changed` so a Total field (UAMS-Web/uams-statamic-archive#488) sums it alongside
// products. Mirrors the CP-side feedback in `OptionFieldtype.vue`.
import { registerOptionPricingEngine } from './option-pricing-engine.js';

// Public-form product pricing engine. Computes each product field's line
// subtotal (quantity × unit price), writes it to the hidden `[line_total]`
// input, and dispatches `product:price-changed` so a Total field (UAMS-Web/uams-statamic-archive#488) can
// sum the order. Mirrors the CP-side feedback in `ProductFieldtype.vue`.
import { registerProductPricingEngine } from './product-pricing-engine.js';

// Public-form total pricing engine. Sums every product/option line subtotal in
// the form on `product:price-changed` and writes the running order total to the
// Total field's display + hidden input (UAMS-Web/uams-statamic-archive#488).
import { registerTotalPricingEngine } from './total-pricing-engine.js';

// Front-end Table set features (UAMS-Web/uams-statamic-archive#231). Progressive enhancement for tables the
// render partial flags with `data-table-features`: sort, search, pagination,
// adjustable row count and an info line over the existing server-rendered rows.
import { registerTableFeaturesEngine } from './table-features-engine.js';

// Table of Contents block (UAMS-Web/uams-statamic-archive#1283). Scans the entry content for h2/h3 headings, gives each a
// stable anchor id (reusing one the heading already has), and reveals the block's nav with the
// jump links. Without JavaScript the nav stays hidden rather than listing links to ids that
// were never assigned.
import { registerTocEngine } from './toc-engine.js';

// Find-a-Doc facet filter (UAMS-Web/uams-statamic-archive#1242). The `fadFilter` Alpine component narrows a
// provider block's server-rendered cards client-side by the facet slugs each card
// carries in `data-*` attributes — no request, degrades to the static list without JS.
import { registerFadFilter } from './fad-filter.js';

// Site-search type-ahead (UAMS-Web/uams-statamic-archive#1506): the suggestions listbox under the /search input.
import { registerSiteSearchTypeahead } from './site-search-typeahead.js';

// Set Alpine globally. The alpine-csp-forms add-on's script registers the
// `$showField` magic from an `alpine:init` listener on this instance (UAMS-Web/uams-statamic-archive#3073).
window.Alpine = Alpine;

// ---
// Register custom Alpine components before start() — Alpine.data() must run
// before Alpine.start() so the directives are available when the DOM is scanned.
// ---
registerCheckboxRequiredGroup(Alpine);
registerSignatureField(Alpine);
registerListField(Alpine);
registerReplicatorField(Alpine);
registerReplicatorSet(Alpine);
registerGalleryLightbox(Alpine);
registerCalculator(Alpine);
registerLiteVideo(Alpine);
registerFadFilter(Alpine);
registerSiteSearchTypeahead(Alpine);

// Queue calculation-engine binding for after Alpine initializes. Both
// engines reach into each form's reactive scope via `Alpine.$data`, so
// the scope must exist first — the shared `lib/alpine-engine.js` helper
// each engine wraps defers binding until `alpine:initialized`.
registerCalculationEngine();
registerPersonNameEngine();
registerCompositeSplitEngine();
registerCalculatorResetEngine();
registerCalculatorSyncEngine();
registerOptionPricingEngine();
registerProductPricingEngine();
registerTotalPricingEngine();
registerTableFeaturesEngine();
registerTocEngine();

// Start Alpine.js
Alpine.start();

// Import custom field conditions for both frontend and Control Panel
import './conditions.js';

// ---
// Front-end map viewer (progressive enhancement for the `partials/map`
// partial). Leaflet and its CSS are dynamically imported only when a
// `[data-map-viewer]` element is present, so pages without a map pay no
// JS/CSS cost.
// ---
function initFrontendMapViewers() {
    if (!document.querySelector('[data-map-viewer]')) {
        return;
    }

    import('./map-viewer.js').then((module) => module.initMapViewers());
}

// ---
// Knowledge base article engagement (UAMS-Web/uams-statamic-archive#286 ratings, UAMS-Web/uams-statamic-archive#287 views). The module is
// dynamically imported only on a knowledge base article page (which carries the
// `article.knowledge_bases` element), so every other page pays no JS cost. It
// fires the view beacon and wires the like/dislike triggers.
// ---
function initKbArticleEngagement() {
    if (!document.querySelector('article.knowledge_bases[data-article-id]')) {
        return;
    }

    import('./kb-article-engagement.js').then((module) => module.initKbArticleEngagement());
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFrontendMapViewers);
    document.addEventListener('DOMContentLoaded', initKbArticleEngagement);
} else {
    initFrontendMapViewers();
    initKbArticleEngagement();
}
