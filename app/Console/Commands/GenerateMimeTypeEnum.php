<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EnumGeneration\MimeType\MimeTypeEnumGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Command to generate a MimeType_generated.php enum from mime-db, IANA media
 * types, and Apache MIME types.
 *
 * Thin wiring around {@see MimeTypeEnumGenerator}: the generation logic lives in
 * the service so it can be tested in isolation, matching the other
 * `app/Services/EnumGeneration/` generators.
 *
 * This command retrieves MIME type mappings from multiple sources in priority
 * order:
 *   - mime-db database (primary source - includes IANA, Apache, and nginx data)
 *   - IANA Media Types Registry (all categories) - as backup
 *   - Apache HTTP Server mime.types file - as backup
 *
 * It generates a MimeType_generated_YYYY-MM-DD_HH-MM-SS.php enum file with cases
 * from all sources, with mime-db types taking precedence for duplicates.
 *
 * Data Sources:
 *   - mime-db database:
 *     https://github.com/jshttp/mime-db
 *   - IANA Media Types Registry:
 *     https://www.iana.org/assignments/media-types/media-types.xhtml
 *   - Apache HTTP Server MIME Types:
 *     https://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types
 *
 * Usage:
 *   - php artisan generate:mime-type-enum
 *
 * After generation, copy the case list from the generated file to
 * app/Enums/MimeType.php to replace the existing case list. The generated file
 * includes instructions for this process.
 */
#[Description('Generate MimeType_generated.php enum using mime-db, IANA media types, and Apache MIME types')]
#[Signature('generate:mime-type-enum')]
class GenerateMimeTypeEnum extends Command
{
    /**
     * Execute the console command.
     *
     * @param  MimeTypeEnumGenerator  $mimeTypeEnumGenerator  The MIME type enum generator.
     * @return int Exit status code (0 on success, non-zero on error)
     */
    public function handle(MimeTypeEnumGenerator $mimeTypeEnumGenerator): int
    {
        // Inform the user that we are starting a network fetch
        $this->info('Reading MIME types from mime-db, IANA media types, and Apache MIME types...');

        try {
            // Delegate the fetch + merge + render + write to the generator service
            $outputPath = $mimeTypeEnumGenerator->generate();
        } catch (RuntimeException $runtimeException) {
            // Surface an actionable error and exit non-zero
            $this->error($runtimeException->getMessage());

            return 1;
        }

        // Report success and destination path
        $this->info('Wrote '.$outputPath);

        // Normal success exit status
        return 0;
    }
}
