<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the article_engagement_counts table: the sideband, app-owned store for
 * knowledge base article view / like / dislike tallies (UAMS-Web/uams-statamic-archive#286, UAMS-Web/uams-statamic-archive#287).
 *
 * The counts are held here — one row per article entry id — rather than on the
 * entry YAML so a per-vote / per-view increment never rewrites content in the git
 * submodule, never re-indexes the Stache, and never races on a YAML save. The
 * `article` blueprint's existing computed fields (`view_count`, `like_count`,
 * `dislike_count`, `like_count_net`) surface these values at render time.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the article_engagement_counts table on the default connection, keyed
     * by the knowledge_bases article entry id with unsigned counters defaulting to
     * zero so an article with no row reads as all-zero.
     */
    public function up(): void
    {
        // Create the engagement-counts table on the default connection
        Schema::create(
            'article_engagement_counts',
            function (Blueprint $table): void {
                // Surrogate primary key
                $table->id();

                // The knowledge_bases article entry id this row tallies. Unique so
                // insertOrIgnore + atomic increment can key on it race-free.
                $table->string('entry_id')->unique();

                // View tally: bigInteger because views are unbounded over time
                $table->unsignedBigInteger('views')->default(0);

                // Like tally
                $table->unsignedInteger('likes')->default(0);

                // Dislike tally
                $table->unsignedInteger('dislikes')->default(0);

                // Created / updated timestamps
                $table->timestamps();
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * Drops the article_engagement_counts table.
     */
    public function down(): void
    {
        // Drop the engagement-counts table on the default connection
        Schema::dropIfExists('article_engagement_counts');
    }
};
