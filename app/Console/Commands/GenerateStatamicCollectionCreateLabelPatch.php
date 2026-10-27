<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GenerateStatamicPatch\StatamicPatchGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('app:generate-statamic-collection-create-label-patch')]
#[Description('Generate Statamic collection createLabel patch from snippets')]
/**
 * Generate the Statamic collection createLabel patch from canonical snippets.
 *
 * This command defines patch-specific constants and delegates patch generation
 * to `StatamicPatchGenerator`, then reports success or failure in the console.
 *
 * Related Statamic feature request:
 * https://github.com/statamic/ideas/issues/1448
 *
 * Expected directories:
 *
 * - Canonical snippet inputs: `patches/src/`
 * - Generated patch output: `patches/`
 *
 * Requires `git` on `PATH`. After success, run `composer patches-relock`.
 */
class GenerateStatamicCollectionCreateLabelPatch extends Command
{
    /**
     * Basename used for snippet and patch files.
     */
    private const string PATCH_BASENAME = 'statamic-collection-create-label-blueprint';

    /**
     * Target file path in the Statamic CMS package beginning with 'src/'.
     */
    private const string PATCH_TARGET_DIFF_PATH = 'src/Entries/Collection.php';

    /**
     * Create a new command instance with dependencies.
     *
     * @param  StatamicPatchGenerator  $statamicPatchGenerator  The Statamic patch generator service.
     */
    public function __construct(
        private readonly StatamicPatchGenerator $statamicPatchGenerator
    ) {
        // Call parent constructor
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int The exit code of the command
     */
    public function handle(): int
    {
        // Attempt to generate the patch from command-level configuration values
        try {
            // Generate the patch from command-level configuration values
            $generatedPatchPath = $this->statamicPatchGenerator->generatePatchFromValues(
                self::PATCH_BASENAME,
                self::PATCH_TARGET_DIFF_PATH
            );
        } catch (RuntimeException $runtimeException) {
            // Output error details when patch generation fails
            $this->error($runtimeException->getMessage());

            // Release local variables before returning failure
            unset($runtimeException);

            // Return failure exit code
            return self::FAILURE;
        }

        // Output generated patch path when generation succeeds
        $this->info('Generated '.$generatedPatchPath);

        // Remind to refresh patch lock checksums after changing the patch file
        $this->warn('Run: composer patches-relock --no-interaction');

        // Release local variables before returning success
        unset($generatedPatchPath);

        // Return success exit code
        return self::SUCCESS;
    }
}
