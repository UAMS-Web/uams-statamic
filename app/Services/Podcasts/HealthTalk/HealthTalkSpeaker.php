<?php

declare(strict_types=1);

namespace App\Services\Podcasts\HealthTalk;

/**
 * A featured speaker as an item page names them (UAMS-Web/uams-statamic-archive#1803).
 *
 * The name is kept exactly as the source spells it, credentials and missing
 * periods included (`Tyler Rives, M.D`), because it is the key a speaker
 * mapping row matches on.
 */
final readonly class HealthTalkSpeaker
{
    /**
     * @param  string  $name  Raw speaker string from the item page
     * @param  string|null  $providerSlug  The `uamshealth.com/provider/<slug>/` slug the page links for this speaker, if any
     */
    public function __construct(
        public string $name,
        public ?string $providerSlug
    ) {}
}
