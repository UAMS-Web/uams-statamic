<?php

declare(strict_types=1);

namespace App\Services\Podcasts\HealthTalk;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Reconciles the UAMS Health Talk `audio` entries with the DoctorPodcasting feed (UAMS-Web/uams-statamic-archive#1803).
 *
 * One run handles the four transitions the ticket names:
 *
 * - **New** -- a feed item with no entry is imported, but only once every
 *   speaker on its item page resolves. Otherwise nothing is created, not even
 *   a draft, and each unresolved speaker becomes a mapping row. Because of
 *   that, an unpublished entry can only mean "withdrawn from the feed".
 * - **Changed** -- an entry whose feed metadata differs has its feed-owned
 *   fields rewritten in place. Every other field, including the other credit
 *   roles and any segment rows an editor added, is left alone.
 * - **Departed** -- an entry whose id left the feed is unpublished, never deleted.
 * - **Returned** -- an unpublished entry whose id is back in the feed is re-published.
 *
 * Nothing changes when the feed cannot be fetched, is not 200, parses to zero
 * items, or would unpublish more entries than the configured threshold. Every
 * publication transition is logged with the episode id, title and direction.
 */
final class HealthTalkEpisodeSync
{
    /**
     * Cache key for the most recent run's report, completed or not.
     */
    public const string LAST_REPORT_CACHE_KEY = 'uams_health_talk.last_report';

    /**
     * Cache key for the most recent completed run's report, the one the backlog is read from.
     */
    public const string LAST_COMPLETED_REPORT_CACHE_KEY = 'uams_health_talk.last_completed_report';

    /**
     * Lock held for a run, so the schedule and the "Run sync now" button never overlap.
     */
    public const string LOCK_KEY = 'uams_health_talk.sync';

    /**
     * Seconds the lock outlives a run that died without releasing it.
     */
    private const int LOCK_SECONDS = 3600;

    /**
     * The destination's feed-owned fields, resolved once per run.
     */
    private ?HealthTalkEntryFields $fields = null;

    /**
     * @param  HealthTalkClient  $client  Fetches the feed and item pages
     * @param  HealthTalkFeedParser  $feedParser  Reads the feed
     * @param  HealthTalkItemPageParser  $pageParser  Reads an item page
     * @param  HealthTalkSpeakerMappings  $mappings  Resolves speakers to `people` entries
     * @param  LoggerInterface  $log  Receives every transition
     * @param  array{collection: string, blueprint: string, site: string, series: string}  $destination  Where episodes live
     * @param  int  $maxUnpublishPerRun  A run that would unpublish more aborts
     * @param  int  $pauseMs  Pause between item-page requests
     */
    public function __construct(
        private readonly HealthTalkClient $client,
        private readonly HealthTalkFeedParser $feedParser,
        private readonly HealthTalkItemPageParser $pageParser,
        private readonly HealthTalkSpeakerMappings $mappings,
        private readonly LoggerInterface $log,
        private readonly array $destination,
        private readonly int $maxUnpublishPerRun,
        private readonly int $pauseMs
    ) {}

    /**
     * Run one reconciliation and cache its report for the dashboard widget.
     *
     * A run that finds another already in progress changes nothing and caches
     * nothing, so the running one's report is the one the widget shows.
     */
    public function run(): HealthTalkSyncReport
    {
        $report = new HealthTalkSyncReport(CarbonImmutable::now());
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);

        // A second run would read the same feed and race the first one's saves
        if (! $lock->get()) {
            $report->aborted = 'Another UAMS Health Talk sync is already running.';
            $this->log->info($report->aborted);

            return $report;
        }

        try {
            $this->reconcile($report);
        } finally {
            $lock->release();
        }

        Cache::forever(self::LAST_REPORT_CACHE_KEY, $report->toArray());

        if ($report->completed()) {
            Cache::forever(self::LAST_COMPLETED_REPORT_CACHE_KEY, $report->toArray());
        }

        return $report;
    }

    /**
     * Apply every transition the feed calls for, or abort before the first one.
     */
    private function reconcile(
        HealthTalkSyncReport $report
    ): void {
        try {
            $items = $this->feedParser->parse($this->client->feed());
        } catch (HealthTalkSourceException $healthTalkSourceException) {
            $this->abort($report, $healthTalkSourceException->getMessage());

            return;
        }

        $report->feedItems = \count($items);

        try {
            $this->fields = HealthTalkEntryFields::for($this->destination['collection'], $this->destination['blueprint']);
        } catch (HealthTalkSourceException $healthTalkSourceException) {
            $this->abort($report, $healthTalkSourceException->getMessage());

            return;
        }

        if ($items === []) {
            $this->abort($report, 'The UAMS Health Talk feed parsed to zero items.');

            return;
        }

        // Without the set, deferred speakers could not be recorded and would defer unseen forever
        if (! $this->mappings->exists()) {
            $this->abort($report, 'The speaker mapping global set does not exist, so no speaker could be recorded; nothing was changed.');

            return;
        }

        $entries = $this->entriesBySourceId();
        $inFeed = \array_fill_keys(\array_map(static fn (HealthTalkFeedItem $item): string => $item->sourceId, $items), true);

        $departed = \array_filter(
            $entries,
            static fn (Entry $entry, int|string $sourceId): bool => ! isset($inFeed[$sourceId]) && $entry->published(),
            ARRAY_FILTER_USE_BOTH
        );

        // A drop larger than a clinician's worth of episodes is more likely a broken feed
        if (\count($departed) > $this->maxUnpublishPerRun) {
            $this->abort($report, \sprintf(
                'The run would unpublish %d UAMS Health Talk episodes, over the limit of %d; nothing was changed.',
                \count($departed),
                $this->maxUnpublishPerRun
            ));

            return;
        }

        $fetched = 0;

        foreach ($items as $item) {
            $entry = $entries[$item->sourceId] ?? null;

            if ($entry instanceof Entry && $this->feedFieldsMatch($entry, $item)) {
                $this->reconcileCredits($entry, $item, false, $report);

                continue;
            }

            if ($fetched++ > 0 && $this->pauseMs > 0) {
                Sleep::for($this->pauseMs)->milliseconds();
            }

            try {
                $page = $this->pageParser->parse($this->client->itemPage($item->sourceId));
            } catch (HealthTalkSourceException $exception) {
                $report->pageErrors[$item->sourceId] = $exception->getMessage();
                $this->log->warning('UAMS Health Talk item page could not be fetched; its feed changes wait for the next run.', [
                    'source_item_id' => $item->sourceId,
                    'title' => $item->title,
                    'error' => $exception->getMessage(),
                ]);

                if ($entry instanceof Entry) {
                    $this->reconcileCredits($entry, $item, false, $report);
                }

                continue;
            }

            if ($entry instanceof Entry) {
                $this->update($entry, $item, $page, $report);
                $this->reconcileCredits($entry, $item, true, $report);

                continue;
            }

            $this->create($item, $page, $report);
        }

        foreach ($departed as $entry) {
            $this->unpublish($entry, $report);
        }

        $report->mappingRowsAdded = $this->mappings->recordUnmapped($report->unresolvedSpeakers());

        $this->log->info('UAMS Health Talk sync finished.', [
            'feed_items' => $report->feedItems,
            'created' => \count($report->created),
            'updated' => \count($report->updated),
            'unpublished' => \count($report->unpublished),
            'republished' => \count($report->republished),
            'deferred' => \count($report->deferred),
            'page_errors' => \count($report->pageErrors),
            'mapping_rows_added' => $report->mappingRowsAdded,
        ]);
    }

    /**
     * Record a whole-run failure: nothing is changed, and the reason is surfaced.
     */
    private function abort(
        HealthTalkSyncReport $report,
        string $reason
    ): void {
        $report->aborted = $reason;
        $this->log->error('UAMS Health Talk sync aborted; no entries were changed.', ['reason' => $reason]);
    }

    /**
     * Existing episodes keyed by their source id.
     *
     * @return array<string, Entry>
     */
    private function entriesBySourceId(): array
    {
        $query = $this->destinationQuery();
        $query->whereNotNull('source_item_id');

        $bySourceId = [];

        $entries = $query->get();

        foreach (\is_iterable($entries) ? $entries : [] as $entry) {
            if (! $entry instanceof Entry) {
                continue;
            }

            $sourceId = $entry->get('source_item_id');

            if (\is_scalar($sourceId) && (string) $sourceId !== '' && ! isset($bySourceId[(string) $sourceId])) {
                $bySourceId[(string) $sourceId] = $entry;
            }
        }

        return $bySourceId;
    }

    /**
     * Import a new episode, or defer it when any speaker is unresolved.
     */
    private function create(
        HealthTalkFeedItem $item,
        HealthTalkItemPage $page,
        HealthTalkSyncReport $report
    ): void {
        $credits = $this->resolveSpeakers($page->speakers);

        if ($credits['unresolved'] !== []) {
            $report->deferred[$item->sourceId] = $credits['unresolved'];
            $this->log->info('UAMS Health Talk episode deferred: a speaker is not mapped yet.', [
                'source_item_id' => $item->sourceId,
                'title' => $item->title,
                'unresolved_speakers' => $credits['unresolved'],
            ]);

            return;
        }

        $data = $this->fields()->feedOwned($item, $page) + [
            'series' => $this->destination['series'],
            'source_item_id' => $item->sourceId,
            'segments' => $this->fields()->segments([], $item),
            'podcast_credits' => ['guest' => $credits['people']],
        ];

        $entry = EntryFacade::make();

        if (! $entry instanceof Entry) {
            throw new HealthTalkSourceException('Entry::make() did not return a Stache entry.');
        }

        $entry->collection($this->destination['collection']);
        $entry->blueprint($this->destination['blueprint']);
        $entry->locale($this->destination['site']);
        $entry->slug($this->uniqueSlug($item));
        $entry->date(($item->publishedAt ?? CarbonImmutable::now())->toMutable());
        $entry->published(true);
        $entry->data($data);
        $entry->save();

        $report->created[] = $item->sourceId;
        $this->logTransition('published', $item->sourceId, $item->title, 'new in the feed');
    }

    /**
     * Set an existing entry's feed-owned fields from the feed and its item page.
     *
     * Nothing is saved here and no credit is touched: {@see reconcileCredits()}
     * runs next and saves once.
     */
    private function update(
        Entry $entry,
        HealthTalkFeedItem $item,
        HealthTalkItemPage $page,
        HealthTalkSyncReport $report
    ): void {
        foreach ($this->fields()->feedOwned($item, $page) as $key => $value) {
            $entry->set($key, $value);
        }

        $segments = $entry->get('segments');
        $entry->set('segments', $this->fields()->segments(\is_array($segments) ? $segments : [], $item));

        if ($item->publishedAt instanceof CarbonImmutable) {
            $entry->date($item->publishedAt->toMutable());
        }

        $report->updated[] = $item->sourceId;
        $this->log->info('UAMS Health Talk episode updated from the feed.', [
            'source_item_id' => $item->sourceId,
            'title' => $item->title,
        ]);
    }

    /**
     * Re-resolve an existing entry's guests from its stored speakers, and
     * re-publish it if it was withdrawn, then save whatever changed.
     *
     * Resolution reads the stored speaker names and slugs, not the item page,
     * so a mapping completed or changed since the last run reaches the entry
     * without a fetch. When any speaker is unresolved the entry keeps its
     * previous guests, the speaker becomes a mapping row, and a withdrawn entry
     * stays withdrawn: no path publishes an entry with an unresolved speaker.
     *
     * @param  bool  $changed  Whether the feed-owned fields were just rewritten
     */
    private function reconcileCredits(
        Entry $entry,
        HealthTalkFeedItem $item,
        bool $changed,
        HealthTalkSyncReport $report
    ): void {
        $credits = $this->resolveSpeakers($this->fields()->storedSpeakers($entry));

        if ($credits['unresolved'] !== []) {
            $report->heldCredits[$item->sourceId] = $credits['unresolved'];

            if ($changed) {
                $entry->save();
            }

            return;
        }

        $group = $entry->get('podcast_credits');
        $group = \is_array($group) ? $group : [];

        if (($group['guest'] ?? null) !== $credits['people']) {
            $group['guest'] = $credits['people'];
            $entry->set('podcast_credits', $group);
            $changed = true;

            if (! \in_array($item->sourceId, $report->updated, true)) {
                $report->updated[] = $item->sourceId;
            }
        }

        $republish = ! $entry->published();

        if ($republish) {
            $entry->published(true);
            $changed = true;
        }

        if ($changed) {
            $entry->save();
        }

        if ($republish) {
            $report->republished[] = $item->sourceId;
            $this->logTransition('published', $item->sourceId, $item->title, 'returned to the feed');
        }
    }

    /**
     * Withdraw an entry whose id left the feed, keeping it and everything on it.
     */
    private function unpublish(
        Entry $entry,
        HealthTalkSyncReport $report
    ): void {
        $entry->published(false);
        $entry->save();

        $sourceId = $entry->get('source_item_id');
        $sourceId = \is_scalar($sourceId) ? (string) $sourceId : '';

        $title = $entry->get('title');

        $report->unpublished[] = $sourceId;
        $this->logTransition('unpublished', $sourceId, \is_string($title) ? $title : '', 'withdrawn from the feed');
    }

    /**
     * Log one publication transition.
     */
    private function logTransition(
        string $direction,
        string $sourceId,
        string $title,
        string $reason
    ): void {
        $this->log->info(\sprintf('UAMS Health Talk episode %s: %s.', $direction, $reason), [
            'source_item_id' => $sourceId,
            'title' => $title,
            'direction' => $direction,
        ]);
    }

    /**
     * Whether an entry already carries the feed's current metadata.
     */
    private function feedFieldsMatch(
        Entry $entry,
        HealthTalkFeedItem $item
    ): bool {
        $date = $entry->date();

        // Entry dates are stored to the minute, so the feed's date is compared to the minute
        $dateMatches = ! $item->publishedAt instanceof CarbonImmutable
            || ($date instanceof CarbonInterface && \intdiv($date->getTimestamp(), 60) === \intdiv($item->publishedAt->getTimestamp(), 60));

        return $entry->get('title') === $item->title
            && $dateMatches
            && $entry->get('show_notes') == $this->fields()->prosemirror('show_notes', $item->showNotesHtml)
            && $entry->get('episode_duration') === $item->duration
            && $entry->get('episode_number') === $item->episodeNumber
            && $this->fields()->storedAudioUrl($entry) === $item->audioUrl;
    }

    /**
     * Resolve a list of speakers.
     *
     * @param  list<HealthTalkSpeaker>  $speakers
     * @return array{people: list<string>, unresolved: list<string>}
     */
    private function resolveSpeakers(
        array $speakers
    ): array {
        $people = [];
        $unresolved = [];

        foreach ($speakers as $speaker) {
            $resolution = $this->mappings->resolve($speaker);

            if ($resolution['status'] === HealthTalkSpeakerMappings::PERSON && $resolution['person'] !== null) {
                $people[] = $resolution['person'];
            } elseif ($resolution['status'] !== HealthTalkSpeakerMappings::NO_ENTRY) {
                $unresolved[] = $speaker->name;
            }
        }

        return ['people' => \array_values(\array_unique($people)), 'unresolved' => $unresolved];
    }

    /**
     * A slug from the title, suffixed with the source id when another entry holds it.
     */
    private function uniqueSlug(
        HealthTalkFeedItem $item
    ): string {
        $slug = Str::slug($item->title);
        $slug = $slug === '' ? 'uams-health-talk-'.Str::slug($item->sourceId) : $slug;

        $query = $this->destinationQuery();
        $query->where('slug', $slug);

        return $query->count() > 0 ? $slug.'-'.Str::slug($item->sourceId) : $slug;
    }

    /**
     * A query over the destination collection and site.
     */
    private function destinationQuery(): EntryQueryBuilder
    {
        $query = EntryFacade::query();

        if (! $query instanceof EntryQueryBuilder) {
            throw new HealthTalkSourceException('Entries are not stored in the Stache, which the UAMS Health Talk sync queries.');
        }

        $query->where('collection', $this->destination['collection']);
        $query->where('site', $this->destination['site']);

        return $query;
    }

    /**
     * The feed-owned fields, which every transition after the blueprint check can rely on.
     */
    private function fields(): HealthTalkEntryFields
    {
        if (! $this->fields instanceof HealthTalkEntryFields) {
            throw new HealthTalkSourceException('The UAMS Health Talk destination was not resolved before it was used.');
        }

        return $this->fields;
    }
}
