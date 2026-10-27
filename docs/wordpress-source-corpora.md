# The WordPress source corpora

Almost every importer, blueprint, and migration decision in this project is ultimately a claim about **what the WordPress sites actually contain**. Two corpora answer that, and neither lives in this repository. Reach for them before guessing, and cite them when you record a finding.

| Corpus | What it is | What it answers |
| --- | --- | --- |
| A local **`uamshealthmu`** WordPress install | The plugin and theme *source* the sites run on | "Which plugin owns this shortcode?" · "What shape does this custom post type have?" · "What does this block actually render?" |
| **[`UAMS-Web/wordpress-importer-exports`](https://github.com/UAMS-Web/wordpress-importer-exports)** | The exported production *content* — WXR per site, plus every form export | "Is this feature actually used?" · "How many entries would this affect?" · "What does real content look like here?" |

They answer different questions and are not interchangeable. The install tells you how a thing works; the corpus tells you whether anyone uses it.

## Paths are per-machine, so this file does not name one

Both corpora sit outside the repository, at a location that differs per developer and per platform. Per the **Supported development platforms** rule in [`AGENTS.md`](../AGENTS.md), no absolute path from one checkout belongs in tracked prose. Record your own locations in your git-ignored `.claude/settings.local.json` as read permissions, which is where an agent will look and what lets it read them without prompting:

```jsonc
{
  "permissions": {
    "allow": [
      "Read(/absolute/path/to/uamshealthmu/**)",
      "Read(/absolute/path/to/wordpress-importer-exports/**)"
    ]
  }
}
```

**No local WordPress install yet?** The team's [`wp-to-herd`](https://github.com/UAMS-Web/uams-claude-skills/tree/main/wp-to-herd) skill stands one up in Laravel Herd on Windows or macOS, from either a Local by Flywheel directory or a BigScoots production backup. It covers the multisite case specifically, which is what these sites are, and ends with running a WXR export. It lives in [`UAMS-Web/uams-claude-skills`](https://github.com/UAMS-Web/uams-claude-skills), a separate private repo installed opt-in — that repo's `install.sh` puts its skills where Claude will find them.

## The install: `uamshealthmu`

A local copy of the multisite network. Its value is `wp-content/`:

- **`themes/uams-2020`** and **`themes/uams-2016`** — the two themes in play. Not every site runs `uams-2020`; `patientslearn.uams.edu` and `news.uams.edu` are on `uams-2016` lineage, and assuming otherwise has produced wrong conclusions before.
- **`plugins/`** — roughly 140 directories. This is where a shortcode, a post type, or a block gets traced to an owner.

Two habits worth having:

- **A shortcode's tag often does not match its class or plugin name.** `UAMSWP-shortcodes-ui` derives its tags from class names, so `class-basebutton.php` registers `[basebutton]` and a naive `add_shortcode('basebutton'` grep finds nothing. Search the registration mechanism, not just the literal tag.
- **Two unrelated plugins can register the same tag.** Confirm ownership by the *attributes in use*, not the name alone. `[iframe_loader]` is registered both by the Articulate embed plugin and by an unrelated iframe plugin; only the former accepts a `type=` attribute, which is what settles it for this corpus.

## The corpus: `wordpress-importer-exports`

The real exported production content, not samples.

- **`WordPress/`** — one WXR per site, roughly 262 files, named `wordpress-export_<site>.xml`.
- **`GravityForms/`** — one JSON per site, roughly 163 files. **`FormidableForms/`** and **`NinjaForms/`** likewise.
- **`UAMSWP-MinervaKB/`** — the knowledge-base export.
- **Pre-computed audits** — `audit/forms/all_forms.csv`, `gravityforms-export_consolidated_fields.json`, and `WordPressExports.yaml`.

### Reading a WXR without being misled

- **An export can be invalid XML.** WordPress serializes attachment metadata verbatim, including raw control bytes from media files, which a conforming parser rejects outright. The importer already handles this through `WxrSanitizedStream`; anything else you point at the file may not. Strip control characters before parsing ad hoc.
- **A strict parser stopping early looks exactly like a short export.** If a count seems low, confirm the parse reached the end of the file before trusting it.
- **Grep the whole corpus, not one site.** "Is this feature used?" is usually a question about all 262 exports, and the answer is often "on two sites" or "nowhere".
- **The export carries references, not files.** Anything unzipped on disk rather than uploaded to the media library — e.g. e-learning packages — appears as a path and nothing else. Sizing that content means looking at the server, not the export.
- **Some data lives in tables WXR never exports.** Quiz answers, user progress, and form add-on feeds are examples. When an export seems to be missing something, check whether it was ever in scope for the format before concluding the data is gone.

## Where it lands

This file names the corpora and how to reach them. It does not restate what is in the sites — that belongs to the `docs/input_*.md` specs and to the issues that measured it — nor how to stand up a WordPress install, which is [`wp-to-herd`](https://github.com/UAMS-Web/uams-claude-skills/tree/main/wp-to-herd)'s job.
