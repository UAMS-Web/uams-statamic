<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records why an occurrence's target was not reached (UAMS-Web/uams-statamic-archive#1768).
 *
 * The outcome alone cannot say it. A configured intranet host that is `unverifiable` may have been
 * refused by the guard or may simply not have answered, and the scan's printed output is discarded
 * by every production entry point, which run it through `Artisan::call()`. So the reason is stored
 * beside the outcome, where a report reading the run later can show it to an editor.
 *
 * Nullable, because an answered link has no reason and every row written before this column
 * existed has none either.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('link_health_occurrences', function (Blueprint $table): void {
            $table->text('reason')->nullable()->after('outcome');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('link_health_occurrences', function (Blueprint $table): void {
            $table->dropColumn('reason');
        });
    }
};
