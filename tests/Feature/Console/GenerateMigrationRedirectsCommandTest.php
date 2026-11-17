<?php

declare(strict_types=1);

use App\Services\Redirects\MigrationRedirectGenerator;
use App\Services\Redirects\MigrationRedirectReport;
use App\Support\Redirects\MigrationRedirect;
use Illuminate\Support\Facades\Artisan;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Coverage for the `content:generate-migration-redirects` command's own surface (UAMS-Web/uams-statamic-archive#1641):
 * the summary it prints, the dry-run wording, the retired-permalink list it reads and the
 * order it reports refused duplicate pairs in. The generator is faked, so what the command
 * hands it and what it prints from its report are asserted exactly; the generator itself
 * and the duplicate-pair file are covered by MigrationRedirectGeneratorTest and
 * MigrationRedirectDedupTest.
 *
 * @command  php artisan test --compact tests/Feature/Console/GenerateMigrationRedirectsCommandTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

beforeEach(function (): void {
    $this->redirectsDir = sys_get_temp_dir().'/generate-migration-redirects-'.bin2hex(random_bytes(6));
    mkdir($this->redirectsDir);
});

afterEach(function (): void {
    foreach (glob($this->redirectsDir.'/*') ?: [] as $file) {
        unlink($file);
    }

    rmdir($this->redirectsDir);
});

/**
 * The given number of distinct moves, so each summary count is told apart from the others.
 *
 * @return list<MigrationRedirect>
 */
function migrationRedirectMoves(string $prefix, int $count): array
{
    $moves = [];

    for ($i = 1; $i <= $count; $i++) {
        $moves[] = MigrationRedirect::moved("/{$prefix}-{$i}", "/new/{$prefix}-{$i}");
    }

    return $moves;
}

/**
 * Fake the generator: expect exactly these arguments, once, and return the report.
 *
 * @param  list<string>  $retired
 * @param  list<array<string, mixed>>  $pairs
 */
function fakeMigrationRedirectGenerator(bool $dryRun, array $retired, array $pairs, ?string $site, MigrationRedirectReport $report): void
{
    /** @var MigrationRedirectGenerator&MockInterface $generator */
    $generator = Mockery::mock(MigrationRedirectGenerator::class);
    $generator->shouldReceive('generate')->once()->with($dryRun, $retired, $pairs, $site)->andReturn($report);

    app()->instance(MigrationRedirectGenerator::class, $generator);
}

/**
 * A report with the given number of rules in every bucket.
 *
 * @param  list<array{source: string, target: string, reason: string}>  $refused
 */
function migrationRedirectReport(int $each, bool $written, array $refused = []): MigrationRedirectReport
{
    return new MigrationRedirectReport(
        entryMoves: migrationRedirectMoves('entry', $each),
        assetMoves: migrationRedirectMoves('asset', $each),
        structural: migrationRedirectMoves('structural', $each),
        ancestorMoves: migrationRedirectMoves('ancestor', $each),
        gone: migrationRedirectMoves('gone', $each),
        skippedExisting: $each,
        written: $written,
        dedupMoves: migrationRedirectMoves('dedup', $each),
        dedupRefused: $refused,
    );
}

/**
 * The command's output, one trimmed line per element, blank lines dropped.
 *
 * @return list<string>
 */
function migrationRedirectOutputLines(): array
{
    return array_values(array_filter(
        array_map(trim(...), explode("\n", Artisan::output())),
        static fn (string $line): bool => $line !== '',
    ));
}

/**
 * Bail out of a test whose generator must never be reached.
 */
function forbidMigrationRedirectGenerator(): void
{
    /** @var MigrationRedirectGenerator&MockInterface $generator */
    $generator = Mockery::mock(MigrationRedirectGenerator::class);
    $generator->shouldReceive('generate')->never();

    app()->instance(MigrationRedirectGenerator::class, $generator);
}

