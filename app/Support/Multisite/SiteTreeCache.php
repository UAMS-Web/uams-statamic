<?php

declare(strict_types=1);

namespace App\Support\Multisite;

use App\Listeners\ForgetSiteTree;
use Illuminate\Support\Facades\Cache;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Facades\Stache;
use Statamic\Globals\Variables;
use Statamic\Sites\Site as SiteObject;
use Statamic\Stache\Stores\Store;

/**
 * The {@see SiteTreeSnapshot} for search, built from `uams_multisite_settings` and cached (#480).
 *
 * Building it reads every site's localization of the global set (255 `in()` reads). The result is
 * cached under {@see KEY} with a fingerprint of the files the global set is stored in, so no edit
 * reaches a request stale, by whatever route it arrives:
 *
 * - **The fingerprint** hashes the content of the global set's own file (the origins) and of each
 *   site's variables file (the `subject`s), keyed by path. A save from the Control Panel, a deploy and a hand
 *   edit all change a file, so the next request's fingerprint differs and the tree is rebuilt.
 * - **The events** forget the cache on a global set or variables save or delete, and on a Stache
 *   clear or warm ({@see ForgetSiteTree}). A deploy that changes the files before
 *   the Stache is refreshed rebuilds from the Stache it has; the refresh then forgets that build,
 *   so it is never kept past the Stache it came from.
 *
 * One instance serves a request (it is bound `scoped`), so the cache is read at most once.
 */
final class SiteTreeCache
{
    /**
     * The cache key the tree is stored under.
     */
    public const string KEY = 'multisite.search-site-tree';

    /**
     * Handle of the multisite-settings global set.
     */
    private const string GLOBAL_HANDLE = 'uams_multisite_settings';

    /**
     * This request's snapshot, once read.
     */
    private ?SiteTreeSnapshot $snapshot = null;

    /**
     * The tree as it stands, from the cache when its fingerprint still matches the files.
     */
    public function snapshot(): SiteTreeSnapshot
    {
        if ($this->snapshot instanceof SiteTreeSnapshot) {
            return $this->snapshot;
        }

        $fingerprint = $this->fingerprint();
        $cached = Cache::get(self::KEY);

        if (\is_array($cached) && ($cached['fingerprint'] ?? null) === $fingerprint && \is_array($cached['parents'] ?? null) && \is_array($cached['subjects'] ?? null)) {
            /** @var array<string, string|null> $parents */
            $parents = $cached['parents'];
            /** @var array<string, string|null> $subjects */
            $subjects = $cached['subjects'];

            return $this->snapshot = new SiteTreeSnapshot($parents, $subjects);
        }

        $snapshot = $this->build();
        Cache::forever(self::KEY, ['fingerprint' => $fingerprint, 'parents' => $snapshot->parents, 'subjects' => $snapshot->subjects]);

        return $this->snapshot = $snapshot;
    }

    /**
     * Forget the cached tree, so the next request builds it again.
     */
    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }

    /**
     * Read each configured site's parent and `subject` from the global set.
     */
    private function build(): SiteTreeSnapshot
    {
        $parents = [];
        $subjects = [];
        $set = GlobalSet::find(self::GLOBAL_HANDLE);
        $sites = Site::all();

        foreach (is_iterable($sites) ? $sites : [] as $site) {
            if (! $site instanceof SiteObject) {
                continue;
            }

            /** @var string $handle */
            $handle = $site->handle();
            $variables = $set?->in($handle);

            if (! $variables instanceof Variables) {
                $parents[$handle] = null;
                $subjects[$handle] = null;

                continue;
            }

            $origin = $variables->origin();
            $parents[$handle] = $origin instanceof Variables && \is_string($origin->locale()) ? $origin->locale() : null;

            // `value()` falls back to the origin's value, which `get()` does not
            $subject = $variables->value('subject');
            $subjects[$handle] = \is_string($subject) ? $subject : null;
        }

        return new SiteTreeSnapshot($parents, $subjects);
    }

    /**
     * A hash of every file the global set is stored in: its own file and each site's variables.
     */
    private function fingerprint(): string
    {
        $files = [
            $this->directory('globals').self::GLOBAL_HANDLE.'.yaml',
            ...(glob($this->directory('global-variables').'*/'.self::GLOBAL_HANDLE.'.yaml') ?: []),
        ];

        // Each file by its path, so contents moved between files change the hash too, and an absent
        // file as null rather than as empty
        $contents = [];

        foreach ($files as $file) {
            $contents[$file] = is_file($file) ? file_get_contents($file) : null;
        }

        return hash('xxh128', serialize($contents));
    }

    /**
     * The directory a Stache store reads its files from, which Statamic keeps with a trailing slash.
     *
     * @param  string  $store  The store's key.
     */
    private function directory(string $store): string
    {
        // The `globals` and `global-variables` stores are Statamic's own, so each is a file store
        /** @var Store $stache */
        $stache = Stache::store($store);
        /** @var string $directory */
        $directory = $stache->directory();

        return $directory;
    }
}
