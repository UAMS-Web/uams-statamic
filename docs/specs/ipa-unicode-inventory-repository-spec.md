# Specification: IPA Unicode Inventory Repository

This document specifies a **standalone, language-agnostic repository** that publishes a **versioned, machine-readable inventory** of Unicode scalar values (and optional normalization rules) judged **IPA-relevant** for transcription, validation, and tooling. It is intended to replace ad hoc regex subsets (e.g. hand-maintained allowlists inside validators) with a **single source of truth** and **generated artifacts** for any runtime (PHP/Laravel, JavaScript, etc.).

---

## 1. Goals

1. **Single canonical dataset** — One authoritative JSON (or equivalent) definition of allowed code points and related metadata.
2. **Cross-platform consumption** — Any application can depend on the repo (Git submodule, Composer package, npm package, or copied release assets) without parsing PHP.
3. **Explicit policy** — The inventory reflects a **documented policy** (e.g. “IPA chart + common modifiers,” “extIPA subset,” “delimiters optional”), not an implicit “whatever fits in a regex.”
4. **Separation of concerns** — **Inventory** (what may appear) vs **normalization** (how to canonicalize before validation or TTS) vs **compiled outputs** (regex fragments, sorted code-point sets).
5. **Reproducible builds** — Generated files are produced by a deterministic script from the canonical data; consumers may use either the JSON or a generated artifact.

## 2. Non-goals

1. **Full linguistic well-formedness** — The repository does not define syllable structure, feature geometry, or phonological legality of sequences.
2. **Perfect “IPA-only” alignment with Unicode** — The Unicode Character Database does not expose an official `Is_IPA` property; “IPA-relevant” is **policy-defined** by this repository’s maintainers.
3. **Font or rendering** — No requirement to bundle fonts or guarantee glyph coverage.
4. **Audio or articulatory definitions** — Optional links or notes may appear in metadata; articulatory data is out of core scope.

## 3. Repository layout

```
.
├── README.md                 # Human overview, policy summary, install for consumers
├── LICENSE                   # SPDX-identified license (recommend permissive: MIT OR Apache-2.0)
├── CHANGELOG.md              # Follows Keep a Changelog; ties releases to dataset version
├── CONTRIBUTING.md           # How to propose code points, normalization entries, and policy changes
│
├── schema/                   # JSON Schema files (draft 2020-12 or later)
│   ├── inventory.meta.schema.json
│   ├── inventory.code_points.schema.json
│   └── normalization.rules.schema.json
│
├── data/                     # Canonical source (edited by humans + reviewed PRs)
│   ├── inventory.json        # Required: allowed code points + meta
│   └── normalization.json    # Optional: ligature/legacy → preferred sequences
│
├── scripts/                  # Build tooling (any language; document runtime in README)
│   └── build.(js|py|php)     # Reads data/, writes build/output/
│
├── build/                    # Gitignored locally; populated in CI and attached to releases
│   └── output/
│       ├── inventory.min.json
│       ├── code_points.txt           # One U+XXXX per line (optional)
│       ├── pcre-class-fragment.txt   # Body of a character class for PCRE
│       ├── php/
│       │   └── AllowedCodePoints.php # Optional: const array for fast lookup
│       └── manifest.json             # Hashes, dataset version, generator version
│
├── .github/workflows/        # CI: validate JSON against schema, run build, diff check
└── tests/                    # Fixture strings + expected pass/fail for reference validator
```

**Release artifacts:** GitHub (or similar) **releases** MUST attach at minimum `inventory.min.json`, `manifest.json`, and `pcre-class-fragment.txt` so consumers can pin a version without cloning the full repo.

## 4. Canonical data formats

### 4.1 `data/inventory.json`

**Top-level structure:**

| Key            | Type   | Required | Description |
|----------------|--------|----------|-------------|
| `$schema`      | string | Recommended | URI of `inventory` wrapper schema (if supported by tooling). |
| `meta`         | object | Yes      | See §4.2. |
| `code_points`  | array  | Yes      | Ordered list of objects; see §4.3. |

**Ordering:** `code_points` SHOULD be sorted ascending by numeric code point value for stable diffs. Build scripts MAY emit a sorted copy regardless of source order.

### 4.2 `meta` object

