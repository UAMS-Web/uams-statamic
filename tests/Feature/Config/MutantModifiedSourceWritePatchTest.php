<?php

declare(strict_types=1);

use Pest\Mutate\Mutation;
use Symfony\Component\Finder\SplFileInfo as FinderFile;

/**
 * Guards the `pest-plugin-mutate` mutated-source write patch (UAMS-Web/uams-statamic-archive#2812), which stops a mutation run
 * from scoring mutants it never applied. The patch moves the write into
 * `Mutation::writeModifiedSource()`, which takes the directory as an argument, so these cases
 * call it against a temporary directory and never touch the plugin's own `.temp/mutations`.
 *
 * The defect: `Mutation::create()` wrote each mutated source with `file_put_contents()` into
 * `.temp/mutations`, ignored the result, and nothing created that directory. With it removed, no
 * mutant was applied, yet the run printed a score and exited 0 (defect 3 of pestphp/pest#1942).
 * The plugin's mutation cache hid the same failure on a warm run: a cached mutation points at a
 * file in `.temp/mutations`, so `MutationGenerator` now regenerates a cached set whose files are
 * gone, through `Mutation::modifiedSourcesExist()`.
 *
 * Unpatched, `writeModifiedSource()` does not exist, so every behavioral case here fails.
 *
 * Retire this with the patch, per UAMS-Web/uams-statamic-archive#2812.
 *
 * @command  php artisan test --compact tests/Feature/Config/MutantModifiedSourceWritePatchTest.php
 */
$mutationSource = dirname(__DIR__, 3).'/vendor/pestphp/pest-plugin-mutate/src/Mutation.php';
$generatorSource = dirname(__DIR__, 3).'/vendor/pestphp/pest-plugin-mutate/src/Support/MutationGenerator.php';

/**
 * Build a mutation whose mutated source is at the given path.
 *
 * @param  string  $modifiedSourcePath  Where the mutated source is expected.
 */
function mutantWritePatchMutation(string $modifiedSourcePath): Mutation
{
    return new Mutation(
        file: new FinderFile(__FILE__, '', basename(__FILE__)),
        id: 'id',
        mutator: 'Mutator',
        startLine: 1,
        endLine: 1,
        diff: '',
        modifiedSourcePath: $modifiedSourcePath,
    );
}

/**
 * Make an empty temporary directory and return its path.
 *
 * @return string Absolute path of the new directory.
 */
function mutantWritePatchTempDirectory(): string
{
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mutant-write-'.bin2hex(random_bytes(6));

    if (! mkdir($directory) && ! is_dir($directory)) {
        throw new RuntimeException("Unable to create the temporary directory [{$directory}].");
    }

    return $directory;
}

/**
 * Remove a directory tree made by a case, whatever it contains.
 *
 * @param  string  $directory  Absolute path of the tree to remove.
 */
function mutantWritePatchRemove(string $directory): void
{
    if (! file_exists($directory)) {
        return;
    }

    if (is_file($directory)) {
        unlink($directory);

        return;
    }

    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    ) as $entry) {
        /** @var SplFileInfo $entry */
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }

    rmdir($directory);
}

it('routes create() through the checked write instead of a bare file_put_contents', function () use ($mutationSource): void {
    $source = file_get_contents($mutationSource);

    if (! is_string($source)) {
        throw new RuntimeException("Unable to read the vendored source at [{$mutationSource}].");
    }

    if (! str_contains($source, 'self::writeModifiedSource(self::TMP_FOLDER')) {
        throw new RuntimeException(
            'The vendored pest-plugin-mutate is NOT patched: create() does not call writeModifiedSource(). '
            .'Run: composer patches-relock, then remove vendor/pestphp/pest-plugin-mutate and composer install.'
        );
    }

    expect(substr_count($source, 'self::writeModifiedSource(self::TMP_FOLDER, $modifiedSource)'))->toBe(1)
        ->and($source)->not->toContain('file_put_contents($modifiedSourcePath');
});

