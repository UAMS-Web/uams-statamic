<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use JackSleight\StatamicBardMutator\Facades\Mutator;

/**
 * Service provider for registering Statamic Bard Mutator configurations.
 *
 * This provider handles the setup of Bard Mutator plugins to customize the
 * rendering of Bard fieldtype content, such as modifying HTML output for specific
 * node types.
 */
class BardMutatorServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * Registers Bard Mutator plugins to modify Bard field rendering.
     */
    public function boot(): void
    {
        // Register paragraph HTML plugin so we can strip <p> only inside list items
        Mutator::html(
            'paragraph',
            function ($value, object $info): mixed {
                // Strip the paragraph wrapper when the paragraph is inside a list item
                $parent = $info->parent ?? null;
                if (is_object($parent) && (($parent->type ?? null) === 'listItem')) {
                    // Return null so no <p> tag is rendered; content is still output
                    return null;
                }

                // Leave the default HTML value unchanged so <p>...</p> is rendered
                return $value;
            }
        );
    }
}
