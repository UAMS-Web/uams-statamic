<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Http\Middleware\ProtectEntry;
use App\Jobs\ApplyScheduledPublishing;
use Carbon\CarbonInterface;
use Statamic\Contracts\Auth\User;
use Statamic\Data\HasDirtyState;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaving;
use Statamic\Facades\User as UserFacade;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;

/**
 * Reconciles Statamic's native `published` boolean with the WordPress-style
 * `status_and_visibility` field on save, and enforces the publish permission (UAMS-Web/uams-statamic-archive#223).
 *
 * The `uamsweb_common_status` fieldset offers a richer status than Statamic's
 * publish toggle — `draft`, `pending`, `scheduled`, `published`, `private`.
 * `status_and_visibility` is the source of truth for the live state, but the
 * native `published` toggle and Unpublish action still exist and legacy content
 * predates the field, so the reconciliation has to consider *what changed* this
 * save (via {@see HasDirtyState}), not just the current value:
 *
 *   1. **Honor a direct `published` change.** The native publish toggle / Unpublish
 *      action flips `published` without touching the status field. When `published`
 *      is dirty and the status is not, that explicit intent wins and the status is
 *      brought back in line (so the two never split-brain).
 *   2. **Protect legacy content.** An entry authored before the field was added
 *      carries no stored `status_and_visibility`, so the CP injects the field's
 *      `draft` default on an unrelated edit. That must not silently unpublish
 *      already-live content, so a still-published legacy entry adopts `published`
 *      rather than the injected default.
 *   3. **Enforce the publish permission.** A signed-in user who lacks
 *      `publish {collection} entries` (e.g. the `contributor` role) may only *set*
 *      draft/pending: a live status they deliberately choose is downgraded to
 *      `pending` ("submit for review"). Merely editing an entry whose live status
 *      they did not change is left alone. Super users, users with the permission,
 *      and user-less saves (CLI / the WordPress importer) are never downgraded.
 *   4. **Map the (possibly adjusted) status to `published`**: `published` /
 *      `private` / `scheduled` → live (`private` is then role-gated by
 *      {@see ProtectEntry}); `draft` / `pending` → not live.
 *   5. **Reconcile the scheduled go-live date (UAMS-Web/uams-statamic-archive#1162).** A `scheduled` entry is
 *      withheld until its date by the collection's `date_behavior: future:
 *      private`, but Statamic's date-gating keys off the collection `date` field
 *      (default `now`), while the fieldset stores the editor-chosen date in a
 *      separate `date_scheduled` field. On a dated collection, set the entry date
 *      from `date_scheduled` so the withhold-then-go-live actually keys off the
 *      chosen date. (`knowledge_bases` carries the companion `date_behavior` in
 *      the content submodule; `pages` and `people` are undated and schedule
 *      with `publish_at` / `unpublish_at` instead, UAMS-Web/uams-statamic-archive#2552 and UAMS-Web/uams-statamic-archive#2564.)
 *
 * Before any of that, a `publish_at` / `unpublish_at` change (UAMS-Web/uams-statamic-archive#2552) by a user who
 * cannot publish is put back, since a scheduled publish is a deferred publish and the
 * job applying it saves with no user.
 *
 * An entry in a collection without the status fieldset carries no
 * `status_and_visibility` value and is left entirely untouched.
 */
class SyncEntryPublishedStatus
{
    /**
     * The statuses that map to Statamic's published (live) state.
     *
     * @var list<string>
     */
    private const array LIVE_STATUSES = ['published', 'private', 'scheduled'];

    /**
     * The `status_and_visibility` fieldset default, injected by the CP for an
     * entry that has no stored value (see `uamsweb_common_status.yaml`).
     */
    private const string DEFAULT_STATUS = 'draft';

    /**
     * Fields that schedule a later publish or unpublish, applied by
     * {@see ApplyScheduledPublishing} (UAMS-Web/uams-statamic-archive#2552, UAMS-Web/uams-statamic-archive#2564).
     *
     * @var list<string>
     */
    private const array SCHEDULE_FIELDS = ['publish_at', 'unpublish_at'];

