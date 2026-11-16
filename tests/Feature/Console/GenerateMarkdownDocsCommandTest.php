<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Coverage for the `docs:generate` command (GenerateMarkdownDocs): it reads a list of local
 * paths and GitHub URLs, with `!` exclusions, and overwrites that list with each file's path
 * and contents as fenced Markdown.
 *
 * Local paths are resolved against the application root, so each test works in its own
 * uniquely named directory under `storage/framework/testing`, removed afterwards. GitHub is
 * never reached: every request goes to `Http::fake()`, and a stray one fails the test.
 *
 * @command  php artisan test --compact tests/Feature/Console/GenerateMarkdownDocsCommandTest.php
 */
uses(TestCase::class);

beforeEach(function (): void {
    $this->docsRelative = 'storage/framework/testing/markdown-docs-'.bin2hex(random_bytes(6));
    $this->docsDir = base_path($this->docsRelative);
    File::makeDirectory($this->docsDir, 0755, true);
    $this->docsInput = $this->docsDir.'/input.md';

    Http::preventStrayRequests();
});

afterEach(function (): void {
    File::deleteDirectory($this->docsDir);
});

/**
 * Write a file under the test's directory, creating its parents.
 */
function markdownDocsFile(string $dir, string $relative, string $contents): void
{
    $path = $dir.'/'.$relative;
    File::ensureDirectoryExists(dirname($path));
    File::put($path, $contents);
}

/**
 * The command's output, one trimmed line per element, blank lines dropped.
 *
 * @return list<string>
 */
function markdownDocsOutputLines(): array
{
    return array_values(array_filter(
        array_map(trim(...), explode("\n", Artisan::output())),
        static fn (string $line): bool => $line !== '',
    ));
}

/**
 * The rewritten input file, with Windows directory separators made forward slashes.
 */
function markdownDocsResult(string $input): string
{
    return str_replace('\\', '/', (string) file_get_contents($input));
}

/**
 * A GitHub Contents API file response.
 *
 * @return array{type: string, content: string}
 */
function githubContentsFile(string $contents): array
{
    return ['type' => 'file', 'content' => base64_encode($contents)];
}

it('refuses a list file that does not exist', function (): void {
    expect(Artisan::call('docs:generate', ['markdown' => $this->docsInput]))->toBe(1)
        ->and(markdownDocsOutputLines())->toBe(["The file '{$this->docsInput}' does not exist."])
        ->and(file_exists($this->docsInput))->toBeFalse();
});

it('refuses a list with no paths, and leaves it as it was', function (): void {
    File::put($this->docsInput, "  \n\n\t\n");

    expect(Artisan::call('docs:generate', ['markdown' => $this->docsInput]))->toBe(1)
        ->and(markdownDocsOutputLines())->toBe(["No valid paths found in '{$this->docsInput}'."])
        ->and(file_get_contents($this->docsInput))->toBe("  \n\n\t\n");
});

it('refuses a list none of whose paths yields a file, and leaves it as it was', function (): void {
    $list = "{$this->docsRelative}/no-such-path\nhttps://github.com/starfleet/enterprise\n";
    File::put($this->docsInput, $list);

    expect(Artisan::call('docs:generate', ['markdown' => $this->docsInput]))->toBe(1)
        ->and(markdownDocsOutputLines())->toBe([
            "Path '{$this->docsRelative}/no-such-path' is neither a file nor a directory. Skipping.",
            "Path 'https://github.com/starfleet/enterprise' is neither a file nor a directory. Skipping.",
            'No valid files found in the specified paths after exclusions.',
        ])
        ->and(file_get_contents($this->docsInput))->toBe($list);
});

