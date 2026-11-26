<?php

declare(strict_types=1);

use App\Support\Entries\PrivateEntryAccess;
use App\Tags\CanViewPrivateEntry;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(CanViewPrivateEntry::class);

/**
 * `{{ can_view_private_entry :id="..." }}` resolves the entry with the given id and asks
 * `PrivateEntryAccess` whether the current user may view it (UAMS-Web/uams-statamic-archive#1152).
 * The access rule itself is covered by its own tests; here it is stubbed, so each test
 * reads which entry the tag hands it.
 *
 * The tag narrows what the lookup returns to an `Entry`. That narrowing is annotated as
 * equivalent in the tag: this application's entry repository is typed to return an
 * `Entry` or null, so a lookup returning anything else fails before the tag sees it.
 *
 * Mutation: 100% on App\Tags\CanViewPrivateEntry (9 mutants), measured on macOS 26.5 (arm64) on 2026-11-25 with PHP 8.4.28 and Herd's Xdebug 3.5.3, without --covered-only, over
 * this file, NavPrimaryNavExclusionTest and NavExcludesNonPublicEntriesTest
 * (UAMS-Web/uams-statamic#801), with the one equivalent above annotated.
 *
 * @command  php artisan test --compact tests/Feature/Tags/CanViewPrivateEntryTagTest.php
 */

/**
 * The tag's answer for the given parameters.
 *
 * @param  array<string, mixed>  $params
 */
function canViewPrivateEntryFor(array $params): bool
{
    $tag = new CanViewPrivateEntry;
    $tag->setContext([]);
    $tag->setParameters($params);

    return $tag->index();
}

/**
 * Stub the access rule to answer for the one entry it expects, and fail for any other.
 */
function canViewPrivateEntryAccess(?Entry $expected, bool $answer): void
{
    app()->instance(PrivateEntryAccess::class, Mockery::mock(
        PrivateEntryAccess::class,
        fn (MockInterface $mock) => $mock->expects('canView')->with($expected)->andReturns($answer)
    ));
}

it('asks the access rule about the entry with the given id', function (mixed $id, bool $answer): void {
    $entry = Mockery::mock(Entry::class);
    EntryFacade::shouldReceive('find')->withArgs(fn (mixed $given): bool => $given === '42')->andReturn($entry);
    canViewPrivateEntryAccess($entry, $answer);

    expect(canViewPrivateEntryFor(['id' => $id]))->toBe($answer);
})->with([
    'a text id the user may view' => ['42', true],
    'a text id the user may not view' => ['42', false],
    // The id is passed on as text
    'a number id' => [42, true],
]);

it('refuses without looking anything up when the id is missing or not an id', function (bool $given, mixed $id): void {
    EntryFacade::shouldReceive('find')->never();
    app()->instance(PrivateEntryAccess::class, Mockery::mock(
        PrivateEntryAccess::class,
        fn (MockInterface $mock) => $mock->expects('canView')->never()
    ));

    expect(canViewPrivateEntryFor($given ? ['id' => $id] : []))->toBeFalse();
})->with([
    'no id' => [false, null],
    'a list' => [true, ['42']],
    'a decimal' => [true, 4.2],
]);

it('asks about no entry when the id finds none', function (): void {
    EntryFacade::shouldReceive('find')->with('42')->andReturn(null);
    canViewPrivateEntryAccess(null, false);

    expect(canViewPrivateEntryFor(['id' => '42']))->toBeFalse();
});
