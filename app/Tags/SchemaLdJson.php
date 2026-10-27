<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\SchemaTemplates\SchemaOutputService;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Single Antlers tag for JSON-LD schema output.
 *
 * Usage: {{ schema_ld_json }} or with overrides:
 * {{ schema_ld_json subject="entry-id" website="entry-id" webpage="entry-id" main_content="entry-id" }}
 *
 * Resolves the current entry/term from the route, runs the Schema Templates
 * cascade (site → section → blueprint → entry), and if enabled outputs
 * <script type="application/ld+json">...schema...</script>. Optional parameters
 * override the cascade for each slot with a schema_objects entry id.
 */
class SchemaLdJson extends Tags
{
    /**
     * @return string Script tag with JSON-LD or empty string
     */
    public function index(): string
    {
        /** @var Parameters $params */
        $params = $this->params;

        $subjectParam = $params->get('subject');
        $websiteParam = $params->get('website');
        $webpageParam = $params->get('webpage');
        $mainContentParam = $params->get('main_content');

        /** @var array{subject?: string, website?: string, webpage?: string, main_content?: string} $tagParams */
        $tagParams = [];

        if (is_string($subjectParam)) {
            $tagParams['subject'] = $subjectParam;
        }

        if (is_string($websiteParam)) {
            $tagParams['website'] = $websiteParam;
        }

        if (is_string($webpageParam)) {
            $tagParams['webpage'] = $webpageParam;
        }

        if (is_string($mainContentParam)) {
            $tagParams['main_content'] = $mainContentParam;
        }

        return app(SchemaOutputService::class)->outputForCurrentContext($tagParams);
    }
}
