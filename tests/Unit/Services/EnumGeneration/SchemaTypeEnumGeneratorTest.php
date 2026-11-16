<?php

declare(strict_types=1);

use App\Services\EnumGeneration\Schema\SchemaTypeEnumGenerator;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Formatting of the four files SchemaTypeEnumGenerator
 * (app/Services/EnumGeneration/Schema/SchemaTypeEnumGenerator.php) writes, rendered
 * from the schema.org JSON-LD committed beside it so no network is involved
 * (UAMS-Web/uams-statamic#722).
 *
 * @command  php artisan test --compact tests/Unit/Services/EnumGeneration/SchemaTypeEnumGeneratorTest.php
 */

/**
 * Run the generator over the committed JSON-LD and return each file it writes, keyed by enum name.
 *
 * @return array<string, string>
 */
function schemaEnumGeneratedFiles(): array
{
    $root = dirname(__DIR__, 4);
    $jsonLd = mustBeString(file_get_contents($root.'/app/Services/EnumGeneration/Schema/schemaorg-current-https.jsonld'), 'the committed schema.org JSON-LD');

    /** @var array<string, string> $written */
    $written = [];
    File::partialMock()->shouldReceive('put')->times(4)->andReturnUsing(static function (string $path, string $php) use (&$written): int {
        $written[pathinfo($path, PATHINFO_FILENAME)] = $php;

        return \strlen($php);
    });

    (new SchemaTypeEnumGenerator(static fn (string $url): string => $jsonLd, static fn (array $names): null => null))->generate();
    ksort($written);

    return $written;
}

it('renders all four files so Pint reports them clean with no separate pass (#722)', function (): void {
    $root = dirname(__DIR__, 4);
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'schema-enums-'.bin2hex(random_bytes(6));
    mkdir($directory);
    $files = schemaEnumGeneratedFiles();

    foreach ($files as $name => $php) {
        file_put_contents($directory.DIRECTORY_SEPARATOR.$name.'.php', $php);
    }

    try {
        $process = new Process([
            PHP_BINARY,
            $root.'/vendor/bin/pint',
            '--test',
            '--config',
            $root.'/pint.json',
            $directory,
        ]);
        $process->setTimeout(300);
        $process->run();
    } finally {
        array_map(unlink(...), glob($directory.DIRECTORY_SEPARATOR.'*.php') ?: []);
        rmdir($directory);
    }

    expect(array_keys($files))->toBe(['SchemaDataType', 'SchemaEnumerationMember', 'SchemaProperty', 'SchemaType'])
        ->and($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput());
});

it('regenerates each committed enum byte for byte apart from its generation date (#722)', function (): void {
    $root = dirname(__DIR__, 4);
    $files = schemaEnumGeneratedFiles();

    // The docblock records the day the file was generated; everything else must match
    $undated = static fn (string $php): string => (string) preg_replace('/on \d{4}-\d{2}-\d{2} using/', 'on <date> using', $php, 1);

    foreach ($files as $name => $php) {
        $committed = mustBeString(file_get_contents($root.'/app/Enums/'.$name.'.php'), 'the committed '.$name.' enum');

        expect($undated($php))->toBe($undated($committed));
    }

    expect($files)->toHaveCount(4);
});
