<?php

declare(strict_types=1);

use App\Console\Commands\GenerateCredentialRegistryEnum;
use App\Console\Commands\GenerateMimeTypeEnum;
use App\Console\Commands\GenerateSchemaTypeEnum;
use App\Services\EnumGeneration\CredentialRegistry\CredentialRegistryEnumGenerator;
use App\Services\EnumGeneration\MimeType\MimeTypeEnumGenerator;
use App\Services\EnumGeneration\Schema\SchemaTypeEnumGenerator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The `generate:credential-registry-enum`, `generate:schema-type-enum` and
 * `generate:mime-type-enum` commands: each announces its run, runs its generator,
 * and reports the files written; the MIME type command also turns a generator
 * failure into an error and exit code 1.
 *
 * The real generators run, built with their network reads replaced by closures and
 * bound into the container the commands resolve them from, so nothing reaches the
 * network. `File::put()` is captured, so nothing is written to `app/Enums`.
 *
 * @command  php artisan test --compact tests/Feature/Console/GenerateVocabularyEnumCommandsTest.php
 */
uses(TestCase::class);

covers(GenerateCredentialRegistryEnum::class, GenerateSchemaTypeEnum::class, GenerateMimeTypeEnum::class);

beforeEach(function (): void {
    // The MIME type generator caches its downloads; keep that in memory
    config(['cache.default' => 'array']);
});

/**
 * Capture every `File::put()` a generator makes, keyed by path, instead of writing to disk.
 *
 * @return ArrayObject<string, string>
 */
function vocabularyEnumCommandCapturedWrites(int $times): ArrayObject
{
    /** @var ArrayObject<string, string> $written */
    $written = new ArrayObject;
    File::partialMock()->shouldReceive('put')->times($times)->andReturnUsing(static function (string $path, string $php) use ($written): int {
        $written[str_replace('\\', '/', $path)] = $php;

        return \strlen($php);
    });

    return $written;
}

/**
 * The one written body whose path ends with the given suffix.
 *
 * @param  ArrayObject<string, string>  $written
 */
function vocabularyEnumCommandWritten(ArrayObject $written, string $suffix): string
{
    foreach ($written as $path => $php) {
        if (str_ends_with($path, $suffix)) {
            return $php;
        }
    }

    throw new RuntimeException('Nothing was written to a path ending '.$suffix);
}

it('writes the Credential Registry enum from the registry fixtures and reports its path', function (): void {
    $urls = [];
    app()->instance(CredentialRegistryEnumGenerator::class, new CredentialRegistryEnumGenerator(static function (string $url) use (&$urls): ?string {
        $urls[] = $url;

        return match (true) {
            str_ends_with($url, '/subclasses_map.json') => '{"ceterms:Credential": {"ceterms:Degree": {}}}',
            str_ends_with($url, '/description_set_item_maps.yml') => '',
            // CTDL term metadata is optional; a miss leaves the case without it
            default => null,
        };
    }));
    $written = vocabularyEnumCommandCapturedWrites(1);

    pendingArtisan('generate:credential-registry-enum')
        ->expectsOutput('Generating CredentialRegistry enum from Credential Registry fixtures...')
        ->expectsOutput('Wrote app/Enums/CredentialRegistry.php')
        ->assertExitCode(0);

    expect(vocabularyEnumCommandWritten($written, '/app/Enums/CredentialRegistry.php'))->toContain('ceterms:Degree')
        ->and($urls)->toContain('https://raw.githubusercontent.com/CredentialEngine/CredentialRegistry/refs/heads/master/fixtures/subclasses_map.json');
});

it('lets a failed Credential Registry fetch propagate without writing', function (): void {
    app()->instance(CredentialRegistryEnumGenerator::class, new CredentialRegistryEnumGenerator(static fn (string $url): ?string => null));
    File::partialMock()->shouldReceive('put')->never();

    expect(fn (): int => Artisan::call('generate:credential-registry-enum'))
        ->toThrow(RuntimeException::class, 'Failed to fetch: https://raw.githubusercontent.com/CredentialEngine/CredentialRegistry/refs/heads/master/fixtures/subclasses_map.json');
});