it('writes each local file, every file of a directory but the excluded ones, and skips what it cannot read', function (): void {
    $base = $this->docsRelative;
    markdownDocsFile($this->docsDir, 'kirk.php', '<?php echo "captain";');
    markdownDocsFile($this->docsDir, 'bridge/a-helm.js', 'sulu();');
    markdownDocsFile($this->docsDir, 'bridge/b-nav.YML', 'chekov: true');
    markdownDocsFile($this->docsDir, 'bridge/c-log.txt', 'captains log');
    markdownDocsFile($this->docsDir, 'bridge/d-view.antlers.html', '{{ uhura }}');
    markdownDocsFile($this->docsDir, 'bridge/brig/e-prisoner.php', 'a prisoner');
    markdownDocsFile($this->docsDir, 'bridge/sickbay/f-chart.md', 'mccoy');
    markdownDocsFile($this->docsDir, 'shuttle/galileo.php', 'excluded');

    File::put($this->docsInput, implode("\n", [
        "  {$base}/kirk.php  ",
        '',
        "{$base}/bridge",
        "!{$base}/bridge/brig",
        "{$base}/shuttle",
        "!{$base}/shuttle",
        "!{$base}/no-such-dir",
        "{$base}/no-such-file.php",
    ]));

    expect(Artisan::call('docs:generate', ['markdown' => $this->docsInput]))->toBe(0)
        ->and(markdownDocsOutputLines())->toBe([
            "No files found in directory '{$base}/shuttle' after applying exclusions. Skipping.",
            "Path '{$base}/no-such-file.php' is neither a file nor a directory. Skipping.",
            "Markdown file updated successfully at '{$this->docsInput}'.",
        ])
        ->and(markdownDocsResult($this->docsInput))->toBe(
            "`{$base}/kirk.php`:\n```php\n<?php echo \"captain\";\n```\n\n"
            ."`{$base}/bridge/a-helm.js`:\n```javascript\nsulu();\n```\n\n"
            ."`{$base}/bridge/b-nav.YML`:\n```yaml\nchekov: true\n```\n\n"
            ."`{$base}/bridge/c-log.txt`:\n```\ncaptains log\n```\n\n"
            ."`{$base}/bridge/d-view.antlers.html`:\n```html\n{{ uhura }}\n```\n\n"
            ."`{$base}/bridge/sickbay/f-chart.md`:\n```markdown\nmccoy\n```\n\n"
        );
});

it('fences every mapped extension in its language', function (string $extension, string $language): void {
    markdownDocsFile($this->docsDir, "file.{$extension}", 'x');
    File::put($this->docsInput, "{$this->docsRelative}/file.{$extension}");

    expect(Artisan::call('docs:generate', ['markdown' => $this->docsInput]))->toBe(0)
        ->and(markdownDocsResult($this->docsInput))->toBe("`{$this->docsRelative}/file.{$extension}`:\n```{$language}\nx\n```\n\n");
})->with([
    ['php', 'php'],
    ['js', 'javascript'],
    ['ts', 'typescript'],
    ['css', 'css'],
    ['scss', 'scss'],
    ['html', 'html'],
    ['blade', 'blade'],
    ['md', 'markdown'],
    ['json', 'json'],
    ['yaml', 'yaml'],
    ['yml', 'yaml'],
    ['xml', 'xml'],
]);

it('fetches a GitHub file with the configured token, under its full URL', function (): void {
    config(['services.github.token' => 'ncc-1701']);

    Http::fake([
        'https://api.github.com/repos/starfleet/enterprise/contents/src/Bridge/Helm.php?ref=main' => Http::response(githubContentsFile('<?php // helm')),
    ]);

    File::put($this->docsInput, 'https://github.com/starfleet/enterprise/blob/main/src/Bridge/Helm.php');

    expect(Artisan::call('docs:generate', ['markdown' => $this->docsInput]))->toBe(0)
        ->and(file_get_contents($this->docsInput))->toBe(
            "`https://github.com/starfleet/enterprise/blob/main/src/Bridge/Helm.php`:\n```php\n<?php // helm\n```\n\n"
        );

    Http::assertSent(static fn (Request $request): bool => $request->header('Authorization') === ['Bearer ncc-1701']
        && $request->header('Accept') === ['application/vnd.github+json']
        && $request->header('X-GitHub-Api-Version') === ['2022-11-28']);
});

