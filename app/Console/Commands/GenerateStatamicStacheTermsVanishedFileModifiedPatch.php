<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GenerateStatamicPatch\StatamicPatchGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('app:generate-statamic-stache-terms-vanished-file-modified-patch')]
#[Description('Generate Statamic Stache terms vanished-file (modified) patch from snippets')]
/**
 * Generate the `TaxonomyTermsStore::getItemFromModifiedPath()` vanished-file patch.
 *
 * The override calls `localizations()` on the parent's result. Once the
 * `statamic-stache-store-vanished-file-modified` patch lets the parent return null for
 * a file deleted before its read (UAMS-Web/uams-statamic-archive#2643), that call would fatal; the patched method
 * returns no items instead. See `patches/README.md`.
 */
class GenerateStatamicStacheTermsVanishedFileModifiedPatch extends Command
{
    /**
     * Basename used for snippet and patch files.
     */
    private const string PATCH_BASENAME = 'statamic-stache-terms-vanished-file-modified';

    /**
     * Target file path in the Statamic CMS package beginning with 'src/'.
     */
    private const string PATCH_TARGET_DIFF_PATH = 'src/Stache/Stores/TaxonomyTermsStore.php';

    /**
     * Method replaced in the target vendor file.
     */
    private const string PATCH_METHOD_NAME = 'getItemFromModifiedPath';

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