/**
 * Bind a schema.org generator reading a one-class graph, warming the cache with the given closure.
 *
 * @param  Closure(list<string>): mixed  $warm
 */
function vocabularyEnumCommandSchemaGenerator(Closure $warm): void
{
    $graph = json_encode(['@graph' => [
        ['@id' => 'schema:Thing', '@type' => 'rdfs:Class', 'rdfs:label' => 'Thing', 'rdfs:comment' => 'The most generic type of item.'],
    ]], JSON_THROW_ON_ERROR);

    app()->instance(SchemaTypeEnumGenerator::class, new SchemaTypeEnumGenerator(static fn (string $url): string => $graph, $warm));
}

it('writes the four schema.org enums, reports each path, and says when the alias cache was warmed', function (): void {
    $warmed = [];
    vocabularyEnumCommandSchemaGenerator(static function (array $typeNames) use (&$warmed): void {
        $warmed[] = $typeNames;
    });
    $written = vocabularyEnumCommandCapturedWrites(4);

    pendingArtisan('generate:schema-type-enum')
        ->expectsOutput('Generating SchemaType, SchemaProperty, SchemaDataType, and SchemaEnumerationMember enums from schema.org JSON-LD...')
        ->expectsOutput('Wrote app/Enums/SchemaType.php')
        ->expectsOutput('Wrote app/Enums/SchemaProperty.php')
        ->expectsOutput('Wrote app/Enums/SchemaDataType.php')
        ->expectsOutput('Wrote app/Enums/SchemaEnumerationMember.php')
        ->expectsOutput('Warmed EntriesBySchemaType alias cache.')
        ->assertExitCode(0);

    expect($warmed)->toBe([['Thing']])
        ->and(vocabularyEnumCommandWritten($written, '/app/Enums/SchemaType.php'))->toContain('Thing');
});

it('says nothing about the alias cache when warming it fails', function (): void {
    vocabularyEnumCommandSchemaGenerator(static function (array $typeNames): never {
        throw new RuntimeException('cache store unavailable');
    });
    vocabularyEnumCommandCapturedWrites(4);

    pendingArtisan('generate:schema-type-enum')
        ->expectsOutput('Wrote app/Enums/SchemaEnumerationMember.php')
        ->doesntExpectOutputToContain('Warmed')
        ->assertExitCode(0);
});

/**
 * Bind a MIME type generator whose downloads answer only the Apache list, with its
 * header comment and one type.
 */
function vocabularyEnumCommandMimeGenerator(): void
{
    app()->instance(MimeTypeEnumGenerator::class, new MimeTypeEnumGenerator(
        static fn (string $url, array $headers, int $timeout): ?string => str_contains($url, 'mime.types')
            ? "# MIME type (lowercased)\t\t\tExtensions\napplication/x-uams-probe    probe\n"
            : null
    ));
}

it('writes the MIME type enum and reports its path', function (): void {
    vocabularyEnumCommandMimeGenerator();
    $written = vocabularyEnumCommandCapturedWrites(1);

    // Artisan::call() keeps the output for reading after the run, when the path is known
    expect(Artisan::call('generate:mime-type-enum'))->toBe(0);
    $output = Artisan::output();
    $path = (string) array_key_first($written->getArrayCopy());

    expect($written[$path])->toContain('application/x-uams-probe')
        ->and($path)->toMatch('#/app/Enums/MimeType_generated_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.php$#')
        ->and($output)->toStartWith('Reading MIME types from mime-db, IANA media types, and Apache MIME types...'.PHP_EOL)
        // The capture normalizes the path, and Windows reports it with mixed separators
        ->and(str_replace('\\', '/', $output))->toContain('Wrote '.$path.PHP_EOL);
});

it('turns a MIME type generator failure into an error and exit code 1', function (): void {
    vocabularyEnumCommandMimeGenerator();
    File::partialMock()->shouldReceive('put')->once()->andThrow(new RuntimeException('disk is read-only'));

    pendingArtisan('generate:mime-type-enum')
        ->expectsOutput('disk is read-only')
        ->doesntExpectOutputToContain('Wrote ')
        ->assertExitCode(1);
});
