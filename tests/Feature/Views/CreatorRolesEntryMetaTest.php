<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Renders the creator-role entry-meta partials (layout/page-header/entry-meta/...):
 * an empty role row when entries are null, nothing when hide_creator_query is true,
 * and a single "Creator:" row when only the generic creator is set. Antlers template
 * partials with no backing PHP class, so there is no mutable PHP surface.
 *
 * @command  php artisan test --compact tests/Feature/Views/CreatorRolesEntryMetaTest.php
 */
it('renders nothing for a creator role row when entries are null', function () {
    $out = (string) Antlers::parse('{{ partial:layout/page-header/entry-meta/creator_role_row }}', [
        'label' => 'Writer:',
        'entries' => null,
    ], true);

    expect(trim($out))->toBe('');
});

it('renders nothing for creator roles when hide_creator_query is true', function () {
    $out = (string) Antlers::parse('{{ partial:layout/page-header/entry-meta/creator_roles }}', [
        'hide_creator_query' => true,
        'creator' => ['writer' => ['fake-id']],
    ], true);

    expect(trim($out))->toBe('');
    expect($out)->not->toContain('Writer:');
});

it('shows a single creator row when only the generic creator is set', function () {
    $out = (string) Antlers::parse('{{ partial:layout/page-header/entry-meta/creator_roles }}', [
        'hide_creator_query' => false,
        'creator' => [
            'generic_creator' => 'person-a',
            'host' => ['person-b'],
            'guest' => null,
            'writer' => null,
            'sound_recordist' => null,
            'videographer' => null,
            'audio_editor' => null,
            'video_editor' => null,
            'producer' => null,
        ],
    ], true);

    expect($out)->toContain('Creator:');
    expect($out)->not->toContain('Host:');
    expect($out)->not->toContain('Writer:');
});

// The collapsed "Creator:" row passes no `icon`, so the creator partial falls back to
// a count-derived one. That ternary read `count > 0 ? singular : plural`, which is the
// wrong way round — every populated row got the singular icon and the plural was
// unreachable (UAMS-Web/uams-statamic-archive#1693). The count itself is sound here: `creator_role_row` resolves the
// relation through `normalize_list` before passing it on.
it('uses the plural creator icon for more than one person', function () {
    $out = (string) Antlers::parse('{{ partial:layout/page-header/entry-meta/creator }}', [
        'label' => 'Creator:',
        // Passed explicitly, never omitted: `Antlers::parse` leaks assigned
        // variables across calls in one process, so an `icon` left behind by an
        // earlier render would satisfy the `??` and skip the branch under test.
        'icon' => null,
        'value' => [
            ['title' => 'Nyota Uhura'],
            ['title' => 'Hikaru Sulu'],
        ],
    ], true);

    expect($out)->toContain('fa-users')
        ->and($out)->not->toContain('fa-user ');
});

it('uses the singular creator icon for exactly one person', function () {
    $out = (string) Antlers::parse('{{ partial:layout/page-header/entry-meta/creator }}', [
        'label' => 'Creator:',
        'icon' => null,
        'value' => [
            ['title' => 'Nyota Uhura'],
        ],
    ], true);

    expect($out)->toContain('fa-user')
        ->and($out)->not->toContain('fa-users');
});
