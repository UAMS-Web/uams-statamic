<?php

declare(strict_types=1);

namespace App\Http\View\Composers;

use App\Providers\ValidationServiceProvider;
use Illuminate\Support\Collection as IlluminateCollection;
use Illuminate\Support\Facades\Validator as IlluminateValidatorFacade;
use Illuminate\View\View;
use Statamic\Facades\Fieldset;
use Statamic\Fields\FieldTransformer;
use Statamic\Statamic;
use Statamic\Support\Arr;
use Statamic\Support\Str;

/**
 * Control Panel view composer that mirrors Statamic's fieldset payload for Vue
 * while applying custom validation rule labels in the Rules picker.
 *
 * ## Problem Solved
 *
 * Statamic's Control Panel displays validation rules in the "Rules" field using
 * Laravel's `Str::title()` helper to convert snake_case rule names to title case.
 * This results in incorrect capitalization for certain rules:
 *
 *   - `youtube_url` becomes "Youtube Url" instead of "YouTube Url"
 *   - `vimeo_url` becomes "Vimeo Url" instead of "Vimeo Url" (correct)
 *   - `uams_video_url` becomes "Uams Video Url" instead of "UAMS Video Url"
 *   - `tri_profile_url` becomes "Tri Profile Url" instead of "TRI Profile Url"
 *
 * ## Solution
 *
 * This composer merges `fieldsets` and `extensionRules` into the Statamic script
 * payload. Fieldset serialization matches
 * `Statamic\Http\Controllers\CP\Fields\ManagesFields::fieldsets()` so fieldsets
 * authored with sections (Statamic 6.x) flatten correctly for the CP. Custom
 * display names come from `ValidationServiceProvider::getStaticCustomDisplayNames()`.
 *
 * ## Integration
 *
 * Registered on `statamic::partials.scripts` in
 * `CustomFieldComposerServiceProvider` alongside Statamic's `JavascriptComposer`.
 * Custom display names are defined in `ValidationServiceProvider::boot()` when
 * registering rules using the enhanced `registerCustomRule()` method.
 *
 * ## Example Usage
 *
 * In `ValidationServiceProvider::boot()`:
 *   ```php
 *   $this->registerCustomRule('youtube_url', YouTubeUrl::class, 'YouTube Url');
 *   $this->registerCustomRule('uams_video_url', UamsVideoUrl::class, 'UAMS Video Url');
 *   ```
 *
 * This will display as "YouTube Url `youtube_url`" and
 * "UAMS Video Url `uams_video_url`" in the Statamic Control Panel Rules field
 * interface.
 */
class CustomFieldComposer
{
    /**
     * Merge fieldsets and extension rules into the Statamic CP script payload.
     */
    public function compose(View $view): void
    {
        // Merge fieldsets and extension rules into Statamic's JS bootstrap
        Statamic::provideToScript([
            'fieldsets' => $this->fieldsets(),
            'extensionRules' => $this->extensionRules(),
        ]);
    }

    /**
     * Build the fieldset map for Vue, matching ManagesFields::fieldsets().
     *
     * @return IlluminateCollection<string, array<string, mixed>> Fieldsets keyed by handle, each with Vue field rows plus section metadata when applicable.
     */
    private function fieldsets(): IlluminateCollection
    {
        // Map each fieldset to the payload shape expected by the CP field builder
        return Fieldset::all()->mapWithKeys(
            /**
             * @param  \Statamic\Fields\Fieldset  $fieldset
             * @return array<string, array<string, mixed>>
             */
            function ($fieldset): array {
                /** @var \Statamic\Fields\Fieldset $fieldset */

                // Flatten sectioned fieldsets into a single field list for Vue.
                // Contents/sections always store arrays; the (array) casts are
                // defensive and observationally identical under mutation.
                $fields = $fieldset->hasSections()
                    ? $fieldset->sections()->flatMap(
                        function ($section): array {
                            // @pest-mutate-ignore: RemoveArrayCast
                            $sectionArray = (array) $section;

                            // @pest-mutate-ignore: RemoveArrayCast
                            return (array) Arr::get(
                                $sectionArray,
                                'fields',
                                []
                            );
                        }
                    )
                    : collect(
                        // @pest-mutate-ignore: RemoveArrayCast
                        (array) Arr::get(
                            $fieldset->contents(),
                            'fields',
                            []
                        )
                    );

                // Transform each raw field definition for the Vue field builder
                /** @var array<string, mixed> $payload */
                $payload = [
                    'handle' => $fieldset->handle(),
                    'title' => $fieldset->title(),
                    'has_sections' => $fieldset->hasSections(),
                    'sections_count' => $fieldset->hasSections()
                        ? $fieldset->sections()->count()
                        : 0,
                    'fields' => $fields
                        ->map(FieldTransformer::toVue(...))
                        ->sortBy('config.display')
                        ->values()
                        ->all(),
                ];

                return [$fieldset->handle() => $payload];
            }
        )->sortBy('title');
    }

    /**
     * List validation extension rules with optional custom labels.
     *
     * @return array<int, array{label: string, value: string}> Validation rules with custom labels when available.
     */
    private function extensionRules(): array
    {
        // Load custom labels registered alongside custom validation rules
        $customDisplayNames = ValidationServiceProvider::getStaticCustomDisplayNames();

        // Build label/value rows for every registered Validator extension
        return collect(
            IlluminateValidatorFacade::make(
                [],
                []
            )->extensions
        )
            ->keys()
            ->map(
                fn ($rule): array => [
                    'label' => $customDisplayNames[$rule] ?? Str::title(
                        \str_replace(
                            '_',
                            ' ',
                            $rule
                        )
                    ),
                    'value' => $rule,
                ]
            )
            ->all();
    }
}
