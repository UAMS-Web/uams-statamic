<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EnumGeneration\County\CountyEnumGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Command to generate County_generated.php enum from TIGERweb.
 *
 * Thin wiring around {@see CountyEnumGenerator}: the generation logic lives in
 * the service so it can be tested in isolation, matching the other
 * `app/Services/EnumGeneration/` generators.
 *
 * Usage:
 *   - php artisan generate:county-enum
 */
#[Description('Generate County_generated.php enum using TIGERweb API data')]
#[Signature('generate:county-enum')]
class GenerateCountyEnum extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Inform the user that we are starting a network fetch
        $this->info('Fetching TIGERweb county data...');

        try {
            // Delegate the fetch + merge + render + write to the generator service
            $outputPath = (new CountyEnumGenerator)->generate();
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
