<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Services\Posts\PostsReadingMetricsService;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;

/**
 * Registers `word_count` and `reading_time` computeds for the `posts` collection using
 * aggregated body text rules from PostsReadingMetricsService.
 */
final readonly class PostsReadingMetricsCollectionComputedsRegistrar
{
    /**
     * @param  PostsReadingMetricsService  $metrics  Computes word counts and reading time buckets for posts entries.
     */
    public function __construct(
        private PostsReadingMetricsService $metrics
    ) {}

    /**
     * Register posts collection reading metrics computeds with Statamic.
     */
    public function register(): void
    {
        // Register whitespace-delimited word count computed for posts
        Collection::computed(
            'posts',
            'word_count',
            fn (Entry $entry, mixed $_stored): int => $this->metrics->wordCount($entry)
        );

        // Register whole-minute reading time in seconds for posts
        Collection::computed(
            'posts',
            'reading_time',
            fn (Entry $entry, mixed $_stored): int => $this->metrics->readingTimeSecondsRoundedUpToWholeMinutes($entry)
        );
    }
}