it('sends no token when none is configured, or when it is "0"', function (mixed $token): void {
    config(['services.github.token' => $token]);

    Http::fake([
        'https://api.github.com/repos/starfleet/enterprise/contents/README.md?ref=main' => Http::response(githubContentsFile('# Enterprise')),
    ]);

    File::put($this->docsInput, 'https://github.com/starfleet/enterprise/blob/main/README.md');

    expect(Artisan::call('docs:generate', ['markdown' => $this->docsInput]))->toBe(0);

    Http::assertSent(static fn (Request $request): bool => ! $request->hasHeader('Authorization'));
})->with([
    'none' => [null],
    'empty' => [''],
    'zero' => ['0'],
    'not a scalar' => [['ncc-1701']],
]);

it('skips a GitHub file it cannot fetch, or that is not a file, and goes on to the next', function (): void {
    Http::fake([
        'https://api.github.com/repos/starfleet/enterprise/contents/missing.php?ref=main' => Http::response(['message' => 'Not Found'], 404),
        'https://api.github.com/repos/starfleet/enterprise/contents/src?ref=main' => Http::response(['type' => 'dir', 'content' => '']),
        'https://api.github.com/repos/starfleet/enterprise/contents/kept.php?ref=main' => Http::response(githubContentsFile('kept')),
    ]);

    File::put($this->docsInput, implode("\n", [
        'https://github.com/starfleet/enterprise/blob/main/missing.php',
        'https://github.com/starfleet/enterprise/blob/main/src',
        'https://github.com/starfleet/enterprise/blob/main/kept.php',
    ]));

    expect(Artisan::call('docs:generate', ['markdown' => $this->docsInput]))->toBe(0)
        ->and(markdownDocsOutputLines())->toBe([
            "Failed to fetch GitHub file 'missing.php' from starfleet/enterprise. Status: 404. Skipping.",
            "GitHub path 'src' is not a file. Skipping.",
            "Markdown file updated successfully at '{$this->docsInput}'.",
        ])
        ->and(file_get_contents($this->docsInput))->toBe(
            "`https://github.com/starfleet/enterprise/blob/main/kept.php`:\n```php\nkept\n```\n\n"
        );
});

it('walks a GitHub directory, recursing into subdirectories and leaving out the excluded paths', function (): void {
    $api = 'https://api.github.com/repos/starfleet/enterprise/contents/';

    Http::fake([
        $api.'src?ref=dev' => Http::response([
            'not an item',
            ['path' => 'src/Bridge.php', 'type' => 'file', 'url' => $api.'src/Bridge.php?ref=dev'],
            ['path' => 'src/Brig.php', 'type' => 'file', 'url' => $api.'src/Brig.php?ref=dev'],
            ['path' => 'src/Broken.php', 'type' => 'file', 'url' => $api.'src/Broken.php?ref=dev'],
            ['path' => 'src/Decks', 'type' => 'dir', 'url' => $api.'src/Decks?ref=dev'],
            ['path' => 'src/Tests', 'type' => 'dir', 'url' => $api.'src/Tests?ref=dev'],
            ['path' => 'src/Hangar', 'type' => 'dir', 'url' => $api.'src/Hangar?ref=dev'],
            ['path' => 'src/warp-core', 'type' => 'symlink', 'url' => $api.'src/warp-core?ref=dev'],
            ['path' => 'src/z-last.css', 'type' => 'file', 'url' => $api.'src/z-last.css?ref=dev'],
        ]),
        $api.'src/Bridge.php?ref=dev' => Http::response(githubContentsFile('bridge')),
        $api.'src/Broken.php?ref=dev' => Http::response([], 500),
        $api.'src/Decks?ref=dev' => Http::response([
            ['path' => 'src/Decks/Deck1.php', 'type' => 'file', 'url' => $api.'src/Decks/Deck1.php?ref=dev'],
        ]),
        $api.'src/Decks/Deck1.php?ref=dev' => Http::response(githubContentsFile('deck one')),
        $api.'src/z-last.css?ref=dev' => Http::response(githubContentsFile('body {}')),
    ]);

    File::put($this->docsInput, implode("\n", [
        'https://github.com/starfleet/enterprise/tree/dev/src',
        '!src/Brig.php',
        '!src/Tests',
        '!src/Hang',
    ]));

    expect(Artisan::call('docs:generate', ['markdown' => $this->docsInput]))->toBe(0)
        ->and(markdownDocsOutputLines())->toBe([
            "Failed to fetch GitHub file 'src/Broken.php' from starfleet/enterprise. Status: 500. Skipping.",
            "Markdown file updated successfully at '{$this->docsInput}'.",
        ])
        ->and(file_get_contents($this->docsInput))->toBe(
            "`https://github.com/starfleet/enterprise/blob/dev/src/Bridge.php`:\n```php\nbridge\n```\n\n"
            ."`https://github.com/starfleet/enterprise/blob/dev/src/Decks/Deck1.php`:\n```php\ndeck one\n```\n\n"
            ."`https://github.com/starfleet/enterprise/blob/dev/src/z-last.css`:\n```css\nbody {}\n```\n\n"
        );

    // The excluded file and the excluded directories are never requested
    Http::assertNotSent(static fn (Request $request): bool => str_contains($request->url(), 'Brig.php')
        || str_contains($request->url(), 'Tests')
        || str_contains($request->url(), 'Hangar'));
});