    /**
     * Sync `published` from `status_and_visibility` before the entry persists.
     */
    public function handle(EntrySaving $event): void
    {
        /** @var Entry $entry */
        $entry = $event->entry;

        // (0) A scheduled publish or unpublish is a deferred publish, so only a user who
        // may publish now may schedule one; otherwise the job would publish on their
        // behalf and skip review (UAMS-Web/uams-statamic-archive#2552).
        $this->revertScheduleFromNonPublisher($entry);

        // Decide the status and the published state this save resolves to
        $resolution = $this->resolve($entry);

        // No status field on this collection: leave `published` exactly as-is.
        if ($resolution === null) {
            return;
        }

        // Apply the resolved status when it differs from the one on the entry
        if ($resolution['status'] !== $entry->value('status_and_visibility')) {
            $entry->set('status_and_visibility', $resolution['status']);
        }

        // Apply the resolved published state
        $entry->published($resolution['published']);

        // (5) A `scheduled` entry goes live on the editor-chosen date, but
        // Statamic's date_behavior gates on the collection `date` field
        // (default `now`), not the `date_scheduled` field the fieldset stores the
        // chosen date in. Reconcile them so the withhold-then-go-live keys off the
        // scheduled date (UAMS-Web/uams-statamic-archive#1162). A save decided by rule (1) or (2) leaves the
        // date alone, as it always has.
        if ($resolution['fromStatus'] && $resolution['status'] === 'scheduled') {
            $this->syncScheduledDate($entry);
        }
    }

    /**
     * Whether this save leaves the entry published, as `handle()` will decide it.
     *
     * Reads `status_and_visibility` and the changes this save makes rather than
     * `published()`, which `handle()` has not yet reconciled when another
     * `EntrySaving` listener runs before it (UAMS-Web/uams-statamic-archive#3240). An entry whose collection has
     * no status field keeps its own `published()`.
     *
     * @param  Entry  $entry  The entry being saved
     * @return bool True when the save leaves the entry published
     */
    public function willBePublished(Entry $entry): bool
    {
        return $this->resolve($entry)['published'] ?? $entry->published() === true;
    }

    /**
     * Decide the status and the published state a save resolves to.
     *
     * Null when the entry carries no `status_and_visibility` value, which leaves
     * `published` untouched. `fromStatus` is false when rule (1) or (2) decided
     * the save, so `published` came from the entry rather than from the status.
     *
     * @param  Entry  $entry  The entry being saved
     * @return ?array{status: string, published: bool, fromStatus: bool} The decision
     */
    private function resolve(Entry $entry): ?array
    {
        // `value()` (not `get()`) so a secondary-site localization resolves the
        // origin's value — `status_and_visibility` is `localizable: false`, so a
        // localization has no own copy and `get()` would return null.
        $status = $entry->value('status_and_visibility');

        // No status field on this collection
        if (! \is_string($status) || $status === '') {
            return null;
        }

        $statusDirty = $entry->isDirty('status_and_visibility');
        $publishedDirty = $entry->isDirty('published');

        // (1) A native publish toggle / Unpublish action changed `published`
        // directly without touching the status field. Honor that explicit intent
        // and reconcile the status so the two never split-brain.
        if ($publishedDirty && ! $statusDirty) {
            $published = $entry->published() === true;

            if ($published && ! $this->isLive($status)) {
                $status = 'published';
            } elseif (! $published && $this->isLive($status)) {
                $status = self::DEFAULT_STATUS;
            }

            return ['status' => $status, 'published' => $published, 'fromStatus' => false];
        }

        // (2) A legacy entry that predates the field has no stored status, so the
        // CP injects the `draft` default on an unrelated edit. Don't let that
        // unpublish already-live content: keep it published. A later deliberate
        // status change works normally (the value is then stored, so it is real).
        if ($this->isDefaultInjectedOntoLegacyPublished($entry, $status, $publishedDirty)) {
            // `fromStatus` only gates the scheduled-date sync, which needs a
            // `scheduled` status, so it cannot change this `published` result
            // @pest-mutate-ignore: FalseToTrue
            return ['status' => 'published', 'published' => true, 'fromStatus' => false];
        }

        // (3) Enforce the publish permission: a user who cannot publish this
        // collection may only *set* draft/pending. A live status they deliberately
        // choose is downgraded to "pending"; an already-live entry whose status
        // they did not change is left alone.
        if ($this->isLive($status) && $statusDirty && ! $this->canPublish($entry)) {
            $status = 'pending';
        }

        // (4) The (possibly adjusted) status drives `published`.
        return ['status' => $status, 'published' => $this->isLive($status), 'fromStatus' => true];
    }

