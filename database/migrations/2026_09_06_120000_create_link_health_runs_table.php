<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the link-health run tables: one row per scan, one row per occurrence outcome (UAMS-Web/uams-statamic-archive#1766).
 *
 * Persisted in the database rather than in the cache, and the reason is the feature's headline
 * output rather than durability in the abstract. "Newly broken" is a TRANSITION from a previous
 * recorded outcome, so a history that is cleared leaves every occurrence with no previous record
 * — and an occurrence with no previous record is deliberately not newly broken. A cache flush
 * would therefore make the next run report ZERO newly-broken links, silently and with no error,
 * which reads exactly like good news.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // One row per scan.
        Schema::create('link_health_runs', function (Blueprint $table): void {
            $table->id();

            // When the scan began, which is what orders history and what an editor is shown.
            $table->timestamp('started_at')->index();

            // What the scan covered, so a `--site`-scoped run is never diffed against a full one
            // and mistaken for hundreds of links having recovered.
            $table->string('scope_site')->nullable();
            $table->string('scope_collection')->nullable();

            $table->timestamps();
        });

        // One row per occurrence within a scan.
        Schema::create('link_health_occurrences', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('run_id')->constrained('link_health_runs')->cascadeOnDelete();

            // The occurrence identity the diff keys on — item, site and location within it.
            // Keyed together because the diff compares occurrences, not URLs: the same URL in two
            // entries is two rows with two independent histories.
            $table->string('occurrence_key', 512);

            // The stored value, kept so a report can show what is actually written in the entry
            // rather than a normalized form of it.
            $table->text('value');

            $table->string('outcome', 32);

            $table->index(['run_id', 'occurrence_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Occurrences first: they carry the foreign key.
        Schema::dropIfExists('link_health_occurrences');
        Schema::dropIfExists('link_health_runs');
    }
};