it("prints every bucket's own count and the total written", function (): void {
    fakeMigrationRedirectGenerator(false, [], [], null, new MigrationRedirectReport(
        entryMoves: migrationRedirectMoves('entry', 1),
        assetMoves: migrationRedirectMoves('asset', 3),
        structural: migrationRedirectMoves('structural', 4),
        ancestorMoves: migrationRedirectMoves('ancestor', 5),
        gone: migrationRedirectMoves('gone', 6),
        skippedExisting: 7,
        written: true,
        dedupMoves: migrationRedirectMoves('dedup', 2),
    ));

    expect(Artisan::call('content:generate-migration-redirects'))->toBe(0)
        ->and(migrationRedirectOutputLines())->toBe([
            'Entry moves (301):     1',
            'Duplicates (301):      2',
            'Asset moves (301):     3',
            'Structural URLs:       4',
            'Retired → ancestor:    5',
            'Retired → gone (410):  6',
            'Skipped (existing):    7',
            'Wrote 21 migration redirects.',
        ]);
});

it('words the total by whether it was written, and by whether the run was dry', function (bool $dryRun, bool $written, int $each, string $total): void {
    fakeMigrationRedirectGenerator($dryRun, [], [], null, migrationRedirectReport($each, $written));

    $options = $dryRun ? ['--dry-run' => true] : [];

    expect(Artisan::call('content:generate-migration-redirects', $options))->toBe(0)
        ->and(migrationRedirectOutputLines())->toContain($total);
})->with([
    'a dry run' => [true, false, 2, 'Would write 12 migration redirects.'],
    'a written run' => [false, true, 2, 'Wrote 12 migration redirects.'],
    'a run that wrote nothing' => [false, false, 0, 'Wrote 0 migration redirects.'],
    'a dry run the report calls written' => [true, true, 0, 'Wrote 0 migration redirects.'],
]);

it('names a single redirect in the singular', function (): void {
    fakeMigrationRedirectGenerator(true, [], [], null, new MigrationRedirectReport(
        entryMoves: migrationRedirectMoves('entry', 1),
        assetMoves: [],
        structural: [],
        ancestorMoves: [],
        gone: [],
        skippedExisting: 0,
        written: false,
    ));

    expect(Artisan::call('content:generate-migration-redirects', ['--dry-run' => true]))->toBe(0)
        ->and(migrationRedirectOutputLines())->toContain('Would write 1 migration redirect.');
});

it('reads one retired permalink a line, whatever the line ending, trimmed and without blanks', function (): void {
    $path = $this->redirectsDir.'/retired.txt';
    file_put_contents($path, "  /kirk/  \r\n\r\n/spock\r/mccoy\n   \n/uhura\n");

    fakeMigrationRedirectGenerator(false, ['/kirk/', '/spock', '/mccoy', '/uhura'], [], null, migrationRedirectReport(0, true));

    expect(Artisan::call('content:generate-migration-redirects', ['--retired-from' => $path]))->toBe(0);
});

it('reads an empty --retired-from as no list', function (): void {
    fakeMigrationRedirectGenerator(false, [], [], null, migrationRedirectReport(0, true));

    expect(Artisan::call('content:generate-migration-redirects', ['--retired-from' => '']))->toBe(0);
});

it('refuses a retired-permalink list it cannot read, and generates nothing', function (): void {
    forbidMigrationRedirectGenerator();
    $path = $this->redirectsDir.'/no-such-list.txt';

    expect(Artisan::call('content:generate-migration-redirects', ['--retired-from' => $path]))->toBe(1)
        ->and(migrationRedirectOutputLines())->toBe(["Could not read retired-permalink list: {$path}"]);
});

it('refuses a retired-permalink path that is a directory, and generates nothing', function (): void {
    forbidMigrationRedirectGenerator();

    expect(Artisan::call('content:generate-migration-redirects', ['--retired-from' => $this->redirectsDir]))->toBe(1)
        ->and(migrationRedirectOutputLines())->toBe(["Could not read retired-permalink list: {$this->redirectsDir}"]);
});

