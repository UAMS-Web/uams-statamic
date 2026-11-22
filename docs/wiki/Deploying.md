Each deploy pulls the site repository, brings the submodules to the commits it records, installs the dependencies, builds the front end, and refreshes Statamic's caches. These are the scripts for two deploy tools, Ploi and Forge. Each tool fills in the placeholders (`{SITE_DIRECTORY}`, `$FORGE_SITE_PATH` and the rest) for the site it deploys.

## Steps

1. **Skip the deploy for a commit made on the server.** A commit whose message carries `[BOT]` was made on production, so there is nothing to deploy and the script exits.
2. **Pull and update the submodules:** `git pull`, then `git submodule update --init --recursive` (see [Git submodules](Git-submodules.md)).
3. **Install the PHP dependencies** without the development packages.
4. **Pin npm, then install and build the front end.** `.npmrc` sets `engine-strict` against `engines.npm >= 11.10.0`, and Node 22 bundles npm 10.9.x, so `npm ci` fails with `EBADENGINE` unless npm is raised first.
5. **Reload PHP-FPM.**
6. **Refresh the caches:** the application, configuration and route caches, the Stache, the queue workers, the search indexes and the static cache.

**Note:** pending changes to `people` entries live under `storage/`. Both scripts deploy into the same directory, so they keep them. A deploy that builds each release in a fresh directory has to share that folder across releases; see [Working copies of people entries](Working-copies-of-people-entries.md).

## Ploi

```bash
if [[ {COMMIT_MESSAGE} =~ "[BOT]" ]] && [[ {DEPLOYMENT_SOURCE} == "quick-deploy" ]]; then
    echo "Automatically committed on production. Nothing to deploy."
    {DO_NOT_NOTIFY}
    exit 0
fi

cd {SITE_DIRECTORY}
git pull origin {BRANCH}
git submodule update --init --recursive
{SITE_COMPOSER} install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# .npmrc sets engine-strict against engines.npm >= 11.10.0, and Node 22 bundles
# npm 10.9.x, so npm ci fails with EBADENGINE unless npm is pinned first.
npm install -g npm@^11.10.0
npm ci
npm run build

{RELOAD_PHP_FPM}

{SITE_PHP} artisan cache:clear
{SITE_PHP} artisan config:cache
{SITE_PHP} artisan route:cache
{SITE_PHP} artisan statamic:stache:warm
{SITE_PHP} artisan queue:restart
{SITE_PHP} artisan statamic:search:update --all
{SITE_PHP} artisan statamic:static:clear
{SITE_PHP} artisan statamic:static:warm --queue

echo "Application deployed!"
```

## Forge

```bash
if [[ $FORGE_QUICK_DEPLOY == 1 ]]; then
    if [[ $FORGE_DEPLOY_MESSAGE =~ "[BOT]" ]]; then
        echo "Automatically committed on production. Nothing to deploy."
        exit 0
    fi
fi

cd $FORGE_SITE_PATH
git pull origin $FORGE_SITE_BRANCH
git submodule update --init --recursive
$FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# .npmrc sets engine-strict against engines.npm >= 11.10.0, and Node 22 bundles
# npm 10.9.x, so npm ci fails with EBADENGINE unless npm is pinned first.
npm install -g npm@^11.10.0
npm ci
npm run build

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

$FORGE_PHP artisan cache:clear
$FORGE_PHP artisan config:cache
$FORGE_PHP artisan route:cache
$FORGE_PHP artisan statamic:stache:warm
$FORGE_PHP artisan queue:restart
$FORGE_PHP artisan statamic:search:update --all
$FORGE_PHP artisan statamic:static:clear
$FORGE_PHP artisan statamic:static:warm --queue
```

## Reference

- [Web server configuration](Web-server-configuration.md): the NGINX cache settings that go with a deploy.
- [Git submodules](Git-submodules.md)
- [Working copies of people entries](Working-copies-of-people-entries.md)
