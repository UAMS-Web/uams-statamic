<?php

declare(strict_types=1);

namespace App\Services\KnowledgeBase;

use App\Models\ArticleEngagementCount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * App-owned sideband store for knowledge base article engagement tallies — views
 * (UAMS-Web/uams-statamic-archive#287) and like / dislike ratings (UAMS-Web/uams-statamic-archive#286) — keyed by the article's entry id.
 *
 * All mutations are atomic SQL statements so concurrent votes / views never lose
 * an update: the row is created race-free with insertOrIgnore (keyed on the unique
 * `entry_id`), then a single UPDATE increments or decrements the counter. The
 * decrements are floored at zero via a `> 0` guard so the unsigned columns can
 * never underflow. Session-scoped repeat-vote prevention and bot filtering live in
 * the HTTP layer; this service is a pure store and knows nothing about the request.
 */
class ArticleEngagementService
{
    /**
     * The database table holding one engagement row per article entry id.
     */
    private const string TABLE = 'article_engagement_counts';

    /**
     * The "like" reaction handle.
     */
    public const string REACTION_LIKE = 'like';

    /**
     * The "dislike" reaction handle.
     */
    public const string REACTION_DISLIKE = 'dislike';

    /**
     * Read the current tallies for an article. An article with no row reads as all
     * zero, so callers never special-case a missing row.
     *
     * @param  string  $entryId  The knowledge_bases article entry id
     * @return array{views: int, likes: int, dislikes: int, net: int} The current tallies plus the net like score
     */
    public function counts(string $entryId): array
    {
        // Degrade to zero when the sideband table is absent. The engagement
        // computeds are global, so this read fires during augmentation of every
        // knowledge_bases article — including a page rendered before the migration
        // has run (fresh environment / deploy ordering) and tests that augment an
        // article without a migrated database. It must never throw `no such table`.
        if (! Schema::hasTable(self::TABLE)) {
            return $this->shape(0, 0, 0);
        }

        // Read the single row for this entry id via the model, whose integer casts
        // give typed counters (a missing row reads as all-zero)
        $row = ArticleEngagementCount::query()
            ->where('entry_id', $entryId)
            ->first();

        // A missing row means the article has no recorded engagement yet
        if (! $row instanceof ArticleEngagementCount) {
            return $this->shape(0, 0, 0);
        }

        // Assemble the tally shape from the model's cast integer columns
        return $this->shape($row->views, $row->likes, $row->dislikes);
    }

    /**
     * Atomically record one view for an article and return the fresh tallies.
     *
     * @param  string  $entryId  The knowledge_bases article entry id
     * @return array{views: int, likes: int, dislikes: int, net: int} The tallies after the increment
     */
    public function incrementViews(string $entryId): array
    {
        // Ensure the row exists without racing concurrent inserts
        $this->ensureRow($entryId);

        // Atomically bump the view counter and touch updated_at
        DB::table(self::TABLE)
            ->where('entry_id', $entryId)
            ->increment('views', 1, ['updated_at' => now()]);

        // Return the tallies after the increment
        return $this->counts($entryId);
    }

    /**
     * Apply a like / dislike reaction, resolving the transition from the voter's
     * previous reaction: an identical reaction toggles the vote off, a null previous
     * casts a fresh vote, and a different previous switches sides (decrement the old,
     * increment the new). The whole transition runs in one transaction so a switch
     * never leaves both counters bumped.
     *
     * @param  string  $entryId  The knowledge_bases article entry id
     * @param  string|null  $previous  The voter's previous reaction (`like`, `dislike`, or null)
     * @param  string  $requested  The reaction just requested (`like` or `dislike`)
     * @return array{counts: array{views: int, likes: int, dislikes: int, net: int}, reaction: string|null} The tallies after the transition and the voter's resulting reaction (null when toggled off)
     */
    public function applyReaction(string $entryId, ?string $previous, string $requested): array
    {
        // Guard against a bogus requested reaction reaching the store
        if (! $this->isReaction($requested)) {
            return [
                'counts' => $this->counts($entryId),
                'reaction' => $this->isReaction($previous) ? $previous : null,
            ];
        }

        // Resolve the whole transition atomically so a switch is all-or-nothing
        $reaction = DB::transaction(function () use ($entryId, $previous, $requested): ?string {
            // Ensure the row exists before mutating its counters
            $this->ensureRow($entryId);

            // An identical reaction toggles the existing vote off
            if ($previous === $requested) {
                $this->decrement($entryId, $this->column($requested));

                return null;
            }

            // A recorded previous reaction on the other side is retracted first
            if ($this->isReaction($previous)) {
                $this->decrement($entryId, $this->column($previous));
            }

            // Cast the newly requested vote
            $this->increment($entryId, $this->column($requested));

            return $requested;
        });

        // Return the fresh tallies plus the voter's resulting reaction
        return [
            'counts' => $this->counts($entryId),
            'reaction' => $reaction,
        ];
    }

    /**
     * Whether a value is one of the two supported reaction handles.
     *
     * @param  string|null  $reaction  The candidate reaction handle
     * @return bool True when the value is `like` or `dislike`
     *
     * @phpstan-assert-if-true 'like'|'dislike' $reaction
     */
    public function isReaction(?string $reaction): bool
    {
        return $reaction === self::REACTION_LIKE
            || $reaction === self::REACTION_DISLIKE;
    }

    /**
     * Insert the article's row if it does not yet exist, race-free.
     *
     * insertOrIgnore relies on the unique `entry_id` index: a concurrent insert
     * losing the race is silently ignored rather than throwing, so the caller can
     * always assume a row is present afterward.
     *
     * @param  string  $entryId  The knowledge_bases article entry id
     */
    private function ensureRow(string $entryId): void
    {
        // Create the zeroed row only when absent; a duplicate is ignored
        DB::table(self::TABLE)->insertOrIgnore([
            'entry_id' => $entryId,
            'views' => 0,
            'likes' => 0,
            'dislikes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Atomically increment a reaction counter and touch updated_at.
     *
     * @param  string  $entryId  The knowledge_bases article entry id
     * @param  string  $column  The counter column (`likes` or `dislikes`)
     */
    private function increment(string $entryId, string $column): void
    {
        DB::table(self::TABLE)
            ->where('entry_id', $entryId)
            ->increment($column, 1, ['updated_at' => now()]);
    }

    /**
     * Atomically decrement a reaction counter, floored at zero so the unsigned
     * column can never underflow, and touch updated_at.
     *
     * @param  string  $entryId  The knowledge_bases article entry id
     * @param  string  $column  The counter column (`likes` or `dislikes`)
     */
    private function decrement(string $entryId, string $column): void
    {
        DB::table(self::TABLE)
            ->where('entry_id', $entryId)
            ->where($column, '>', 0)
            ->decrement($column, 1, ['updated_at' => now()]);
    }

    /**
     * The counter column backing a reaction handle.
     *
     * @param  string  $reaction  A validated reaction handle (`like` or `dislike`)
     * @return string The column name (`likes` or `dislikes`)
     */
    private function column(string $reaction): string
    {
        return $reaction === self::REACTION_LIKE ? 'likes' : 'dislikes';
    }

    /**
     * Assemble the tally shape, deriving the net like score.
     *
     * @param  int  $views  The view tally
     * @param  int  $likes  The like tally
     * @param  int  $dislikes  The dislike tally
     * @return array{views: int, likes: int, dislikes: int, net: int} The tallies plus net = likes - dislikes
     */
    private function shape(int $views, int $likes, int $dislikes): array
    {
        return [
            'views' => $views,
            'likes' => $likes,
            'dislikes' => $dislikes,
            'net' => $likes - $dislikes,
        ];
    }
}
