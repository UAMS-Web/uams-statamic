<?php

declare(strict_types=1);

namespace App\Services\GenerateStatamicPatch;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Generate unified patch files from canonical PHP snippet classes.
 *
 * Patches are produced with **`git diff`** inside a temporary repository so they
 * apply under **`cweagans/composer-patches` v2**, which uses **`git apply`** (not
 * GNU `patch`). The vendor file may be **unpatched** (method matches
 * **`.original.php`**) or **already patched** (method matches
 * **`.patched.php`**); the baseline for the diff is always the unpatched upstream
 * method body from **`.original.php`**.
 *
 * Expected directories:
 *
 * - Canonical snippet inputs: `patches/src/`
 * - Generated patch outputs: `patches/`
 * - Target vendored Statamic source: `vendor/statamic/cms/`
 *
 * Requirements:
 *
 * - `git` on `PATH`
 * - After regenerating `.patch` files, run **`composer patches-relock`**
 *
 * Usage from command classes: call `generatePatchFromValues($basename,
 * $targetDiffPath, $methodName)` with patch-specific constants.
 */
class StatamicPatchGenerator
{
    /**
     * Generate and write a patch file from explicit configuration values.
     *
     * @param  string  $basename  Shared basename for snippet and patch files
     * @param  string  $targetDiffPath  Target file path inside diff header
     * @param  string  $methodName  Method name to extract from snippet classes
     * @return string Generated patch output path
     */
    public function generatePatchFromValues(
        string $basename,
        string $targetDiffPath,
        string $methodName = 'createLabel'
    ): string {
        // Initialize workspace base path from Laravel helper
        $basePath = base_path();

        // Validate required patch-generation values
        $this->validatePatchValues(
            $basename,
            $targetDiffPath,
            $methodName
        );

        // ---
        // Build source and output file paths from the shared basename
        // ---

        // Build path to canonical snippet directory under patches/src
        $sourceDir = $basePath.'/patches/src';

        // Build path for the unified diff file written next to snippets
        $patchPath = sprintf('%s/patches/%s.patch', $basePath, $basename);

        // Build path to the original (upstream) snippet PHP file
        $originalPath = sprintf('%s/%s.original.php', $sourceDir, $basename);

        // Build path to the patched (desired) snippet PHP file
        $patchedPath = sprintf('%s/%s.patched.php', $sourceDir, $basename);

        // Build absolute path to the target PHP file inside vendor/statamic/cms
        $vendorFilePath = sprintf('%s/vendor/statamic/cms/%s', $basePath, $targetDiffPath);

        // ---
        // Read the canonical snippet sources
        // ---

        // Load original snippet file contents from disk
        $originalSource = file_get_contents($originalPath);

        // Load patched snippet file contents from disk
        $patchedSource = file_get_contents($patchedPath);

        // Check whether either snippet source failed to load
        if (
            $originalSource === false
            || $patchedSource === false
        ) {
            // Abort when snippet files are missing or unreadable
            throw new RuntimeException('Unable to read snippet source files.');
        }

        // Read the vendored Statamic target file
        $vendorSource = file_get_contents($vendorFilePath);

        // Check whether the vendor target file failed to load
        if ($vendorSource === false) {
            // Abort when the Statamic vendor target file is missing or unreadable
            throw new RuntimeException(
                'Unable to read vendor file: vendor/statamic/cms/'.$targetDiffPath
            );
        }

        // ---
        // Extract the target method from snippet and vendor sources
        // ---

        // Extract the method body from the original snippet class source
        $originalMethod = $this->extractMethod(
            $originalSource,
            $methodName
        );

        // Extract the method body from the patched snippet class source
        $patchedMethod = $this->extractMethod(
            $patchedSource,
            $methodName
        );

        // Normalize vendor file newlines for consistent comparisons and replacement
        $vendorNormalized = $this->normalizeUnixNewlines($vendorSource);

        // Extract the same method from the vendored Statamic file
        $vendorMethod = $this->extractMethod(
            $vendorNormalized,
            $methodName
        );

        // ---
        // Normalize method bodies for comparison
        // ---

        // Normalize the vendor method body for strict string comparison
        $normalizedVendorMethod = $this->normalizeUnixNewlines($vendorMethod);

        // Normalize the original snippet method for strict string comparison
        $normalizedOriginalMethod = $this->normalizeUnixNewlines($originalMethod);

        // Normalize the patched snippet method for strict string comparison
        $normalizedPatchedMethod = $this->normalizeUnixNewlines($patchedMethod);

        // ---
        // Build unpatched baseline file contents from the vendor tree
        // ---

        // Check whether Composer patches are already applied in the vendor tree
        if ($normalizedVendorMethod === $normalizedPatchedMethod) {
            // Reconstruct upstream by replacing the patched method with the original snippet
            $beforeFull = $this->replaceUniqueMethodSubstring(
                $vendorNormalized,
                $vendorMethod,
                $originalMethod
            );
        } elseif ($normalizedVendorMethod === $normalizedOriginalMethod) {
            // Use the vendor file as-is when patches are not applied yet
            $beforeFull = $vendorNormalized;
        } else {
            // Abort when the vendor method matches neither canonical snippet variant
            throw new RuntimeException(
                sprintf('The vendor method does not match either the original or patched snippet in vendor/statamic/cms/%s. ', $targetDiffPath)
                .sprintf('Update patches/src/%s.original.php and %s.patched.php, run `composer install`, then rerun.', $basename, $basename)
            );
        }

        // Build post-patch file contents from the unpatched baseline
        $afterFull = $this->replaceUniqueMethodSubstring(
            $beforeFull,
            $originalMethod,
            $patchedMethod
        );

        // Check whether the patched output is identical to the baseline
        if ($beforeFull === $afterFull) {
            // Abort when substitution produced no change (check snippet contents)
            throw new RuntimeException(
                'Patched method is identical to the original snippet; nothing to generate.'
            );
        }

        // Ensure git is available before building a diff
        $this->assertGitAvailable();

        // Build patch text with git diff for git-apply-compatible hunks
        $patchContents = $this->buildPatchContentsWithGit(
            $beforeFull,
            $afterFull,
            $targetDiffPath
        );

        // Check whether git produced an empty diff
        if (trim($patchContents) === '') {
            // Abort when git reports no differences between baseline and patched tree
            throw new RuntimeException(
                'Git produced an empty diff; patched method may be identical to vendor.'
            );
        }

        // Write generated patch contents to the output file
        $writeResult = file_put_contents(
            $patchPath,
            $this->ensureTrailingNewline($patchContents)
        );

        // Check whether writing the patch file failed
        if ($writeResult === false) {
            // Abort when the patch path is not writable
            throw new RuntimeException('Unable to write patch file.');
        }

        // Return the generated patch file path
        return $patchPath;
    }

