<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EnumGeneration\ZipCode\ZipCodeEnumGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Command to generate a ZipCode enum cases file from HUD USPS Crosswalk API.
 *
 * Thin wiring around {@see ZipCodeEnumGenerator}: the generation logic lives in
 * the service so it can be tested in isolation, matching the other
 * `app/Services/EnumGeneration/` generators.
 *
 * Usage:
 *   - HUD API (default):
 *     php -d memory_limit=256M artisan generate:zip-enum
 *     php -d memory_limit=256M artisan generate:zip-enum --limit=5000
 *
 * After generation, copy the case list from the generated file to
 * app/Enums/ZipCode.php to replace the existing case list. The generated file
 * includes instructions for this process.
 */
#[Description('Generate ZipCode enum cases file from the HUD USPS Crosswalk API')]
#[Signature('generate:zip-enum {--limit= : Optional max ZIP codes}')]
class GenerateZipCodeEnum extends Command
{
    /**
     * Execute the console command.
     *
     * @return int Exit status code (0 on success, non-zero on error)
     */
    public function handle(): int
    {
        // Parse optional --limit argument
        $limitOpt = $this->option('limit');

        // Normalized integer limit (null = no limit)
        $limit = null;

        // Check if limit is specified
        if (
            $limitOpt !== null
            && $limitOpt !== ''
        ) {
            // Convert provided limit string to int
            $limit = (int) $limitOpt;
        }

        // Inform the user that we are starting a network fetch
        $this->info('Reading ZIP codes from HUD USPS Crosswalk API...');

        try {
            // Delegate the fetch + normalize + render + write to the generator service
            $outputPath = (new ZipCodeEnumGenerator)->generate($limit);
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
