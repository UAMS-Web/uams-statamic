<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Query\Builder;
use Tests\TestCase;

/**
 * The `bard:sanitize` command (UAMS-Web/uams-statamic#314): it reads each entry's YAML file,
 * rewrites the ones the BardContentSanitizer flags, and reports what it found.
 *
 * The collections and the entry query are faked, as in BackfillLocationRegionsCommandTest, and
 * each entry points at a real file in a private temporary directory, so the real sanitizer
 * reads and rewrites real bytes without touching `content/`.
 *
 * @command  php artisan test --compact tests/Feature/Console/SanitizeBardContentCommandTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

beforeEach(function (): void {
    $this->bardDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sanitize-bard-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->bardDir);
});

afterEach(function (): void {
    File::deleteDirectory($this->bardDir);
});

/**
 * A mock collection with the given handle and title.
 */
function sanitizeBardCollection(string $handle, string|int|float|bool|null $title): Collection&MockInterface
{
    $collection = Mockery::mock(Collection::class);
    $collection->shouldReceive('handle')->andReturn($handle);
    $collection->shouldReceive('title')->andReturn($title);

    return $collection;
}

/**
 * A mock entry whose title, slug and file path are as given.
 */
function sanitizeBardEntry(string|int|float|bool|null $title, ?string $slug, ?string $path): Entry&MockInterface
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('value')->with('title')->andReturn($title);
    $entry->shouldReceive('slug')->andReturn($slug);
    $entry->shouldReceive('path')->andReturn($path);

    return $entry;
}

/**
 * Fake the entry query so each collection handle returns its own entries.
 *
 * @param  array<string, list<Entry>>  $entriesByHandle
 */
function fakeSanitizeBardQuery(array $entriesByHandle): void
{
    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('where')->andReturnUsing(function (string $column, string $handle) use ($entriesByHandle): Builder {
        expect($column)->toBe('collection');

        $filtered = Mockery::mock(Builder::class);
        $filtered->shouldReceive('get')->andReturn(collect($entriesByHandle[$handle] ?? []));

        return $filtered;
    });

    EntryFacade::shouldReceive('query')->andReturn($builder);
}

/**
 * The command's output as trimmed lines, blank lines kept.
 *
 * @return list<string>
 */
function sanitizeBardOutputLines(): array
{
    return array_map(trim(...), explode("\n", str_replace("\r\n", "\n", trim(Artisan::output()))));
}

it('refuses a collection that does not exist, and reads no entries', function (): void {
    CollectionFacade::shouldReceive('find')->with('klingon')->once()->andReturnNull();
    CollectionFacade::shouldReceive('all')->never();
    EntryFacade::shouldReceive('query')->never();

    expect(Artisan::call('bard:sanitize', ['--collection' => 'klingon']))->toBe(1)
        ->and(Artisan::output())->toContain("Collection 'klingon' not found.")
        ->not->toContain('Summary:');
});

