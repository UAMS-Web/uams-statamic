<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Support\Statamic\Assets\AugmentedAssetHook;
use App\Support\Statamic\Assets\GenericAugmentedAssetWithCredit;
use App\Support\Statamic\Assets\Logos\AugmentedAsset as LogosAugmentedAsset;
use App\Support\Statamic\Assets\LogosUnrestricted\AugmentedAsset as LogosUnrestrictedAugmentedAsset;
use App\Support\Statamic\Assets\NewsReleases\AugmentedAsset as NewsReleasesAugmentedAsset;

/**
 * Registers Statamic asset augmented pipeline hooks for logos containers, the
 * news_releases usage indicator, and the computed `credit_text` fallback pipeline.
 */
final class AugmentedAssetComputedPipelinesRegistrar
{
    /**
     * Register all asset augmented pipeline hooks used by computed asset fields.
     */
    public function register(): void
    {
        // Register the logos_unrestricted container augmented pipeline
        AugmentedAssetHook::registerPipelineHook(
            AugmentedAssetHook::pipelineHook(
                'logos_unrestricted',
                LogosUnrestrictedAugmentedAsset::class
            )
        );

        // Register the news_releases container augmented pipeline (UAMS-Web/uams-statamic-archive#1376): the
        // computed in_use / used_by usage indicator from the news_release_entry link
        AugmentedAssetHook::registerPipelineHook(
            AugmentedAssetHook::pipelineHook(
                'news_releases',
                NewsReleasesAugmentedAsset::class
            )
        );

        // Register the logos container augmented pipeline
        AugmentedAssetHook::registerPipelineHook(
            AugmentedAssetHook::pipelineHook(
                'logos',
                LogosAugmentedAsset::class
            )
        );

        // Register the generic credit-text fallback pipeline for other containers
        AugmentedAssetHook::registerPipelineHook(
            AugmentedAssetHook::creditTextFallbackPipelineHook(
                GenericAugmentedAssetWithCredit::class
            )
        );
    }
}