    /**
     * Replace exactly one occurrence of a method substring inside a file.
     *
     * @param  string  $fileContents  Full file text
     * @param  string  $needleMethod  Method block to find
     * @param  string  $replacementMethod  Replacement method block
     * @return string Updated file text
     */
    private function replaceUniqueMethodSubstring(
        string $fileContents,
        string $needleMethod,
        string $replacementMethod
    ): string {
        // Find the first occurrence of the needle method text
        $offset = strpos(
            $fileContents,
            $needleMethod
        );

        // Check whether the needle method was not found
        if ($offset === false) {
            // Abort when the method substring is missing from the file buffer
            throw new RuntimeException(
                'Unable to locate method text inside the file for replacement.'
            );
        }

        // Check whether the needle method appears more than once
        if (str_contains(substr($fileContents, $offset + 1), $needleMethod)) {
            // Abort when the same method text would be replaced more than once
            throw new RuntimeException(
                'Ambiguous method match: the same method text appears more than once in the file.'
            );
        }

        // Replace the single occurrence and return the updated file
        return substr_replace(
            $fileContents,
            $replacementMethod,
            $offset,
            \strlen($needleMethod)
        );
    }

    /**
     * Assert that the `git` binary is available.
     */
    private function assertGitAvailable(): void
    {
        // ---
        // Run git --version to verify the binary is on PATH
        // ---

        // Build a subprocess that prints the git version string
        $process = new Process(['git', '--version']);

        // Run the version probe without interactive input
        $process->run();

        // Check whether git is not available
        if (! $process->isSuccessful()) {
            // Abort when git is missing from PATH or fails immediately
            throw new RuntimeException(
                'The `git` executable is required to generate Statamic patches. Install git or fix PATH.'
            );
        }
    }

    /**
     * Normalize line endings to Unix newlines for comparisons and patching.
     *
     * @param  string  $value  Input text
     * @return string Normalized text
     */
    private function normalizeUnixNewlines(
        string $value
    ): string {
        // Replace CRLF with LF for deterministic comparisons
        return str_replace(
            "\r\n",
            "\n",
            $value
        );
    }

