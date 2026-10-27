<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GenerateStatamicPatch\StatamicPatchGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('app:generate-statamic-stache-terms-vanished-file-paths-patch')]
#[Description('Generate Statamic Stache TaxonomyTermsStore::paths() vanished-file patch from snippets')]
/**
 * Generate the `TaxonomyTermsStore::paths()` vanished-file patch from canonical snippets.
 *
 * `TaxonomyTermsStore::paths()` reads a path the Stache has already recorded. A file removed in between
 * reads as null through `Statamic\Facades\File`, and `makeItemFromFile()` then builds
 * a new, empty item from it (UAMS-Web/uams-statamic-archive#2643). The patched method treats a file a fresh directory
 * listing shows is gone as absent. See `patches/README.md`.
 */
class GenerateStatamicStacheTermsVanishedFilePathsPatch extends Command
{
    /**
     * Basename used for snippet and patch files.
     */
    private const string PATCH_BASENAME = 'statamic-stache-terms-vanished-file-paths';

    /**
     * Target file path in the Statamic CMS package beginning with 'src/'.
     */
    private const string PATCH_TARGET_DIFF_PATH = 'src/Stache/Stores/TaxonomyTermsStore.php';

    /**
     * Method replaced in the target vendor file.
     */
    private const string PATCH_METHOD_NAME = 'paths';

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
