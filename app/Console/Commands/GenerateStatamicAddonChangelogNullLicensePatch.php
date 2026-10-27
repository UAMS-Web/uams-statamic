<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GenerateStatamicPatch\StatamicPatchGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('app:generate-statamic-addon-changelog-null-license-patch')]
#[Description('Generate Statamic AddonChangelog isLicensed null-safe license patch from snippets')]
/**
 * Generate the Statamic AddonChangelog `isLicensed()` patch from canonical snippets.
 *
 * This command defines patch-specific constants and delegates patch generation
 * to `StatamicPatchGenerator`, then reports success or failure in the console.
 *
 * Expected directories:
 *
 * - Canonical snippet inputs: `patches/src/`
 * - Generated patch output: `patches/`
 *
 * Requires `git` on `PATH`. After success, run `composer patches-relock`.
 */
class GenerateStatamicAddonChangelogNullLicensePatch extends Command
{
    /**
     * Basename used for snippet and patch files.
     */
    private const string PATCH_BASENAME = 'statamic-addon-changelog-null-license';

    /**
     * Target file path in the Statamic CMS package beginning with 'src/'.
     */
    private const string PATCH_TARGET_DIFF_PATH = 'src/Updater/AddonChangelog.php';

    /**
     * Method name replaced inside the target vendor file.
     */
    private const string PATCH_METHOD_NAME = 'isLicensed';

    /**
     * Create a new command instance with dependencies.
     *
     * @param  StatamicPatchGenerator  $statamicPatchGenerator  The Statamic patch generator service.
     */
    public function __construct(
        private readonly StatamicPatchGenerator $statamicPatchGenerator
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int The exit code of the command
     */
    public function handle(): int
    {
        try {
            $generatedPatchPath = $this->statamicPatchGenerator->generatePatchFromValues(
                self::PATCH_BASENAME,
                self::PATCH_TARGET_DIFF_PATH,
                self::PATCH_METHOD_NAME
            );
        } catch (RuntimeException $runtimeException) {
            $this->error($runtimeException->getMessage());

            unset($runtimeException);

            return self::FAILURE;
        }

        $this->info('Generated '.$generatedPatchPath);

        $this->warn('Run: composer patches-relock --no-interaction');

        unset($generatedPatchPath);

        return self::SUCCESS;
    }
}