it('rewrites every flagged file in every collection, skips missing files, and counts each', function (): void {
    $kirkPath = $this->bardDir.'/kirk.md';
    $spockPath = $this->bardDir.'/spock.md';
    $mccoyPath = $this->bardDir.'/mccoy.md';
    File::put($kirkPath, "content:\n  - type: text\n    text: ...\n");
    File::put($spockPath, "content:\n  - type: text\n    text: logic\\_first\n");
    File::put($mccoyPath, "content:\n  - type: text\n    text: 'I am a doctor'\n");

    // The missing files come first in each collection, so a skip must move on to the next entry
    CollectionFacade::shouldReceive('all')->once()->andReturn(collect([
        sanitizeBardCollection('bridge', 'Bridge'),
        sanitizeBardCollection('sickbay', null),
    ]));
    fakeSanitizeBardQuery([
        'bridge' => [
            sanitizeBardEntry('Scotty', 'scotty', null),
            sanitizeBardEntry('Kirk', 'kirk', $kirkPath),
            sanitizeBardEntry(null, 'spock', $spockPath),
        ],
        'sickbay' => [
            sanitizeBardEntry(null, null, null),
            sanitizeBardEntry('Uhura', 'uhura', $this->bardDir.'/uhura.md'),
            sanitizeBardEntry('McCoy', 'mccoy', $mccoyPath),
        ],
    ]);

    expect(Artisan::call('bard:sanitize'))->toBe(0);

    expect(sanitizeBardOutputLines())->toBe([
        'Scanning for Bard content that needs sanitization...',
        'Processing collection: Bridge',
        "- Entry 'Scotty' file not found:",
        "- Entry 'Kirk' has Bard content needing sanitization",
        "- Entry 'spock' has Bard content needing sanitization",
        'Processing collection:',
        "- Entry '' file not found:",
        sprintf("- Entry 'Uhura' file not found: %s", $this->bardDir.'/uhura.md'),
        '',
        'Summary:',
        '- Total entries processed: 6',
        '- Entries with Bard content issues: 2',
        '- Entries updated: 2',
    ])
        ->and(File::get($kirkPath))->toBe("content:\n  - type: text\n    text: '...'\n")
        ->and(File::get($spockPath))->toBe("content:\n  - type: text\n    text: \"logic\\_first\"\n")
        ->and(File::get($mccoyPath))->toBe("content:\n  - type: text\n    text: 'I am a doctor'\n")
        ->and(File::exists($this->bardDir.'/uhura.md'))->toBeFalse();
});

it('reports an empty scan when there are no collections', function (): void {
    CollectionFacade::shouldReceive('all')->once()->andReturn(collect());
    EntryFacade::shouldReceive('query')->never();

    expect(Artisan::call('bard:sanitize'))->toBe(0);

    expect(sanitizeBardOutputLines())->toBe([
        'Scanning for Bard content that needs sanitization...',
        '',
        'Summary:',
        '- Total entries processed: 0',
        '- Entries with Bard content issues: 0',
        '- Entries updated: 0',
    ]);
});

it('lists the flagged files of one collection on a dry run and changes none of them', function (): void {
    $kirkPath = $this->bardDir.'/kirk.md';
    $original = "content:\n  - type: text\n    text: ---\n";
    File::put($kirkPath, $original);

    CollectionFacade::shouldReceive('find')->with('bridge')->once()->andReturn(sanitizeBardCollection('bridge', 'Bridge'));
    CollectionFacade::shouldReceive('all')->never();
    fakeSanitizeBardQuery(['bridge' => [sanitizeBardEntry('Kirk', 'kirk', $kirkPath)]]);

    expect(Artisan::call('bard:sanitize', ['--collection' => 'bridge', '--dry-run' => true]))->toBe(0);

    expect(sanitizeBardOutputLines())->toBe([
        'Scanning for Bard content that needs sanitization...',
        'Processing collection: Bridge',
        "- Entry 'Kirk' has Bard content needing sanitization",
        '',
        'Summary:',
        '- Total entries processed: 1',
        '- Entries with Bard content issues: 1',
        '- This was a dry run. No changes were made.',
        '- Run without --dry-run to apply changes.',
    ])
        ->and(File::get($kirkPath))->toBe($original);
});

it('prints a title that is a number or true the way PHP writes it as text', function (): void {
    // A title read from YAML can be a scalar that is not a string. The command prints it inside
    // text, where 1701 reads `1701`, 2.5 reads `2.5` and true reads `1`.
    CollectionFacade::shouldReceive('all')->once()->andReturn(collect([sanitizeBardCollection('ncc', 1701)]));
    fakeSanitizeBardQuery(['ncc' => [
        sanitizeBardEntry(74656, 'voyager', null),
        sanitizeBardEntry(2.5, 'half', null),
        sanitizeBardEntry(true, 'flag', null),
    ]]);

    expect(Artisan::call('bard:sanitize'))->toBe(0);

    expect(array_slice(sanitizeBardOutputLines(), 1, 4))->toBe([
        'Processing collection: 1701',
        "- Entry '74656' file not found:",
        "- Entry '2.5' file not found:",
        "- Entry '1' file not found:",
    ]);
});