    /**
     * Ensure the patch file ends with a single trailing newline.
     *
     * @param  string  $patchContents  Raw patch text from git
     * @return string Patch text with trailing newline
     */
    private function ensureTrailingNewline(
        string $patchContents
    ): string {
        // Check whether the patch already ends with a newline
        if (
            $patchContents !== ''
            && ! str_ends_with($patchContents, "\n")
        ) {
            // Append a trailing newline so patch files end with a POSIX newline
            return $patchContents.PHP_EOL;
        }

        // Return patch text unchanged when already newline-terminated
        return $patchContents;
    }

    /**
     * Run `git diff` in a temporary repository to build the patch file text.
     *
     * @param  string  $beforeContents  Full file contents before the change
     * @param  string  $afterContents  Full file contents after the change
     * @param  string  $targetDiffPath  Path relative to the package root
     * @return string Unified diff suitable for `git apply -p1`
     */
    private function buildPatchContentsWithGit(
        string $beforeContents,
        string $afterContents,
        string $targetDiffPath
    ): string {
        // Build a unique temporary working directory for the git repo
        $tmp = sys_get_temp_dir().'/statamic-patch-gen-'.bin2hex(random_bytes(8));

        // Check whether the temporary directory could not be created
        if (
            ! mkdir($tmp, 0700, true)
            && ! is_dir($tmp)
        ) {
            // Abort when the temp root directory cannot be created
            throw new RuntimeException('Unable to create temporary directory for git diff.');
        }

        // Ensure temp workspace cleanup even when git or IO fails mid-run
        try {
            // ---
            // Build the full path to the target file inside the temp tree
            // ---

            // Compose the absolute file path mirroring the package-relative path
            $fullPath = sprintf('%s/%s', $tmp, $targetDiffPath);

            // Resolve the parent directory for nested paths such as src/Entries
            $parentDir = dirname($fullPath);

            // ---
            // Create parent directories for the nested target path
            // ---

            // Check whether parent directories still need to be created
            if (
                ! is_dir($parentDir)
                && ! mkdir($parentDir, 0700, true)
            ) {
                // Abort when nested temp directories cannot be created
                throw new RuntimeException('Unable to create temporary path for git diff.');
            }

            // ---
            // Write the pre-change file contents and initialize git
            // ---

            // Write the unpatched baseline as the first tree snapshot
            file_put_contents(
                $fullPath,
                $beforeContents
            );

            // Initialize a new git repository in the temp working directory
            $this->runGit(
                $tmp,
                ['git', 'init', '-q']
            );

            // Set a local commit email so git can create the baseline commit
            $this->runGit(
                $tmp,
                ['git', 'config', 'user.email', 'statamic-patch-generator@local']
            );

            // Set a local commit author name for the baseline commit
            $this->runGit(
                $tmp,
                ['git', 'config', 'user.name', 'statamic-patch-generator']
            );

            // Stage the target file for the initial commit
            $this->runGit(
                $tmp,
                ['git', 'add', $targetDiffPath]
            );

            // Create the baseline commit git diff will compare against
            $this->runGit(
                $tmp,
                ['git', 'commit', '-q', '-m', 'base', '--no-gpg-sign']
            );

            // ---
            // Overwrite with post-change contents and capture the diff
            // ---

            // Overwrite the working tree with the patched file contents
            file_put_contents(
                $fullPath,
                $afterContents
            );

            // Build a process that diffs the working file against HEAD
            $process = new Process(
                ['git', 'diff', 'HEAD', '--', $targetDiffPath],
                $tmp
            );

            // Run git diff and populate stdout with the unified patch
            $process->run();

            // Check whether git diff failed
            if (! $process->isSuccessful()) {
                // Abort when git diff exits with a non-zero status
                throw new RuntimeException(
                    'git diff failed: '.$process->getErrorOutput()
                );
            }

            // Return unified diff text from git
            return $process->getOutput();
        } finally {
            // Remove the temporary directory tree
            $this->removeDirectoryRecursively($tmp);
        }
    }

    /**
     * Run a git command in a working directory or throw a runtime exception.
     *
     * @param  string  $workingDirectory  Git working tree path
     * @param  list<string>  $command  Command and arguments
     */
    private function runGit(
        string $workingDirectory,
        array $command
    ): void {
        // Execute the git subprocess
        $process = new Process(
            $command,
            $workingDirectory
        );

        // Run git and map process failures to a runtime exception
        try {
            // Require a successful exit without reading stdout here
            $process->mustRun();
        } catch (ProcessFailedException $processFailedException) {
            // Re-throw with stderr context while preserving the process chain
            throw new RuntimeException(
                'Git command failed: '.$process->getErrorOutput(),
                0,
                $processFailedException
            );
        }
    }

