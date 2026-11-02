<?php

declare(strict_types=1);

use App\Support\ContentMirror;
use App\Support\ContentRoot;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Statamic\Facades\YAML;
use Statamic\Yaml\ParseException;
use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#2762: a YAML parse failure in the test content mirror names the mirror as the cause.
 *
 * A torn mirror file surfaced as a Statamic `ParseException` in two form suites that never
 * read it, naming only the YAML file. {@see ContentMirror::parseFailureDiagnosis()} tells a
 * torn mirror from a genuine content defect by parsing the file's counterpart in `content/`,
 * and the two hooks (the shared `TestCase` and the app's exception map) put that explanation
 * where the failure is reported.
 *
 * The diagnosis cases run on throwaway trees. The two hook cases need a torn file in the
 * real mirror, so they write one under a directory no Stache store reads, named for this
 * process, and remove it afterwards.
 *
 * @command  php artisan test --compact tests/Feature/ContentMirrorTornDiagnosisTest.php
 */
$root = null;

beforeEach(function () use (&$root): void {
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'content-mirror-torn-'.uniqid();

    $files = new Filesystem;
    $files->ensureDirectoryExists($root.'/source/globals');
    $files->ensureDirectoryExists($root.'/mirror/globals');
});

afterEach(function () use (&$root): void {
    if (\is_string($root)) {
        (new Filesystem)->deleteDirectory($root);
    }
});

/**
 * The `ParseException` Statamic raises for a file, with that file as its path.
 *
 * @param  string  $file  A file that does not parse.
 */
function tornDiagnosisParseFailure(string $file): ParseException
{
    try {
        YAML::file($file)->parse();
    } catch (Throwable $exception) {
        return mustBe(ParseException::class, $exception, 'the parse failure for '.$file);
    }

    throw new RuntimeException(sprintf('%s parsed, so it cannot stand in for a torn file.', $file));
}

// Valid YAML, and the same document cut off partway through a key, as a torn copy is
const TORN_DIAGNOSIS_WHOLE = "title: Redirects\nrules:\n  - from: /a\n    to: /b\n";
const TORN_DIAGNOSIS_TORN = "title: Redirects\nrules:\n  - from: /a\n    to: [/b\n";

it('names the mirror and the rebuild command when the content counterpart parses', function () use (&$root): void {
    file_put_contents($root.'/source/globals/redirects.yaml', TORN_DIAGNOSIS_WHOLE);
    file_put_contents($root.'/mirror/globals/redirects.yaml', TORN_DIAGNOSIS_TORN);

    $diagnosis = ContentMirror::parseFailureDiagnosis(
        tornDiagnosisParseFailure($root.'/mirror/globals/redirects.yaml'),
        $root.'/source',
        $root.'/mirror',
    );

    expect($diagnosis)->toBeString()
        ->toContain('The test content mirror at '.realpath($root.'/mirror').' is torn')
        ->toContain('globals'.DIRECTORY_SEPARATOR.'redirects.yaml does not parse')
        ->toContain('the same file in '.realpath($root.'/source').' does')
        ->toContain('php artisan test:seed-stache');
});

it('calls a mirror-only file a test fixture rather than a content file', function () use (&$root): void {
    file_put_contents($root.'/mirror/globals/fixture.yaml', TORN_DIAGNOSIS_TORN);

    $diagnosis = ContentMirror::parseFailureDiagnosis(
        tornDiagnosisParseFailure($root.'/mirror/globals/fixture.yaml'),
        $root.'/source',
        $root.'/mirror',
    );

    expect($diagnosis)->toBeString()
        ->toContain('is torn')
        ->toContain('written into the mirror by a test');
});

it('reports a content defect, not a tear, when the content counterpart fails too', function () use (&$root): void {
    file_put_contents($root.'/source/globals/redirects.yaml', TORN_DIAGNOSIS_TORN);
    file_put_contents($root.'/mirror/globals/redirects.yaml', TORN_DIAGNOSIS_TORN);

    $diagnosis = ContentMirror::parseFailureDiagnosis(
        tornDiagnosisParseFailure($root.'/mirror/globals/redirects.yaml'),
        $root.'/source',
        $root.'/mirror',
    );

    expect($diagnosis)->toBeString()
        ->toContain('is invalid YAML in content/ itself')
        ->toContain('This is a content defect')
        // The torn diagnosis's own phrase; the paths here contain the word "torn" themselves
        ->not->toContain('is torn');
});

it('finds the parse failure through a wrapping exception', function () use (&$root): void {
    file_put_contents($root.'/mirror/globals/redirects.yaml', TORN_DIAGNOSIS_TORN);

    $wrapped = new RuntimeException('render failed', 0, tornDiagnosisParseFailure($root.'/mirror/globals/redirects.yaml'));

    expect(ContentMirror::parseFailureDiagnosis($wrapped, $root.'/source', $root.'/mirror'))
        ->toBeString()->toContain('is torn');
});

it('stays silent for anything that is not a parse failure in the mirror', function () use (&$root): void {
    file_put_contents($root.'/source/globals/elsewhere.yaml', TORN_DIAGNOSIS_TORN);

    expect(ContentMirror::parseFailureDiagnosis(new RuntimeException('unrelated'), $root.'/source', $root.'/mirror'))->toBeNull()
        // A parse failure outside the mirror is not the mirror's
        ->and(ContentMirror::parseFailureDiagnosis(tornDiagnosisParseFailure($root.'/source/globals/elsewhere.yaml'), $root.'/source', $root.'/mirror'))->toBeNull()
        // Outside the test env the mirror is content/ itself, so there is no mirror to blame
        ->and(ContentMirror::parseFailureDiagnosis(tornDiagnosisParseFailure($root.'/source/globals/elsewhere.yaml'), $root.'/source', $root.'/source'))->toBeNull();
});

/**
 * Write a torn file into the real mirror, under a directory no Stache store reads.
 *
 * @return array{directory: string, file: string}
 */
function tornDiagnosisProbe(): array
{
    $mirror = ContentRoot::path(Config::string('app.env'));
    $directory = $mirror.DIRECTORY_SEPARATOR.'zz-torn-diagnosis-'.getmypid();

    (new Filesystem)->ensureDirectoryExists($directory);
    file_put_contents($directory.'/probe.yaml', TORN_DIAGNOSIS_TORN);

    return ['directory' => $directory, 'file' => $directory.'/probe.yaml'];
}

it('re-throws a failing test with the diagnosis, keeping the original as previous', function (): void {
    $probe = tornDiagnosisProbe();

    try {
        $original = tornDiagnosisParseFailure($probe['file']);

        $rethrown = mustBe(
            RuntimeException::class,
            throwableFrom(fn () => (fn () => $this->onNotSuccessfulTest($original))->call($this)),
            'the re-thrown failure'
        );

        expect($rethrown->getMessage())->toContain('is torn')
            ->and($rethrown->getPrevious())->toBe($original);
    } finally {
        (new Filesystem)->deleteDirectory($probe['directory']);
    }
});

it('maps a request failure on a torn mirror file to the diagnosis', function (): void {
    $probe = tornDiagnosisProbe();

    try {
        $original = tornDiagnosisParseFailure($probe['file']);

        $handler = app(ExceptionHandler::class);
        $mapped = mustBe(RuntimeException::class, (new ReflectionMethod($handler, 'mapException'))->invoke($handler, $original), 'the mapped exception');

        expect($mapped->getMessage())->toContain('is torn')
            ->and($mapped->getPrevious())->toBe($original);
    } finally {
        (new Filesystem)->deleteDirectory($probe['directory']);
    }
});
