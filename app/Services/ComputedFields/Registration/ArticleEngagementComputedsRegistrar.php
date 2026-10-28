<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Services\KnowledgeBase\ArticleEngagementService;
use Statamic\Entries\Entry;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection;
use Statamic\Fields\Blueprint;

/**
 * Surfaces the sideband engagement store through the `article` blueprint's computed
 * fields: `view_count` (UAMS-Web/uams-statamic-archive#287), `like_count`, `dislike_count`, and the net score
 * `like_count_net` (UAMS-Web/uams-statamic-archive#286).
 *
 * These four fields are declared `visibility: computed` on the blueprint but have no
 * resolver until registered here, so without this registrar they read as zero and
 * the "Most Viewed" / "Most Liked" topic-box sorts (which order by `view_count` /
 * `like_count_net`) never rank. Only `article` entries carry engagement; topic and
 * knowledge_base roots read as zero without touching the store. The single store
 * read per entry is memoized per request so all four fields — and the card that
 * renders them — share one query.
 */
final readonly class ArticleEngagementComputedsRegistrar
{
    /**
     * @param  ArticleEngagementService  $engagement  The sideband engagement counter store
     */
    public function __construct(
        private ArticleEngagementService $engagement
    ) {}

    /**
     * Register the four engagement computeds on the `knowledge_bases` collection.
     */
    public function register(): void
    {
        // View tally (UAMS-Web/uams-statamic-archive#287)
        Collection::computed(
            'knowledge_bases',
            'view_count',
            fn (Entry $entry, mixed $_stored): int => $this->counts($entry)['views']
        );

        // Like tally (UAMS-Web/uams-statamic-archive#286)
        Collection::computed(
            'knowledge_bases',
            'like_count',
            fn (Entry $entry, mixed $_stored): int => $this->counts($entry)['likes']
        );

        // Dislike tally (UAMS-Web/uams-statamic-archive#286)
        Collection::computed(
            'knowledge_bases',
            'dislike_count',
            fn (Entry $entry, mixed $_stored): int => $this->counts($entry)['dislikes']
        );

        // Net like score, likes − dislikes (UAMS-Web/uams-statamic-archive#286) — the "Most Liked" sort key
        Collection::computed(
            'knowledge_bases',
            'like_count_net',
            fn (Entry $entry, mixed $_stored): int => $this->counts($entry)['net']
        );
    }

    /**
     * The engagement tallies for an entry: the store row for an article (memoized
     * once per request per id so the four computeds share one read), or all-zero for
     * a non-article entry or an unsaved entry with no string id.
     *
     * @param  Entry  $entry  The knowledge_bases entry being augmented
     * @return array{views: int, likes: int, dislikes: int, net: int} The tallies
     */
    private function counts(Entry $entry): array
    {
        // Only article entries carry engagement; skip the store for topic / roots
        /** @var Blueprint $blueprint */
        $blueprint = $entry->blueprint();
        if ($blueprint->handle() !== 'article') {
            return $this->zero();
        }

        // An unsaved entry has no stable id to key the store on
        $id = $entry->id();
        if (! is_string($id)) {
            return $this->zero();
        }

        // Read the store once per request per id and reuse for every computed
        $cached = Blink::once(
            'kb_engagement_counts_'.$id,
            fn (): array => $this->engagement->counts($id)
        );

        // Blink erases the closure's array shape to mixed; coerce it back. A
        // scalar has none of the keys below, so the `??` reads would also give
        // zero; removing this return is an equivalent mutant. It stays so the
        // offsets are only ever read from an array.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! is_array($cached)) {
            return $this->zero();
        }

        return [
            'views' => $this->toInt($cached['views'] ?? 0),
            'likes' => $this->toInt($cached['likes'] ?? 0),
            'dislikes' => $this->toInt($cached['dislikes'] ?? 0),
            'net' => $this->toInt($cached['net'] ?? 0),
        ];
    }

    /**
     * The all-zero tally shape for entries with no engagement.
     *
     * @return array{views: int, likes: int, dislikes: int, net: int} Zeroed tallies
     */
    private function zero(): array
    {
        return [
            'views' => 0,
            'likes' => 0,
            'dislikes' => 0,
            'net' => 0,
        ];
    }

    /**
     * Coerce a memoized (mixed) tally value back to an integer.
     *
     * @param  mixed  $value  The value read back from the Blink cache
     * @return int The integer value, or 0 when non-numeric
     */
    private function toInt(mixed $value): int
    {
        // Coerce integers and numeric strings; zero anything else
        return is_numeric($value) ? (int) $value : 0;
    }
}
