<?php

declare(strict_types=1);

use App\Enums\Isco08;
use App\Services\EnumGeneration\Isco08\Isco08EnumGenerator;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Runs {@see Isco08EnumGenerator::generate()} against the ILO workbooks committed
 * beside it, the inputs `php artisan generate:isco-08-enum --local` reads.
 *
 * The generator keys its records by ISCO code, and PHP stores a decimal-integer
 * string key such as `'1111'` as the int `1111`, so every code read back through
 * `array_keys()` used to reach the strictly typed string helpers as an int and
 * throw a `TypeError` before anything was written (#713). Its output also needed a
 * separate Pint pass before it matched the committed enum (#715).
 *
 * The application path is pointed at a scratch directory for the run, so the
 * generator writes its enum there and never over the committed `app/Enums/Isco08.php`.
 *
 * @command  php artisan test --compact tests/Unit/Services/EnumGeneration/Isco08EnumGeneratorTest.php
 */
it('regenerates the committed ISCO-08 enum from the committed workbooks, Pint-clean', function (): void {
    $fixtures = Isco08EnumGenerator::defaultLocalFixturePaths();
    expect(Isco08EnumGenerator::localFixturesAreReadable())->toBeTrue();

    $committed = (string) file_get_contents(app_path('Enums/Isco08.php'));
    $originalAppPath = app_path();
    $scratch = sys_get_temp_dir().DIRECTORY_SEPARATOR.'isco08-enum-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($scratch.DIRECTORY_SEPARATOR.'Enums');

    try {
        app()->useAppPath($scratch);

        $path = (new Isco08EnumGenerator)->generate($fixtures['definitions'], $fixtures['index']);

        expect($path)->toBe(app_path('Enums/Isco08.php'));
        $php = (string) file_get_contents($path);

        $pint = new Process(
            [PHP_BINARY, base_path('vendor/bin/pint'), '--test', '--config', base_path('pint.json'), $path],
            base_path(),
        );
        $pint->run();
    } finally {
        app()->useAppPath($originalAppPath);
        File::deleteDirectory($scratch);
    }

    // Pint has nothing to change in the file exactly as the generator wrote it.
    expect($pint->getExitCode())->toBe(0, $pint->getOutput().$pint->getErrorOutput());

    // Byte for byte the committed enum, apart from the date it was generated on.
    $undated = static fn (string $source): string => (string) preg_replace('/^ \* Generated: \d{4}-\d{2}-\d{2}$/m', ' * Generated: <date>', $source);
    expect($undated($php))->toBe($undated($committed));

    preg_match_all("/^    case (ISCO08_\\d{1,4}) = '(\\d{1,4})';$/m", $php, $matches);

    $expected = array_map(
        static fn (Isco08 $case): string => $case->name.'='.$case->value,
        Isco08::cases(),
    );
    $generated = array_map(
        static fn (string $name, string $value): string => $name.'='.$value,
        $matches[1],
        $matches[2],
    );

    // Same cases, in the same order, as the committed enum.
    expect($generated)->toBe($expected)
        ->and(count($generated))->toBeGreaterThan(600);
});

it('writes a PHP string literal that evaluates to the value it was given', function (string $value): void {
    $literal = mustBeString((new ReflectionMethod(Isco08EnumGenerator::class, 'phpString'))->invoke(new Isco08EnumGenerator, $value), 'phpString literal');

    expect(eval('return '.$literal.';'))->toBe($value);
})->with([
    'plain' => ['Armed Forces Occupations'],
    'forward slash' => ['and/or'],
    'single quote' => ["Prince George's"],
    'double quote' => ['a "quoted" word'],
    'dollar sign' => ['costs $5 or $total'],
    'backslash' => ['C:\\path\\to'],
    'trailing backslash' => ['ends with \\'],
    'newline and tab' => ["line one\n\tline two\r\n"],
    'quote and newline' => ["it's\nhere"],
    'control character' => ["bell\x07 and \x1B escape"],
    'non-ASCII' => ['Café – 1–4 digit'],
    'empty' => [''],
]);

it('single-quotes a value Pint would single-quote, and double-quotes one it would not', function (string $value, string $expected): void {
    $literal = mustBeString((new ReflectionMethod(Isco08EnumGenerator::class, 'phpString'))->invoke(new Isco08EnumGenerator, $value), 'phpString literal');

    expect($literal)->toBe($expected);
})->with([
    'plain' => ['and/or', "'and/or'"],
    'double quote stays single-quoted' => ['say "hi"', "'say \"hi\"'"],
    'single quote needs double quotes' => ["it's", '"it\'s"'],
    'newline needs double quotes' => ["a\nb", '"a\\nb"'],
]);
