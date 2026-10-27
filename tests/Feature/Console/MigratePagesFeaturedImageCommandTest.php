<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Query\Builder;
use Tests\Support\ThrowawayRoot;
use Tests\TestCase;

/**
 * Coverage for the one-shot `content:migrate-pages-featured-image` command
 * (UAMS-Web/uams-statamic-archive#2005): it rewrites the entry file surgically, writes nothing on a dry run,
 * and scopes its query to `pages` so the collections that still legitimately
 * define `featured_image` cannot be touched.
 *
 * The entry query is faked and every fixture is written to a per-test temp
 * directory — never into the `content` submodule, which the suite must not
 * write to.
 *
 * The branch table lives in RetiredFeaturedImageMigratorTest; this file proves
 * the wiring.
 *
 * Helpers are prefixed `migratePagesFi*` because Pest declares them in the
 * GLOBAL namespace, where a generic name collides fatally at load time.
 *
 * @command  php artisan test --compact tests/Feature/Console/MigratePagesFeaturedImageCommandTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * The fixture directory for this worker.
 *
 * A plain function, NOT a property read through `test()`: outside a test closure
 * `test()` statically resolves to TestCall|HigherOrderTapProxy, which declares no
 * such property — green at runtime via Pest's `__call`, red under larastan.
 *
 * `ThrowawayRoot::segment()` is called INLINE inside `storage_path()` on purpose.
 * It is the repo's per-worker + per-process identity, and hoisting it (or a bare
 * `getenv('TEST_TOKEN')`) to a local hides it from `check-parallel-safe-tests.php`
 * Rule B, which reds the `isolation` CI job — the first link in the chain, so
 * every downstream job is skipped.
 */
function migratePagesFiDir(): string
{
    return storage_path('framework/testing/migrate-pages-fi-'.ThrowawayRoot::segment());
}

beforeEach(fn () => File::ensureDirectoryExists(migratePagesFiDir()));

afterEach(fn () => File::deleteDirectory(migratePagesFiDir()));

/**
 * Write an entry fixture file and return a mock entry pointing at it.
 */
function migratePagesFiEntry(string $name, string $contents): Entry
{
    $path = migratePagesFiDir().\DIRECTORY_SEPARATOR.$name;
    file_put_contents($path, $contents);

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('path')->andReturn($path);

    return $entry;
}

/**
 * Read a fixture back off disk.
 */
function migratePagesFiRead(string $name): string
{
    return (string) file_get_contents(migratePagesFiDir().\DIRECTORY_SEPARATOR.$name);
}

/**
 * Fake the entry query, capturing the collection handle the command scoped to.
 *
 * @param  array<int, Entry>  $entries
 */
function migratePagesFiFakeQuery(array $entries, ?string &$scopedTo = null): void
{
    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('where')->withArgs(function (string $field, string $value) use (&$scopedTo): bool {
        if ($field === 'collection') {
            $scopedTo = $value;
        }

        return true;
    })->andReturnSelf();
    $builder->shouldReceive('get')->andReturn(collect($entries));

    EntryFacade::shouldReceive('query')->andReturn($builder);
}

/** A page entry file carrying the retired handle. */
function migratePagesFiFixture(string $handleLine): string
{
    return "---\nid: abc\nblueprint: landing_page\ntitle: 'About UAMS'\n{$handleLine}\n---\nBody copy.\n";
}

it('renames the key in the entry file, leaving every other byte identical', function (): void {
    $before = migratePagesFiFixture('featured_image: default/hospital-exterior-nite.jpg');
    migratePagesFiFakeQuery([migratePagesFiEntry('about-uams.md', $before)]);

    pendingArtisan('content:migrate-pages-featured-image')
        ->expectsOutputToContain('Moved 1 entry')
        ->assertSuccessful();

    $after = migratePagesFiRead('about-uams.md');

    expect($after)->toBe(migratePagesFiFixture('featured_image_16_9: default/hospital-exterior-nite.jpg'))
        ->and(array_diff(explode("\n", $after), explode("\n", $before)))->toHaveCount(1);
});

it('writes nothing on a dry run', function (): void {
    $before = migratePagesFiFixture('featured_image: default/hospital-exterior-nite.jpg');
    migratePagesFiFakeQuery([migratePagesFiEntry('about-uams.md', $before)]);

    pendingArtisan('content:migrate-pages-featured-image --dry-run')
        ->expectsOutputToContain('Would move 1 entry')
        ->assertSuccessful();

    expect(migratePagesFiRead('about-uams.md'))->toBe($before);
});

it('scopes the query to pages so collections that still define the handle are safe', function (): void {
    // clinical_locations and clinical_expertise blueprints still declare
    // `featured_image`. A repo-wide sweep would strip their entries; this pins
    // the scope so widening it goes red.
    $scopedTo = null;
    migratePagesFiFakeQuery([], $scopedTo);

    pendingArtisan('content:migrate-pages-featured-image')->assertSuccessful();

    expect($scopedTo)->toBe('pages');
});

it('leaves an entry without the retired handle untouched', function (): void {
    $before = migratePagesFiFixture('featured_image_16_9: default/bridge.jpg');
    migratePagesFiFakeQuery([migratePagesFiEntry('already-migrated.md', $before)]);

    pendingArtisan('content:migrate-pages-featured-image')
        ->expectsOutputToContain('Moved 0 entries')
        ->assertSuccessful();

    expect(migratePagesFiRead('already-migrated.md'))->toBe($before);
});

it('reports a value-less key for review instead of touching it', function (): void {
    $before = migratePagesFiFixture("featured_image:\n  - default/chekov.jpg");
    migratePagesFiFakeQuery([migratePagesFiEntry('block-value.md', $before)]);

    pendingArtisan('content:migrate-pages-featured-image')
        ->expectsOutputToContain('Needs review')
        ->expectsOutputToContain('1 entry left untouched')
        ->assertSuccessful();

    expect(migratePagesFiRead('block-value.md'))->toBe($before);
});