    /**
     * Delete a directory and all of its contents.
     *
     * @param  string  $path  Absolute directory path
     */
    private function removeDirectoryRecursively(
        string $path
    ): void {
        // Check whether the path is not a directory
        if (! is_dir($path)) {
            // Skip removal when the temp path was never created or was removed
            return;
        }

        // Walk the tree and unlink files before removing directories
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $path,
                \RecursiveDirectoryIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        // ---
        // Best-effort removal: Windows may deny unlink on `.git/objects` briefly
        // after `git` exits, which would mask a successful `git diff` otherwise.
        // ---

        // Remove each filesystem entry from deepest paths upward
        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            // Check whether the iterator entry is a directory
            if ($fileInfo->isDir()) {
                // Remove an empty subdirectory left after its children
                @rmdir($fileInfo->getPathname());
            } else {
                // Delete a file entry before its parent directories
                @unlink($fileInfo->getPathname());
            }
        }

        // Remove the now-empty temp repository root directory
        @rmdir($path);
    }

    /**
     * Extract a method block from a full PHP class source.
     *
     * @param  string  $source  Full class source contents
     * @param  string  $methodName  Method name to extract
     * @return string Extracted method block
     */
    private function extractMethod(
        string $source,
        string $methodName
    ): string {
        // Normalize line endings for deterministic parsing
        $normalizedSource = str_replace(
            "\r\n",
            "\n",
            $source
        );

        // Split normalized source into lines
        $lines = explode(
            "\n",
            $normalizedSource
        );

        // Initialize method start line index
        $methodStartLine = null;

        // ---
        // Locate the method declaration line
        // ---

        // Build a pattern matching a public, protected, or private declaration, with
        // an optional `static` between the visibility and `function`. Statamic's
        // Antlers runtime declares `ModifierManager::evaluate()` static, and without
        // this the scan finds no declaration and the generator aborts with "Unable to
        // find evaluate() in the given PHP source" — which reads as a missing method
        // rather than an unsupported modifier.
        $methodDeclarationPattern = '/^\s*(?:public|protected|private)\s+(?:static\s+)?function\s+'
            .preg_quote($methodName, '/')
            .'\s*\(/';

        // Scan class lines for the target method declaration
        foreach ($lines as $index => $line) {
            // Check whether this line declares the requested method
            if (preg_match($methodDeclarationPattern, $line) === 1) {
                // Record the zero-based line index of the method start
                $methodStartLine = $index;

                // Stop scanning after the declaration line is found
                break;
            }
        }

        // Check whether the method declaration was not found
        if ($methodStartLine === null) {
            // Abort when the source does not contain the expected method
            throw new RuntimeException(sprintf('Unable to find %s() in the given PHP source.', $methodName));
        }

        // Initialize collected method lines
        $methodLines = [];

        // Initialize running brace depth counter
        $braceDepth = 0;

        // Initialize opening-brace detection flag
        $hasSeenOpeningBrace = false;
        $counter = \count($lines);

        // Collect method lines until brace depth returns to zero
        for ($lineIndex = $methodStartLine; $lineIndex < $counter; $lineIndex++) {
            // Read the current line
            $line = $lines[$lineIndex];

            // Append the current line to the method buffer
            $methodLines[] = $line;

            // Count opening braces in the current line
            $openingBraceCount = substr_count(
                $line,
                '{'
            );

            // Count closing braces in the current line
            $closingBraceCount = substr_count(
                $line,
                '}'
            );

            // Check whether the line contains an opening brace
            if ($openingBraceCount > 0) {
                // Mark that the method body opening brace has been seen
                $hasSeenOpeningBrace = true;
            }

            // Increase brace depth by opening brace count
            $braceDepth += $openingBraceCount;

            // Decrease brace depth by closing brace count
            $braceDepth -= $closingBraceCount;

            // Stop once the full method block is captured
            if (
                $hasSeenOpeningBrace
                && $braceDepth === 0
            ) {
                // Stop collecting lines once braces balance back to zero
                break;
            }
        }

        // Build extracted method text without trailing blank lines
        return rtrim(
            implode(
                "\n",
                $methodLines
            ),
            "\n"
        );
    }

    /**
     * Validate required values before attempting patch generation.
     *
     * @param  string  $basename  Shared basename for snippet and patch files
     * @param  string  $targetDiffPath  Target file path inside diff header
     * @param  string  $methodName  Method name to extract from snippet classes
     */
    private function validatePatchValues(
        string $basename,
        string $targetDiffPath,
        string $methodName
    ): void {
        // Check whether required string inputs are empty
        if (
            $basename === ''
            || $targetDiffPath === ''
            || $methodName === ''
        ) {
            // Abort when any required generator argument is an empty string
            throw new RuntimeException('Missing required patch-generation values.');
        }
    }
}
