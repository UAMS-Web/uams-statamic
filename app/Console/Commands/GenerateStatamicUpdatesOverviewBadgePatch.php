<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GenerateStatamicPatch\StatamicPatchGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('app:generate-statamic-updates-overview-badge-patch')]
#[Description('Generate Statamic UpdatesOverview badge patch from snippets')]
/**
 * Generate the Statamic CP updates nav badge patch from canonical snippets.
 *
 * Aligns `UpdatesOverview::checkForAddonUpdates()` with the updater index page.
 * See `patches/README.md` for upstream report notes.
 */
class GenerateStatamicUpdatesOverviewBadgePatch extends Command
{
    /**
     * Basename used for snippet and patch files.
     */
    private const string PATCH_BASENAME = 'statamic-updates-overview-badge';

    /**
     * Target file path in the Statamic CMS package beginning with 'src/'.
     */
    private const string PATCH_TARGET_DIFF_PATH = 'src/Updater/UpdatesOverview.php';

    /**
     * Method replaced in the target vendor file.
     */
    private const string PATCH_METHOD_NAME = 'checkForAddonUpdates';

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
