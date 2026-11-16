<?php

declare(strict_types=1);

use App\Console\Commands\GenerateInternationalCodeDesignatorEnum;
use App\Enums\IcdInfo;
use App\Enums\InternationalCodeDesignator;
use Symfony\Component\Process\Process;

/**
 * The source `generate:international-code-designator-enum` writes to
 * `app/Enums/InternationalCodeDesignator.php` declares the enum and nothing else
 * (UAMS-Web/uams-statamic#554).
 *
 * `IcdInfo` lives in a file of its own, so an enum file that still carried it would
 * declare the class twice and fail to load. The command fetches its list over the
 * network and writes into `app/Enums`, so the builder is called directly on one
 * record rather than through `handle()`.
 *
 * @command  php artisan test --compact tests/Unit/Console/GenerateInternationalCodeDesignatorEnumTest.php
 */

/**
 * The class-like names a PHP source declares, in order.
 *
 * @return list<string>
 */
function icdGeneratedDeclarations(string $source): array
{
    $tokens = token_get_all($source, TOKEN_PARSE);
    $names = [];

    // A declaration keyword followed by its name; `::class` and `new class` are not declarations
    foreach ($tokens as $index => $token) {
        if (! is_array($token) || ! in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
            continue;
        }

        $previous = $tokens[$index - 1] ?? null;
        $beforePrevious = $tokens[$index - 2] ?? null;

        if (is_array($previous) && $previous[0] === T_DOUBLE_COLON) {
            continue;
        }

        if (is_array($previous) && $previous[0] === T_WHITESPACE && is_array($beforePrevious) && $beforePrevious[0] === T_NEW) {
            continue;
        }

        $name = $tokens[$index + 2] ?? null;

        if (is_array($name) && $name[0] === T_STRING) {
            $names[] = $name[1];
        }
    }

    return $names;
}

it('writes an enum file that declares only the enum and leaves IcdInfo to its own file', function (): void {
    $build = new ReflectionMethod(GenerateInternationalCodeDesignatorEnum::class, 'buildEnumPhpContent');
    $source = $build->invoke(new GenerateInternationalCodeDesignatorEnum, [
        ['id' => '0002', 'name' => 'SIRENE', 'description' => 'French trade register.'],
    ], '2026-11-03');

    expect($source)->toBeString()
        ->and(icdGeneratedDeclarations(mustBeString($source, 'the generated enum source')))->toBe(['InternationalCodeDesignator'])
        ->and(mustBeString($source, 'the generated enum source'))->toContain("#[IcdInfo(id: '0002', name: 'SIRENE', description: 'French trade register.')]")
        ->and(class_exists(IcdInfo::class))->toBeTrue();
});

/**
 * Build the enum source for the given records through the private builder.
 *
 * @param  array<int, array{id: string, name: string, description: string}>  $records
 */
function icdGeneratedSource(array $records, string $generatedAt = '2026-11-03'): string
{
    $build = new ReflectionMethod(GenerateInternationalCodeDesignatorEnum::class, 'buildEnumPhpContent');

    return mustBeString($build->invoke(new GenerateInternationalCodeDesignatorEnum, $records, $generatedAt), 'the generated enum source');
}

it('regenerates the committed enum byte for byte from its own cases (#720)', function (): void {
    $committed = (string) file_get_contents(dirname(__DIR__, 3).'/app/Enums/InternationalCodeDesignator.php');
    expect(preg_match('/^ \* Generated: (\d{4}-\d{2}-\d{2})$/m', $committed, $match))->toBe(1);

    $records = array_map(
        static fn (InternationalCodeDesignator $case): array => ['id' => $case->id(), 'name' => $case->name(), 'description' => $case->description()],
        InternationalCodeDesignator::cases(),
    );

    // The committed file is Pint-clean, so a byte-equal regeneration is too; with
    // PHP_EOL in the template this differed on Windows by one CRLF
    expect(count($records))->toBeGreaterThan(200)
        ->and(icdGeneratedSource($records, mustBeString($match[1] ?? null, 'the committed Generated date')))->toBe($committed);
});

it('renders output that Pint reports clean with no separate pass (#720)', function (): void {
    $source = icdGeneratedSource([
        ['id' => '0002', 'name' => 'SIRENE', 'description' => 'French trade register.'],
        ['id' => '0008', 'name' => "Partners' and Suppliers' Code", 'description' => 'Ends in a backslash\\'],
    ]);

    $root = dirname(__DIR__, 3);
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'icd-enum-'.bin2hex(random_bytes(6)).'.php';
    file_put_contents($path, $source);

    try {
        $process = new Process([
            PHP_BINARY,
            $root.'/vendor/bin/pint',
            '--test',
            '--config',
            $root.'/pint.json',
            $path,
        ]);
        $process->run();
    } finally {
        @unlink($path);
    }

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput())
        ->and(substr_count($source, "\r"))->toBe(0);
});

it('writes every case literal so it evaluates back to its value (#720)', function (string $value): void {
    $source = icdGeneratedSource([['id' => $value, 'name' => $value, 'description' => $value]]);

    // id, name and description in the attribute, then the case's backing value
    expect(preg_match('/^    #\\[IcdInfo\\(id: (.*), name: (.*), description: (.*)\\)\\]\\n    case \\S+ = (.*);$/m', $source, $match))->toBe(1);

    $values = array_map(static function (string $literal): mixed {
        // A literal that does not parse is a failure of this case, not of the run
        try {
            return eval('return '.$literal.';');
        } catch (ParseError $error) {
            return 'ParseError: '.$error->getMessage();
        }
    }, array_slice($match, 1));

    expect($values)->toBe(array_fill(0, 4, $value));
})->with([
    'plain' => ['0002'],
    // No spaces: the value is also the case name's suffix, which the pattern reads as \S+
    'apostrophe' => ["Suppliers'"],
    'trailing backslash' => ['end\\'],
    'doubled backslash' => ['a\\\\b'],
    'backslash before apostrophe' => ['x\\\'y'],
    'lone backslash' => ['\\'],
]);
