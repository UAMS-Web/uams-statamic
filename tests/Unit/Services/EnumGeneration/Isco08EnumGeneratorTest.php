<?php

declare(strict_types=1);

use App\Enums\Isco08;
use App\Services\EnumGeneration\Isco08\Isco08EnumGenerator;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Runs {@see Isco08EnumGenerator::generate()} against the ILO workbooks committed
 * beside it, the inputs `php artisan generate:isco-08-enum --local` reads (#713).
 *
 * The generator keys its records by ISCO code, and PHP stores a decimal-integer
 * string key such as `'1111'` as the int `1111`, so every code read back through
 * `array_keys()` used to reach the strictly typed string helpers as an int and
 * throw a `TypeError` before anything was written.
 *
 * The application path is pointed at a scratch directory for the run, so the
 * generator writes its enum there and never over the committed `app/Enums/Isco08.php`.
 *
 * @command  php artisan test --compact tests/Unit/Services/EnumGeneration/Isco08EnumGeneratorTest.php
 */
it('generates the committed ISCO-08 cases from the committed workbooks', function (): void {
    $fixtures = Isco08EnumGenerator::defaultLocalFixturePaths();
    expect(Isco08EnumGenerator::localFixturesAreReadable())->toBeTrue();

    $originalAppPath = app_path();
    $scratch = sys_get_temp_dir().DIRECTORY_SEPARATOR.'isco08-enum-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($scratch.DIRECTORY_SEPARATOR.'Enums');

    try {
        app()->useAppPath($scratch);

        $path = (new Isco08EnumGenerator)->generate($fixtures['definitions'], $fixtures['index']);

        expect($path)->toBe(app_path('Enums/Isco08.php'));
        $php = (string) file_get_contents($path);
    } finally {
        app()->useAppPath($originalAppPath);
        File::deleteDirectory($scratch);
    }

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