it('skips a GitHub directory it cannot list, or whose listing is not a list', function (): void {
    $api = 'https://api.github.com/repos/starfleet/enterprise/contents/';

    Http::fake([
        $api.'gone?ref=main' => Http::response(['message' => 'Not Found'], 404),
        $api.'odd?ref=main' => Http::response('"not a listing"'),
        $api.'empty?ref=main' => Http::response([]),
        $api.'kept.php?ref=main' => Http::response(githubContentsFile('kept')),
    ]);

    File::put($this->docsInput, implode("\n", [
        'https://github.com/starfleet/enterprise/tree/main/gone',
        'https://github.com/starfleet/enterprise/tree/main/odd',
        'https://github.com/starfleet/enterprise/tree/main/empty',
        'https://github.com/starfleet/enterprise/blob/main/kept.php',
    ]));

    expect(Artisan::call('docs:generate', ['markdown' => $this->docsInput]))->toBe(0)
        ->and(markdownDocsOutputLines())->toBe([
            "Failed to fetch GitHub directory 'gone' from starfleet/enterprise. Status: 404. Skipping.",
            "No files found in GitHub directory 'gone' from starfleet/enterprise after exclusions. Skipping.",
            "Invalid response for GitHub directory 'odd' from starfleet/enterprise. Skipping.",
            "No files found in GitHub directory 'odd' from starfleet/enterprise after exclusions. Skipping.",
            "No files found in GitHub directory 'empty' from starfleet/enterprise after exclusions. Skipping.",
            "Markdown file updated successfully at '{$this->docsInput}'.",
        ])
        ->and(file_get_contents($this->docsInput))->toBe(
            "`https://github.com/starfleet/enterprise/blob/main/kept.php`:\n```php\nkept\n```\n\n"
        );
});

it('reads a tree URL with no path as the repository root', function (): void {
    $api = 'https://api.github.com/repos/starfleet/enterprise/contents/';

    Http::fake([
        $api.'?ref=main' => Http::response([
            ['path' => 'LICENSE', 'type' => 'file', 'url' => $api.'LICENSE?ref=main'],
        ]),
        $api.'LICENSE?ref=main' => Http::response(githubContentsFile('MIT')),
    ]);

    File::put($this->docsInput, 'https://github.com/starfleet/enterprise/tree/main');

    expect(Artisan::call('docs:generate', ['markdown' => $this->docsInput]))->toBe(0)
        ->and(file_get_contents($this->docsInput))->toBe(
            "`https://github.com/starfleet/enterprise/blob/main/LICENSE`:\n```\nMIT\n```\n\n"
        );
});