| Field                 | Type   | Required | Description |
|-----------------------|--------|----------|-------------|
| `schema_version`      | string | Yes      | Semver of *this* spec’s data model (e.g. `1.0.0`), not Unicode version. |
| `dataset_version`     | string | Yes      | Semver for the dataset release (independent of `schema_version`). |
| `unicode_version_min` | string | Yes      | Minimum Unicode version the inventory was reviewed against (e.g. `15.1.0`). |
| `policy_id`           | string | Yes      | Stable slug: e.g. `ipa-chart-2020-plus-modifiers`. |
| `policy_title`        | string | Yes      | Short human title. |
| `policy_description`  | string | Yes      | Paragraph describing inclusion criteria and known exclusions. |
| `generated_at`        | string | No       | ISO-8601 UTC; set by CI on release builds, omitted in hand-edited drafts. |

### 4.3 `code_points[]` entry

Each element describes **one assigned Unicode scalar value** (BMP or supplementary). Surrogate pairs are not stored; use scalar values only.

| Field        | Type            | Required | Description |
|--------------|-----------------|----------|-------------|
| `cp`         | integer         | Yes      | Code point in **decimal** (JSON number), range `0`–`0x10FFFF`, excluding surrogates. |
| `cp_hex`     | string          | Yes      | Normalized form `U+` + uppercase hex, zero-padded to minimum 4 digits (e.g. `U+02A7`, `U+1D4F`). |
| `category`   | string          | Yes      | One of the **repository-defined** enum values (§5). |
| `aliases`    | array of string | No       | Optional alternate labels (e.g. `tesh`, `voiceless postalveolar affricate`). |
| `notes`      | string          | No       | Maintainer-facing rationale or caveat. |
| `deprecated` | boolean         | No       | If true, entry is **still allowed** for backward compatibility but SHOULD appear in CHANGELOG deprecation notices; default false. |

**Constraints:**

- `cp` and `cp_hex` MUST denote the same scalar value; CI MUST verify consistency.
- Duplicate `cp` values are **forbidden**.

### 4.4 `data/normalization.json` (optional)

Maps **input** scalar values (or short strings) to **preferred** output strings for canonicalization **before** allowlist checks or downstream pipelines.

**Top-level structure:**

| Key     | Type   | Required | Description |
|---------|--------|----------|-------------|
| `meta`  | object | Yes      | Same `schema_version` / `dataset_version` / `policy_id` as inventory or a dedicated `normalization_version`. |
| `rules` | array  | Yes      | Ordered list; see below. |

**`rules[]` entry:**

| Field        | Type   | Required | Description |
|--------------|--------|----------|-------------|
| `from`       | string | Yes      | Single Unicode character **or** a short literal string (e.g. ligature `ʧ`). |
| `to`         | string | Yes      | Replacement string (often multiple scalars, e.g. `t` + `ʃ`). |
| `id`         | string | Yes      | Stable slug: `normalize-tesh-ligature`. |
| `notes`      | string | No       | Why this rule exists (e.g. “align with IPA chart spelling; ligature retired”). |

**Application order:** Rules SHOULD be applied **longest-match-first** if `from` lengths vary; if all `from` are single characters, order is the array order. The spec RECOMMENDS single-character `from` for simplicity.

**Safety:** Rules MUST NOT introduce scalars not present in `inventory.json` unless documented as intentional (e.g. expanding to ASCII `t` that is separately listed). CI SHOULD verify every scalar in every `to` string appears in the inventory (or in an explicit “implicit ASCII letters” annex documented in policy).

## 5. `category` enum (normative for v1)

Maintainers MAY extend the enum in a **minor** `schema_version` bump. Consumers MUST treat unknown categories as opaque unless they opt into handling.

**Suggested initial values:**

| Value              | Meaning |
|--------------------|---------|
| `letter`           | Core IPA / phonetic letters (including precomposed digraph letters if policy allows). |
| `modifier_letter`  | Spacing modifier letters routinely used in IPA (e.g. aspiration, labialization). |
| `combining_mark`   | Combining diacritics permitted above/below base letters. |
| `suprasegmental`   | Stress, length, intonation-related symbols (including tie bars if listed as scalars). |
| `tone`             | Tone letters / marks when included by policy. |
| `delimiter`        | Phonemic / phonetic brackets and slashes **only if** policy allows them in-band (often excluded). |
| `connector`        | Tie bar U+0361, double breve, etc., when not classified under `combining_mark`. |
| `diacritic_spacing`| Spacing clones of diacritics if explicitly included. |
| `other`            | Escape hatch; use sparingly and document in `notes`. |

