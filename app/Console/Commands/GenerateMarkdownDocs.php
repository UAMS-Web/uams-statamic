<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File as LaravelFile;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Generates a Markdown file with paths and contents of files from local paths or
 * GitHub repository URLs.
 *
 * This command reads a Markdown file containing a list of paths (local
 * folders/files or GitHub repository URLs) and optional directory exclusions.
 *
 * Local paths are processed from the filesystem with relative paths in output
 * (e.g., `app/Console/Commands/ImportWordPress.php`).
 *
 * GitHub URLs (e.g., directories like
 * https://github.com/owner/repo/tree/branch/path or files like
 * https://github.com/owner/repo/blob/branch/path) are fetched using the GitHub
 * Contents API, with full URLs in output
 * (e.g., `https://github.com/owner/repo/blob/branch/path/to/file.php`).
 *
 * Exclusions are prefixed with '!' and apply to both local and GitHub directories.
 *
 * Example input (`docs/input.md`):
 * ```
 * app/Console/Commands
 * https://github.com/ueberdosis/tiptap-php/tree/main/src/Core
 * https://github.com/ueberdosis/tiptap-php/blob/main/src/Nodes/CodeBlock.php
 * !app/Console/Commands/Tests
 * !src/Core/Tests
 * ```
 *
 * Usage:
 * ```bash
 * php artisan docs:generate docs/input.md
 * ```
 *
 * Set `GITHUB_TOKEN` in the environment (typically via `.env`) so it is merged
 * into `config('services.github.token')` for authenticated API requests to private
 * repositories or higher rate limits. Public repositories may work without a
 * token, subject to rate limits. The command validates inputs, skips invalid
 * paths/URLs, and logs warnings for errors.
 *
 * @see  https://laravel.com/docs/11.x/artisan
 * @see  https://docs.github.com/en/rest/repos/contents
 */
#[Description('Generates a Markdown file with paths and contents of files from local paths or GitHub repository URLs, with optional directory exclusions')]
#[Signature('docs:generate {markdown : Path to the Markdown file containing folder/file paths or GitHub URLs}')]
class GenerateMarkdownDocs extends Command
{
    /**
     * Execute the console command.
     *
     * @return int Exit code (0 for success, 1 for failure)
     */
    public function handle(): int
    {
        // Get the markdown file path from the command argument
        $markdownPath = $this->argument('markdown');

        // Check if the markdown file exists
        if (! LaravelFile::exists($markdownPath)) {
            $this->error(sprintf("The file '%s' does not exist.", $markdownPath));

            return 1;
        }

        // Read the content of the markdown file
        $content = LaravelFile::get($markdownPath);

        // Parse the content into lines, trim whitespace, and filter out empty lines
        $lines = \array_filter(
            \array_map(
                trim(...),
                \explode(
                    "\n",
                    $content
                )
            ),
            fn ($line): bool => ! empty($line)
        );

        // Check if any valid lines were found
        if ($lines === []) {
            // Display error message if no valid paths found
            $this->error(sprintf("No valid paths found in '%s'.", $markdownPath));

            // Return error exit code
            return 1;
        }

        // Initialize array to store paths to include
        $includePaths = [];

        // Initialize array to store directories to exclude
        $excludeDirs = [];

        // Loop through each line to separate includes and exclusions
        foreach ($lines as $line) {
            // Check if the line starts with '!' indicating an exclusion
            if (
                \str_starts_with(
                    $line,
                    '!'
                )
            ) {
                // Exclude directory (remove '!' prefix)
                $excludeDirs[] = \ltrim(
                    $line,
                    '!'
                );
            } else { // Otherwise
                // Include path (local file/folder or GitHub URL)
                $includePaths[] = $line;
            }
        }

        // Initialize the output string to accumulate markdown content
        $output = '';

        // Get the base path of the Laravel application
        $basePath = base_path();

        // Loop through each path to process
        foreach ($includePaths as $path) {
            // Check if the path is a GitHub URL
            if ($this->isGithubUrl($path)) {
                // Handle GitHub URL
                $output .= $this->processGithubUrl(
                    $path,
                    $excludeDirs
                );
            } else { // Otherwise
                // Handle local path
                $fullPath = base_path($path);

                // Check if the path is a file
                if (LaravelFile::isFile($fullPath)) {
                    // Handle individual local file
                    $output .= $this->processFile(
                        $fullPath,
                        $path
                    );
                } elseif (LaravelFile::isDirectory($fullPath)) { // Check if the path is a directory
                    // Handle local directory with exclusions
                    $files = $this->getFilesExcludingDirs(
                        $fullPath,
                        $excludeDirs
                    );

                    // Check if any files were found after applying exclusions
                    if ($files === []) {
                        // Display warning if no files found
                        $this->warn(sprintf("No files found in directory '%s' after applying exclusions. Skipping.", $path));

                        // Skip to next path
                        continue;
                    }

                    // Loop through each file in the directory
                    foreach ($files as $file) {
                        // Process the file and append to output
                        $output .= $this->processFile(
                            $file->getPathname(),
                            \str_replace(
                                $basePath.DIRECTORY_SEPARATOR,
                                '',
                                $file->getPathname()
                            )
                        );
                    }
                } else { // Otherwise
                    // Display warning if path is neither file nor directory
                    $this->warn(sprintf("Path '%s' is neither a file nor a directory. Skipping.", $path));

                    // Skip to next path
                    continue;
                }
            }
        }

        // Check if any output was generated
        if ($output === '' || $output === '0') {
            // Display error if no valid files were found
            $this->error('No valid files found in the specified paths after exclusions.');

            // Return error exit code
            return 1;
        }

        // Overwrite the original Markdown file
        LaravelFile::put(
            $markdownPath,
            $output
        );

        // Display success message
        $this->info(sprintf("Markdown file updated successfully at '%s'.", $markdownPath));

        // Return success exit code
        return 0;
    }

    /**
     * Check if a path is a GitHub repository URL.
     *
     * @param  string  $path  The path or URL to check
     * @return bool True if the path is a valid GitHub URL, false otherwise
     */
    private function isGithubUrl(
        string $path
    ): bool {
        return \preg_match(
            '/^https:\/\/github\.com\/[^\/]+\/[^\/]+\/(tree|blob)\/[^\/]+(\/.*)?$/',
            $path
        ) === 1;
    }

    /**
     * Parse a GitHub URL into its components.
     *
     * Extracts owner, repository, branch, path, and type (tree or blob) from a GitHub
     * URL. Returns null if the URL is invalid or cannot be parsed.
     *
     * @param  string  $url  The GitHub URL to parse
     * @return ?array{owner: string, repo: string, type: string, branch: string, path: string} Associative array with 'owner', 'repo', 'branch', 'path', 'type', or null if invalid
     */
    private function parseGithubUrl(
        string $url
    ): ?array {
        // Define regex pattern to match GitHub URL structure
        $pattern = '/^https:\/\/github\.com\/([^\/]+)\/([^\/]+)\/(tree|blob)\/([^\/]+)(\/.*)?$/';

        // Check if the URL matches the GitHub pattern
        if (
            \preg_match(
                $pattern,
                $url,
                $matches
            )
        ) {
            // Return parsed URL components as associative array
            return [
                'owner' => $matches[1],
                'repo' => $matches[2],
                'type' => $matches[3], // tree or blob
                'branch' => $matches[4],
                'path' => \ltrim(
                    $matches[5] ?? '',
                    '/'
                ),
            ];
        }

        // Return null if URL doesn't match the pattern
        return null;
    }

    /**
     * Process a GitHub URL and return Markdown content for its files.
     *
     * Fetches contents of a GitHub directory (tree) or file (blob) using the GitHub
     * Contents API. For directories, recursively fetches all files, skipping excluded
     * subdirectories. For files, fetches the content directly. Returns formatted
     * Markdown with full GitHub URLs
     * (e.g., https://github.com/owner/repo/blob/branch/path/to/file.php) and file
     * contents in code blocks.
     *
     * @param  string  $url  The GitHub URL (tree or blob)
     * @param  list<string>  $excludeDirs  Array of directory paths to exclude (relative to repository root)
     * @return string Markdown formatted output for all included files
     */
    private function processGithubUrl(
        string $url,
        array $excludeDirs
    ): string {
        // Parse the GitHub URL into its components
        $parsed = $this->parseGithubUrl($url);

        // Check if URL parsing was successful
        if (! $parsed) {
            // Display warning if URL is invalid
            $this->warn(sprintf("Invalid GitHub URL '%s'. Skipping.", $url));

            // Return empty string if URL is invalid
            return '';
        }

        // Extract repository owner from parsed URL
        $owner = $parsed['owner'];

        // Extract repository name from parsed URL
        $repo = $parsed['repo'];

        // Extract type (tree or blob) from parsed URL
        $type = $parsed['type'];

        // Extract branch name from parsed URL
        $branch = $parsed['branch'];

        // Extract file/directory path from parsed URL
        $path = $parsed['path'];

        // Initialize output string for markdown content
        $output = '';

        // GitHub token from config (backed by GITHUB_TOKEN in .env at config build time)
        $token = config('services.github.token');

        // Normalize token to a string for header interpolation
        $token = \is_scalar($token) ? (string) $token : '';

        // Set up HTTP headers for GitHub API requests
        $headers = [
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];

        // Check if GitHub token is available
        if ($token !== '' && $token !== '0') {
            // Add authorization header if token exists
            $headers['Authorization'] = 'Bearer '.$token;
        }

        // Check if the URL type is a blob (single file)
        if ($type === 'blob') {
            // Handle single file
            $response = Http::withHeaders($headers)->get(sprintf('https://api.github.com/repos/%s/%s/contents/%s?ref=%s', $owner, $repo, $path, $branch));

            // Check if the API request failed
            if ($response->failed()) {
                // Display warning if file fetch failed
                $this->warn(sprintf("Failed to fetch GitHub file '%s' from %s/%s. Status: %d. Skipping.", $path, $owner, $repo, $response->status()));

                // Return empty string on failure
                return '';
            }

            // Decode JSON response from GitHub API
            /** @var array<string, mixed> $data */
            $data = $response->json();

            // Check if the returned item is actually a file
            if ($data['type'] !== 'file') {
                // Display warning if path is not a file
                $this->warn(sprintf("GitHub path '%s' is not a file. Skipping.", $path));

                // Return empty string if not a file
                return '';
            }

            // Decode base64-encoded file content
            $encodedContent = $data['content'];
            $content = base64_decode(\is_string($encodedContent) ? $encodedContent : '');

            // Construct the full GitHub URL for the file
            $fileUrl = sprintf('https://github.com/%s/%s/blob/%s/%s', $owner, $repo, $branch, $path);

            // Format and append file content to output
            $output .= $this->formatFileContent(
                $fileUrl,
                $content,
                pathinfo(
                    $path,
                    PATHINFO_EXTENSION
                )
            );
        } else { // Otherwise
            // Handle directory (tree)
            $files = $this->getGithubFiles(
                $owner,
                $repo,
                $path,
                $branch,
                $excludeDirs,
                $headers
            );

            // Check if any files were found after exclusions
            if ($files === []) {
                // Display warning if no files found
                $this->warn(sprintf("No files found in GitHub directory '%s' from %s/%s after exclusions. Skipping.", $path, $owner, $repo));

                // Return empty string if no files
                return '';
            }

            // Loop through each file found
            foreach ($files as $file) {
                // Construct the full GitHub URL for the file
                $fileUrl = sprintf('https://github.com/%s/%s/blob/%s/%s', $owner, $repo, $branch, $file['path']);

                // Format and append file content to output
                $output .= $this->formatFileContent(
                    $fileUrl,
                    $file['content'],
                    pathinfo(
                        $file['path'],
                        PATHINFO_EXTENSION
                    )
                );
            }
        }

        // Return the accumulated markdown output
        return $output;
    }

    /**
     * Fetch all files from a GitHub directory recursively, excluding specified
     * directories.
     *
     * Uses the GitHub Contents API to retrieve directory contents. For
     * subdirectories, recursively fetches files unless the directory is excluded.
     * Files are returned with their repository paths and decoded contents.
     *
     * @param  string  $owner  GitHub repository owner (user or organization)
     * @param  string  $repo  GitHub repository name
     * @param  string  $path  Directory path in the repository (empty for root)
     * @param  string  $branch  Branch name
     * @param  list<string>  $excludeDirs  Array of directory paths to exclude (relative to repository root)
     * @param  array<string, string>  $headers  HTTP headers for API requests (includes authentication)
     * @return list<array{path: string, content: string}> Array of file data (path and content)
     */
    private function getGithubFiles(
        string $owner,
        string $repo,
        string $path,
        string $branch,
        array $excludeDirs,
        array $headers
    ): array {
        // Initialize array to store file data
        $files = [];

        // Fetch directory contents from GitHub API
        $response = Http::withHeaders($headers)->get(sprintf('https://api.github.com/repos/%s/%s/contents/%s?ref=%s', $owner, $repo, $path, $branch));

        // Check if the API request failed
        if ($response->failed()) {
            // Display warning if directory fetch failed
            $this->warn(sprintf("Failed to fetch GitHub directory '%s' from %s/%s. Status: %d. Skipping.", $path, $owner, $repo, $response->status()));

            // Return empty array on failure
            return [];
        }

        $contents = $response->json();

        // Check if the response is a valid array
        if (! \is_array($contents)) {
            // Display warning if response is invalid
            $this->warn(sprintf("Invalid response for GitHub directory '%s' from %s/%s. Skipping.", $path, $owner, $repo));

            // Return empty array if response is invalid
            return [];
        }

        // Loop through each item in the directory
        foreach ($contents as $item) {
            // Skip any non-array entries in the directory listing
            if (! \is_array($item)) {
                // Ignore malformed item
                continue;
            }

            // Get the path of the current item
            $itemPath = \is_string($item['path']) ? $item['path'] : '';

            // Check if this item's path is in the exclusion list
            if (
                \in_array(
                    $itemPath,
                    $excludeDirs
                )
            ) {
                // Skip this item if it's excluded
                continue;
            }

            // Check if the item is a file
            if ($item['type'] === 'file') {
                // Fetch the file content from GitHub API
                $itemUrl = \is_string($item['url']) ? $item['url'] : '';
                $fileResponse = Http::withHeaders($headers)->get($itemUrl);

                // Check if the file fetch was successful
                if ($fileResponse->successful()) {
                    // Decode JSON response containing file data
                    /** @var array<string, mixed> $fileData */
                    $fileData = $fileResponse->json();

                    // Decode base64-encoded file content
                    $encodedContent = $fileData['content'];

                    // Add file data to the files array
                    $files[] = [
                        'path' => $itemPath,
                        'content' => base64_decode(\is_string($encodedContent) ? $encodedContent : ''),
                    ];
                } else { // Otherwise
                    // Display warning if file fetch failed
                    $this->warn(sprintf("Failed to fetch GitHub file '%s' from %s/%s. Status: %d. Skipping.", $itemPath, $owner, $repo, $fileResponse->status()));
                }
            } elseif ($item['type'] === 'dir') {
                $isExcluded = array_any($excludeDirs, fn (string $excludeDir): bool => \str_starts_with(
                    $itemPath,
                    $excludeDir
                ));
                // Check if directory is not excluded
                if (! $isExcluded) {
                    // Recursively fetch files from subdirectory
                    $subFiles = $this->getGithubFiles(
                        $owner,
                        $repo,
                        $itemPath,
                        $branch,
                        $excludeDirs,
                        $headers
                    );

                    // Merge subdirectory files into main files array
                    $files = \array_merge(
                        $files,
                        $subFiles
                    );
                }
            }
        }

        return $files;
    }

    /**
     * Process a single local file and return its Markdown representation.
     *
     * Reads the file's content, computes its relative path (or uses provided path for
     * consistency), determines the appropriate language for Markdown code block
     * syntax highlighting based on the file extension, and formats the output.
     *
     * @param  string  $filePath  Absolute path to the file
     * @param  string  $relativePath  Relative path to use in output
     * @return string Markdown formatted output for the file
     */
    private function processFile(
        string $filePath,
        string $relativePath
    ): string {
        // Read the content of the local file
        $fileContent = LaravelFile::get($filePath);

        // Extract the file extension for syntax highlighting
        $extension = pathinfo(
            $filePath,
            PATHINFO_EXTENSION
        );

        // Format and return the file content as markdown
        return $this->formatFileContent(
            $relativePath,
            $fileContent,
            $extension
        );
    }

    /**
     * Format file content as a Markdown code block.
     *
     * Creates a Markdown representation with the file path (local relative path or
     * full GitHub URL) in backticks and content in a language-specific code block.
     * Uses a language map to determine the code block language based on the file
     * extension.
     *
     * @param  string  $path  File path to display (relative for local, full GitHub URL for GitHub)
     * @param  string  $content  File content
     * @param  string  $extension  File extension (e.g., 'php', 'js')
     * @return string Markdown formatted output
     *                (e.g., "`path/to/file.php`:\n```php\n{content}\n```\n\n")
     */
    private function formatFileContent(
        string $path,
        string $content,
        string $extension
    ): string {
        // Map file extensions to Markdown code block languages
        $languageMap = [
            'php' => 'php',
            'js' => 'javascript',
            'ts' => 'typescript',
            'css' => 'css',
            'scss' => 'scss',
            'html' => 'html',
            'blade' => 'blade',
            'md' => 'markdown',
            'json' => 'json',
            'yaml' => 'yaml',
            'yml' => 'yaml',
            'xml' => 'xml',
            'antlers.html' => 'antlers',
            // Add more as needed
        ];

        // Get the language identifier for syntax highlighting, defaulting to empty string
        $language = $languageMap[\strtolower($extension)] ?? '';

        // Return formatted markdown with file path and code block
        return "`{$path}`:\n```{$language}\n{$content}\n```\n\n";
    }

    /**
     * Get all files in a local directory, excluding specified subdirectories.
     *
     * Recursively collects files from the given directory, skipping any files whose
     * directory or parent directories match the excluded paths. Excluded directories
     * are resolved to absolute paths to ensure accurate matching. Non-existent
     * exclude directories are ignored.
     *
     * @param  string  $directory  Absolute path to the directory to scan
     * @param  list<string>  $excludeDirs  Array of relative directory paths to exclude (relative to project root)
     * @return list<SplFileInfo> Array of SplFileInfo objects representing included files
     */
    private function getFilesExcludingDirs(
        string $directory,
        array $excludeDirs
    ): array {
        // Initialize array to store included files
        $files = [];

        // ---
        // Convert exclude directory paths to absolute paths and filter out non-existent
        // directories
        // ---
        $excludeFullPaths = \array_map(
            fn (string $dir): string|false => realpath(base_path($dir)),
            \array_filter(
                $excludeDirs,
                fn (string $dir) => LaravelFile::isDirectory(base_path($dir))
            )
        );

        // Get all files recursively
        $allFiles = LaravelFile::allFiles($directory);

        // Loop through each file found
        foreach ($allFiles as $file) {
            // Get the absolute path of the file's directory
            $fileDir = realpath(dirname($file->getPathname()));
            $isExcluded = array_any($excludeFullPaths, fn (string|false $excludePath): bool => \str_starts_with(
                (string) $fileDir,
                (string) $excludePath
            ));

            // Check if file is not excluded
            if (! $isExcluded) {
                // Add file to the included files array
                $files[] = $file;
            }
        }

        // Return array of included files
        return $files;
    }
}