    /**
     * Set a scheduled entry's collection date from the editor-chosen
     * `date_scheduled`, so date-gating keys off the go-live date rather than the
     * always-`now` collection `date` field.
     */
    private function syncScheduledDate(Entry $entry): void
    {
        $collection = $entry->collection();

        // The date only gates on a dated collection; the `date()` setter throws on
        // a non-dated one, so leave those entries (and their date) untouched.
        if (! $collection instanceof Collection || ! $collection->dated()) {
            return;
        }

        // On a CP save the Date fieldtype's `process()` stores `date_scheduled` as
        // a formatted string, and `value()` returns that raw string unaugmented —
        // so reading `value()` alone and checking `instanceof CarbonInterface`
        // would never gate (the sync would silently no-op for its primary use
        // case). Read it back through the field's `augment()`, which resolves both
        // a stored string (CP) and an already-`Carbon` value (importer /
        // programmatic) to a Carbon.
        $blueprint = $entry->blueprint();

        if (! $blueprint instanceof Blueprint) {
            return;
        }

        $field = $blueprint->field('date_scheduled');

        if (! $field instanceof Field) {
            return;
        }

        $fieldtype = $field->fieldtype();

        if (! $fieldtype instanceof Fieldtype) {
            return;
        }

        // An empty/unset value augments to null and leaves the date as-is.
        $scheduled = $fieldtype->augment($entry->value('date_scheduled'));

        if (! $scheduled instanceof CarbonInterface) {
            return;
        }

        $entry->date($scheduled);
    }

    /**
     * Whether this save is the CP injecting the `draft` default onto a legacy
     * entry that has no stored status but is already published — the case that
     * must not silently unpublish live content.
     */
    private function isDefaultInjectedOntoLegacyPublished(Entry $entry, string $status, bool $publishedDirty): bool
    {
        if ($status !== self::DEFAULT_STATUS || $publishedDirty) {
            return false;
        }

        if ($entry->getOriginal('published') !== true) {
            return false;
        }

        $originalStatus = $entry->getOriginal('status_and_visibility');

        return ! \is_string($originalStatus) || $originalStatus === '';
    }

    /**
     * Put back any `publish_at` / `unpublish_at` change made by a user who cannot
     * publish the entry's collection, so the stored schedule is always one a
     * publisher set.
     */
    private function revertScheduleFromNonPublisher(Entry $entry): void
    {
        // Collect the schedule fields this save changed. Compared directly rather than with
        // `isDirty()`, which reads a removed data key as unchanged (null against null).
        $changed = array_values(array_filter(
            self::SCHEDULE_FIELDS,
            fn (string $field): bool => $entry->get($field) !== $entry->getOriginal($field),
        ));

        if ($changed === [] || $this->canPublish($entry)) {
            return;
        }

        // Restore each to its stored value, removing one that was not stored before. Clearing
        // a `publish_at` withdraws a publish rather than making one, so it is allowed.
        foreach ($changed as $field) {
            if ($field === 'publish_at' && $entry->get($field) === null) {
                continue;
            }

            $original = $entry->getOriginal($field);

            if ($original === null) {
                $entry->remove($field);
            } else {
                $entry->set($field, $original);
            }
        }
    }

    /**
     * Whether a status maps to Statamic's published (live) state.
     */
    private function isLive(string $status): bool
    {
        return \in_array($status, self::LIVE_STATUSES, true);
    }

    /**
     * Whether the current actor may publish the entry's collection.
     *
     * A user-less save (CLI / import / programmatic) and a super user always may;
     * otherwise the user must hold `publish {collection} entries`.
     */
    private function canPublish(Entry $entry): bool
    {
        $user = UserFacade::current();

        if (! $user instanceof User) {
            return true;
        }

        if ($user->isSuper()) {
            return true;
        }

        $collection = $entry->collection();

        if (! $collection instanceof Collection) {
            return true;
        }

        $handle = $collection->handle();

        return ! \is_string($handle) || $user->hasPermission('publish '.$handle.' entries');
    }
}
