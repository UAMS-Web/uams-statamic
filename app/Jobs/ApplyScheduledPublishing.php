<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Listeners\SyncEntryPublishedStatus;
use App\Support\Collections\CollectionExtensions;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;
use Statamic\Stache\Query\EntryQueryBuilder;
use Throwable;

/**
 * Publishes and unpublishes entries on their scheduled times, for the undated
 * collections that schedule this way: `people` (UAMS-Web/uams-statamic-archive#2552) and `pages` (UAMS-Web/uams-statamic-archive#2564). `people`
 * takes part by registering through {@see CollectionExtensions} (UAMS-Web/uams-statamic-archive#3180).
 *
 * Those collections are undated, so Statamic's `date_behavior` cannot hold an entry
 * back until a go-live time. Their blueprints carry two optional fields instead,
 * `publish_at` and `unpublish_at`, and this job applies them: an entry whose
 * `publish_at` has passed is published, and one whose `unpublish_at` has passed is set
 * back to draft. When both have passed, the later of the two decides the outcome.
 *
 * A field is cleared once the job has applied it, so a schedule acts once. Without
 * that, an editor who unpublished an entry after its `publish_at` would see it
 * republished on the next run.
 *
 * The schedule fields are `localizable: false`, so only an origin carries them, and a
 * localization follows its origin (UAMS-Web/uams-statamic-archive#2621). A due publish reaches the localizations
 * without help here: saving the origin re-saves its direct descendants, and
 * {@see SyncEntryPublishedStatus} maps the shared `status_and_visibility` onto each one's
 * `published` flag. A due unpublish does not, because that listener keeps a live entry
 * live when it reads a `draft` it takes for a Control Panel default, so this job takes
 * each live localization down itself, writing only its `published` flag.
 *
 * Every transition is logged. The status is written through `status_and_visibility`,
 * which {@see SyncEntryPublishedStatus} maps to `published` on save,
 * and that listener only keeps a schedule a publisher set.
 *
 * Dispatched by the schedule in `routes/console.php` on the pattern
 * {@see UnpublishExpiredClassifieds} follows.
 */
