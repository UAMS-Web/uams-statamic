<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

/**
 * Tracks the last issued template instance suffix per allocation key during a
 * single JSON-LD graph build so repeated `schema_template_instance_index` values
 * (e.g. replicator `index` 0 for each contact under one organization context)
 * still produce distinct `@id` fragments, while a row emitted more than once
 * keeps one fragment.
 */
final class SchemaTemplateInstanceWatermarkBag
{
    /**
     * Last committed effective instance index per allocation key.
     *
     * @var array<string, int>
     */
    public array $lastIssuedByKey = [];

    /**
     * Effective instance index already issued to a replicator row, per
     * allocation key and row identity, so the same row emitted again (for
     * example under two properties, or in a second copy of its person) reuses
     * its `@id` instead of taking a new suffix.
     *
     * @var array<string, array<string, int>>
     */
    public array $issuedByRowIdentity = [];
}
