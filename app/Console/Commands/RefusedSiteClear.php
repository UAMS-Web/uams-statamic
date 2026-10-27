<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Providers\SiteClearGuardServiceProvider;
use Statamic\Console\Commands\SiteClear;
use Statamic\Console\RunsInPlease;

/**
 * A `statamic:site:clear` that refuses to run in this application (UAMS-Web/uams-statamic-archive#2022).
 *
 * Statamic's command is written for a scratch site. Here it is a loaded gun
 * pointed at tracked git submodules: one invocation clears six `content/`
 * directories (the **content** submodule) and `users/` (the **users**
 * submodule), wipes `resources/{views,blueprints,fieldsets}`,
 * `resources/forms` and `storage/forms`, replaces `config/statamic` with
 * Statamic's defaults, and then — for every asset container whose disk is not
 * one of `local`, `public`, `s3`, `assets` — regex-strips that disk's stanza
 * out of `config/filesystems.php` and deletes its root directory.
 *
 * That last filter protects nothing here. **All eleven** of this app's asset
 * containers use a bespoke disk name, every one is `driver: local`, and every
 * root lives inside a tracked submodule — nine under `public/assets/`, two
 * under `app/private/assets/`. So the "skip the default disks" guard skips
 * none of ours.
 *
 * **The confirmation prompt is not a safety net; it is the opposite.**
 * `SiteClear::shouldAbort()` returns `false` — meaning *proceed* — as soon as
 * `--no-interaction` is present, and [`AGENTS.md`](../../../AGENTS.md)
 * instructs every agent to pass that flag to all Artisan commands. The house
 * convention therefore disarms the only thing standing between a routine
 * command invocation and a destroyed working tree.
 *
 * Refusal is absolute and carries no escape hatch. An env flag was rejected
 * because `--no-interaction` would satisfy it and because slot provisioning
 * copies `.env` between worktrees, so enabling it once would propagate it
 * everywhere. Standing up a genuine scratch site is better served by a fresh
 * Statamic install than by leaving this path live here.
 *
 * Bound over the parent in {@see SiteClearGuardServiceProvider}.
 * Overriding `handle()` rather than the signature is deliberate: the command
 * stays visible in `php artisan list`, so the refusal reads as a decision
 * rather than as a broken install.
 *
 * **`RunsInPlease` is re-declared here, and it is load-bearing.** The parent
 * already uses it, but `Statamic\Console\Please\Application::resolve()` gates
 * on `class_uses($command)`, which reports only the traits a class declares
 * **itself** — never the ones it inherits. Without this line the subclass is
 * pushed onto the deferred pile instead of registered, `site:clear` disappears
 * from `php please`, and Symfony's namespace abbreviation quietly resolves
 * `please site:clear` to `sitemapamic:clear` instead. Verified by running it:
 * the guard reported success and cleared a sitemap cache.
 */
final class RefusedSiteClear extends SiteClear
{
    use RunsInPlease;

    /**
     * Refuse, and say why, without touching the filesystem.
     *
     * Every destructive step in the parent lives behind its `handle()`, so
     * overriding this one method is sufficient — nothing is inherited that runs
     * on its own.
     */
    #[\Override]
    public function handle(): int
    {
        $this->components->error('statamic:site:clear is disabled in this application.');

        $this->components->bulletList([
            'It clears the tracked `content/` and `users/` submodules, and every `resources/` tree.',
            'It deletes all 11 asset-container disk roots, inside `public/assets/` and `app/private/assets/`.',
            'It rewrites `config/filesystems.php`, removing each of those disks.',
            '`--no-interaction` does NOT protect you — it skips the confirmation and proceeds.',
        ]);

        $this->components->warn('There is no override. To start a genuinely fresh site, install Statamic somewhere else.');

        return self::FAILURE;
    }
}