it('creates a missing mutations directory and writes the source into it', function (): void {
    $root = mutantWritePatchTempDirectory();
    $directory = $root.DIRECTORY_SEPARATOR.'.temp'.DIRECTORY_SEPARATOR.'mutations';

    try {
        expect(is_dir($directory))->toBeFalse();

        $path = Mutation::writeModifiedSource($directory, '<?php echo 1;');

        expect($path)->toBe($directory.DIRECTORY_SEPARATOR.hash('xxh3', '<?php echo 1;'))
            ->and(file_get_contents($path))->toBe('<?php echo 1;');
    } finally {
        mutantWritePatchRemove($root);
    }
});

it('throws, naming the directory, when the mutations directory cannot be created', function (): void {
    $root = mutantWritePatchTempDirectory();
    // A regular file where a parent directory belongs makes mkdir() fail on every platform.
    $blocker = $root.DIRECTORY_SEPARATOR.'blocker';
    file_put_contents($blocker, '');
    $directory = $blocker.DIRECTORY_SEPARATOR.'mutations';

    try {
        expect(fn () => Mutation::writeModifiedSource($directory, '<?php echo 1;'))
            ->toThrow(RuntimeException::class, "Unable to create the mutations directory [{$directory}]");
    } finally {
        mutantWritePatchRemove($root);
    }
});

it('throws, naming the path, when the mutated source cannot be written', function (): void {
    $root = mutantWritePatchTempDirectory();
    // A directory already standing at the target path makes file_put_contents() fail on every platform.
    $path = $root.DIRECTORY_SEPARATOR.hash('xxh3', '<?php echo 1;');
    mkdir($path);

    try {
        expect(fn () => Mutation::writeModifiedSource($root, '<?php echo 1;'))
            ->toThrow(RuntimeException::class, "Unable to write the mutated source to [{$path}]");
    } finally {
        mutantWritePatchRemove($root);
    }
});

it('regenerates a cached mutation set whose mutated sources are gone', function () use ($generatorSource): void {
    $source = file_get_contents($generatorSource);

    if (! is_string($source) || ! str_contains($source, 'Mutation::modifiedSourcesExist($cache->get(')) {
        throw new RuntimeException(
            'The vendored pest-plugin-mutate is NOT patched: MutationGenerator uses a cached set without checking its files. '
            .'Run: composer patches-relock, then remove vendor/pestphp/pest-plugin-mutate and composer install.'
        );
    }

    expect(substr_count($source, 'Mutation::modifiedSourcesExist($cache->get($file, $contents, $linesToMutate, $mutator))'))->toBe(1);
});

it('reports whether every mutated source is still on disk', function (): void {
    $root = mutantWritePatchTempDirectory();

    try {
        $present = Mutation::writeModifiedSource($root, '<?php echo 1;');
        $missing = $root.DIRECTORY_SEPARATOR.'gone';

        expect(Mutation::modifiedSourcesExist([]))->toBeTrue()
            ->and(Mutation::modifiedSourcesExist([mutantWritePatchMutation($present)]))->toBeTrue()
            ->and(Mutation::modifiedSourcesExist([mutantWritePatchMutation($present), mutantWritePatchMutation($missing)]))->toBeFalse()
            ->and(Mutation::modifiedSourcesExist([mutantWritePatchMutation($missing), mutantWritePatchMutation($present)]))->toBeFalse();
    } finally {
        mutantWritePatchRemove($root);
    }
});

it('restores the previous error handler after a write and after a failure', function (): void {
    $root = mutantWritePatchTempDirectory();
    $sentinel = static fn (): bool => false;
    $handlers = [];

    try {
        foreach (['written', 'failed'] as $arm) {
            set_error_handler($sentinel);

            try {
                if ($arm === 'written') {
                    Mutation::writeModifiedSource($root, '<?php echo 1;');
                } else {
                    // A directory standing at the target path makes the write fail.
                    mkdir($root.DIRECTORY_SEPARATOR.hash('xxh3', '<?php echo 2;'));
                    Mutation::writeModifiedSource($root, '<?php echo 2;');
                }
            } catch (RuntimeException) {
                // The failing arm throws by design; only the handler stack is under test here.
            } finally {
                // set_error_handler() returns the handler on top, which must be the sentinel again.
                $handlers[$arm] = set_error_handler(null);
                restore_error_handler();
                restore_error_handler();
            }
        }

        expect($handlers['written'])->toBe($sentinel)
            ->and($handlers['failed'])->toBe($sentinel);
    } finally {
        mutantWritePatchRemove($root);
    }
});
