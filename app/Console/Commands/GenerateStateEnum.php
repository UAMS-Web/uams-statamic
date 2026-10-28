<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EnumGeneration\State\StateEnumGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Command to generate State_generated.php enum from TIGERweb.
 *
 * Thin wiring around {@see StateEnumGenerator}: the generation logic lives in
 * the service so it can be tested in isolation, matching the other
 * `app/Services/EnumGeneration/` generators.
 *
 * Usage:
 *   - php artisan generate:state-enum
 */
#[Description('Generate State_generated.php enum using TIGERweb states layer (0)')]
#[Signature('generate:state-enum')]
class GenerateStateEnum extends Command
{
    /**
     * Execute the console command.
     *
     * @param  StateEnumGenerator  $stateEnumGenerator  The TIGERweb state enum generator.
     */
    public function handle(StateEnumGenerator $stateEnumGenerator): int
    {
        // Inform the user that we are starting a network fetch
        $this->info('Fetching TIGERweb state attributes...');

        try {
            // Delegate the fetch + render + write to the generator service
            $outputPath = $stateEnumGenerator->generate();
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
