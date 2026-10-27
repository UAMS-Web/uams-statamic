<?php

declare(strict_types=1);

use App\Support\Entries\PrivateEntryAccess;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Auth\User;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Nav;
use Statamic\Facades\User as UserFacade;
use Statamic\Structures\NavTree;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Render-level coverage that the primary nav excludes non-public entries on
 * EVERY nav source — including a manual `primary_navigation` Nav (UAMS-Web/uams-statamic-archive#1151) — and
 * still surfaces `private` entries to a user who may view them (UAMS-Web/uams-statamic-archive#1152).
 *
 * UAMS-Web/uams-statamic-archive#223 filtered non-public entries with `{{ nav … field:isnt }}` params, but
 * `Structure::queryForNav` ignores tag conditions, so on a manual nav they leak
 * (characterized in {@see NavExcludesNonPublicEntriesTest}). The desktop/mobile
 * templates now wrap each item in a `{{ unless }}` guard that re-applies the
 * exclusion regardless of source. `private` is guarded only in the template (not
 * at the query) so it reaches the guard, which reveals it to a super user or a
 * user who can publish the entry's collection — the same rule
 * {@see PrivateEntryAccess} gives the front-end gate.
 *
 * The `{{ nav }}` tag renders under `Antlers::parse` in an isolated Stache (unlike
 * a bare page render), so this drives the REAL guard + `{{ can_view_private_entry }}`
 * tag over a manual nav. NAV_PRIMARY_GUARD is asserted verbatim against both
 * templates below, so this reproduction cannot silently drift from what ships.
 *
 * @command  php artisan test --compact tests/Feature/Navigation/NavPrimaryNavExclusionTest.php
 */
uses(StacheIsolatedTestCase::class, MockeryPHPUnitIntegration::class);

/**
 * The exact per-item guard the desktop/mobile primary-nav templates use. Kept
 * identical to what ships by the source-wiring assertions below.
 */
const NAV_PRIMARY_GUARD = '{{ unless hide_from_menu || password_protected || ( status_and_visibility == "private" && !{can_view_private_entry :id="entry_id"} ) }}';

/**
 * Build public / private / password / hidden sibling pages and a manual nav
 * referencing all four (in that order).
 */
function navPrimaryFixture(): void
{
    $collection = Collection::make('np_pages');
    $collection->routes('{slug}');
    $collection->save();

    $entries = [
        ['id' => 'np-pub', 'slug' => 'pub', 'title' => 'Public', 'data' => ['status_and_visibility' => 'published']],
        ['id' => 'np-priv', 'slug' => 'priv', 'title' => 'Private', 'data' => ['status_and_visibility' => 'private']],
        ['id' => 'np-pass', 'slug' => 'pass', 'title' => 'Password', 'data' => ['status_and_visibility' => 'published', 'password_protected' => true]],
        ['id' => 'np-hide', 'slug' => 'hide', 'title' => 'Hidden', 'data' => ['status_and_visibility' => 'published', 'hide_from_menu' => true]],
    ];

    foreach ($entries as $e) {
        /** @var Statamic\Entries\Entry $entry */
        $entry = Entry::make();
        $entry->collection('np_pages');
        $entry->id($e['id']);
        $entry->slug($e['slug']);
        $entry->published(true);
        $entry->data(array_merge(['title' => $e['title']], $e['data']));
        $entry->save();
    }

    /** @var Statamic\Structures\Nav $nav */
    $nav = Nav::make('np_manual');
    $nav->title('NP Manual');
    $nav->save();

    /** @var NavTree $tree */
    $tree = $nav->makeTree('default', [['entry' => 'np-pub'], ['entry' => 'np-priv'], ['entry' => 'np-pass'], ['entry' => 'np-hide']]);
    $tree->save();
}

/**
 * Fake the current user: null = guest; otherwise a user with the given super flag
 * and permission set.
 *
 * @param  list<string>  $permissions
 */
function navPrimaryActingAs(?bool $super = null, array $permissions = []): void
{
    if ($super === null) {
        UserFacade::shouldReceive('current')->andReturn(null);

        return;
    }

    // shouldIgnoreMissing so any other User method the nav render happens to call
    // returns null instead of throwing (which would blank the whole parse); only
    // the two the visibility rule needs are stubbed.
    $user = Mockery::mock(User::class)->shouldIgnoreMissing();
    $user->shouldReceive('isSuper')->andReturn($super);
    $user->shouldReceive('hasPermission')->andReturnUsing(fn (string $p): bool => in_array($p, $permissions, true));
    UserFacade::shouldReceive('current')->andReturn($user);
}

/**
 * Render the manual primary nav through the real guard + can_view_private_entry
 * tag, returning the titles that survived the exclusion, in order.
 *
 * @return list<string>
 */
function navPrimaryVisibleTitles(): array
{
    // Mirrors the real `{{ nav … }}` call (the `:isnt` params are inert on a manual
    // nav, so the guard does the work) wrapping each item in NAV_PRIMARY_GUARD.
    $template = '{{ nav handle="np_manual" hide_from_menu:isnt="true" password_protected:isnt="true" }}'
        .NAV_PRIMARY_GUARD.'[{{ title }}]{{ /unless }}{{ /nav }}';

    $out = (string) Antlers::parse($template, [], true);

    return preg_match_all('/\[([^\]]+)\]/', $out, $m) ? $m[1] : [];
}

beforeEach(function (): void {
    navPrimaryFixture();
});

it('excludes private, password and hidden entries on the manual nav for a guest', function (): void {
    navPrimaryActingAs(super: null);

    expect(navPrimaryVisibleTitles())->toBe(['Public']);
});

it('reveals a private entry in the manual nav for a super user', function (): void {
    navPrimaryActingAs(super: true);

    // Private is shown; password and hidden stay excluded for everyone.
    expect(navPrimaryVisibleTitles())->toBe(['Public', 'Private']);
});

it('reveals a private entry for a user who can publish the collection', function (): void {
    navPrimaryActingAs(super: false, permissions: ['publish np_pages entries']);

    expect(navPrimaryVisibleTitles())->toBe(['Public', 'Private']);
});

it('keeps a private entry hidden from a user who can only edit (not publish)', function (): void {
    // A contributor holds edit but not publish — it must not see private in the nav.
    navPrimaryActingAs(super: false, permissions: ['edit np_pages entries']);

    expect(navPrimaryVisibleTitles())->toBe(['Public']);
});

it('wires both primary-nav templates to the guard and drops the private query filter', function (): void {
    foreach (['main/desktop', 'main/mobile'] as $view) {
        $markup = (string) file_get_contents(
            resource_path("views/layout/navigation/{$view}.antlers.html")
        );

        expect($markup)
            // The template guard this test renders is what actually ships.
            ->toContain(NAV_PRIMARY_GUARD)
            ->toContain('{{ /unless }}')
            // hidden/password stay query-filtered on the collection path...
            ->toContain('password_protected:isnt="true"');
        // ...but `private` is no longer filtered at the query, so staff can see it.
        expect($markup)->not->toContain('status_and_visibility:isnt="private"');
    }
});
