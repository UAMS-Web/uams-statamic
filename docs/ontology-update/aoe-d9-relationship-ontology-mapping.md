# D9 Medical-Ontology Relationship Model — Statamic Mapping (AoE Port, Pass 2)

Companion to the [structure mapping](docs/ontology-update/aoe-virtual-subsite-statamic-mapping.md) (pass 1). Pass 1 mapped structure, routing, chrome, validation, and blocks. This pass maps directive **D9** — the team's newer medical-ontology relationship model, post-dating the [WordPress plan](docs/ontology-update/aoe-virtual-subsite-wordpress-plan.md) — onto the existing Statamic build. Audience: the project owner. Every claim is grounded in a real build path; `existsToday` is marked honestly (yes / partial / no).

## Decisions locked (2026-06-23)

All design forks in this document are resolved. This section is the authority; the "Design forks to decide" section below is retained for rationale but is **superseded**.

- **Relabel = display-only.** Keep handles (`clinical_expertise` / `clinical_conditions` / `clinical_services`); relabel to *Medical Specialties / Medical Conditions / Medical Services*.
- **Subtypes.** Required Service subtype (Procedure / Therapeutic / Test) + optional Condition subtype (InfectiousDisease / Sign / Symptom), schema-aligned (largely already present).
- **Relationship graph.** Each M2M edge = **single authored side + computed inverse**; the generic Service↔Condition edge = a **computed rollup** unioning the existing semantic schema.org fields.
- **Severing.** Deprecate the direct Condition/Service↔Provider/Location fields (move to a deprecated tab) + make the **transitive** computed the default + keep an explicit override path.
- **Specialties controlled vocabulary.** Keep editorial specialty pages but **anchor each to a controlled term** (promote the term relation to required/recommended; reuses the existing `specialties_*` / `clinical_specializations` taxonomies).
- **Schema vocabulary.** Physician `knowsAbout`; Location `availableService` (node resolves to `MedicalClinic` / `Hospital`); `relevantSpecialty` for specialty legs; Special Interest as a **Role-wrapped node** where context is needed.
- **Special Interest (final, lean).** NOT its own entity and NOT a cascade. Two typed entries fields — `special_interest_clinical_conditions` (→ `clinical_conditions`) and `special_interest_clinical_services` (→ `clinical_services`) — authored on **both Provider and Location** independently (provider SI = a person's clinical focus; location SI = a site's institutional focus). Input = **scoped C/T pickers** (scoped to the entry's own specialties via a save-time `EntrySaving` validation listener over the union of both fields). **No per-interest location scoping.** Surfacing = computed inverse onto the C/T (keyed per collection to the matching typed field). Schema (delivered by UAMS-Web/uams-statamic-archive#1067): provider → `knowsAbout`; location → `availableService`. Conditions and Services are split into two typed fields so each consumer reads a pre-typed list and the schema.org divergence (a Location condition has no valid predicate) is captured at authoring. (This shrinks the originally-sized "fork A" cascade fieldtype to lean pickers; see [`docs/DECISIONS.md`](docs/DECISIONS.md) 2026-07-09.)
- **Conditions & Treatments = data, not pages.** No standalone C/T pages, **no `/conditions` archive**, conditions/services **not mounted**. "Special interest in X" surfaces via a **filter option on the existing FaD Providers/Locations blocks** (droppable anywhere, no anchoring). Bespoke destinations = deliberate **content pages in `clinical_expertise`**. A page declares it is **about** a C/T via the **existing aboutness layer** — the `subject_matter` taxonomy for general concepts + entries-typed `about` / `mentions` / `main_entity_of_page` targeting `clinical_conditions` / `clinical_services` (per the `knows_about_*_entries` precedent) — emitting `MedicalWebPage.about` / `mainEntity` = the C/T's `MedicalCondition` / `MedicalProcedure` node.

## The D9 model as a graph

Five entities, renamed conceptually, all relationships many-to-many unless noted:

```
            ┌─────────────────────┐
            │  Medical Specialties │  (clinical_expertise)
            │  trend → controlled  │
            │  certifying-org vocab │
            └──────────┬───────────┘
            M2M        │       M2M
       ┌───────────────┼────────────────┐
       │               │                │
┌──────▼─────┐  ┌──────▼──────┐         │
│  Medical   │◄─M2M─►│ Medical  │        │  anchor edges
│ Conditions │       │ Services │        │  ride transitivity
│(clinical_  │       │(clinical_│        │
│ conditions)│       │ services)│        │
│ subtype:   │       │ subtype: │        │
│ Infectious/│       │ Procedure/│       │
│ Sign/Symptom│      │ Therapeutic/Test │
│ (MAY, opt) │       │ (MUST, 1 of 3)   │
└────────────┘       └──────────────────┘
       ▲                      ▲
       │  transitive (severed direct)
       │                      │
┌──────┴───────┐      ┌───────┴────────┐
│  Providers   │      │   Locations    │
│  (people)    │      │ (clinical_     │
│              │      │  locations)    │
└──────┬───────┘      └───────┬────────┘
       │   physician_expertise │ related_locations / expertise
       └──────► Medical Specialties ◄──────┘
              (the two anchor edges)
```

- **Three M2M edges** between the ontology core: Services↔Specialties, Services↔Conditions, Conditions↔Specialties.
- **Severed direct attachments + transitive inference.** Conditions/Services are no longer directly tied to Locations (all cases) or Providers (most cases). Instead: for each **Specialty** related to a Provider, that Specialty's Services/Conditions are *implicitly* related to the Provider (→ a TBD property on the Physician JSON-LD). Same Specialty→Location chain → LocalBusiness JSON-LD. The two **anchor edges** transitivity rides on already exist and are stored on **both** ends: provider↔specialty (`physician_expertise` at [`expertise.yaml:100`](resources/blueprints/collections/clinical_expertise/expertise.yaml), reciprocal `expertise` at [`person.yaml:1171`](resources/blueprints/collections/people/person.yaml)) and location↔specialty (`related_locations` at [`expertise.yaml:138`](resources/blueprints/collections/clinical_expertise/expertise.yaml), reciprocal `expertise` at [`location.yaml:1369`](resources/blueprints/collections/clinical_locations/location.yaml)).
- **"Special Interest"** — the novel exception. When a Provider has a special interest in a specific Service/Condition, a multi-directional relationship is authored through a **chain of scoped, cascading inputs**, each scoped by the prior picks (Specialty → Location(s) → Service → Condition(s), each list filtered by what's related to the previous selection). It surfaces as a "Special Interest" section on all five profile types and as TBD JSON-LD properties.

### The reframe to Statamic primitives

The good news dominates: the build already supplies almost every primitive D9 needs, because pass 1 and the native schema rebuild front-loaded the hard infrastructure. The reframe lands as:

| D9 piece | Statamic primitive that already carries it |
| --- | --- |
| Five renamed entities | The three collections already exist (`clinical_expertise`, `clinical_conditions`, `clinical_services`) + `people` + `clinical_locations` — only labels differ |
| Subtypes (Procedure/Therapeutic/Test; Infectious/Sign/Symptom) | Built natively in [`app/Enums/SchemaType.php`](app/Enums/SchemaType.php) + required subtype fields in [`treatment.yaml`](resources/blueprints/collections/clinical_services/treatment.yaml)/[`condition.yaml`](resources/blueprints/collections/clinical_conditions/condition.yaml) |
| Three M2M edges | `entries`-fieldtype fields (JSON ID arrays) already authored on both endpoints |
| Subtype-scoped pickers | [`app/Support/Scopes/SchemaTypeSubtypeScope.php`](app/Support/Scopes/SchemaTypeSubtypeScope.php) + ~25 `SchemaType*` scopes, wired via `query_scopes:` |
| Transitive inference | `whereJsonOverlaps` primitive ([`OntologyOverlapExpertise.php`](app/Scopes/OntologyOverlapExpertise.php)) + computed-field framework ([`app/Services/ComputedFields`](app/Services/ComputedFields)) |
| Multi-directional surfacing | `Collection::computed()` inverse-read registrars via [`ComputedPropertiesProvider`](app/Providers/ComputedPropertiesProvider.php) |
| JSON-LD emission | Production-grade schema subsystem ([`SchemaThingFromRelationship`](app/Tags/SchemaThingFromRelationship.php) multi-hop, `{{ schema_ld_json }}`) |
| **Special Interest cascade** | **Genuinely new** — no native or custom mechanism scopes one picker's options by a sibling field's runtime value (the standout build-fresh item) |

The single item with no existing analog is the **Special Interest cascading dependent-relationship fieldtype**; everything else is wiring or relabeling on top of built, proven machinery.

## Reframe, controlled vocabulary & subtypes

The reframe targets already exist as collections — `clinical_expertise` → "Medical Specialties", `clinical_conditions` → "Medical Conditions", `clinical_services` → "Medical Services" — so no new collections are needed; only display labels differ. The `clinical_services` handle already literally means "services." The schema.org subtype machinery is fully built and production-grade: [`app/Enums/SchemaType.php`](app/Enums/SchemaType.php) carries the entire medical tree, subtype radio fields live in [`resources/fieldsets/uamsweb_schema.yaml`](resources/fieldsets/uamsweb_schema.yaml), and both blueprints already **require** their subtype discriminator ([`treatment.yaml:144`](resources/blueprints/collections/clinical_services/treatment.yaml) `schema_medicalentity_subtype`, [`condition.yaml:131`](resources/blueprints/collections/clinical_conditions/condition.yaml) `schema_medicalcondition_subtype`).

The certifying-org controlled vocabulary D9 gestures toward — ACGME, ABMS/AOA boards, ABPTS, etc. — **also already exists**, but as taxonomies bound to `people`, not to `clinical_expertise`: [`specialties_acgme`](resources/blueprints/taxonomies/specialties_acgme/specialty_acgme.yaml), `specialties_aoa`, `specialties_non_accredited`, plus [`clinical_specializations`](resources/blueprints/taxonomies/clinical_specializations/clinical_specialization.yaml) (NUCC code set + schema.org `MedicalSpecialty` enum member + CMS/O*Net-SOC/ISCO-08 codes), [`certification_organizations`](resources/blueprints/taxonomies/certification_organizations/certification_organization.yaml), and [`app/Enums/CredentialRegistry.php`](app/Enums/CredentialRegistry.php). So Medical Specialties have **two parallel representations today**: the editorial `clinical_expertise` collection (marketing pages, free `title`) and the certifying-org-aligned controlled taxonomies attached to people — bridged softly via expertise's `schema_medicalspecialty` and `schema_code_nucc`.

The two genuine mismatches: D9's flat three-way Service subtype {Procedure, Therapeutic, Test} doesn't line up with the build's two-root {MedicalProcedure, MedicalTest}-with-nested-Therapeutic model (schema.org-correct, since `TherapeuticProcedure` subclasses `MedicalProcedure`); and D9 says Condition subtype is *optional* ("MAY belong") while the field is currently `required` (though it carries a built-in "None" opt-out, so this is mostly a validation tweak).

| D9 concept | Statamic mechanism | Exists | Gap | Effort | Risk |
| --- | --- | --- | --- | --- | --- |
| AoE → "Medical Specialties" | Relabel `title`/`display`/`instructions` in `expertise.yaml` + collection title | partial | All labels say "Area of Expertise"; handle `clinical_expertise` unchanged | S | Handle/label mismatch (cosmetic) |
| Conditions → "Medical Conditions" | Relabel in `condition.yaml` + collection title | partial | Near-identical already ("Medical" prefix only) | XS | — |
| Treatments → "Medical Services" | Relabel in `treatment.yaml`; handle `clinical_services` already fits | partial | Labels say "treatment or procedure"; keep relation handle `treatments` | S | `treatments` handle vs "Service" label (cosmetic) |
| Service subtype: exactly one of {Procedure, Therapeutic, Test} | `schema_medicalentity_subtype` (required) + nested procedure/therapy chain; `SchemaType*` scopes | partial | Top-level offers only {Procedure, Test}; Therapeutic sits two levels deep | M | If D9 wants Therapeutic as a *sibling*, conflicts with schema-faithful nesting |
| Condition subtype: optional one of {Infectious, Sign, Symptom} | `schema_medicalcondition_subtype` (required) + `schema_medicalsignorsymptom_subtype`; type scopes | partial | Field is `required`; "None" opt-out already supports optionality | S | "Optional" tweak weakens schema completeness |
| Specialties as certifying-org controlled vocab | Taxonomies `specialties_*` + `clinical_specializations` (NUCC) + `CredentialRegistry`; bridged via `schema_medicalspecialty` | partial | Vocab is attached to `people`, not `clinical_expertise`; AoE entries are free-text | L | Collapsing collection→taxonomy is a model rewrite (breaks pass-1) |
| Subtype filters on relationship inputs | `query_scopes:` referencing `SchemaType*` scopes (already used ~10×) | yes | None — the Google Doc "Filter Options" patterns are already ported | XS | — |
| `searchable` toggle on all three | `uamsweb_common.true_false` (exists on conditions/services); add to expertise; wire into search index | partial | No consuming logic; absent from `expertise.yaml` | S | — |

## The relationship graph

All three M2M edges of the D9 graph already exist as authored `entries` fields on **both** endpoints, defined once as reusable fields in [`resources/fieldsets/uamsweb_common_fad.yaml`](resources/fieldsets/uamsweb_common_fad.yaml) (`expertise`, `conditions`, `treatments`, `clinical_locations`, `providers` — all `entries`, `select_across_sites: true`, storing a JSON array of IDs). Edge 1 (Specialty↔Service): `treatments` on [`expertise.yaml:299`](resources/blueprints/collections/clinical_expertise/expertise.yaml) and `expertise` on [`treatment.yaml:365`](resources/blueprints/collections/clinical_services/treatment.yaml). Edge 2 (Specialty↔Condition): `conditions` on `expertise.yaml:291` and `expertise` on [`condition.yaml:288`](resources/blueprints/collections/clinical_conditions/condition.yaml). Edge 3 (Condition↔Service): `treatments` on `condition.yaml:294` and `conditions` on `treatment.yaml:357` — **plus** ~10 finer schema.org semantic relationship fields over the same two collections (`schema_possibletreatment`, `schema_treatedmedicalcondition`, `schema_typicaltest`, `schema_signdetected_*`), each already subtype-scoped via `query_scopes`. Those semantic fields are the WP `field_*_schema_*` properties from the Google Doc, ported faithfully, and they are the real source of truth driving JSON-LD.

The topology is built; what's undecided is the **authoring model**. Each edge is authored twice (one field per endpoint) with no enforced sync — an editor can relate A→B without the reciprocal B→A, and D9's transitive inference reads one direction, so an unsynced edge under-reports. The inverse/transitive primitive already exists and is in production for blocks: [`OntologyOverlapExpertise.php`](app/Scopes/OntologyOverlapExpertise.php) and [`OntologyOverlapLocations.php`](app/Scopes/OntologyOverlapLocations.php) use `whereJsonOverlaps('expertise'|'locations', $ids)` — exactly the array-intersection an inverse query needs. The resolve-by-ID-array shape exists too: [`ProviderPeopleListingComputedValues::computeAspFilter`](app/Services/ComputedFields/ProviderPeople/ProviderPeopleListingComputedValues.php) already walks a provider's `expertise`+`conditions`+`treatments` arrays and resolves titles.

| D9 concept | Statamic mechanism | Exists | Gap | Effort | Risk |
| --- | --- | --- | --- | --- | --- |
| Service↔Specialty M2M | `treatments`/`expertise` `entries` fields both ends | yes | Dual-authored, no enforced sync | M | Sides silently diverge; transitive read under-reports |
| Condition↔Specialty M2M | `conditions`/`expertise` fields both ends | yes | Same dual-authored shape | M | Same drift |
| Condition↔Service M2M | `treatments`/`conditions` + ~10 subtype-scoped schema fields | yes | Generic edge coexists with semantic fields; rollup vs separate unclear | M | Triple data entry if both authored |
| Inverse navigation of any edge | No ontology inverse computed yet; `whereJsonOverlaps` + computed framework present | partial | No registrar wires inverse onto the three collections | L | `whereJsonOverlaps` perf at scale (proven in prod blocks) |
| Subtype constraint on a relationship field | `SchemaTypeSubtypeScope` + `SchemaType*` + cascading `if:`+`required` validation | yes | Already enforced (treatment.yaml:144, condition.yaml:131) | XS | — |
| exclude-self / hierarchy exclusion | `ExcludeSelf`, `ExcludeDescendants`, `ExcludeSiblings`, `ExcludeAncestors` | partial | **`ExcludeSiblings` is a no-op stub** (pass-1 known defect) | S | Editors believe siblings excluded from `associated` — they aren't |
| Sever Condition/Service→Locations (all cases) | Direct `clinical_locations` on condition/treatment + reciprocals on location | yes | D9 severs; direct fields still authored | L | "not in ALL cases" — blanket removal drops legit data |
| Sever Condition/Service→Providers (most cases) | Direct `providers` fields; `computeAspFilter` resolves provider side | yes | "MOST cases" implies a retained exception = Special Interest | L | Severing must not also sever the special-interest path |
| Relabel as it affects relationship-field display | Field `display`/`instructions` in the fieldset | partial | Strings still say "Areas of Expertise" etc.; handles unchanged | S | Renaming handles would break every `whereJsonOverlaps('expertise',…)` — argues for relabel-only |

## Transitive inference (severed direct attachments)

D9 replaces direct Condition/Service→Location (all cases) and →Provider (most cases) attachments with transitive inference over the two anchor edges. The build models the graph today with **direct, redundant** relationship fields on every collection, **plus the exact array-overlap primitive transitivity needs**. Both anchor edges are stored symmetrically on each end, so neither requires a reverse-lookup index: the provider↔specialty edge is `physician_expertise` (target collection `clinical_providers`) on [`expertise.yaml:100`](resources/blueprints/collections/clinical_expertise/expertise.yaml) with reciprocal `expertise` on [`person.yaml:1171`](resources/blueprints/collections/people/person.yaml), and the location↔specialty edge is `related_locations` on [`expertise.yaml:138`](resources/blueprints/collections/clinical_expertise/expertise.yaml) with reciprocal `expertise` on [`location.yaml:1369`](resources/blueprints/collections/clinical_locations/location.yaml). Both a person and a location carry their own specialty IDs directly — the transitive read can source the specialty set from whichever side is chosen as canonical (Fork D), no reverse query required.

One collection-identity wrinkle to pin before building the provider transitive read: the provider-side anchor `physician_expertise` resolves through `uamsweb_common_fad.providers`, whose `collections:` target is **`clinical_providers`** ([`content/collections/clinical_providers.yaml`](content/collections/clinical_providers.yaml)) — a collection distinct on disk from `people` ([`content/collections/people.yaml`](content/collections/people.yaml)), where the reciprocal `expertise` field lives. So `physician_expertise` (on the expertise side, pointing at `clinical_providers`) and `person.yaml`'s `expertise` field are **not** strictly the same edge's two ends unless `clinical_providers` is a view/alias over `people`. The provider transitive computed must name its read source precisely — read the specialty IDs from the people-side `expertise` field, and confirm whether the provider profile that emits the Physician JSON-LD is a `people` entry or a `clinical_providers` entry before wiring the hop.

The transitive primitive is built and production-tested (`whereJsonOverlaps('expertise', $ids)` = "every ontology entry whose specialty array intersects this list"). The computed-field framework hosts the derived sets: per-collection registrars wired via [`ComputedPropertiesProvider`](app/Providers/ComputedPropertiesProvider.php), with the [`KnowledgeBaseComputedFieldsService`](app/Services/KnowledgeBase/KnowledgeBaseComputedFieldsService.php) demonstrating the `Blink::once("computed_kb_ancestor_{$id}", …)` per-request memoization and the precomputed-count precedent (`direct_subtopic_count`) for bounding fan-out. The schema emitter ([`SchemaThingFromRelationship`](app/Tags/SchemaThingFromRelationship.php) `secondary`/`tertiary` modes) already does the one/two-hop traversal the transitive JSON-LD node needs, and the candidate properties exist in [`app/Enums/SchemaProperty.php`](app/Enums/SchemaProperty.php) with verified domains: `knowsAbout` (Person/Organization), `availableService` (Hospital/MedicalClinic/Physician), `makesOffer`/`hasOfferCatalog` (Organization). Note `availableService` is **invalid on bare LocalBusiness** — the Location node's `@type` must resolve to `MedicalClinic`/`Hospital` (scopes exist) for it to pass [`SchemaPropertyValidator`](app/Tags/Schema/SchemaPropertyValidator.php).

| D9 concept | Statamic mechanism | Exists | Gap | Effort | Risk |
| --- | --- | --- | --- | --- | --- |
| Anchor: Provider↔Specialty | `physician_expertise` (expertise → `clinical_providers`) + reciprocal `expertise` (person) | yes | Two-sided storage; need one authoritative side; confirm `clinical_providers` vs `people` identity | S | Drift; people-side `expertise` is the natural read source |
| Anchor: Location↔Specialty | `related_locations` (expertise side) + reciprocal `expertise` (location.yaml:1369) | yes | Two-sided storage; need one authoritative side | S | Drift; symmetric to the provider edge |
| Provider → Specialties → Services/Conditions | New people registrar computed via `whereJsonOverlaps('expertise', $specialtyIds)` | no | No computed; overlap scope wired only to block tags | M | Fan-out cardinality; memoize per entry |
| Location → Specialties → Services/Conditions | New location registrar reusing the location-side `expertise` IDs + overlap | no | No computed; overlap scope wired only to block tags | M | Two-stage fan-out; memoize |
| Sever Condition/Service→Locations (all) | Remove/demote `clinical_locations` (condition:280, treatment:373) | yes | Field present; nothing routes through transitivity | S | Imported content data keys linger as orphans (merge sweep) |
| Sever Condition/Service→Providers (most) | Demote `providers` (condition:272, treatment:383) | yes | "MOST" = retained Special-Interest exception | S | Default→transitive vs direct-special-interest boundary |
| Render transitive sets on a profile | Pass-1 routed `{entry}/{section}` model, computed ID source | partial | Render path exists; reads stored fields, not computed lists | S | Empty-state when specialty has no services |
| Transitive props on Physician JSON-LD (TBD) | `SchemaThingFromRelationship` secondary mode; `knowsAbout`/`relevantSpecialty` | partial | No template emits it; property name TBD | M | `knowsAbout` is a loose semantic fit (fork) |
| Transitive props on LocalBusiness JSON-LD (TBD) | Same tag; `availableService`/`makesOffer`/`hasOfferCatalog` | partial | No template; property TBD; Condition→Location has no clean property | M | `availableService` invalid on non-medical LocalBusiness |
| Unbounded hierarchy × transitivity (descendants?) | D1 unbounded structure; `ExcludeDescendants` shows tree is walked | no | Undecided whether descendant specialties' services inherit | L | Subtree expansion multiplies fan-out (fork) |
| Performance/caching of fan-out | `Blink::once` + precomputed-count + KB save-listener precedent | partial | Several fan-out queries per profile; no cross-request cache | M | Large collections may need a stored mirror |

## Special Interest (cascading fieldtype + multi-directional)

**This is the one genuinely build-fresh piece.** The build has every primitive the feature decomposes into but has never assembled them into **cross-field-dependent option scoping** — scoping one picker's options by another field's *runtime* value in the same form.

The cascade is: Specialty (scoped to the provider's specialties) → Location(s) (scoped to locations related to the provider AND the selected Specialty) → Service (scoped to the selected Specialty) → Condition(s) (scoped to conditions related to the selected Service AND Specialty); symmetric for a Condition special interest. **Native Statamic cannot do this.** The CP relationship picker is a standalone XHR endpoint ([`RelationshipFieldtypeController::index()`](vendor/statamic/cms/src/Http/Controllers/CP/Fieldtypes/RelationshipFieldtypeController.php)); [`Entries::getIndexQuery()`](vendor/statamic/cms/src/Fieldtypes/Entries.php) calls `applyIndexQueryScopes($query, $request->all())`, but the request carries only `config`/`filters`/`search`/`exclusions`/`site`/`sort`/`paginate` — **never the current entry's other field values**. The project's own [`app/Fieldtypes/Entries.php`](app/Fieldtypes/Entries.php) only merges configured scope handles into params; it adds no sibling values. So a native scope structurally cannot see "the Specialty just picked in the row above." Native `if:` config can only show/hide a whole field by a sibling value — it cannot scope another field's *options*.

The good news: **the exact custom-fieldtype pattern is already shipping.** [`SchemaObjectFieldtype.vue`](resources/js/components/fieldtypes/SchemaObjectFieldtype.vue) does `instance.$axios.post(cp_url('schema-object/replicator-data'), { types })` to fetch scoped data on demand from a custom CP route registered in [`SchemaObjectServiceProvider.php`](app/Providers/SchemaObjectServiceProvider.php), and reads/writes form state via `inject(publishContextKey)` (`@statamic/cms/ui`). ~43 custom Vue fieldtypes exist (registered in [`resources/js/cp.js`](resources/js/cp.js)), several reading sibling state via `publishContext` (`PersonName`, `RoleTitle`, `TextPhoneticPair`). The intersection queries reuse `whereJsonOverlaps` from the Ontology* scopes. The multi-directional surfacing reuses `Collection::computed()` inverse-read registrars (precedents: `RoleTitleMirroredComputedsRegistrar`, `ProvidersPeopleSharedCollectionComputedsRegistrar`).

| D9 concept | Statamic mechanism | Exists | Gap | Effort | Risk |
| --- | --- | --- | --- | --- | --- |
| Cascade: N pickers each scoped live by prior picks | **Custom Vue fieldtype** + custom CP route, reading `inject(publishContextKey)`, mirroring `SchemaObjectFieldtype.vue` + `SchemaObjectServiceProvider` | no | No fieldtype scopes options by a sibling's runtime value; native picker never receives sibling values | XL | Highest novelty; Vue reactivity, debounced scoped fetches, `markRaw` non-plain library instances |
| Scoped candidate queries (4 steps, ∩ where needed) | Server-side `whereJsonOverlaps`/`whereJsonContains` in a thin CP controller per step | partial | Intersection primitive exists; the endpoint + 4 specific queries don't | L | Empty-prior-pick must yield EMPTY downstream — **opposite** of Ontology* scopes' return-unchanged-on-empty |
| Multi-directional surfacing on 5 profile types | One source field on Provider + 4 `Collection::computed()` inverse-read registrars | partial | Framework exists; no special-interest inverse computed | L | Inverse query is `whereJsonContains` on **nested** tuple keys; perf across `people` |
| TBD JSON-LD properties on related nodes | `SchemaTemplates`/`SchemaObject` + `SchemaThingFromRelationship`, fed by the inverse computeds | partial | Machinery production-grade; the specific property is TBD | M | No canonical schema.org "special interest" term (defer to schema pass) |
| Special-Interest section alongside transitive sections | Pass-1 routed `{entry}/{section}` render model | partial | Section model exists; coexistence/dedup with transitive list unsettled | M | Special-interest items must be visually distinguished, not duplicated |

## Schema.org for the relationships

The schema subsystem is rebuilt natively, production-grade, and overwhelmingly relationship-driven and data-driven. Every D9 `@type` exists in [`app/Enums/SchemaType.php`](app/Enums/SchemaType.php) (MedicalSpecialty, MedicalProcedure, MedicalTherapy, MedicalTest, MedicalCondition, MedicalSign/Symptom/SignOrSymptom, InfectiousDisease, Physician, MedicalBusiness/LocalBusiness/MedicalClinic/Hospital, TherapeuticProcedure, DiagnosticProcedure, **Role**). Every D9 graph property exists in [`app/Enums/SchemaProperty.php`](app/Enums/SchemaProperty.php) **with verified domain/range metadata**: `relevantSpecialty` (MedicalEntity→MedicalSpecialty), `availableService` (Hospital/MedicalClinic/Physician→Procedure/Test/Therapy), `knowsAbout` (Person/Organization→Thing), `recognizingAuthority` (MedicalEntity→Organization), `possibleTreatment`, `primaryPrevention`/`secondaryPrevention`, `typicalTest`, `usedToDiagnose`, `signDetected`, and more. `Role` appears in nearly every property's `range_includes`, so Role-wrapping is a first-class supported shape. [`SchemaPropertyValidator`](app/Tags/Schema/SchemaPropertyValidator.php) validates property-per-`@type` from these enums.

The relationship→JSON-LD wiring is built: [`SchemaThingFromRelationship`](app/Tags/SchemaThingFromRelationship.php) + [`SchemaThingFromRelationshipResolver`](app/Services/SchemaTemplates/SchemaThingFromRelationshipResolver.php) do direct/secondary/tertiary/global multi-hop walks → JSON-LD nodes with `@id`/dedupe; [`SchemaOutputService`](app/Services/SchemaTemplates/SchemaOutputService.php) composes the `@graph`, emitted via `inject.schema_templates` + `{{ schema_ld_json }}`. **Caveat:** the per-collection `schema_objects` *property templates* are entries living in the `content/` submodule, **empty in this worktree** — so the exact JSON-LD currently emitted cannot be audited here, and the `partial` rows need a content-populated checkout to confirm. What is **not present today**: any `availableService`/`knowsAbout` emission in `resources/blueprints/`, any `transitive` logic, and any `special_interest` field/scope/template anywhere.

| D9 concept | Statamic mechanism | Exists | Gap | Effort | Risk |
| --- | --- | --- | --- | --- | --- |
| Service subtype → `@type` | treatment.yaml subtype chain; `SchemaType` literals | yes | Make `schema_medicalentity_subtype` required to enforce "exactly one of three" | XS | Confirm "Therapeutic" = MedicalTherapy via TherapeuticProcedure |
| Condition subtype → `@type` (optional) | condition.yaml subtype chain | yes | Already optional/`if:`-gated — satisfies "MAY" | XS | — |
| Subtype-scoped pickers | `SchemaType*` + `query_scopes:` | yes | Covers subtype leg of the cascade; not the related-to-prior-pick leg | XS | — |
| Service↔Specialty link | `relevantSpecialty` (Service) / `availableService` (Specialty); `schema_relevantspecialty` field | partial | Emission is a `schema_objects` template (empty submodule); reverse is transitive | S | M2M side-of-truth |
| Condition↔Specialty link | `relevantSpecialty` on Condition; reverse on Specialty TBD | partial | No `schema_relevantspecialty` on condition.yaml; no symmetric MedicalSpecialty→Condition property | S | Reverse leg needs an owner-chosen property (fork) |
| Service↔Condition link | `possibleTreatment`/`primaryPrevention`/`typicalTest`/`usedToDiagnose` (semantic fields) | yes | Richer than D9's flat edge — keep the typed edges | S | Don't collapse typed edges into one generic (loses fidelity) |
| Transitive Provider→Services/Conditions | `SchemaThingFromRelationship` tertiary hop from Physician; `availableService`/`knowsAbout` | no | Nothing emits it; 2-hop walk unbuilt | M | Fan-out cost; computed materialization vs live walk |
| Transitive Location→Services/Conditions | Same, from Location node; `availableService` needs MedicalClinic/Hospital `@type` | no | Same; node `@type` must be a MedicalBusiness subtype | M | `availableService` invalid on bare LocalBusiness |
| **Special Interest** JSON-LD | `Role`-wrapped node attached to all 5 nodes via Role-rangeable property | no | Entirely new; needs the cascade field + relationship-aware scope | XL | Cascade-by-sibling scope is the crux; property name TBD |
| Specialty controlled vocab → certifying org | `recognizingAuthority` (MedicalEntity→Organization) from `certification_organizations` | partial | No `recognizingAuthority` emitted; AoE not constrained to vocab | M | Constraining is cross-domain; schema deliverable is just the org link |

## Conditions & Treatments profiles (circle-back resolved)

Both collections are routeless + mountless today — [`content/collections/clinical_conditions.yaml`](content/collections/clinical_conditions.yaml) and [`clinical_services.yaml`](content/collections/clinical_services.yaml) carry no `route:` or `mount:`, so neither has a public URL; the `template:` lines and `preview_targets` are vestigial, and [`resources/views/clinical_services/show.antlers.html`](resources/views/clinical_services/show.antlers.html) is a 4-line shell that reads no relationship field (the configured `clinical_conditions/show` template doesn't exist on disk at all). Pass 1 already settled the *display* side of the circle-back (mapping §9, forks §13.7/§13.8): C&T are demoted to keywords, render as plain title text inside AoE with no `{{ url }}`, no singles/archives, with legacy redirects via the Redirects global at launch.

D9 reopens the **narrower** question: do C&T entries get standalone profiles *at all*, and under what rule? D9's own wording — "may or may not continue having profiles … likely determined based on Special Interest relationships to Providers" — ties profile existence to the relationship data. The faithful, enforceable reading is a **computed `has_profile` gate** over the exact graph the blueprints already model: a profile exists iff the entry has ≥1 special-interest provider OR ≥1 related specialty OR a manual `force_profile` override. Mount the collection (D6) but 404/410 and de-index entries where `has_profile=false`; non-qualifying entries still render as plain text inside their specialty/provider pages exactly as pass-1 settled, while qualifying ones graduate to real profiles with transitive + special-interest sections and rich JSON-LD. This kills thin orphan pages (honoring the FD20-3267 demotion intent pass-1 carried) and degrades cleanly. The only real cost is per-entry URL suppression under a whole-collection mount — a verifiable routing detail (`Route::statamic` guard vs unpublished convention vs redirect rule), not a model change.

The subtype model on both blueprints already matches D9 1:1, the M2M graph fields exist both directions, and all enabling mechanisms (the ~25 `SchemaType*` scopes, the 11-registrar computed framework, `SchemaThingFromRelationship`) are present — so the profile build is wiring (transitive computeds + routed sections + the gate), not new modeling. Note the `searchable` field exists on both ([`condition.yaml:29`](resources/blueprints/collections/clinical_conditions/condition.yaml), [`treatment.yaml:29`](resources/blueprints/collections/clinical_services/treatment.yaml)) but [`config/statamic/search.php`](config/statamic/search.php) does not read it — wiring it (and per-entry exclusion, since `searchables` is collection-level) is a small task that interacts with the gate.

## Already enabled by the build

- **The three collections + subtype model** already match D9. `clinical_services` literally means "services"; subtype discriminators are required and the full conditional cascade is authored (treatment.yaml:144–255, condition.yaml:131–262).
- **Full schema.org medical vocabulary** — every D9 `@type` and graph property exists with correct domain/range metadata in [`SchemaType.php`](app/Enums/SchemaType.php) / [`SchemaProperty.php`](app/Enums/SchemaProperty.php); `Role`-wrapping is first-class.
- **All three M2M edges** exist as authored `entries` (JSON-ID-array) fields on both endpoints, plus the richer subtype-scoped semantic schema fields that drive JSON-LD.
- **The transitive/inverse query primitive** — `whereJsonOverlaps` in [`OntologyOverlapExpertise`](app/Scopes/OntologyOverlapExpertise.php)/[`OntologyOverlapLocations`](app/Scopes/OntologyOverlapLocations.php) — is built and production-tested; resolve-by-ID-array in `computeAspFilter`.
- **Subtype-scoped relationship pickers** — `SchemaTypeSubtypeScope` + ~25 `SchemaType*` scopes + enum-driven `EntriesBySchemaType`, wired ~10× — are the Google Doc "Filter Options Available in Inputs" patterns, already ported.
- **The computed-field framework + memoization pattern** — [`ComputedPropertiesProvider`](app/Providers/ComputedPropertiesProvider.php) + ~11 registrars with `Blink::once` and the precomputed-count precedent — is the established, copy-able way to expose any inverse/transitive/rollup relationship.
- **The relationship→JSON-LD engine** — [`SchemaThingFromRelationship`](app/Tags/SchemaThingFromRelationship.php) multi-hop + `SchemaOutputService` `@graph` composition + `{{ schema_ld_json }}` — wires D9 edges with no engine change once fields + property templates exist.
- **The custom-Vue-fieldtype-with-custom-CP-endpoint pattern** — [`SchemaObjectFieldtype.vue`](resources/js/components/fieldtypes/SchemaObjectFieldtype.vue) + [`SchemaObjectServiceProvider`](app/Providers/SchemaObjectServiceProvider.php) + `inject(publishContextKey)` — means the Special Interest fieldtype copies an in-build pattern, not greenfield.
- **The certifying-org controlled vocabulary** — taxonomies `specialties_acgme/aoa/non_accredited`, `clinical_specializations` (NUCC + schema enum + CMS/O*Net/ISCO codes), `certification_organizations`, [`CredentialRegistry`](app/Enums/CredentialRegistry.php) — already exists, attached to `people`.
- **Both anchor edges, stored symmetrically** — `physician_expertise`/`expertise` (provider) and `related_locations`/`expertise` (location, location.yaml:1369) — and **the routed `{entry}/{section}` render model** (pass-1); transitive + special-interest sections reuse the section model with a computed ID source.
- **The Redirects global** ([`resources/blueprints/globals/redirects.yaml`](resources/blueprints/globals/redirects.yaml)) for legacy C&T URL handling at launch.

## Genuinely new work

In rough descending novelty:

1. **The Special Interest cascading dependent-relationship fieldtype (XL).** The standout build-fresh item. A custom Vue fieldtype where each picker's options are scoped live by the prior picks, backed by a new custom CP route running the 4-step scoped queries (`whereJsonOverlaps` for the ∩ steps), with the empty-prior-pick guard inverted from the Ontology* default. Layer a server-side scope-chain `ValidationRule` underneath as defense-in-depth so no invalid tuple persists via API/import.
2. **Transitive-inference computeds (L).** New registrars on `people` and `clinical_locations` computing implied Services/Conditions from each entry's own related-specialties array (both anchor edges store the specialty IDs on the entry directly — no reverse index needed), Blink-memoized, mirroring `KnowledgeBaseComputedFieldsService`.
3. **Inverse/multi-directional surfacing computeds (L).** An ontology inverse registrar (for the three M2M edges) and a `SpecialInterestInverseComputedsRegistrar` exposing the special interest on the other four collections via `whereJsonContains` on nested tuple keys.
4. **`has_profile` gate + per-entry URL suppression (M).** The C&T profile rule plus the route guard / de-index mechanism.
5. **Transitive + Special-Interest JSON-LD emission (M).** New `schema_objects` property templates emitting `availableService`/`knowsAbout` (transitive) and a `Role`-wrapped node (special interest) — once the TBD property names are chosen.
6. **Severing the direct attachments (S–L) + relabel sweep (S) + `searchable` wiring (S)** — plus the pre-existing **`ExcludeSiblings` no-op stub** fix (S), which rides the same relationship-scope surface (in scope per adversarial-review: a pre-existing bug a new slice reuses).

## Design forks to decide

### Fork A — Special Interest input implementation (the headline)

The cascade scopes each picker's options by a sibling field's runtime value, which native Statamic structurally cannot do (the CP picker endpoint never receives sibling values).

| Dimension | (i) Custom Vue fieldtype + CP endpoints | (ii) Replicator/Grid of native entries + static scopes + `if:` | (iii) Free native entry + save-time ValidationRule |
| --- | --- | --- | --- |
| Meets "scoped by prior pick" | Yes (live cascade) | **No** — pickers show full/static-subtype lists | No authoring guidance; rejects on save |
| Authoring UX | Best (instant re-scope) | Misleading (invalid combos pickable) | Poor (pick → reject for a 4-deep chain) |
| In-build precedent | `SchemaObjectFieldtype.vue` + provider route | condition.yaml pattern | whereJsonOverlaps in a Rule |
| Effort | XL + L | Near-zero | Low UI |
| Correctness guarantee | Server enforces scope | None | Guaranteed at save |

**Recommendation: (i), with (iii)'s scope-chain ValidationRule layered underneath as defense-in-depth.** It is the only approach that satisfies the literal spec *and* fits this build's conventions — the exact pattern (custom Vue fieldtype POSTing to a custom CP route, reading form state via `inject(publishContextKey)`) is already shipping. Reject (ii): it provably cannot scope options by sibling values, which is the entire point. (iii) alone gives no guidance for a 4-deep chain but is an excellent safety net under (i) — the UI guides, the Rule guarantees no invalid tuple persists even via API/import.

### Fork B — Medical Specialties: controlled vocabulary vs editorial pages

| Dimension | (1) Keep editorial pages, anchor each to a controlled term | (2) Specialties ARE the terms (collection→taxonomy) | (3) Dual-track: terms + separate pages |
| --- | --- | --- | --- |
| Pass-1 routing/content/schema | Preserved | **Broken** | Preserved (most parts) |
| Controlled-vocab strength | At the anchor field | True single source | True (terms side) |
| Reuses built taxonomy subsystem | Yes | Yes (becomes the model) | Yes |
| Drift risk | Page/term sync | None | Page/term sync |
| Effort / risk | L / low | Very high / breaks settled directives | Highest / most moving parts |

**Recommendation: (1) — keep editorial pages, strengthen the existing soft bridge.** The controlled vocabulary already exists and is mature, but deliberately attached to people; `clinical_expertise` is the human-readable, hierarchical, routable marketing surface pass-1 built routing/content-pages/schema around. Promote `schema_medicalspecialty` (and/or a `clinical_specializations` term relation) to a required/recommended **anchor** so every specialty page provably maps to a certifying-org term and its `MedicalSpecialty` enum member — delivering D9's "trends toward a controlled vocabulary" intent at L effort by reusing what's built, without a model rewrite. Option (2) would demolish settled pass-1 work and the D1 unbounded-hierarchy directive. **Confirm with the team:** does "controlled" mean "each page must map to a term" (anchoring satisfies this) or "only terms may exist" (the rewrite)?

### Fork C — Rename handles vs relabel display only (the reframe)

| Dimension | Relabel display only | Rename handles too |
| --- | --- | --- |
| Importer/relationship safety | Safe (all keys/refs unchanged) | High blast radius (sweep every consumer + content-key merge) |
| Code coherence | Lasting handle/label mismatch | Clean (handle matches concept) |
| Coordination cost | None | Re-coordinate wordpress-importer#31/UAMS-Web/uams-statamic-archive#919 |
| Effort | S/XS | High |

**Recommendation: relabel display only.** The directive is a conceptual/display reframe, not a data-model change; the collections already correspond 1:1 to the new names. Display edits deliver the full editor/visitor-facing effect at S/XS with zero importer or relationship risk; a handle rename buys only cosmetic code-side coherence at a large, importer-coupled cost (the build's own convention: rename = sweep all references). Every `whereJsonOverlaps('expertise',…)` and `get('treatments')` in scopes/computeds would break — argues strongly for relabel.

### Fork D — Edge authoring: single side + computed inverse vs both authored

> **SUPERSEDED (2026-07-08, UAMS-Web/uams-statamic-archive#1062 → UAMS-Web/uams-statamic-archive#1360).** This recommendation was **reversed**: the ontology M2M edges are now kept in sync as **both-authored stored fields via `stillat/relationships`** (the UAMS-Web/uams-statamic-archive#1360 cluster), not single-authored + computed inverse. The "both-authored + save-listener is net-new infrastructure" objection below was resolved by using the already-installed, battle-tested addon (which owns the race/ordering/delete-cleanup) instead of a hand-rolled `EntrySaved` listener. See the 2026-07-08 entry in [`docs/DECISIONS.md`](../DECISIONS.md). The text below is retained for history.

**Recommendation: single authored side + computed inverse.** Cannot drift; matches the computed-fields convention and reuses the proven `whereJsonOverlaps` primitive — one new registrar + 1–2 parameterized overlap scopes. Both-authored + save-listener is net-new infrastructure with race/ordering hazards; both-authored-no-sync (status quo) under-reports for the transitive inference. The transitive and Special-Interest features *require* a single source of truth per edge to be correct. (Open: which endpoint is canonical per edge — e.g. is Specialty the authoritative author of its Services/Conditions, and for the anchor edges, is the people/location side or the expertise side canonical? Settle the `clinical_providers`-vs-`people` identity here too.)

### Fork E — Generic D9 Service↔Condition edge vs the existing semantic fields

> **SUPERSEDED in mechanism (2026-07-08, UAMS-Web/uams-statamic-archive#1062 → UAMS-Web/uams-statamic-archive#1360).** The observation still holds — the semantic schema.org fields are the source of truth and no separate *authored* generic field should compete with them — but the semantic fields are now kept in sync as **stored bidirectional `stillat/relationships` pairs** (UAMS-Web/uams-statamic-archive#1363), not rolled up into a standalone **computed** generic edge. Any consumer needing the union (transitive UAMS-Web/uams-statamic-archive#1063, JSON-LD UAMS-Web/uams-statamic-archive#1067) unions the semantic fields at read time. See the 2026-07-08 entry in [`docs/DECISIONS.md`](../DECISIONS.md).

**Recommendation: computed rollup unioning the semantic fields.** The semantic schema.org fields are load-bearing (they drive the built JSON-LD subtype matrix) and were ported faithfully; they are the real source of truth. A separate authored generic field duplicates that data and invites drift; the rollup gives D9 its single generic edge at zero extra data-entry cost (dedupe the union of ~5 source fields per side).

### Fork F — Scope of severing direct Location/Provider attachments

**Recommendation: hide/deprecate direct fields, derive transitively, keep an explicit override path.** D9's own wording ("not in ALL cases", "in MOST cases") proves the severance is not absolute — a retained exception path is required, and Special Interest *is* that path. Move direct fields to a `deprecated_soon` tab (existing convention), make the transitive computed the default, preserve the exception data, and avoid an irreversible destructive migration before the exact exception rule is pinned. Hard-removal drops legitimate retained-provider data; keeping-as-additive double-counts in JSON-LD.

### Fork G — Live-computed vs stored-mirror transitive sets

**Recommendation: live-computed + Blink memoization.** No stale data, no listener, matches the 11-registrar convention; `whereJsonOverlaps` is the efficient path the scopes were rewritten to use. Promote a specific hot computed to a stored mirror (the KB save-listener machinery exists) only if profiling shows a bottleneck — start correctness-by-construction.

### Fork H — Descendant inheritance in transitivity

**Recommendation: flat — exact specialty match only.** D9 says "for each Specialty related to a Provider, all Services/Conditions related to THAT Specialty" — the literal reading is flat, and the existing `OntologyOverlapExpertise` implements flat overlap with zero new code and bounded fan-out. Inheritance (subtree walk before the overlap) can be added later as opt-in if the flat graph proves too sparse.

### Fork I — TBD JSON-LD property names

**Recommendation: Physician `knowsAbout`; Location `availableService` (+ `makesOffer`/`hasOfferCatalog`); `relevantSpecialty` for Service/Condition→Specialty (exact); Special Interest as a `Role`-wrapped node.** These are the only enum cases with domains valid for their `@type`, so they're emittable today without new vocabulary. The genuine residual the team must confirm is the **semantic fit** (transitive ≠ personally-performed) and the **missing Condition→Location predicate** (LocalBusiness has no clean condition property — may need to omit or use a generic node) — a vocabulary judgment, not derivable from the build. Role-wrapping is the right Special-Interest shape because D9 explicitly calls it "multi-directional" with distinguishing context; a flat property cannot distinguish a special-interest edge from an ordinary transitive one.

### Fork J — Do C&T keep standalone profiles, under what rule?

**Recommendation: computed `has_profile` gate** (profile iff ≥1 special-interest provider OR ≥1 related specialty OR `force_profile`; mount + 404/410 + de-index otherwise). See the circle-back section above. Always-profile floods the index with thin pages (reintroduces the FD20-3267 problem); no-profiles forfeits D9's transitive/special-interest pages and their JSON-LD.

## Suggested sequencing

The dependency spine is **graph + subtypes → transitive → Special Interest → schema wiring**, with relabel and the no-op-stub fix as cheap parallel work.

1. **Reframe relabel (S/XS) + `searchable` wiring (S) + `ExcludeSiblings` fix (S)** — parallel, low-risk, no dependencies. Decide Fork C (relabel) first; it gates how every later query targets handles (assume relabel-only — queries target `clinical_*` handles as-is).
2. **Subtype enforcement (XS).** Make `schema_medicalentity_subtype` required; relax `condition` subtype to optional. The model already matches D9; this is a validation tweak. Confirm Fork (Therapeutic = MedicalTherapy via TherapeuticProcedure) before touching it.
3. **Decide the edge authoring model (Fork D) + generic-edge rollup (Fork E), then build the relationship graph computeds (M–L).** Single authored side per edge + computed inverse via a new ontology registrar + parameterized overlap scopes. Settle the `clinical_providers`-vs-`people` identity here, since it names the canonical author of the provider anchor edge. *Everything transitive depends on a clean single-source graph.*
4. **Build transitive inference (L).** Add the Provider/Location transitive computeds reading each entry's own specialty-IDs array (both anchor edges already store them symmetrically — no reverse index to build) (Forks G live-computed, H flat). *Depends on step 3's clean edges.* Decide Fork F (severance disposition) here — deprecate direct fields once the transitive default works.
5. **Build Special Interest (XL).** The custom Vue fieldtype + CP scoped-query endpoints + ValidationRule (Fork A), then the inverse-surfacing computeds on all five collections, rendered via the routed `{entry}/{section}` model. *Depends on steps 3–4 (it reads the same anchor edges and reuses `whereJsonOverlaps`); pin the stored tuple shape and cascade cardinality/optionality first.*
6. **Schema.org wiring (M).** Emit transitive (`availableService`/`knowsAbout`) and Special-Interest (`Role`-wrapped) JSON-LD via new `schema_objects` property templates (Fork I), plus `recognizingAuthority` for the controlled-vocab anchor (Fork B). *Comes last because it consumes the computeds from steps 4–5; also requires a content-populated checkout to audit the existing templates the `partial` rows reference.*
7. **C&T profiles (M).** The `has_profile` gate + per-entry URL suppression + show templates rendering direct/transitive/special-interest sections (Fork J). *Depends on the transitive + special-interest computeds existing.* Finalize legacy redirect targets after the profile rule lands.

Blocked-after-blocker discipline: never co-schedule a step with its dependency in the same iteration — steps 3, 4, 5, 6/7 must land in strictly increasing iterations.