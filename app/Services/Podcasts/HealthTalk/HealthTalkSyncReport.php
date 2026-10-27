<?php

declare(strict_types=1);

namespace App\Services\Podcasts\HealthTalk;

use Carbon\CarbonImmutable;

/**
 * What one UAMS Health Talk sync run did (UAMS-Web/uams-statamic-archive#1803).
 *
 * The command prints it, and the dashboard widget reads its cached array form,
 * so the widget never fetches the feed inside a request.
 */
final class HealthTalkSyncReport
{
    /**
     * Source ids of entries created this run.
     *
     * @var list<string>
     */
    public array $created = [];

    /**
     * Source ids of entries whose feed-owned fields were rewritten this run.
     *
     * @var list<string>
     */
    public array $updated = [];

    /**
     * Source ids of entries withdrawn from the feed and unpublished this run.
     *
     * @var list<string>
     */
    public array $unpublished = [];

    /**
     * Source ids of withdrawn entries that returned to the feed and were re-published.
     *
     * @var list<string>
     */
    public array $republished = [];

    /**
     * Episodes not imported because a speaker is unresolved, as source id to those speakers.
     *
     * @var array<string, list<string>>
     */
    public array $deferred = [];

    /**
     * Existing entries that kept their previous guests because a new speaker is unresolved, as source id to those speakers.
     *
     * @var array<string, list<string>>
     */
    public array $heldCredits = [];

    /**
     * Item pages that could not be fetched, as source id to the reason.
     *
     * @var array<string, string>
     */
    public array $pageErrors = [];

    /**
     * How many mapping rows this run added for newly seen speakers.
     */
    public int $mappingRowsAdded = 0;

    /**
     * How many episodes the feed carried.
     */
    public int $feedItems = 0;

    /**
     * Why the run changed nothing, when it did not complete.
     */
    public ?string $aborted = null;

    /**
     * @param  CarbonImmutable  $ranAt  When the run started
     */
    public function __construct(
        public readonly CarbonImmutable $ranAt
    ) {}

    /**
     * Whether the run completed without a whole-run failure.
     */
    public function completed(): bool
    {
        return $this->aborted === null;
    }

    /**
     * The distinct unresolved speaker strings behind every deferred episode or held credit.
     *
     * @return list<string>
     */
    public function unresolvedSpeakers(): array
    {
        $names = [];

        foreach ([...\array_values($this->deferred), ...\array_values($this->heldCredits)] as $speakers) {
            foreach ($speakers as $speaker) {
                $names[$speaker] = true;
            }
        }

        return \array_map(strval(...), \array_keys($names));
    }

    /**
     * The report as a serializable array, the form cached for the widget.
     *
     * @return array{ran_at: string, completed: bool, aborted: string|null, feed_items: int, created: list<string>, updated: list<string>, unpublished: list<string>, republished: list<string>, deferred: array<string, list<string>>, held_credits: array<string, list<string>>, page_errors: array<string, string>, mapping_rows_added: int}
     */
    public function toArray(): array
    {
        return [
            'ran_at' => $this->ranAt->toIso8601String(),
            'completed' => $this->completed(),
            'aborted' => $this->aborted,
            'feed_items' => $this->feedItems,
            'created' => $this->created,
            'updated' => $this->updated,
            'unpublished' => $this->unpublished,
            'republished' => $this->republished,
            'deferred' => $this->deferred,
            'held_credits' => $this->heldCredits,
            'page_errors' => $this->pageErrors,
            'mapping_rows_added' => $this->mappingRowsAdded,
        ];
    }
}
