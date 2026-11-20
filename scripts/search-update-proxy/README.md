# Search update proxy

A repeatable harness that times `statamic:search:update --all` over a launch-sized content tree, without the migrated content (UAMS-Web/uams-statamic#971; built to be reused by #967).

**The figures it produces are a proxy, and must be labelled as one wherever they are quoted.** They do not establish whether #161's thresholds are met or missed. A run against the real migrated content does that, and also calibrates this proxy.

## What it builds

`generate.php` writes a scratch content tree:

- A copy of `content/`, leaving out the `.git` link and the entries and trees of the nine collections the harness generates (`pages`, `posts`, `people`, `knowledge_bases`, `classifieds`, `clinical_providers`, `clinical_resources`, `clinical_locations`, `clinical_expertise`). Everything else (taxonomies, globals, navigation, the other collections) is copied as it is.
- One entry per published WordPress item in the export corpus, counted as #511 counted them:
  - a post type maps to a collection as the importer maps it (`page`, `post`, `uamspeople` and `tshowcase`, `kb`, `advert`);
  - published means `wp:status` is `publish` and `wp:post_password` is empty;
  - an item is the same entry when collection, site (`wp:base_blog_url`), slug and post id all match.
- The rows #511 read from the live `uamshealth.com` site, which the corpus does not hold (`LIVE_SITE_ROWS` in `SearchUpdateProxyGenerator.php`). They reuse the corpus text, cycled, on the `health` site.

Each entry carries its real title and text, in its blueprint's own shapes (Bard paragraphs for `content` and `article`, a `person_name` for people, plain text for an excerpt), and only the fields the search indexes read. The site a corpus item lands on is fixed per WordPress site, so a run is deterministic.

It also writes `manifest.json`: the counts written per collection and source, and the corpus, application and `content/` revisions.

## What it measures

`measure.php` boots the application with every Stache store, the file cache, the Loupe index path and the local search path moved under the scratch directory. The run therefore reads only the proxy tree and writes only under it; `content/`, the application's own cache and its indexes are not touched, and it refuses to run if any store still points at `content/`. It then runs, in-process:

1. `statamic:stache:warm`, timed separately, so the Stache build is not counted as indexing;
2. `statamic:search:update --all`, the real read, augment, transform and insert path.

It writes `results.json`: both timings and exit codes, peak memory, each index's document count and size with and without the SQLite write-ahead log, PHP, SQLite and OS versions, and the locked versions of `statamic/cms`, `daun/statamic-loupe`, `loupe/loupe` and `laravel/framework`.

Neither script makes a network request, runs an import, or talks to any server.

## Running it

```bash
php -d memory_limit=-1 scripts/search-update-proxy/generate.php \
  --corpus="<wordpress-importer-exports checkout>/WordPress" \
  --out="<a new scratch directory>"

php -d memory_limit=-1 scripts/search-update-proxy/measure.php --root="<the same directory>"
```

- `--out` must not exist yet; each measurement needs a freshly generated tree, and `measure.php` refuses one it has already measured.
- `--limit=N` caps every collection at N entries, for a quick check of the pipeline before a full run.
- A full run writes about 47,000 entries and several gigabytes of index. Put the scratch directory on a disk with room, and bound the run (see the `long-running-commands` rule).

## Reusing it

The generator's selection, shaping and copying are static methods on `SearchUpdateProxyGenerator`, covered by `tests/Unit/Scripts/SearchUpdateProxyGeneratorTest.php`. To measure a different configuration (another `max_total_hits`, a changed index, a driver swap), change the application's configuration on a branch and rerun both scripts. The generated tree does not depend on the search configuration.