it('warns of the rows it could not read before the pairs the generator refused', function (): void {
    $path = $this->redirectsDir.'/pairs.json';
    file_put_contents($path, (string) json_encode([
        ['source' => 'https://news.uams.test/kirk/'],
        ['source' => ' https://news.uams.test/spock/ ', 'target' => 'https://news.uams.test/vulcan/', 'notes' => '  Logic  '],
    ]));

    $pair = [
        'source' => 'https://news.uams.test/spock/',
        'target' => 'https://news.uams.test/vulcan/',
        'notes' => 'Logic',
        'source_wordpress_post_id' => null,
        'target_wordpress_post_id' => null,
    ];

    fakeMigrationRedirectGenerator(false, [], [$pair], null, migrationRedirectReport(0, true, [
        ['source' => 'https://news.uams.test/spock/', 'target' => 'https://news.uams.test/vulcan/', 'reason' => 'no entry has the target'],
    ]));

    expect(Artisan::call('content:generate-migration-redirects', ['--dedup-redirects' => $path]))->toBe(0)
        ->and(array_values(array_filter(migrationRedirectOutputLines(), static fn (string $line): bool => str_starts_with($line, 'Duplicate pair'))))->toBe([
            'Duplicate pair not written (row 1 has no source or no target): https://news.uams.test/kirk/ ->',
            'Duplicate pair not written (no entry has the target): https://news.uams.test/spock/ -> https://news.uams.test/vulcan/',
        ]);
});

it("hands the generator each row's post ids, read as positive integers, and the trimmed site", function (): void {
    $path = $this->redirectsDir.'/pairs.json';
    file_put_contents($path, (string) json_encode([
        ['source' => 'https://news.uams.test/kirk/', 'target' => 'https://news.uams.test/enterprise/', 'source_wordpress_post_id' => 1701, 'target_wordpress_post_id' => ' 74656 '],
        ['source' => 'https://news.uams.test/sulu/', 'target' => 'https://news.uams.test/helm/', 'source_wordpress_post_id' => 0],
        ['source' => 'https://news.uams.test/chekov/', 'target' => 'https://news.uams.test/nav/', 'target_wordpress_post_id' => '0'],
        ['source' => 'https://news.uams.test/scotty/', 'target' => 'https://news.uams.test/engineering/', 'target_wordpress_post_id' => 'NCC'],
        ['source' => 'https://news.uams.test/rand/', 'target' => 'https://news.uams.test/yeoman/', 'source_wordpress_post_id' => 1.5],
    ]));

    $pair = [
        'source' => 'https://news.uams.test/kirk/',
        'target' => 'https://news.uams.test/enterprise/',
        'notes' => null,
        'source_wordpress_post_id' => 1701,
        'target_wordpress_post_id' => 74656,
    ];

    fakeMigrationRedirectGenerator(false, [], [$pair], 'default', migrationRedirectReport(0, true));

    expect(Artisan::call('content:generate-migration-redirects', ['--dedup-redirects' => $path, '--dedup-site' => '  default  ']))->toBe(0)
        ->and(array_values(array_filter(migrationRedirectOutputLines(), static fn (string $line): bool => str_starts_with($line, 'Duplicate pair'))))->toBe([
            'Duplicate pair not written (row 2 has a source_wordpress_post_id that is not a positive integer): https://news.uams.test/sulu/ -> https://news.uams.test/helm/',
            'Duplicate pair not written (row 3 has a target_wordpress_post_id that is not a positive integer): https://news.uams.test/chekov/ -> https://news.uams.test/nav/',
            'Duplicate pair not written (row 4 has a target_wordpress_post_id that is not a positive integer): https://news.uams.test/scotty/ -> https://news.uams.test/engineering/',
            'Duplicate pair not written (row 5 has a source_wordpress_post_id that is not a positive integer): https://news.uams.test/rand/ -> https://news.uams.test/yeoman/',
        ]);
});

it('reads a blank --dedup-site as none given', function (): void {
    fakeMigrationRedirectGenerator(false, [], [], null, migrationRedirectReport(0, true));

    expect(Artisan::call('content:generate-migration-redirects', ['--dedup-site' => '   ']))->toBe(0);
});