final class ApplyScheduledPublishing implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The site's own collections whose entries this job schedules. Each is undated,
     * and its blueprints carry `publish_at` and `unpublish_at`.
     *
     * @var list<string>
     */
    public const array SITE_COLLECTIONS = ['pages'];

    /**
     * Statuses a scheduled publish may promote to `published`.
     *
     * `private` and `published` are already live, so a due `publish_at` on one of them
     * is cleared without a transition.
     *
     * @var list<string>
     */
    private const array PUBLISHABLE_STATUSES = ['draft', 'pending'];

    /**
     * Every collection this job schedules: those registered through
     * {@see CollectionExtensions}, then the site's own.
     *
     * @return list<string>
     */
    public static function collections(): array
    {
        $registered = app(CollectionExtensions::class)->scheduledPublishingCollections();

        return array_values(array_unique([...$registered, ...self::SITE_COLLECTIONS]));
    }

    public function handle(): void
    {
        $now = Carbon::now();

        // Read every entry with a schedule in the scheduled collections
        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->whereIn('collection', self::collections());

        /** @var Collection<int, Entry> $entries */
        $entries = $query->get();

        $scheduled = $entries->filter(
            fn (Entry $entry): bool => $entry->get('publish_at') !== null || $entry->get('unpublish_at') !== null,
        );

        $published = 0;
        $unpublished = 0;

        $failed = 0;

        foreach ($scheduled as $entry) {
            // One entry whose save throws must not hold back everyone after it on every run
            try {
                $actions = $this->apply($entry, $now);
            } catch (Throwable $exception) {
                $failed++;
                Log::error('Could not apply a scheduled publishing change.', [
                    'id' => $entry->id(),
                    'collection' => $entry->collectionHandle(),
                    'exception' => $exception->getMessage(),
                ]);

                continue;
            }

            foreach ($actions as $action) {
                if ($action === 'published') {
                    $published++;
                } else {
                    $unpublished++;
                }
            }
        }

        Log::info('Applied scheduled publishing.', [
            'published' => $published,
            'unpublished' => $unpublished,
            'failed' => $failed,
            'scheduled' => $scheduled->count(),
            'now' => $now->toIso8601String(),
        ]);
    }

    /**
     * Apply one entry's due schedule to it and its localizations, saving what changed.
     *
     * @param  Entry  $entry  The scheduled entry.
     * @param  CarbonInterface  $now  The time the run treats as now.
     * @return list<'published'|'unpublished'> The transitions made, the origin's first.
     */
    private function apply(Entry $entry, CarbonInterface $now): array
    {
        $publishAt = $this->scheduledTime($entry, 'publish_at');
        $unpublishAt = $this->scheduledTime($entry, 'unpublish_at');

        $publishDue = $publishAt instanceof CarbonInterface && $publishAt->lessThanOrEqualTo($now);
        $unpublishDue = $unpublishAt instanceof CarbonInterface && $unpublishAt->lessThanOrEqualTo($now);

        if (! $publishDue && ! $unpublishDue) {
            return [];
        }

        // Consume every due field, so a schedule acts once
        if ($publishDue) {
            $entry->remove('publish_at');
        }

        if ($unpublishDue) {
            $entry->remove('unpublish_at');
        }

        // When both are due, the later one is the state the editor asked to end in; at the
        // same instant the unpublish wins, so an entry is never left live by a tie
        $shouldPublish = $publishDue && (! $unpublishDue || $publishAt->greaterThan($unpublishAt));

        $action = $shouldPublish ? $this->publish($entry) : $this->unpublish($entry);

        // A full save, so caches, the search index and the status listener hear about it
        $entry->save();

        $actions = [];

        if ($action !== null) {
            $actions[] = $action;
            $this->logTransition($entry, $action, $publishAt, $unpublishAt);
        }

        // The origin's save has already carried a publish to its localizations
        if ($shouldPublish) {
            return $actions;
        }

        /** @var Collection<int, Entry> $localizations */
        $localizations = $entry->descendants();

        foreach ($localizations as $localization) {
            $localizationAction = $this->unpublishLocalization($localization);

            if ($localizationAction === null) {
                continue;
            }

            $localization->save();
            $actions[] = $localizationAction;
            $this->logTransition($localization, $localizationAction, $publishAt, $unpublishAt, $entry);
        }

        return $actions;
    }

    /**
     * Log one applied transition.
     *
     * @param  Entry  $entry  The entry whose status changed.
     * @param  'published'|'unpublished'  $action  The transition.
     * @param  CarbonInterface|null  $publishAt  The origin's due `publish_at`, if any.
     * @param  CarbonInterface|null  $unpublishAt  The origin's due `unpublish_at`, if any.
     * @param  Entry|null  $origin  The scheduled origin, when `$entry` is one of its localizations.
     */
    private function logTransition(
        Entry $entry,
        string $action,
        ?CarbonInterface $publishAt,
        ?CarbonInterface $unpublishAt,
        ?Entry $origin = null,
    ): void {
        Log::info('Applied a scheduled '.($action === 'published' ? 'publish' : 'unpublish').'.', array_filter([
            'id' => $entry->id(),
            'collection' => $entry->collectionHandle(),
            'site' => $entry->locale(),
            'origin' => $origin?->id(),
            'title' => $entry->value('title'),
            'action' => $action,
            'publish_at' => $publishAt?->toIso8601String(),
            'unpublish_at' => $unpublishAt?->toIso8601String(),
        ], fn (mixed $value): bool => $value !== null));
    }

    /**
     * Take a live localization down, following its origin's scheduled unpublish.
     *
     * @return 'unpublished'|null The transition, or null when it was not live.
     */
    private function unpublishLocalization(Entry $localization): ?string
    {
        if (! $localization->published()) {
            return null;
        }

        $localization->published(false);

        return 'unpublished';
    }

    /**
     * Publish an entry that is not yet live.
     *
     * @return 'published'|null The transition, or null when it was already live.
     */
    private function publish(Entry $entry): ?string
    {
        $status = $entry->get('status_and_visibility');

        if ($status !== null && ! \in_array($status, self::PUBLISHABLE_STATUSES, true)) {
            return null;
        }

        if ($status === null && $entry->published()) {
            return null;
        }

        $entry->set('status_and_visibility', 'published');
        $entry->published(true);

        return 'published';
    }

    /**
     * Set a live entry back to draft.
     *
     * @return 'unpublished'|null The transition, or null when it was not live.
     */
    private function unpublish(Entry $entry): ?string
    {
        if (! $entry->published()) {
            return null;
        }

        $entry->set('status_and_visibility', 'draft');
        $entry->published(false);

        return 'unpublished';
    }

    /**
     * Read a schedule field as a time, through the field's own fieldtype.
     *
     * A CP save stores the Date fieldtype's formatted string and a programmatic save may
     * store a Carbon, so the value is resolved with `augment()` as
     * {@see SyncEntryPublishedStatus} resolves `date_scheduled`.
     *
     * @param  Entry  $entry  The scheduled entry.
     * @param  string  $handle  `publish_at` or `unpublish_at`.
     */
    private function scheduledTime(Entry $entry, string $handle): ?CarbonInterface
    {
        $value = $entry->get($handle);

        if ($value === null || $value === '') {
            return null;
        }

        $blueprint = $entry->blueprint();
        $field = $blueprint instanceof Blueprint ? $blueprint->field($handle) : null;

        // A stored schedule the current blueprint cannot read would otherwise never apply
        if (! $field instanceof Field) {
            Log::warning('Ignored a stored schedule whose blueprint has no field for it.', [
                'id' => $entry->id(),
                'collection' => $entry->collectionHandle(),
                'blueprint' => $blueprint instanceof Blueprint ? $blueprint->handle() : null,
                'field' => $handle,
            ]);

            return null;
        }

        $fieldtype = $field->fieldtype();

        if (! $fieldtype instanceof Fieldtype) {
            return null;
        }

        $time = $fieldtype->augment($value);

        return $time instanceof CarbonInterface ? $time : null;
    }
}
