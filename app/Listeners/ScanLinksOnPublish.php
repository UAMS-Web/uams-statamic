<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Jobs\ScanEntryLinks;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaved;

/**
 * Dispatches a narrow link scan when a published entry is saved (UAMS-Web/uams-statamic-archive#1772).
 *
 * Closes the loop at the moment an editor can still act: a mistyped URL is caught while
 * the entry is in front of them rather than in a report a week later.
 *
 * Three properties this class exists to guarantee, each of which is a criterion:
 *
 *   NEVER BLOCKS THE SAVE      The listener dispatches and returns. It issues no outbound
 *                              request itself, so save latency does not depend on how many
 *                              hosts the entry links to or how slow they are.
 *   NEVER FAILS THE SAVE       Every throwable is caught and logged. A queue outage or a
 *                              guard refusal is a link-checking problem; surfacing it as a
 *                              save error would make an editor think their content was
 *                              lost. Publication must not depend on link checking working.
 *   DEBOUNCES                  Save-read-save is the normal editing rhythm. Without a
 *                              window, that dispatches a scan per button press and probes
 *                              every linked host at the speed someone edits.
 *
 * Caveat: on a `sync` connection the dispatch runs inline, so the save DOES wait — a property of
 * the connection rather than of this listener, and the safer of the two states while
 * UAMS-Web/uams-statamic-archive#2297 is open: under `database` with no worker the job is written to the table and never
 * executed, silently.
 */
final class ScanLinksOnPublish
{
    private const string CACHE_PREFIX = 'link-health:on-publish:';

    public function handle(EntrySaved $event): void
    {
        try {
            if (! config('link_health.on_publish.enabled')) {
                return;
            }

            /** @var Entry $entry */
            $entry = $event->entry;

            // An unpublished entry is not scanned (AC6). A draft's links are not yet
            // anyone's problem, and scanning them spends outbound requests on content
            // that may never ship.
            if (! $entry->published()) {
                return;
            }

            $id = $entry->id();
            $site = $entry->locale();
            $collection = $entry->collectionHandle();

            // Statamic declares no return type on any of these three, so each is `mixed`
            // to static analysis. The guard is a real one rather than a cast to quiet it:
            // an entry that cannot name itself, its site and its collection is not
            // something the scan can address, and dispatching a malformed job would move
            // the failure into a worker where no editor will ever see it.
            //
            // `locale()` rather than `site()->handle()`: the two are the same value by
            // construction -- Entry::site() is `Site::get($this->locale())` -- and the
            // flat call cannot fatal on an entry whose site is no longer configured,
            // where `site()` returns null and `->handle()` on it does.
            if (! is_scalar($id) || ! is_scalar($site) || ! is_scalar($collection)) {
                return;
            }

            $entryId = (string) $id;

            if (! $this->claimDebounceWindow($entryId)) {
                return;
            }

            // These two casts cannot be killed by a test, and the reason is worth recording
            // rather than rediscovering. PHPStan requires them: after is_scalar() the values
            // are `scalar`, and ScanEntryLinks declares `string` properties. At runtime the
            // cast is invisible, because Laravel constructs the job inside vendor code that
            // does not declare strict_types, so an int is coerced to a string with or without
            // it. The cast on $entryId above IS observable and is not annotated, since that
            // value also builds the debounce cache key.
            ScanEntryLinks::dispatch(
                $entryId,
                // Coerced to the same string without the cast, as above
                // @pest-mutate-ignore: RemoveStringCast
                (string) $site,
                // Coerced to the same string without the cast, as above
                // @pest-mutate-ignore: RemoveStringCast
                (string) $collection
            );
        } catch (\Throwable $throwable) {
            // Caught at the top level deliberately: this runs inside the save path, so
            // ANY throwable here — including one from reading the entry — would otherwise
            // reach the editor as a failed save.
            Log::warning('Could not dispatch on-publish link scan.', [
                'exception' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * Claim the debounce window for one entry, returning false when it is already held.
     *
     * `Cache::add()` rather than a has/put pair: it is atomic, so two saves racing in
     * different processes cannot both see an empty key and both dispatch. A has/put would
     * debounce the common case and let the actual concurrent case through, which is the
     * one worth guarding.
     */
    private function claimDebounceWindow(string $entryId): bool
    {
        $seconds = Config::integer('link_health.on_publish.debounce_seconds');

        if ($seconds <= 0) {
            return true;
        }

        // Three mutants on this line are equivalent rather than untested. Cache::add()
        // reports whether it claimed the key, not what was stored, so `true` to `false`
        // changes nothing observable; and dropping or reordering the prefix leaves a key
        // that is still unique per entry, so debouncing behaves identically. The prefix
        // guards against collision with another subsystem's keys, which no test in this
        // suite can produce.
        // @pest-mutate-ignore: TrueToFalse, ConcatRemoveLeft, ConcatSwitchSides
        return Cache::add(self::CACHE_PREFIX.$entryId, true, $seconds);
    }
}
