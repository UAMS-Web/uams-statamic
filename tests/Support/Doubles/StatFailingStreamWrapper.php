<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

/**
 * An in-memory stream wrapper whose stat or directory listing can be made to
 * fail after a traversal has already listed its files.
 *
 * It drives the two controls on the Stache traverser's vanished-file skip
 * (UAMS-Web/uams-statamic-archive#2639): a file that is still listed but cannot be stat'ed, and a directory
 * that can no longer be listed. Both must still raise. Filesystem permissions
 * could produce the same failures, but not portably: a permission denial means
 * nothing to a process running as root, and Windows has no mode bits to clear.
 * A wrapper behaves identically everywhere, so neither control needs a
 * platform skip.
 *
 * Only the operations Finder and the traverser call are implemented. Files are
 * held as paths relative to the root, and a directory exists wherever a file
 * path passes through it, so a fixture can nest files the way a collection's
 * entries nest under a subdirectory.
 */
final class StatFailingStreamWrapper
{
    /**
     * Scheme the wrapper is registered under.
     */
    public const string SCHEME = 'stat-failing';

    /**
     * Fixed modification time reported for every file and directory.
     */
    public const int MTIME = 1_700_000_000;

    /**
     * Paths of the files the tree holds, relative to the root.
     *
     * @var list<string>
     */
    public static array $files = [];

    /**
     * Relative path of the file whose stat fails once armed, or null for none.
     */
    public static ?string $fileWithFailingStat = null;

    /**
     * Whether listing any directory fails.
     */
    public static bool $listingFails = false;

    /**
     * Stream context PHP assigns to every wrapper instance.
     *
     * @var resource|null
     */
    public $context;

    /**
     * Relative path of the directory this handle has open.
     */
    private string $openDirectory = '';

    /**
     * Entries still to be returned by the open directory handle.
     *
     * @var list<string>
     */
    private array $entries = [];

    /**
     * Register the wrapper with a fresh, unarmed tree of the given files.
     *
     * @param  list<string>  $files  Paths of the files the tree holds, relative to the root
     */
    public static function register(array $files): void
    {
        // Replace any registration a previous test left behind
        self::unregister();

        self::$files = $files;
        self::$fileWithFailingStat = null;
        self::$listingFails = false;

        stream_wrapper_register(self::SCHEME, self::class);
    }

    /**
     * Remove the wrapper's registration, if it has one.
     */
    public static function unregister(): void
    {
        // Unregister only a scheme that is registered, which PHP otherwise warns on
        if (\in_array(self::SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_unregister(self::SCHEME);
        }
    }

    /**
     * The root directory's URL.
     */
    public static function root(): string
    {
        return self::SCHEME.'://root';
    }

    /**
     * Open a directory for listing.
     *
     * @param  string  $path  URL being opened
     * @param  int  $options  Flags PHP passes to every wrapper call
     * @return bool Whether the directory could be opened
     */
    public function dir_opendir(string $path, int $options): bool
    {
        $relative = self::relativePath($path);

        // Fail the listing when armed, or for anything that is not a directory
        if (self::$listingFails || $relative === null || ! self::isDirectory($relative)) {
            return false;
        }

        $this->openDirectory = $relative;
        $this->entries = ['.', '..', ...self::childrenOf($relative)];

        return true;
    }

    /**
     * Return the next entry of the open directory.
     *
     * @return string|false The entry's name, or false when none remain
     */
    public function dir_readdir(): string|false
    {
        return array_shift($this->entries) ?? false;
    }

    /**
     * Restart the listing from its first entry.
     *
     * @return bool Always true
     */
    public function dir_rewinddir(): bool
    {
        $this->entries = ['.', '..', ...self::childrenOf($this->openDirectory)];

        return true;
    }

    /**
     * Close the open directory.
     *
     * @return bool Always true
     */
    public function dir_closedir(): bool
    {
        $this->entries = [];

        return true;
    }

    /**
     * Stat a directory or a file in the tree.
     *
     * @param  string  $path  URL being stat'ed
     * @param  int  $flags  `STREAM_URL_STAT_*` flags
     * @return array<string, int>|false The stat record, or false when the stat fails
     */
    public function url_stat(string $path, int $flags): array|false
    {
        $relative = self::relativePath($path);

        // Fail the stat of anything outside the root
        if ($relative === null) {
            return false;
        }

        // Report a directory the tree passes through
        if (self::isDirectory($relative)) {
            return ['mode' => 0o040755, 'size' => 0, 'mtime' => self::MTIME];
        }

        // Fail the stat of a file the tree does not hold, or of the armed file
        if (! \in_array($relative, self::$files, true) || $relative === self::$fileWithFailingStat) {
            return false;
        }

        return ['mode' => 0o100644, 'size' => 0, 'mtime' => self::MTIME];
    }

    /**
     * The path relative to the root, or null for a URL outside it.
     *
     * @param  string  $path  URL to resolve
     * @return string|null The relative path, empty for the root itself
     */
    private static function relativePath(string $path): ?string
    {
        $root = self::root();

        // Reject a URL that does not start at the root
        if (! str_starts_with($path, $root)) {
            return null;
        }

        // Read either separator as `/`, and collapse repeats so `root//sub` and
        // `root/sub` agree. PHP's directory iterators join a child onto a wrapper
        // URL with `DIRECTORY_SEPARATOR`, so on Windows a listed subdirectory
        // arrives as `root\sub`, which read literally is no directory (UAMS-Web/uams-statamic-archive#3296).
        $relative = preg_replace('#[/\\\\]+#', '/', substr($path, \strlen($root))) ?? '';

        return trim($relative, '/');
    }

    /**
     * Whether a relative path is the root or a directory some file passes through.
     *
     * @param  string  $relative  Path relative to the root
     */
    private static function isDirectory(string $relative): bool
    {
        // Treat the root as a directory whatever the tree holds
        if ($relative === '') {
            return true;
        }

        foreach (self::$files as $file) {
            // Match a file that sits somewhere beneath this path
            if (str_starts_with($file, $relative.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Names of the files and directories directly inside a directory.
     *
     * @param  string  $relative  Directory path relative to the root
     * @return list<string> The names, each listed once
     */
    private static function childrenOf(string $relative): array
    {
        $prefix = $relative === '' ? '' : $relative.'/';
        $children = [];

        foreach (self::$files as $file) {
            // Keep only files beneath this directory
            if ($prefix !== '' && ! str_starts_with($file, $prefix)) {
                continue;
            }

            // Take the first segment below the directory, a file or a subdirectory
            $children[] = explode('/', substr($file, \strlen($prefix)))[0];
        }

        return array_values(array_unique($children));
    }
}
