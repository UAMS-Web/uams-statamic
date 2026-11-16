<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;
use Symfony\Component\Finder\SplFileInfo;

/**
 * The two file steps {@see EntriesAndTermsIsolatedTestCase} takes when it brings its
 * per-process copy in line with the shared content mirror (UAMS-Web/uams-statamic#558).
 *
 * The mirror is shared by every parallel worker, and a test on a base that does not
 * isolate entries still saves into it, so a file can vanish between the copy's
 * listing and its next read. Both steps then treat the file as gone, as the Stache
 * traverser's vanished-file skip does (UAMS-Web/uams-statamic-archive#2639): only
 * when a fresh listing of its directory no longer holds it. Every other failure still
 * raises.
 */
final class MirrorCopyFiles
{
    /**
     * Each listed file as its relative path mapped to `size:mtime`, skipping a file
     * removed after it was listed.
     *
     * @param  array<array-key, SplFileInfo>  $files  The listing, as `File::allFiles()` returns it
     * @return array<string, string>
     *
     * @throws RuntimeException When a file that is still listed cannot be stat'ed
     */
    public static function stamps(array $files): array
    {
        $stamps = [];

        foreach ($files as $file) {
            try {
                $stamp = $file->getSize().':'.$file->getMTime();
            } catch (RuntimeException $e) {
                // Skip the file only when a fresh listing shows it was removed after it was listed
                if (! self::removedFrom($file->getPath(), $file->getFilename())) {
                    throw $e;
                }

                continue;
            }

            // Key on the forward-slashed relative path so both trees compare alike
            $stamps[str_replace('\\', '/', $file->getRelativePathname())] = $stamp;
        }

        return $stamps;
    }

    /**
     * Copy a file, or remove the destination when the source was removed first.
     *
     * Removing the destination keeps a stale copy from standing in for a file the
     * mirror no longer holds.
     *
     * @param  string  $source  The file in the shared mirror
     * @param  string  $destination  Its place in the per-process copy
     * @return bool True when the file was copied, false when the source was gone
     *
     * @throws RuntimeException When the copy fails and the source is still there
     */
    public static function copyOrForget(string $source, string $destination): bool
    {
        [$copied, $warning] = self::withoutWarnings(static fn (): bool => \copy($source, $destination));

        if ($copied) {
            return true;
        }

        if (self::removedFrom(\dirname($source), \basename($source))) {
            if (\file_exists($destination)) {
                \unlink($destination);
            }

            return false;
        }

        throw new RuntimeException("Could not copy \"{$source}\" into this process's copy at \"{$destination}\": ".($warning ?? 'no reason given'));
    }

    /**
     * Whether a fresh listing of the directory shows the file is gone.
     *
     * A directory that can no longer be listed confirms nothing, so it reads as not removed.
     *
     * @param  string  $directory  The directory that held the file
     * @param  string  $filename  The file's name inside it
     */
    private static function removedFrom(string $directory, string $filename): bool
    {
        [$listing] = self::withoutWarnings(static fn (): array|false => \scandir($directory));

        return $listing !== false && ! \in_array($filename, $listing, true);
    }

    /**
     * Run a filesystem call with its warning captured rather than raised.
     *
     * The caller judges the failure itself, so the warning is kept only as its reason.
     *
     * @template T
     *
     * @param  callable(): T  $call  The call to run
     * @return array{0: T, 1: string|null} The call's result, and the last warning it raised
     */
    private static function withoutWarnings(callable $call): array
    {
        $warning = null;

        set_error_handler(static function (int $level, string $message) use (&$warning): bool {
            $warning = $message;

            return true;
        }, E_WARNING);

        try {
            return [$call(), $warning];
        } finally {
            restore_error_handler();
        }
    }
}
