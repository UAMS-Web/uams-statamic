<?php

declare(strict_types=1);

/**
 * Analyze the structure of the schema.org JSON-LD vocabulary with
 * App\Services\EnumGeneration\Schema\SchemaJsonLdStructureAnalyzer (UAMS-Web/uams-statamic#894).
 *
 * Reads the given `.jsonld` file, or `schemaorg-current-https.jsonld` in the
 * analyzer's directory, and writes `schema-jsonld-structure-report.txt` (or
 * `.json` when `ANALYZE_OUTPUT=json`) and `schema-jsonld-all-ids.txt` into that
 * directory. Exits 1 when the input cannot be read or parsed, or the report
 * cannot be written.
 *
 *     php scripts/analyze-schema-jsonld.php
 *     php scripts/analyze-schema-jsonld.php [path-to.jsonld]
 */

require dirname(__DIR__).'/vendor/autoload.php';

use App\Services\EnumGeneration\Schema\SchemaJsonLdStructureAnalyzer;

exit(SchemaJsonLdStructureAnalyzer::runCli($_SERVER, SchemaJsonLdStructureAnalyzer::directory(), STDERR));
