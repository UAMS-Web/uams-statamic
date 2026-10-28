<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EnumGeneration\CredentialRegistry\CredentialRegistryEnumGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Command to generate the CredentialRegistry enum from Credential Engine fixtures.
 *
 * Fetches fixtures/subclasses_map.json, fixtures/description_set_item_maps.yml,
 * and credreg.net CTDL JSON term documents (per term), then writes
 * app/Enums/CredentialRegistry.php with hierarchy, property hints, and vocabulary
 * labels or definitions when available.
 *
 * Usage:
 *   php artisan generate:credential-registry-enum
 */
#[Description('Generate CredentialRegistry enum from registry fixtures and credreg.net CTDL JSON')]
#[Signature('generate:credential-registry-enum')]
class GenerateCredentialRegistryEnum extends Command
{
    /**
     * Execute the console command.
     *
     * @param  CredentialRegistryEnumGenerator  $credentialRegistryEnumGenerator  The Credential Registry enum generator.
     * @return int Command exit code
     */
    public function handle(CredentialRegistryEnumGenerator $credentialRegistryEnumGenerator): int
    {
        $this->info('Generating CredentialRegistry enum from Credential Registry fixtures...');

        $path = $credentialRegistryEnumGenerator->generate();

        $this->info('Wrote '.$path);

        return 0;
    }
}
