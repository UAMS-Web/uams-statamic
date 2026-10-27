# WP → Statamic Mapping: Area-of-Expertise Virtual Subsite (FD20-2671)

Companion to [`docs/ontology-update/aoe-virtual-subsite-wordpress-plan.md`](https://github.com/UAMS-Web/uams-statamic-archive/blob/800-expertise-blueprint/docs/ontology-update/aoe-virtual-subsite-wordpress-plan.md). This document maps the WordPress AoE-as-virtual-subsite epic onto the **existing** Statamic build, marks what already exists vs. the genuine gaps, and isolates the few decisions left for the owner.

The headline: **the Statamic build is dramatically further along than the WordPress feature ever got.** FD20-2671 in WP was almost entirely unbuilt — only the data-model carve-out shipped — yet on the Statamic side the data model is staged, the schema.org subsystem is rebuilt from scratch and production-grade, and the single hardest WP blocker (deep nested permalinks) is a non-issue natively. Most decisively, the `knowledge_bases` collection is a **near-complete reference implementation of this exact pattern**: multiple blueprints in one structured collection, unbounded nested routing, structural validation listeners with an importer bypass, and computed-field registrars. The remaining work is overwhelmingly *behavior wiring + a handful of decisions*, not net-new design. The one truly open architectural fork is how the per-AoE "fake subpages" render (§6); the rest is clone-and-retarget against `knowledge_bases`, plus a small set of content/IA calls.

One routing nuance must not be glossed (it is corrected in §5 below): although AoE deliberately avoids KB's *per-root* `mount_prefix` machinery, native mounting combined with structural nesting still needs a **depth-gated** mount token — the unconditional `{mount}` would duplicate the mount path at every nested level. No collection in this build uses the `{mount}` token today, so the native-mount-with-nesting path is genuinely new here and must be tested before the routing foundation is built on it.

---

## Decisions locked (2026-06-23)

All design forks in this document are resolved. This section is the authority; the "Design forks to decide" section below is retained for rationale but is **superseded**.

**Directives.** D1 — `clinical_expertise` hierarchy is **unbounded** (remove `structure.max_depth`). D2 — "content pages" = new **basic-page + landing-page blueprints reusing the `pages` blueprints/fieldsets**, living *in* `clinical_expertise`. D4 — structural validation by **cloning the `knowledge_bases` pattern** (`EntrySaving` + `CollectionTreeSaving` listeners + valid-parent scopes + `EventServiceProvider` registration + `import:wordpress` bypass): root = `expertise` only; `expertise` parent = none-or-`expertise`; content-page parent = `expertise`-or-content-page (never root). D5 — the **whole collection mounts to one new dedicated `pages` landing entry** (native mount; no per-entry `mount_prefix`).

**Structural forks.** Render model = **routed `/{section}` URLs** (one wildcard `Route::statamic('{entry}/{section}')->where(...)` serving each relationship section at any depth, reading the staged `ontology_fpage_*` fields; `specialties` = structure children) — reused for `clinical_locations` + provider profiles. Route = depth-gated `route: {{ depth == 1 ?= mount }}/{{ parent_uri }}/{{ slug }}`. Mount base = a **new dedicated landing page**. Child-AoE URLs = **flat**. AoE primary nav = **replaces** the global nav (subbrand link + breadcrumbs preserve a path home; build a nearest-`expertise`-ancestor computed). Appointment CTA = **auto-append from nearest ancestor + per-entry opt-out**. Add a per-entry **`searchable`** toggle + gate archive/site search (and fix the `alternate_name`→`alternate_names` index bug in `config/statamic/search.php`).

**Sibling collections (follow-up).** D6 (trimmed) — `clinical_resources`, `clinical_locations`, and the clinical-provider-eligible `people` subset each mount to their own dedicated `pages` entry; **`clinical_conditions`/`clinical_services` are NOT mounted** (no pages — see the D9 doc). D7 — `clinical_locations` mirrors `clinical_expertise` but the `location` blueprint is capped at **effective max-depth 2** (root → one child level) while content pages beneath are unbounded. D8 — the eligible `people` subset gets virtual subsections but is **flat** (no descendants).

**Conditions & Treatments + Special Interest** are resolved in the [D9 mapping doc](https://github.com/UAMS-Web/uams-statamic-archive/blob/800-expertise-blueprint/docs/ontology-update/aoe-d9-relationship-ontology-mapping.md): C&T = data, not pages; SI = a lean two-field model.

---

## What the build already gives us (incl. the knowledge_bases precedent)

The `knowledge_bases` collection is the working blueprint for almost everything FD20-2671 needs. Study these first — the AoE port is largely a faithful clone:

- **Multiple blueprints in one structured collection** — [`resources/blueprints/collections/knowledge_bases/{knowledge_base,topic,article}.yaml`](https://github.com/UAMS-Web/uams-statamic-archive/blob/800-expertise-blueprint/resources/blueprints/collections/knowledge_bases) (root / mid / leaf). Direct analog of an AoE-root blueprint + content-page blueprints living together in `clinical_expertise`.
- **Native unbounded nested routing** — `content/collections/knowledge_bases.yaml:252` `route: '{mount_prefix}{parent_uri}/{slug}'` with `structure.root:false`. `{parent_uri}` chains recursively (`vendor/statamic/cms/src/Structures/Page.php:224,230`), giving arbitrary depth — exactly WP's "infinite subdirectories" goal (FD20-3260) that `add_rewrite_rule` could never deliver. Note KB's route uses the **custom** `{mount_prefix}` token (emitted only on the root blueprint), not the native `{mount}` token — so AoE's route is *not* verbatim-identical to KB's; see §5.
- **Structural validation = the D4 mechanism, already built** — `app/Listeners/ValidateKnowledgeBaseEntry.php` (on `EntrySaving`, resolves parent and enforces blueprint/parent rules, throws on violation) + `app/Listeners/ValidateKnowledgeBaseTree.php` (on `CollectionTreeSaving`, recursive enforcement, **`import:wordpress` bypass**, static dedupe), registered in `app/Providers/EventServiceProvider.php:54-63`. Parent-filter scopes `app/Scopes/Valid{Article,Topic}Parent.php`.
- **Computed-field registrars** — `app/Services/ComputedFields/Registration/KnowledgeBaseCollectionComputedsRegistrar.php` + `app/Services/KnowledgeBase/KnowledgeBaseComputedFieldsService.php`: the pattern for AoE's nearest-ancestor / descendant computeds.
- **Hierarchy query scopes** — `app/Scopes/KnowledgeBase{Only,TopicOnly,TopicChildren,ArticleChildren}.php` (`where('parent', id)` + blueprint filter): the model for AoE descendant-derivation.
- **In-page section rendering** — `resources/views/knowledge_bases/_topic_layout.antlers.html` + `_topic_list.antlers.html` compose multiple child-listing sections on one page via `{{ collection:... :parent:is="id" }}`. KB never makes real entries for per-section views — it is the in-page-section reference.
- **Mount-rename URI re-stamping is a *custom* service in KB, not pure core** — `app/Services/KnowledgeBase/KnowledgeBaseMountUriRefresher.php` exists specifically to re-stamp subtree URIs when KB's per-root mount changes (invoked from `app/Entries/KnowledgeBaseEntry.php` `save()`). KB's case is per-root mounting; AoE's whole-collection single mount is a *different* case and is unproven in this build — see the §5 caveat.

Beyond KB, the build already ships:

- **The AoE data model, staged.** [`resources/blueprints/collections/clinical_expertise/expertise.yaml`](https://github.com/UAMS-Web/uams-statamic-archive/blob/800-expertise-blueprint/resources/blueprints/collections/clinical_expertise/expertise.yaml) carries every relationship (`physician_expertise`, `related_locations`, `associated` self-ref, `clinical_resources`, `conditions`, `treatments`), the hidden computed `brand_organizations`/`regions`, the schema tab, and a complete set of per-section "Fields for Future Functionality" (`ontology_fpage_intro/_short_desc_query/_short_desc/_featured_image`) sourced from the shared fieldset `resources/fieldsets/uamsweb_common_fad.yaml:299-345`. The `expertise_type` discriminator was correctly dropped.
- **A production-grade, natively-rebuilt schema.org subsystem.** Full vocabulary (`app/Enums/SchemaType.php` ~924 types, `SchemaProperty.php` ~1549 properties, `SchemaEnumerationMember.php` ~535 members), a cascade-driven `@graph` runtime (`app/Services/SchemaTemplates/SchemaOutputService.php`, `SchemaJsonLdAtIdDeduplication.php`, output/fragment caches), custom fieldtypes (`app/Fieldtypes/{Identifier,PersonName,CssSelector,Isco_08,...}.php`), the `SchemaPronounceableText` **Tag** (`app/Tags/SchemaPronounceableText.php` — a Tag, not a fieldtype), and **the AoE schema is already authored and wired** (two `schema_objects` entries bound via the `webpage_entry`/`main_content_entry` inject, emitted by `{{ schema_ld_json }}` at `resources/views/layout.antlers.html:188`).
- **Find-a-Doc Settings, already built** — `resources/blueprints/globals/uams_fad_settings.yaml` (~3449 lines) with a dedicated `clinical_areas_of_expertise` tab (line 1341: single/plural/descendant labels, archive headline/intro/image, all five fake-subpage placement contexts), scoped to the `health` site. This is FD20-3255/3310, almost entirely done.
- **The full chrome scaffolding with AoE-shaped stubs** — swappable page-headers (`resources/views/layout/page-header/_{normal,graphic,hero,marketing-landing-page,hidden}.antlers.html`), a stubbed subbrand hook (`resources/views/layout/header/navbar/_brand.antlers.html` `$subsection_query=false` + `_subbrand.antlers.html`, both under `layout/header/navbar/` — **not** under `page-header/`) with explicit AoE TODOs, structure-driven breadcrumbs via core `{{ nav:breadcrumbs }}`, and a section-link-list partial (`navigation/subpages/_subpages.antlers.html`).
- **The FaD marketing block palette + the four FaD blocks** — `resources/fieldsets/page_builder.yaml` (the native "allowed-block whitelist") and `uamsweb_block_fad_{locations,providers,expertise,clinical_resources}.yaml`, each with an `expertise` AoE filter. The Appointment-CTA block body exists as `uamsweb_block_cta.yaml`.

---

## Decided directives (D1–D5)

These are **settled** — map *to* them; do not relitigate.

- **D1 — Unbounded hierarchy.** Remove `structure.max_depth: 2` from `content/collections/clinical_expertise.yaml` (currently `max_depth:2` at line 27, `root:false`), keep `structure.root: false`. This matches `knowledge_bases.yaml` for *depth* (both unbounded, `root:false`) — but **not** for the route line: KB uses the custom `{mount_prefix}` token whereas AoE uses native `{mount}` (depth-gated, §5), so do not assume copying KB's `route:` line works for AoE. `{parent_uri}` then yields arbitrary depth.
- **D2 + D4 — Content pages are new pages-derived blueprints living *in* `clinical_expertise`.** The WP "simple content" descendant page is **not** a revived discriminator. Author new content-page blueprint(s) (basic-page + landing-page) inside `resources/blueprints/collections/clinical_expertise/` that `import:` the same fieldsets as `pages/{basic_page,landing_page}.yaml` — exactly as `knowledge_bases/` carries three blueprints in one collection.
- **D4 — Validation = clone the knowledge_bases pattern.** New `ValidateClinicalExpertiseEntry` (EntrySaving) + `ValidateClinicalExpertiseTree` (CollectionTreeSaving, **keep the `import:wordpress` bypass**, **drop all absolute-depth assertions**), registered in `EventServiceProvider`. Rules: root → only `expertise`; an `expertise` entry → root OR child of `expertise`; a content-page → child of `expertise` OR another content-page, **never root**.
- **D5 — Whole-collection native mount to one pages entry.** The *entire* `clinical_expertise` collection mounts to ONE `pages` entry via Statamic's **native** collection-level `mount:` — not per-root mounting like KB. AoE correctly avoids KB's *per-root* `KnowledgeBaseEntry::routeData()`/`mount_prefix` machinery — but it does **not** avoid mount handling entirely: native `{mount}` resolves for *every* entry at *every* depth (`vendor/statamic/cms/src/Entries/Entry.php:993`), while `{parent_uri}` already contains the recursively-built parent URI (which itself already includes the mount), so an **unconditional** `{mount}` token duplicates the mount path at each nested level. The route must therefore **depth-gate** the mount token: `route: '{{ depth == 1 ?= mount }}/{{ parent_uri }}/{{ slug }}'` (mount emitted only at depth 1), or a small custom `routeData()` that zeroes the mount token below the root (single-mount analog of KB's `mount_prefix`). (Named residuals: *which* pages entry / base path, plus testing the depth-gated route and whole-collection mount-rename re-stamping — see §5.)
- **D3 — Schema is already native.** Reconcile the WP/Google-Doc backlog against the existing subsystem; report only genuine remaining gaps. Do **not** port WP schema PHP.

---

## Data model, blueprints & hierarchy

The data model is the most-finished surface. `clinical_expertise/expertise.yaml` already stages every relationship and the per-section override fields; the only structural change is D1's `max_depth` removal. The net-new work is (a) authoring the D2 content-page blueprint(s), (b) the D4 validation listeners (covered in §12), and (c) a descendant-children scope cloned from KB. One real defect: the `associated` field wires `exclude_siblings`, but `app/Scopes/ExcludeSiblings.php` is a **no-op stub** (its only body line is a commented `// $query->where(...)`) — it advertises a constraint it does not enforce.

| WP mechanism | Statamic equivalent | Exists | Gap | Effort | Recommendation |
|---|---|---|---|---|---|
| FD20-3256 unbounded nesting via `post_parent` | Native structured depth — drop `max_depth` (D1), model on `knowledge_bases.yaml` | partial | `max_depth:2` still in primary config (line 27) | XS | Remove `max_depth`, keep `root:false` |
| FD20-3256 "AoE Ontology Item" (subsite root) | Existing `expertise.yaml` as root blueprint (analog of `knowledge_base.yaml`) | yes | — | XS | Keep as-is; "is an AoE" = blueprint, no flag |
| FD20-3256 "AoE Content Item" (`expertise_type=0`) | New content-page blueprint(s) in `clinical_expertise/` reusing `pages/{basic_page,landing_page}.yaml` fieldsets (D2) | no | Two new blueprint files | M | Copy `pages` structure, `import:` same fieldsets, set `clinical_expertise/*` template defaults |
| FD20-3256 conditionally-filtered parent + EntrySaving validation | Clone `ValidateKnowledgeBaseEntry` (see §12) | no | New listener + registration | M | Retarget to `clinical_expertise`, encode D4 rules |
| FD20-3256 deep-descendant exclusion (`associated`) | `associated` field + `exclude_self`/`exclude_descendants`/`exclude_siblings` scopes | partial | **`ExcludeSiblings` is a no-op stub** | S | Implement it, or drop it from the field — real defect |
| FD20-3256 child-AoEs derived from hierarchy (`get_pages child_of`) | Children scope cloned from `KnowledgeBaseArticleChildren` (`where('parent',id)`, blueprint=expertise) | partial | No AoE children scope yet | S | Add it; aggregation depth is fork §13.2 |
| FD20-3862 staged `*_fpage_*` override fields | `ontology_fpage_*` shared fieldset, imported per-tab | yes | Consumers (templates) unbuilt — separate domain | XS | Leave staged; "Section or Subpage" labels are model-agnostic |
| renames `post_excerpt`→`excerpt`, `youtube_link`→`video_url`; `physician_expertise`→people | Blueprint handle edits + merge-time content-key sweep | partial | Still `post_excerpt` (line 18), `youtube_link` (line 86); `physician_expertise` (line 100) on legacy providers | S | Apply renames, grep references; retarget to `people` (coordinate UAMS-Web/uams-statamic-archive#919, wordpress-importer#31) |
| FD20-3256 schema clones | `expertise.yaml` schema tab + hidden computed `brand_organizations`/`regions` | yes | — | XS | No change; reconciliation is D3 (§10) |

---

## Routing, mount & permalinks

`clinical_expertise` has **no `route:` and no `mount:` today** — AoE entries are not addressable at all. This is the gap KB fills, but D5 is **not** a turnkey "native mount just works" path — it has a real depth-trap and an unproven re-stamping case. Statamic core does resolve a native collection `mount:` to a live entry URI (`vendor/statamic/cms/src/Entries/Collection.php:274-282`), always exposes a `{mount}` route token (`vendor/statamic/cms/src/Entries/Entry.php:993`), chains `parent_uri` recursively for unbounded depth, and runs `URL::tidy()` (`vendor/statamic/cms/src/Routing/UrlBuilder.php:60-63`) so empty tokens collapse cleanly.

The critical correction: the native `{mount}` token is **non-empty for every entry at every depth**, and `{parent_uri}` (`vendor/statamic/cms/src/Structures/Page.php:224`) already contains the **recursively-built** parent URI — which itself already includes the mount. So an **unconditional** `route: '{mount}/{parent_uri}/{slug}'` produces `{mount}/{mount}/{root-slug}/{slug}` at depth 2 (mount twice), `{mount}` thrice at depth 3, and so on. KB sidesteps exactly this by emitting its `mount_prefix` only on the root blueprint and an empty string for non-root blueprints (`app/Entries/KnowledgeBaseEntry.php` `knowledgeBaseMountPrefix()`). Statamic's own docs prescribe the **depth-gated** form for nested mounts: `route: '{{ depth == 1 ?= mount }}/{{ parent_uri }}/{{ slug }}'`. Use that (Antlers form, mount emitted only at depth 1), or a small custom `routeData()` that zeroes the mount token below the root.

No collection in this build uses the `{mount}` token at all (grep `'{mount}'` across `content/collections/*.yaml` → zero matches; `content/collections/pages.yaml` nests with `route: '{parent_uri}/{slug}'` and **no** mount token despite nesting). So AoE will be the build's **first** native-`{mount}` consumer *and* the first to combine native mount with structural nesting. Three things are consequently unproven here and gate the routing foundation: (a) the depth-gated route form itself; (b) whole-collection mount-rename URI re-stamping (KB solved *per-root* re-stamping with `KnowledgeBaseMountUriRefresher`; the whole-collection case is different and unproven — and KB's existence proves core does *not* fully auto-handle this for KB's case); and (c) `URL::tidy()` collapse of the depth-gated empty token. **A feature test pinning the depth-gated route shape and a mount-rename re-stamp is required before ship**, not optional. KB's `KnowledgeBaseEntry`, `KnowledgeBaseMountUriRefresher`, and `KnowledgeBaseMountRedirector` exist **only** because KB mounts each root separately — AoE deliberately does not clone the per-root machinery (D5), but it still needs the depth-gate.

| WP mechanism | Statamic equivalent | Exists | Gap | Effort | Recommendation |
|---|---|---|---|---|---|
| FD20-3260 multi-level permalink / "infinite subdirectories" (ROOT BLOCKER) | Native `parent_uri` chaining (`Page.php:224,230`) | yes | Not wired to `clinical_expertise` yet | XS | Add depth-gated `route: '{{ depth == 1 ?= mount }}/{{ parent_uri }}/{{ slug }}'`, remove `max_depth` — blocker is moot |
| FD20-2671 single-base permalink namespace (`/expertise/...`) | Native collection `mount:` + depth-gated `{mount}` token | partial | No collection uses native mount yet; base entry unchosen; depth-gate unproven here | S | `mount: <pages-entry-id>` (which entry = fork §13.3) + depth-gated route + feature test |
| KB per-root `{mount_prefix}` via custom `routeData()` | **Per-root machinery deliberately not cloned** (but depth-gate still required) | partial | AoE needs a depth-gate (Antlers or small `routeData()`), just not KB's per-root mount_prefix | XS | Do NOT port `KnowledgeBaseEntry`/per-root mount_prefix; DO depth-gate the single mount |
| KB legacy-URL 301 (`KnowledgeBaseMountRedirector`) | None needed — AoE had no prior un-mounted Statamic URL | yes | — | XS | WP `/expertise/...` 301s = one-time launch redirect-map, not a runtime service |
| Content-page subpages nested under AoE | Same collection route (`parent_uri` is blueprint-agnostic) | no | Route line + blueprints (other domains) | XS | One `route:` covers roots, nested AoEs, and content pages |
| Mount-rename URI re-stamping (KB `KnowledgeBaseMountUriRefresher`, per-root) | Core resolves `Collection::uri()` live + flushes `collection-mounts` Blink on save (`Entry.php:438`); whole-collection single-mount case unproven | partial | KB's refresher proves core does not fully auto-handle the per-root case; whole-collection case untested here | S | Verify against core for the single-mount case; **required** feature test that moving the mount-parent restamps AoE subtree URIs |
| Importer must not be blocked by structure rules | Routing is passive; bypass lives in validation (§12) | partial | Confirm importer sets structure parentage | XS | Once `mount:`+depth-gated `route:` set, structure placement determines nested URL |

---

## Fake subpages (rendering)

This is **the one genuinely open architectural fork** — D1–D5 settle depth, validation, content-page blueprints, and collection mounting, but none of them pick how the per-AoE relationship-derived sections (Providers / Locations / Specialties=child-AoEs / Related AoEs / Clinical Resources) render. The data substrate is fully staged and deliberately model-agnostic: the override fields' CP displays literally read "...Section **or** Subpage." The rendering itself is unbuilt — `clinical_expertise/show.antlers.html` is a 4-line placeholder stub, no relationship field is read anywhere, and there is no subpage routing.

The load-bearing routing fact: Statamic's front end is a catch-all that resolves a URL only to a real entry URI in the Stache (`vendor/statamic/cms/src/Http/Controllers/FrontendController.php`), so a fixed `/providers` suffix under an arbitrarily-nested AoE is **not** routable unless either a real entry lives there (option a) or a custom Laravel route catches it (option b). `Route::statamic()` (`vendor/statamic/cms/src/Mixins/Router.php`) accepts full `->where()` regex constraints, and app routes are expected to win over the catch-all — so a *single* `Route::statamic('{aoe}/{fpage}', ...)->where('aoe','.*')->where('fpage','providers|locations|...')` would serve every section at every depth. **Caveat:** this routing-precedence assertion rests on the `Route::statamic` mixin behavior cited from vendor but was **not** exercised against a live app boot in this (un-bootstrapped) build — treat fork §13.1 option (b)'s "door is clean" as plausible-but-unproven, and verify with a routing test when (b) is built.

The full compare/contrast lives in §13.1. The relationship sources are already modeled; the "specialties/descendant" section is just structure-tree children rendered like KB's `_topic_list.antlers.html`.

| WP mechanism | Statamic equivalent | Exists | Gap | Effort | Recommendation |
|---|---|---|---|---|---|
| FD20-3261 fake-subpage dispatch (`fpage` query var + thin templates) | The fork: (a) real child entries, (b) routed `/{fpage}` view, (c) in-page sections, (d) hybrid | no | Entire render + (a/b) routing layer; `show` is a 4-line stub | L | Resolve fork §13.1 |
| FD20-3260/3261 deep `/.../providers/` permalink (ROOT BLOCKER) | Native nesting + one `.*`-wildcard Laravel rule covers all depths | partial | depth-gated `route:` + D1 needed; custom route unwritten; precedence unverified here | XS | Blocker is moot once routing built; verify precedence in a test |
| FD20-3261 per-section override fields | Staged `ontology_fpage_*` fields (`uamsweb_common_fad.yaml:299-345`) | yes | Consuming template only | XS | Read directly; no variable-variables |
| FD20-3261 per-section relationship lists | `physician_expertise`, `related_locations`, `associated`, `clinical_resources` + structure children | partial | List Antlers unwritten | M | Render lists from fields; "specialties" from structure children (model on `_topic_list`) |
| FD20-3262 header forcing (`graphic` on subpages) | Existing swappable header parts | no | Per-render-model header undesigned | S | Defer to whichever model wins |
| FD20-3307 search of overview + virtual subpages | `search_index: clinical_expertise` indexes real entries; virtual subpages need synthetic docs | partial | Virtual subpages not auto-indexed | M | Real entries (a/d) index free; (b/c) need synthetic docs — scoped follow-up |
| D4 validation of any new subpage blueprint | EntrySaving + tree listeners (§12) | partial | Only (a)/(d) add a subpage blueprint to validate | S | (b)/(c) add no fpage blueprint; basic/landing pages validated regardless |

---

## Subsite chrome

The chrome scaffolding is layered and already has AoE-shaped stubs. Two distinct "subsite" axes exist and must not be conflated: the existing per-**Statamic-site** subbrand/breadcrumb machinery (driven by `app/Tags/GlobalOrigin.php` over `content/globals/uams_multisite_settings.yaml`) is the *wrong* granularity for AoE — AoE is an intra-collection structure axis within the `health` site. But the chrome already carries the right hook: `resources/views/layout/header/navbar/_brand.antlers.html` has a `$subsection_query=false` flag (confirmed present) feeding `_subbrand.antlers.html` (which already renders the parent-link + title pair AoE needs), with explicit TODO comments marking the AoE plug-in point. The single load-bearing primitive to build is a **"nearest expertise ancestor" computed** (skipping content-page ancestors) — build once, consume four times (subbrand, primary nav, breadcrumb composition, header suppression).

| WP mechanism | Statamic equivalent | Exists | Gap | Effort | Recommendation |
|---|---|---|---|---|---|
| FD20-3257 AoE site header (subbrand swap) | Activate the stubbed `$subsection_query` → `_subbrand.antlers.html` (both under `layout/header/navbar/`) | partial | Hook hard-disabled; needs ancestor resolver + nearest==farthest suppression | M | Reuse `_subbrand` markup verbatim; feed from computed nearest/top ancestor |
| FD20-3258 AoE primary nav (Overview + populated sections) | New `navigation/expertise/_primary.antlers.html` modeled on `desktop.antlers.html` + `subpages/_subpages.antlers.html` | partial | No per-AoE nav; "populated section" gating | L | Drive off a computed sections list; replace global nav (fork §13.5) |
| FD20-3259 AoE breadcrumbs (never built in WP) | Core `{{ nav:breadcrumbs }}` walks structure ancestors natively | yes | Archive-label crumb has no home; verify site-ancestor composition | S | Rely on core (KB precedent); Statamic is *ahead* of WP here |
| FD20-3262 page-header forcing | Existing swappable header parts; `clinical_expertise/show` already in `$landing_page_templates` (`layout.antlers.html:141`) | partial | Per-blueprint default/forcing only | M | Set header-style defaults per blueprint; reuse parts as-is |
| FD20-3260 microsite permalink namespace | Native nested route + mount (§5) | partial | No `route:`/`mount:` yet | S | Routing-domain deliverable; chrome consumes `url`/`permalink` |
| WP `site_nav_id` (resolve "which AoE am I in") | Computed nearest-expertise-ancestor (model on `GlobalOrigin.php` walk + `nav:breadcrumbs`) | no | No such computed | M | **The load-bearing primitive** — build once, consume four times |

---

## Blocks & page builder

Statamic has no separate "allowed-blocks filter" — the set list in the `page_builder` fieldset a blueprint `import:`s **is** the whitelist (FD20-3263, native and code-free). The four FaD blocks already exist with AoE filters (FD20-3314, done). The critical current gap: `expertise.yaml`'s `page_builder` field resolves to `uamsweb_common_fad.page_builder`, which is an alias of the plain Bard `uamsweb_common.article` (rich text only) — so **AoE has no marketing-block palette at all today**. The Appointment-CTA block body exists (`uamsweb_block_cta.yaml`); only its inheritance behavior is unbuilt.

| WP mechanism | Statamic equivalent | Exists | Gap | Effort | Recommendation |
|---|---|---|---|---|---|
| FD20-3263 `allowed_block_types_all` 15-slug whitelist | Per-blueprint set list = the whitelist (`import: page_builder`, see `pages/landing_page.yaml`) | partial | AoE imports plain Bard, not the Replicator palette | S | Give AoE + content pages a Replicator `page_builder` field; reuse `page_builder.yaml` (curate per fork §13.4) |
| WP 13 theme block slugs | 1:1 sets already in `page_builder.yaml` | yes | `uams-section` has no exact 1:1 (≈`content`/`stacked`) | XS | Map directly; treat `uams-section` as covered |
| FD20-3314 FaD Locations/Providers on AoE | Four `uamsweb_block_fad_*.yaml` with `expertise` filter, in `uams_health` group | yes | Only reaches AoE once palette wired | XS | Include `uams_health`; default the filter to current AoE |
| FD20-3264 Appointment CTA bar | `uamsweb_block_cta.yaml` (full phone/sms/email machinery) + `cta_bars` | partial | Inheritance (auto-append ancestor CTA) unbuilt | M | Reuse `uamsweb_block_cta`; inheritance = computed property (fork §13.6) |
| FD20-3315 block linking to AoE subpages | New set modeled on `kb_topics`/`link_list` over the AoE subtree | no | New set + renderer | M | Build one set; targets are mostly real child entries now |
| FD20-3316 related conditions/treatments listing | Existing `conditions`/`treatments` relationship fields | no | Render unbuilt; C&T demoted to keywords | S | Lean to a computed section, not a block (fork §13.7) |
| FD20-3313 force minimum blocks on AoE | Native `min_sets`/`default`/`required` + D4 listener — *per blueprint* | partial | No `min_sets`/forced-set logic | M | Encode on the AoE palette only; WP per-meta blocker vanishes |
| D2/D4 content-page blueprints | Clone `pages/{basic_page,landing_page}.yaml` into `clinical_expertise/` | no | Blueprints don't exist yet | M | basic → `article_with_sets`; landing → `import: page_builder` |

---

## Conditions & Treatments

The C&T demotion epic (FD20-3267/3269) is **structurally almost done** — both collections (`clinical_conditions`, `clinical_services`) have **no `route:` and no `mount:`** (verified: `routes()`=>NULL, `mount()`=>NULL), so C&T singles already have no public URL and nothing to disable. (Their routeless+mountless siblings `clinical_resources` and `clinical_locations` are likewise un-addressable today; `people.yaml` is the deliberate exception, carrying `route: 'people/{slug}'`.) Because the related collections are routeless, C&T can *only* render as plain title text — FD20-3269's plain-text rendering is structurally guaranteed. The WP-admin-chrome suppression that *blocked* the WP ticket (hiding Gutenberg/SEOPress/permalink panels) is moot in the Statamic CP. The full schema.org subtype matrix is already built natively on both blueprints. The `searchable` toggle exists but is not yet consumed.

| WP mechanism | Statamic equivalent | Exists | Gap | Effort | Recommendation |
|---|---|---|---|---|---|
| FD20-3267 disable C&T singles/archives | No `route:`/`mount:` → no URL | yes | Vestigial `template:`/`preview_targets` lines | XS | Document as intentional; optionally remove dead lines |
| FD20-3267 301 singles/archives → home + UTM | Peak SEO `peak_seo_find_redirect_rule` + Redirects global | partial | No live Statamic URL to redirect *from*; legacy WP URL map unbuilt | S | Don't port runtime `template_redirect`; populate Redirects global at launch (fork §13.8) |
| FD20-3267 admin-chrome suppression (WP blocker) | N/A — Statamic CP is blueprint-driven | yes | — | XS | Moot, resolved by platform |
| FD20-3267 `searchable` toggle | Native field `condition.yaml:29`/`treatment.yaml:29` | partial | Field exists, no consuming search logic | S | Wire into the index/related-results query |
| FD20-3269 render C&T as plain text | Antlers over `conditions`/`treatments` entries, title only | partial | AoE show-template body unbuilt | S | Render `{{ title }}` only (no `{{ url }}`); model on `_topic_list` |
| FD20-3477 related clinical research | Would need a research source + AoE relationship + schema | no | No collection/relationship/emission | L | Defer as research-spike; prefer `clinical_resources` + research subtype (fork §13.9) |
| C&T schema.org subtype matrix | Already built (`condition.yaml:131-262`; 69 `schema_*` in `treatment.yaml`) | yes | — | XS | Out of demotion scope; D3 |

---

## Schema.org (reconciled — mostly already solved)

The "schema is moot" framing is **wrong**. The build carries a mature, natively-rebuilt JSON-LD subsystem that already satisfies most of the Google-Doc backlog and *all* of its hardest infrastructure (dedup, emit-once-reference-by-`@id`, cascade, caching/warm). The entire vocabulary is generated from schema.org (every backlog-named property is grep-verified as a case; the exact totals are approximately 924 types / 1549 properties / 535 enum members and should be treated as approximate, not pinned). The AoE schema is already authored on two `schema_objects` entries and wired via inject + the global `{{ schema_ld_json }}` tag (`layout.antlers.html:188`). Per D3, the work is *reconciliation*, not porting WP PHP — and the genuine remaining gaps are few and small.

| WP / Google-Doc backlog item | Statamic equivalent | Exists | Gap | Effort | Recommendation |
|---|---|---|---|---|---|
| Node dedup, emit-once + `@id` reference, nested scanning | `SchemaJsonLdAtIdDeduplication.php`, `SchemaJsonLdSchemaObjectEmissionCoordinator.php`, `inflateBareAtIdReferences...()` | yes | — | XS | Done, more robustly than WP |
| Transients for repeated schema fns | `SchemaJsonLdOutputCache.php` + fragment cache + warm collector | yes | — | XS | Done; superseded by Laravel cache |
| AoE schema applied + system fallbacks | `{{ schema_ld_json }}` (cascade) + wired `webpage_entry`/`main_content_entry` slots + 2 authored entries | yes | Verify image/text fallback when fields blank | S | Verify fallbacks; otherwise complete |
| AoE `text`/`timeRequired` | Computed from page_builder Bard output | yes(infra) | Word-count/plain-text extraction unwired | M | Add computed (model on `PostsReadingMetricsCollectionComputedsRegistrar`); MVP? |
| speakable / cssSelector | `SchemaPronounceableText` **Tag** (`app/Tags/`) + `CssSelector` **fieldtype** (`app/Fieldtypes/`) + `speakable` authored | partial | Front-end blocks don't yet wrap eligible text in id'd elements | M | Add id'd wrappers — frontend ticket, not schema-engine |
| Structured name inputs | `PersonName.php` fieldtype (+`Name`,`TextPhoneticPair`) | yes | people-domain adoption (UAMS-Web/uams-statamic-archive#919/UAMS-Web/uams-statamic-archive#31) | XS | Built; out of AoE scope |
| All identifier sub-properties | `Identifier.php` fieldtype presets (npi/duns/gln/isic/iso6523/lei/naics/tin/...) | yes | FD20-3482 relevance is content/policy | XS | No engine work |
| AoE bug: derive `brand_organizations` from `related_locations` | Computed registrar (provider analog `ProvidersPeopleSharedCollectionComputedsRegistrar`) | partial | No expertise-specific registrar confirmed | S | Add `ClinicalExpertiseBrandOrganizationsComputedsRegistrar` — genuine AoE work |
| FD20-3482 policy properties (areaServed, paymentAccepted, review, ...) | All cases exist; emission = authoring values | partial | Content/stakeholder decisions, not engine gaps | S | Don't build speculatively; content backlog |

Sibling-domain follow-ups (location ContactPoint/`availableLanguage`/telemedicine `department`, condition/treatment subtype picker filters, clinical-resource per-asset CreativeWork + video API enrichment) are real but belong to their own collections' schema tickets, not the AoE epic.

---

## Search, settings & globals

The surprise here: **FaD Settings (FD20-3255) is almost entirely built** as the `uams_fad_settings` global (~3449-line blueprint, `clinical_areas_of_expertise` tab at line 1341, scoped to `health`), while **site search (FD20-3307/3308) is essentially unbuilt stubs**. The substitution-token *convention* (`[Provider]`, `[Area of Expertise Title]`) is already baked into the staged field defaults — but no interpolation engine exists. And there is a **real, verified search-index bug**: `config/statamic/search.php:115-116` indexes `alternate_name` (singular) for `clinical_expertise`, but the blueprint handle is `alternate_names` (plural, `expertise.yaml:72`). Statamic passes the transformer the value of the *named* field, so `AlternateNameTransformer` receives `null` for the non-existent singular key — AoE alternate names are **not indexed today**.

| WP mechanism | Statamic equivalent | Exists | Gap | Effort | Recommendation |
|---|---|---|---|---|---|
| FD20-3255 editable FaD labels/intros + fake-subpage refs | `uams_fad_settings` global (`clinical_areas_of_expertise` tab, line 1341) | yes | — | XS | Done; only wiring consumers remains |
| FD20-3255 `[token]` substitution syntax | New `fad_tokens` modifier/tag over globals + ancestor/contact data | partial | Convention staged; no interpolation code | M | Build one modifier (`app/Modifiers/`); escape substituted entry values |
| FD20-3255 blank-label fallback | Blueprint `default:` (already set) | yes | — | XS | Rely on defaults; computed only if *derived* |
| FD20-3254 archive search + alternate names + `searchable` | `search.php` indexes + `AlternateNameTransformer` | partial | `searchable` not consumed; archive UI unbuilt | M | Add filter honoring `searchable` |
| FD20-3254 AoE alternate-name matching | `clinical_expertise` index + transformer | partial | **Bug: `alternate_name` (search.php:115-116) vs handle `alternate_names` (expertise.yaml:72) → transformer gets null** | XS | Fix `search.php` to `alternate_names`, reindex, add regression test |
| FD20-3307 global site search (overview + subpages) | New `/search/results` route + `{{ search:results }}` | no | No results route/template (only `_search-tray` stub) | L | Build it (shared with KB). Reframe: D2/D4 content pages are *real* entries → index natively |
| FD20-3308 faceted subpage filtering | `{{ collection }}` + query-param filters on AoE templates | no | No facet UI; templates are stubs | L | Downstream of subpage rendering; its own ticket |
| FD20-3255 site-scoped settings | Global `sites: { health }` | yes | — | XS | No action |

---

## Structural validation (clone of knowledge_bases)

D4 is a faithful clone-and-retarget of the KB validation machinery, with one critical adaptation: **drop every absolute-depth assertion** (KB asserts kb==1/topic>=2/article>=3; AoE is unbounded under D1) and keep only the parent-blueprint checks. A verified nuance to honor: **no KB blueprint has a `handle: parent` field** — the structural parent comes from the CP structure tree, not a blueprint field. The `valid_*_parent` scopes constrain *reference* pickers (e.g. `article.yaml`'s `secondary_topic`), not the structure tree; the **two listeners are the real enforcement**. So the new parent scopes are *optional* UX unless a blueprint exposes an explicit `entries` parent/section reference field.

| WP / D4 piece | Statamic equivalent | Exists | Gap | Effort | Recommendation |
|---|---|---|---|---|---|
| D4 entry-save enforcement | New `ValidateClinicalExpertiseEntry` (EntrySaving), cloned from `ValidateKnowledgeBaseEntry` | no | Rules differ: root→expertise-only; expertise→null/expertise; content-page→expertise/content-page, never root | S | Clone, swap handle to `clinical_expertise`, encode 3 rules; keep `Log::error`+`throw` shape |
| D4 tree-save enforcement + importer bypass | New `ValidateClinicalExpertiseTree` (CollectionTreeSaving), cloned from `ValidateKnowledgeBaseTree` | no | **Must drop all `$depth` assertions** (D1 unbounded); keep parent-blueprint checks | S | Clone, **keep `import:wordpress` bypass verbatim** + static dedupe |
| D4 valid-parent picker UX (expertise) | New `ValidExpertiseParent` scope (`where('blueprint','expertise')`) | no | Optional — only if a reference field needs it | XS | Build only if a blueprint exposes an `entries` parent field; don't manufacture one |
| D4 valid-parent picker UX (content-page) | New `ValidContentPageParent` (`whereIn(['expertise', <content handles>])`) | no | Optional; content-page handles TBD (D2) | XS | Same; or fold into existing `EntriesByBlueprint` |
| D4 registration | Append both to `EventServiceProvider::$listen` | no | Classes don't exist yet | XS | Co-locate after the KB listeners |
| D1 coordination | Remove `max_depth` so the un-asserted validator matches reality | partial | `max_depth:2` still present | XS | Land validator + `max_depth` removal together |
| D4 acceptance tests | New Pest tests cloned from KB validation coverage | no | None exist | S | Cover deep nesting passes, content-at-root/expertise-under-content reject, import bypass; mutation-verify |

---

## Design forks to decide

D1–D5 are settled and are **not** forks. The genuine remaining decisions, recommended option first:

### 13.1 — How do per-AoE fake subpages render? *(the big one)*

| Dimension | **(b) Routed `/{fpage}` view** (chosen) | (c) In-page sections | (d) Hybrid | (a) Real child entries |
|---|---|---|---|---|
| Distinct SEO URLs | yes | no | yes (when promoted) | yes |
| Routing work | one `.*`+enum Laravel rule (precedence unverified here) | none | both paths | none (native) |
| New entries to author | none | none | none | five per AoE |
| Search (FD20-3307) | synthetic docs needed | one page only | synthetic or real | native |
| Tree/CP pollution | none | none | none | heavy |
| Fidelity to WP intent | high | low (loses URLs) | highest | high but contradicts WP ("not real posts") |

**Recommendation: (b) routed-suffix-view, layered toward (d).** A single `Route::statamic('{aoe}/{fpage}', ...)->where('aoe','.*')->where('fpage','providers|locations|...')` serves every section at every depth with no per-section entries and no new blueprint — keeping the data model exactly as staged ("Section or Subpage"). It preserves the WP virtual-subsite SEO intent that (c) abandons, without (a)'s authoring burden and tree pollution. The one real cost — virtual pages aren't auto-indexed (FD20-3307) — is a scoped follow-up (synthetic search docs), and specific sections can graduate to (d)/real entries later without a schema change. The one open verification before committing to (b): confirm `Route::statamic` precedence over the Statamic catch-all with a routing test (asserted from `vendor/statamic/cms/src/Mixins/Router.php` but not exercised in this un-bootstrapped build). *(Note: §13.5's chrome analysis leans (c) in-page anchors; these are the same fork seen from two domains — the owner picks one render model. (b) gives per-section URLs; (c) is simpler. Recommend (b) for SEO parity, accepting the search follow-up and the precedence test.)*

### 13.2 — Descendant aggregation: immediate children vs. all descendants (FD20-3309)

| Dimension | **Immediate children only** (chosen) | Aggregate all descendants |
|---|---|---|
| Clone of KB scope | yes (`where('parent',id)`) | no — needs recursion |
| Cost/determinism | cheap, deterministic | heavier, dedupe needed |
| Top-level richness | may under-show | "rolls up everything" |

**Recommendation: immediate children only** as the base scope default. It is the direct KB clone, deterministic, and shippable without resolving the larger per-section product question — which interacts with FD20-3312 and should be decided when subpage rendering is built. `ContentDescendantIds` is already available if aggregation is later chosen for specific sections.

### 13.3 — Which pages entry / base path does `clinical_expertise` mount to? (D5 residual)

| Dimension | **New dedicated landing** (chosen) | Existing top-level page | Site root |
|---|---|---|---|
| Canonical stability | stable | couples to unrelated lifecycle | volatile |
| Editor index page | yes | maybe | none |
| Slug-collision risk | low | low | high |

**Recommendation: a new dedicated landing page** (e.g. `/medical-services` or `/areas-of-expertise`) — stable base, editor-managed AoE index, mirrors WP's `/expertise/` intent. Slug/title is the owner's content call; native `mount: <id>` works identically for any entry (with the depth-gated route from §5).

### 13.4 — Child-AoE permalink shape: flat vs. section-nested (WP epic-undecided)

| Dimension | **Flat depth-gated `{mount}/{parent_uri}/{slug}`** (chosen) | Section-nested (insert `/specialties/`) |
|---|---|---|
| Native support | yes, depth-gated mount only | no — needs virtual segment |
| URL length / SEO | shorter, cleaner | longer |
| Consistency | matches pages/KB | reintroduces removed complexity |

**Recommendation: flat nesting.** The WP `/specialties/` segment was an artifact of rewrite limits, not deliberate IA; the Statamic rebuild that dissolves those limits need not reproduce it.

### 13.5 — Per-AoE primary nav: replace global nav vs. second bar

| Dimension | **Replace global nav** (chosen) | Second bar below global |
|---|---|---|
| Microsite feel | strong (matches WP `remove_action`) | weaker |
| Path back to site | subbrand link + breadcrumbs | always present |
| Implementation | one-line conditional swap in `_header` | additive |

**Recommendation: replace the global main nav** with the AoE nav (WP fully swapped it); the subbrand parent link + site-ancestor breadcrumbs preserve a path home. Reversible if stakeholders later prefer dual nav.

### 13.6 — Appointment CTA inheritance: auto-append vs. author-placed

**Recommendation: auto-append a computed ancestor CTA** (with a per-entry opt-out), reusing `uamsweb_block_cta` as the rendered shape. Inheritance is a computed/render concern (model on `KnowledgeBaseComputedFieldsService`), not a new block — it delivers WP's signature guaranteed appointment bar and dissolves WP's unsolved locked-vs-meta problem.

### 13.7 — Conditions/Treatments listing: computed section vs. block

**Recommendation: an automatic computed section** rendered from the existing `conditions`/`treatments` fields, not a new block. FD20-3316's "block" role shrank when C&T were demoted to keywords; placement flexibility is unnecessary.

### 13.8 — Legacy WP C&T URL redirects: build a map vs. let them 404

**Recommendation: build a Peak SEO redirect map** (legacy `/condition/`, `/treatment-procedure/` + archives → home/relevant AoE, UTM on target). It honors FD20-3267's intent via the already-installed mechanism; it's a launch/content task, not a code port. Do not replicate WP's runtime `template_redirect`.

### 13.9 — FD20-3477 clinical research: defer vs. spike now

**Recommendation: defer as a separate research-spike.** It is additive (not demotive) and "never started" in WP. If pursued, prefer reusing `clinical_resources` with a research `document_subtype` (it already carries `conditions`/`treatments`/`aoe` relationships) over a new collection. Don't port WP schema PHP.

### 13.10 — Should AoE entries carry their own `searchable` toggle?

**Recommendation: add `searchable` to `expertise.yaml`** mirroring conditions/treatments, gating archive + site search. The WP epic lists a not-searchable flag as desired (plan line 134), and parent/organizational AoEs are exactly the kind an editor wants indexed for SEO but excluded from interactive returns. Pin the exact surfaces it gates when the search filter is built.

### 13.11 — Legacy non-conforming `clinical_expertise` tree at first CP save (D4)

**Recommendation: match KB exactly — hard-throw + `import:wordpress` bypass.** The bypass already prevents the importer from being blocked; the only exposure is the first human tree-save on dirty data, and imported AoE content is purged after each test-import cycle (the real migration is a separate Launch op). If it ever bites a real editor, add a one-time normalization command as a follow-up rather than weakening the invariant now.

---

## Suggested sequencing

1. **Foundation (XS→S, unblocks everything).** Remove `structure.max_depth` (D1); add the **depth-gated** `route: '{{ depth == 1 ?= mount }}/{{ parent_uri }}/{{ slug }}'` + `mount: <pages-entry-id>` after fork §13.3; **write the required feature test** pinning the depth-gated route shape across depths and a mount-rename re-stamp (§5); fix the `alternate_name`→`alternate_names` search bug (`search.php:115-116`) + regression test. Decide forks §13.3, §13.4.
2. **Blueprints + validation (M, the structural core).** Author the D2 content-page blueprint(s) (decide the palette fork §13.4 here); clone the D4 listeners + scopes (§12), land them *with* the `max_depth` removal; write the Pest validation suite + mutation-verify. Add the AoE children scope (§13.2 default: immediate-only). Fix the `ExcludeSiblings` defect.
3. **The big render fork (decide before templates).** Resolve §13.1, including verifying `Route::statamic` precedence if (b) wins. This gates routing detail, templates, chrome nav, search scope, and facets.
4. **Chrome primitive + chrome (M→L).** Build the nearest-expertise-ancestor computed (the load-bearing primitive), then wire subbrand (FD20-3257), primary nav (FD20-3258, fork §13.5), breadcrumb composition (FD20-3259, mostly free), and header defaults (FD20-3262).
5. **Blocks + page builder (M).** Give AoE/content-page blueprints the Replicator palette (fork §13.4); encode minimum-blocks (FD20-3313); the subpage-link block (FD20-3315) and CTA inheritance (fork §13.6); C&T computed section (fork §13.7).
6. **Search + settings consumers (M→L).** Build `/search/results` (shared with KB, FD20-3307); honor `searchable` (fork §13.10); the `fad_tokens` substitution modifier (FD20-3255); facets after subpage templates (FD20-3308).
7. **Schema reconciliation gaps (S→M).** Add `ClinicalExpertiseBrandOrganizationsComputedsRegistrar`; verify fallbacks; AoE `text`/`timeRequired` computed (if MVP); speakable id'd wrappers (frontend, gated behind block rendering).
8. **Launch tasks.** C&T legacy redirect map (fork §13.8); WP `/expertise/...` 301 map. Defer FD20-3477 (fork §13.9) to a research-spike.

---

## Follow-up: sibling-collection mounting (D6)

Out of scope for this epic but parallel work to ticket separately. The **same** native collection-mount + nested-route technique validated for AoE (D5) applies to the other ontology collections, each mounting to its **own** `pages` entry — and each will need the **same depth-gated mount token** when nested (the unconditional `{mount}` double-prefix trap from §5 is not AoE-specific): `clinical_resources`, `clinical_locations`, `clinical_conditions`, `clinical_services` mount **wholesale** (`mount: <id>` + depth-gated `{{ depth == 1 ?= mount }}/{{ parent_uri }}/{{ slug }}`, or `{mount}/{slug}` if flat/single-level). None has a `mount:` or `route:` today (verified across `content/collections/*.yaml`; no collection uses the `{mount}` token at all). The `people` collection is the exception in two ways — it already carries `route: 'people/{slug}'`, and it additionally needs an **eligibility filter** before mounting: it is broader than clinical providers, so only the clinical-provider-profile-eligible subset surfaces under its `pages` entry (relates to UAMS-Web/uams-statamic-archive#919), unlike the wholesale-mount siblings.

Recommended: **one ticket per sibling collection** (four wholesale mounts) **plus a distinct `people`-eligibility ticket**. Each will recur the same chrome questions (per-collection subbrand? primary nav? breadcrumb composition?) and make those mounted siblings valid link targets for the FaD/subpage blocks. Do not design these here; flag the `people` eligibility wrinkle and the shared depth-gate requirement, and reuse the AoE mechanism wholesale.

---

## Appendix: full mapping table

Consolidated WP→Statamic rows across all domains (Exists: yes / partial / no).

| Domain | WP mechanism | Statamic equivalent | Exists | Effort |
|---|---|---|---|---|
| Data model | FD20-3256 unbounded nesting | Drop `max_depth` (D1) | partial | XS |
| Data model | AoE root item | `expertise.yaml` root blueprint | yes | XS |
| Data model | AoE content item | New content-page blueprints (D2) | no | M |
| Data model | Conditional parent validation | Clone `ValidateKnowledgeBaseEntry` | no | M |
| Data model | Deep-descendant exclusion (`associated`) | `exclude_*` scopes (siblings = stub) | partial | S |
| Data model | Child-AoEs from hierarchy | Children scope ex `KnowledgeBaseArticleChildren` | partial | S |
| Data model | `*_fpage_*` override fields | `ontology_fpage_*` fieldset | yes | XS |
| Data model | field renames + provider→people | Handle edits + key sweep | partial | S |
| Routing | FD20-3260 deep permalink (BLOCKER) | Native `parent_uri` | yes | XS |
| Routing | FD20-2671 single namespace | Native `mount:` + depth-gated `{mount}` | partial | S |
| Routing | Depth-gate the single mount token | `{{ depth == 1 ?= mount }}` (or small `routeData()`) | no | XS |
| Routing | KB per-root `mount_prefix` | Per-root machinery NOT cloned | partial | XS |
| Routing | KB legacy 301 | Not needed | yes | XS |
| Routing | Mount-rename re-stamp (whole-collection) | Core Blink flush; case unproven here | partial | S |
| Fake subpages | FD20-3261 dispatch | Render fork (b/c/a/d) | no | L |
| Fake subpages | Per-section override fields | Staged | yes | XS |
| Fake subpages | Relationship lists | Modeled; Antlers unwritten | partial | M |
| Fake subpages | FD20-3307 search | search_index / synthetic docs | partial | M |
| Chrome | FD20-3257 subbrand | Stubbed `$subsection_query` hook (`layout/header/navbar/`) | partial | M |
| Chrome | FD20-3258 primary nav | New `_primary.antlers.html` | partial | L |
| Chrome | FD20-3259 breadcrumbs | Core `{{ nav:breadcrumbs }}` | yes | S |
| Chrome | FD20-3262 page headers | Swappable header parts | partial | M |
| Chrome | `site_nav_id` resolver | Nearest-ancestor computed | no | M |
| Blocks | FD20-3263 whitelist | Per-blueprint set list | partial | S |
| Blocks | FD20-3314 FaD blocks on AoE | Four `fad_*` blocks | yes | XS |
| Blocks | FD20-3264 Appointment CTA | `uamsweb_block_cta` + inheritance computed | partial | M |
| Blocks | FD20-3315 subpage-link block | New set ex `kb_topics` | no | M |
| Blocks | FD20-3316 C&T listing | Computed section | no | S |
| Blocks | FD20-3313 minimum blocks | `min_sets`/`default`/D4 | partial | M |
| C&T | FD20-3267 disable singles | Routeless collections | yes | XS |
| C&T | FD20-3267 301 redirects | Peak SEO Redirects | partial | S |
| C&T | FD20-3267 admin-chrome | N/A (CP) | yes | XS |
| C&T | FD20-3269 plain text | Title-only Antlers | partial | S |
| C&T | FD20-3477 research | Defer / `clinical_resources` reuse | no | L |
| Schema | Dedup + `@id` + cache | Built natively | yes | XS |
| Schema | AoE schema + fallbacks | Authored + wired | yes | S |
| Schema | `text`/`timeRequired` | Computed from Bard | partial | M |
| Schema | speakable cssSelector | `SchemaPronounceableText` Tag + `CssSelector` fieldtype built; frontend wrappers | partial | M |
| Schema | brand_orgs from locations bug | New computed registrar | partial | S |
| Search/Settings | FD20-3255 FaD Settings | `uams_fad_settings` global | yes | XS |
| Search/Settings | FD20-3255 tokens | New substitution modifier | partial | M |
| Search/Settings | FD20-3254 AoE alt-name index | **Bug: singular `alternate_name` vs plural handle** | partial | XS |
| Search/Settings | FD20-3307 site search | New `/search/results` | no | L |
| Search/Settings | FD20-3308 facets | Query-param filters | no | L |
| Validation | D4 entry listener | Clone `ValidateKnowledgeBaseEntry` | no | S |
| Validation | D4 tree listener (drop depth) | Clone `ValidateKnowledgeBaseTree` | no | S |
| Validation | D4 parent scopes | `Valid{Expertise,ContentPage}Parent` | no | XS |
| Validation | D4 registration | `EventServiceProvider` | no | XS |
| Validation | D4 tests | New Pest suite + mutation | no | S |