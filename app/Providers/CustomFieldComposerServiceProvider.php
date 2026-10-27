<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\View\Composers\CustomFieldComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Statamic\Http\View\Composers\JavascriptComposer;

/**
 * Registers the custom CP scripts composer that augments Statamic's script
 * bootstrap with fieldsets and custom validation rule labels.
 *
 * ## Purpose
 *
 * Binds `CustomFieldComposer` to the same Blade views as
 * `JavascriptComposer` (`statamic::partials.scripts`) so `compose()` runs when
 * the Control Panel loads. That mirrors the historical `FieldComposer` hook
 * (removed upstream in favor of per-request `ManagesFields` payloads) while
 * keeping global `fieldsets` and `extensionRules` available to the CP.
 *
 * ## Problem Solved
 *
 * Statamic's Control Panel displays validation rules in the "Rules" field using
 * Laravel's `Str::title()` helper to convert snake_case rule names to title case,
 * which results in incorrect capitalization for certain rules (for example
 * "Youtube Url" instead of "YouTube Url").
 *
 * ## Solution
 *
 * `CustomFieldComposer` merges `fieldsets` (including sectioned fieldsets) and
 * `extensionRules` with custom display names from `ValidationServiceProvider`.
 *
 * ## Integration
 *
 * Registered in `bootstrap/providers.php` together with
 * `ValidationServiceProvider`, which defines custom rule display names via
 * `registerCustomRule()`.
 */
class CustomFieldComposerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    #[\Override]
    public function register(): void
    {
        //
    }

    /**
     * Attach the custom composer to Statamic CP script partials.
     */
    public function boot(): void
    {
        // Run after Statamic's JavascriptComposer when the CP scripts partial renders
        View::composer(
            JavascriptComposer::VIEWS,
            CustomFieldComposer::class
        );
    }
}
