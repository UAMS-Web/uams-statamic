<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GenerateStatamicPatch\StatamicPatchGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('app:generate-statamic-stache-traverser-vanished-file-patch')]
#[Description('Generate Statamic Stache Traverser vanished-file patch from snippets')]
/**
 * Generate the vanished-file traverser patch from canonical snippets.
 *
 * `Traverser::traverse()` lists a store's directory, then reads each file's
 * mtime. A file removed between those two steps makes `getMTime()` raise, and
 * the raise aborts the whole index build as a 500 for whichever request
 * triggered it. The patched method skips a file a fresh directory listing shows
 * is gone, and re-raises every other stat failure. See `patches/README.md` for
 * the upstream report notes.
 */
class GenerateStatamicStacheTraverserVanishedFilePatch extends Command
{
    /**
     * Basename used for snippet and patch files.
     */
    private const string PATCH_BASENAME = 'statamic-stache-traverser-vanished-file';

    /**
     * Target file path in the Statamic CMS package beginning with 'src/'.
     */
    private const string PATCH_TARGET_DIFF_PATH = 'src/Stache/Traverser.php';

    /**
     * Method replaced in the target vendor file.
     */
    private const string PATCH_METHOD_NAME = 'traverse';

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