## 6. JSON Schema requirements

1. All canonical files under `data/` MUST validate against their schemas in `schema/` on every PR and `main` commit.
2. Schemas SHOULD use **draft 2020-12** (or organization standard) and be referenced by `$id` URLs (either raw GitHub URLs or `https://example.org/ipa-inventory/...` if published).
3. CI MUST fail if:
   - Any `cp` is a surrogate or out of range.
   - `cp` / `cp_hex` mismatch.
   - Duplicate `cp`.
   - Normalization `to` strings contain scalars not allowed by policy (per §4.4).

## 7. Build outputs (`build/output/`)

### 7.1 `pcre-class-fragment.txt`

A **single line** (or newline-terminated) string suitable for insertion into a PCRE character class, e.g.:

```text
...contents only, no surrounding [ ]...
```

Build script MUST:

- Escape `]`, `\`, `-`, `^` at class start as required by PCRE rules.
- Emit Unicode code points as `\x{HH...}` or literal UTF-8 depending on documented consumer; **document the encoding assumption** (UTF-8 pattern with `/u` in PHP).

### 7.2 `inventory.min.json`

Minified `inventory.json` for embedding or HTTP fetch.

### 7.3 `manifest.json`

| Field               | Type   | Description |
|---------------------|--------|-------------|
| `dataset_version`   | string | Copy from `meta`. |
| `schema_version`    | string | Copy from `meta`. |
| `files`             | object | Map filename → SHA-256 hex digest. |
| `generator`         | object | `name`, `version`, `commit`. |

### 7.4 Optional `php/AllowedCodePoints.php`

Associative array `0xNNN => true` or `SplFixedArray` / bitmask documentation — **implementation detail**; MUST be generated only, never hand-edited.

## 8. Versioning

1. **`schema_version` (semver):** Bump **MAJOR** for breaking structural or semantic changes (removed required fields, renamed `category` values without migration path).
2. **`dataset_version` (semver):** Bump **MINOR** for added code points or non-breaking normalization additions; **PATCH** for typo/notes-only; **MAJOR** for removed code points or backward-incompatible normalization.
3. **Git tags:** `v1.2.3` MUST match `dataset_version` for that tag (or document mapping if they diverge — not recommended).

## 9. Testing

1. **Schema tests** — Validate golden `data/*.json` files.
2. **Golden build tests** — After build, `manifest.json` hashes match committed golden outputs **or** CI regenerates and fails on unexpected diff (choose one strategy and document).
3. **Reference validator tests** — Given strings, expected pass/fail after optional normalization, using a minimal reference implementation in the repo (language agnostic preferred: e.g. Node or Python script invoked in CI).

## 10. Consumer integration (informative)

### 10.1 Laravel / Statamic

- Depend on the package via Composer **or** copy release assets into `storage/app/ipa/` on deploy.
- Load `inventory.min.json` once; cache a **set of integers** in memory or `Cache::rememberForever` keyed by `dataset_version`.
- Validation: after optional normalization using `normalization.json`, verify every scalar in the string is in the set (or use generated `pcre-class-fragment.txt` inside `/^...$/u`).

### 10.2 Other stacks

- **JavaScript:** import JSON; build `Set` of code points.
- **Go:** `embed` the minified JSON or compile a generated `.go` slice.

## 11. Licensing and attribution

1. Repository **data** is factual (Unicode code points are not copyrightable as facts); still apply a **clear license** for the JSON structure and build scripts.
2. README SHOULD cite **Unicode Consortium** as the authority for scalar definitions and link to the applicable **Unicode Standard** version used for review.

## 12. Security and supply chain

1. Release artifacts MUST be built on CI from tagged commits; **do not** hand-upload mismatched JSON.
2. `manifest.json` allows consumers to verify integrity after download.

---

## Document history

| Version | Date       | Summary                    |
|---------|------------|----------------------------|
| 1.0.0   | (this doc) | Initial repository spec.   |
