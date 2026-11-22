A change to a published `people` entry can be held as a working copy, which goes live only when an administrator publishes it. This page covers turning that on and keeping pending changes safe on a server.

## Turning it on

1. Set `STATAMIC_REVISIONS_ENABLED=true` in the environment's `.env`.
2. Rebuild the configuration cache (`php artisan config:cache`, which both [deploy scripts](Deploying.md) run).

`people` is the only collection with `revisions: true`, so no other collection changes. With the setting off, a change to a published `people` entry is saved to the entry directly.

## Where working copies live

Working copies and revision history are stored under `storage/statamic/revisions` ([`config/statamic/revisions.php`](https://github.com/UAMS-Web/uams-statamic/blob/main/config/statamic/revisions.php)). That folder is ignored by git and is outside the content repository, so:

- **They survive a deploy only if `storage/` does.** Both [deploy scripts](Deploying.md) pull into the same directory, so `storage/` stays in place. A deploy that builds each release in a fresh directory (zero-downtime deployment) must share `storage/statamic/revisions` across releases, or every pending change is lost on the next deploy.
- **They are not in the content repository's history.** Include `storage/statamic/revisions` in the server's backups; nothing else holds a change that is waiting to be published.

## Reference

- Decided in [UAMS-Web/uams-statamic-archive#3127](https://github.com/UAMS-Web/uams-statamic-archive/issues/3127) and [UAMS-Web/uams-statamic-archive#3184](https://github.com/UAMS-Web/uams-statamic-archive/issues/3184).
- [`config/statamic/revisions.php`](https://github.com/UAMS-Web/uams-statamic/blob/main/config/statamic/revisions.php)
